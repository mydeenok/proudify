<?php

namespace Tests\Feature\Certificates;

use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Jobs\Certificates\GenerateCertificateQrCodeJob;
use App\Jobs\Certificates\SendCertificateIssuedEmailJob;
use App\Models\Certificate;
use App\Models\CertificateOrder;
use App\Models\Template;
use App\Models\User;
use App\Services\CertificateRenderService;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IssueCertificateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Single-certificate issuance now calls
     * CertificateRenderService::generateAssetsSynchronously() directly
     * (not a queued job Bus::fake() would intercept), so feature tests
     * that only care about issuance logic (validation, quota, field
     * locking) stub it out here rather than running real Browsershot/
     * Imagick on every test - that correctness is already covered by
     * CertificateRenderServiceTest and the Phase 0 manual verification.
     */
    private function fakeAssetGeneration(): void
    {
        // Wraps a REAL, fully-constructed instance (not Laravel's
        // partialMock() helper, which skips the constructor entirely and
        // leaves the injected QrCodeService property uninitialized) -
        // some of these tests also call other real CertificateRenderService
        // methods (e.g. renderHtml) directly to assert on their output, so
        // only generateAssetsSynchronously is stubbed here; everything
        // else falls through to the real implementation.
        $real = $this->app->make(CertificateRenderService::class);
        $mock = \Mockery::mock($real)->makePartial();
        $mock->shouldReceive('generateAssetsSynchronously')
            ->andReturnUsing(function (Certificate $certificate) {
                $certificate->forceFill([
                    'qr_code_path' => 'certificates/fake/qr.png',
                    'pdf_path' => 'certificates/fake/certificate.pdf',
                    'image_path' => 'certificates/fake/certificate.jpg',
                    'image_generation_status' => 'completed',
                ])->save();
            });

        $this->app->instance(CertificateRenderService::class, $mock);
    }

    /**
     * Pay-per-certificate: a non-admin submitting the form is sent to
     * checkout instead of getting a certificate immediately - no
     * CertificateOrder exists yet at that point, no Certificate ever does.
     */
    public function test_a_user_is_sent_to_checkout_before_a_certificate_is_issued(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();

        $response = $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate of Excellence',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
        ]);

        $order = CertificateOrder::firstOrFail();

        $response->assertRedirect(route('certificates.checkout.show', $order));
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('single', $order->type);
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, $order->quantity);
        $this->assertSame('20.00', $order->unit_price);
        $this->assertSame('20.00', $order->total_amount);
        $this->assertSame(0, Certificate::count());
    }

    /**
     * The other half of the flow above: once Razorpay's signature verifies,
     * the certificate is actually created, exactly as it always was pre-
     * payment-gate - this is what CertificateOrderCompletionService drives.
     */
    public function test_a_certificate_is_issued_after_checkout_payment_is_verified(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifySignature')->once()->andReturn(true);
        });

        $user = User::factory()->create();
        $template = Template::factory()->create();

        $order = CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'payload' => [
                'title' => 'Certificate of Excellence',
                'recipient_name' => 'Jane Doe',
                'recipient_email' => 'jane@example.com',
                'date_of_issue' => now()->toDateString(),
            ],
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($user)->postJson(route('certificates.checkout.verify-payment', $order), [
            'razorpay_order_id' => 'order_test123',
            'razorpay_payment_id' => 'pay_test123',
            'razorpay_signature' => 'sig_test123',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $certificate = Certificate::firstOrFail();
        $order->refresh();

        $this->assertSame($user->id, $certificate->user_id);
        $this->assertNotEmpty($certificate->verification_code);
        $this->assertSame('paid', $order->status);
        $this->assertSame($certificate->id, $order->certificate_id);

        // Single issuance is synchronous - the certificate is fully ready
        // by the time verify-payment returns, no job chain for its assets.
        $this->assertSame('completed', $certificate->image_generation_status);
        Bus::assertDispatched(SendCertificateIssuedEmailJob::class);
        Bus::assertNotDispatched(GenerateCertificateQrCodeJob::class);
        Bus::assertNotDispatched(GenerateCertificatePdfJob::class);
        Bus::assertNotDispatched(ConvertCertificatePdfToImageJob::class);
    }

    public function test_only_active_templates_can_be_used(): void
    {
        $user = User::factory()->create();
        $draftTemplate = Template::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('certificates.create', ['template' => $draftTemplate->id]))
            ->assertNotFound();
    }

    public function test_a_user_cannot_view_another_users_certificate(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $certificate = Certificate::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get(route('certificates.show', $certificate))
            ->assertForbidden();
    }

    public function test_a_template_with_no_custom_field_schema_shows_no_template_fields_section(): void
    {
        // Every template created before this feature (or never opened in
        // the builder's custom-field tools) has custom_field_schema = null
        // - the create form must render exactly as it did before, with no
        // migration or per-template opt-in step required.
        $user = User::factory()->create();
        $template = Template::factory()->create(['custom_field_schema' => null]);

        $this->actingAs($user)
            ->get(route('certificates.create', ['template' => $template->id]))
            ->assertOk()
            ->assertDontSee('Template Fields');
    }

    public function test_an_admin_can_open_the_template_library_to_create_a_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create();

        $this->actingAs($admin)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Template Library');

        $this->actingAs($admin)
            ->get(route('certificates.create', ['template' => $template->id]))
            ->assertOk()
            ->assertSee('Create New Certificate');
    }

    public function test_an_admin_can_view_any_users_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $certificate = Certificate::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)
            ->get(route('certificates.show', $certificate))
            ->assertOk();
    }

    public function test_admins_issue_directly_without_going_through_checkout(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();

        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create();

        $response = $this->actingAs($admin)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
        ]);

        $certificate = Certificate::firstOrFail();
        $response->assertRedirect(route('certificates.show', $certificate));
        $this->assertNull($certificate->user_subscription_id);
    }

    public function test_certificate_status_endpoint_returns_generation_payload(): void
    {
        $user = User::factory()->create();
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'verification_code' => 'ABC12345',
            'qr_code_path' => 'certificates/1/qr/test.png',
            'pdf_path' => 'certificates/1/test.pdf',
            'image_path' => 'certificates/1/test.jpg',
            'image_generation_status' => 'completed',
        ]);

        $response = $this->actingAs($user)->getJson(route('certificates.status', $certificate));

        $response->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('verification_code', 'ABC12345')
            ->assertJsonPath('display_status', 'active');
    }

    public function test_regenerating_a_single_issued_certificate_runs_synchronously(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();

        $user = User::factory()->create();
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'certificate_batch_id' => null,
            'qr_code_path' => 'certificates/1/qr/test.png',
            'pdf_path' => null,
            'image_path' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('certificates.regenerate', $certificate))
            ->assertOk()
            ->assertJsonPath('image_generation_status', 'completed')
            ->assertJsonPath('ready', true);

        Bus::assertNothingDispatched();
    }

    public function test_regenerating_a_bulk_issued_certificate_stays_queued(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $batch = \App\Models\CertificateBatch::factory()->create(['user_id' => $user->id]);
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'certificate_batch_id' => $batch->id,
            'qr_code_path' => 'certificates/1/qr/test.png',
        ]);

        $this->actingAs($user)
            ->postJson(route('certificates.regenerate', $certificate))
            ->assertOk()
            ->assertJsonPath('image_generation_status', 'processing');

        Bus::assertChained([
            GenerateCertificatePdfJob::class,
            ConvertCertificatePdfToImageJob::class,
        ]);
    }

    /**
     * Actor is an admin here - this test is about custom-field/image
     * storage on the issued Certificate, unrelated to billing, and an
     * admin issues directly without the checkout detour a tenant now goes
     * through (see test_a_certificate_is_issued_after_checkout_payment_is_verified
     * for that path).
     */
    public function test_a_certificate_can_be_issued_with_custom_text_and_image_fields(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();
        Storage::fake('local');

        $user = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'custom_field_schema' => [
                ['key' => 'course_name', 'label' => 'Course Name', 'type' => 'text', 'required' => true],
                ['key' => 'course_logo', 'label' => 'Course Logo', 'type' => 'image', 'required' => false],
            ],
        ]);

        $response = $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate of Excellence',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
            'custom_fields' => ['course_name' => 'Advanced Laravel'],
            'custom_image_fields' => ['course_logo' => UploadedFile::fake()->image('logo.png')],
        ]);

        $certificate = Certificate::firstOrFail();
        $response->assertRedirect(route('certificates.show', $certificate));

        $this->assertSame('Advanced Laravel', $certificate->custom_fields['course_name']);
        $this->assertNotEmpty($certificate->custom_image_fields['course_logo']);
        Storage::disk('local')->assertExists($certificate->custom_image_fields['course_logo']);
    }

    public function test_a_required_custom_text_field_is_validated(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'custom_field_schema' => [
                ['key' => 'course_name', 'label' => 'Course Name', 'type' => 'text', 'required' => true],
            ],
        ]);

        $response = $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate of Excellence',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('custom_fields.course_name');
        $this->assertSame(0, Certificate::count());
    }

    /** Admin actor - test is about output escaping, unrelated to billing. */
    public function test_a_script_injection_attempt_in_a_custom_text_field_is_escaped_not_executed(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();

        $user = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'html_content' => '<h1>{course_name}</h1>',
            'custom_field_schema' => [
                ['key' => 'course_name', 'label' => 'Course Name', 'type' => 'text', 'required' => false],
            ],
        ]);

        $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate of Excellence',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
            'custom_fields' => ['course_name' => '<script>alert(document.cookie)</script>'],
        ]);

        $certificate = Certificate::firstOrFail();
        $html = app(\App\Services\CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_custom_image_field_rejects_a_non_image_file(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'custom_field_schema' => [
                ['key' => 'course_logo', 'label' => 'Course Logo', 'type' => 'image', 'required' => false],
            ],
        ]);

        $response = $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
            'custom_image_fields' => ['course_logo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
        ]);

        $response->assertSessionHasErrors('custom_image_fields.course_logo');
        $this->assertSame(0, Certificate::count());
    }

    public function test_a_custom_image_field_rejects_an_oversized_file(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'custom_field_schema' => [
                ['key' => 'course_logo', 'label' => 'Course Logo', 'type' => 'image', 'required' => false],
            ],
        ]);

        $response = $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
            'custom_image_fields' => ['course_logo' => UploadedFile::fake()->image('logo.png')->size(3000)],
        ]);

        $response->assertSessionHasErrors('custom_image_fields.course_logo');
        $this->assertSame(0, Certificate::count());
    }

    /** Admin actor - test is about field-schema security, unrelated to billing. */
    public function test_a_custom_field_key_not_declared_in_the_templates_schema_is_silently_dropped(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();
        Storage::fake('local');

        $user = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'custom_field_schema' => [
                ['key' => 'course_name', 'label' => 'Course Name', 'type' => 'text', 'required' => false],
            ],
        ]);

        $this->actingAs($user)->post('/certificates', [
            'template_id' => $template->id,
            'title' => 'Certificate',
            'recipient_name' => 'Jane Doe',
            'recipient_email' => 'jane@example.com',
            'date_of_issue' => now()->toDateString(),
            'custom_fields' => [
                'course_name' => 'Legitimate value',
                'not_declared_on_this_template' => 'Attacker-supplied key',
            ],
            'custom_image_fields' => [
                'also_not_declared' => UploadedFile::fake()->image('sneaky.png'),
            ],
        ]);

        $certificate = Certificate::firstOrFail();

        $this->assertSame(['course_name' => 'Legitimate value'], $certificate->custom_fields);
        $this->assertSame([], $certificate->custom_image_fields);
        Storage::disk('local')->assertDirectoryEmpty('certificates/custom-fields');
    }

    public function test_bulk_issued_certificates_are_unaffected_by_the_new_custom_image_fields_column(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create();
        $batch = \App\Models\CertificateBatch::factory()->create(['user_id' => $admin->id, 'template_id' => $template->id]);

        // Bulk-upload rows never carry a custom_image_fields key at all (see
        // BulkUploadIngestService) - the Action must default it safely
        // rather than assume every caller now supplies it.
        $certificate = app(\App\Actions\Certificates\IssueSingleCertificateAction::class)->execute(
            $admin,
            $template,
            [
                'title' => 'Bulk Certificate',
                'recipient_name' => 'Bulk Recipient',
                'recipient_email' => 'bulk@example.com',
                'date_of_issue' => now()->toDateString(),
            ],
            $batch,
        );

        $this->assertNull($certificate->custom_image_fields);
        $this->assertSame($batch->id, $certificate->certificate_batch_id);
    }

    public function test_canvas_page_falls_back_to_the_plain_form_when_the_template_has_no_canvas_json(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create(['canvas_json' => null]);

        $this->actingAs($user)
            ->get(route('certificates.create.canvas', ['template' => $template->id]))
            ->assertRedirect(route('certificates.create', ['template' => $template->id]));
    }

    public function test_canvas_page_overlays_inputs_only_for_editable_bindings(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'canvas_json' => [
                'elements' => [
                    ['id' => 'el_1', 'type' => 'text', 'binding' => 'title', 'xPercent' => 10, 'yPercent' => 10, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 0],
                    ['id' => 'el_2', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 1],
                    ['id' => 'el_3', 'type' => 'text', 'binding' => 'organization_name', 'xPercent' => 10, 'yPercent' => 30, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 2],
                    ['id' => 'el_4', 'type' => 'qrcode', 'binding' => 'qrcode', 'xPercent' => 60, 'yPercent' => 30, 'widthPercent' => 15, 'heightPercent' => 15, 'rotation' => 0, 'z' => 3],
                    ['id' => 'el_5', 'type' => 'text', 'binding' => 'course_name', 'label' => 'Course Name', 'xPercent' => 10, 'yPercent' => 40, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 4],
                ],
            ],
            'custom_field_schema' => [
                ['key' => 'course_name', 'label' => 'Course Name', 'type' => 'text', 'required' => false],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('certificates.create.canvas', ['template' => $template->id]));

        $response->assertOk();
        $response->assertSee('name="title"', false);
        $response->assertSee('name="recipient_name"', false);
        $response->assertSee('name="custom_fields[course_name]"', false);
        // organization_name and qrcode are system-populated, never user-editable on this page.
        $response->assertDontSee('name="organization_name"', false);
        $response->assertDontSee('name="qrcode"', false);
    }

    /** Admin actor - test is about the canvas form mapping to the right
     * validated fields, unrelated to billing. */
    public function test_a_certificate_can_be_issued_through_the_canvas_pages_form(): void
    {
        Bus::fake();
        $this->fakeAssetGeneration();
        Storage::fake('local');

        $user = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'canvas_json' => [
                'elements' => [
                    ['id' => 'el_1', 'type' => 'text', 'binding' => 'title', 'xPercent' => 10, 'yPercent' => 10, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 0],
                    ['id' => 'el_2', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 30, 'heightPercent' => 5, 'rotation' => 0, 'z' => 1],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('certificates.create.canvas', ['template' => $template->id]))
            ->assertOk();

        // The page has no bespoke submit endpoint - it posts straight to
        // certificates.store, unchanged.
        $response = $this->actingAs($user)->post(route('certificates.store'), [
            'template_id' => $template->id,
            'title' => 'Canvas Issued Certificate',
            'recipient_name' => 'Canvas Recipient',
            'recipient_email' => 'canvas@example.com',
            'date_of_issue' => now()->toDateString(),
        ]);

        $certificate = Certificate::firstOrFail();
        $response->assertRedirect(route('certificates.show', $certificate));
        $this->assertSame('Canvas Issued Certificate', $certificate->title);
    }

    public function test_a_synchronous_generation_failure_is_recorded_not_thrown(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
        ]);

        // Forces a fast, deterministic failure inside renderHtml() (which
        // renderPdf() calls first) without needing to break real
        // Browsershot/Chrome: a soft-deleted template makes the belongsTo
        // relation resolve to null on a fresh load, so accessing
        // $template->html_content throws immediately.
        $template->delete();
        $freshCertificate = Certificate::find($certificate->id);

        app(CertificateRenderService::class)->generateAssetsSynchronously($freshCertificate);

        $freshCertificate->refresh();

        // The method itself must not throw (already implicit - reaching
        // this line proves it) - and the failure must be recorded so the
        // existing regenerate/retry action can surface it.
        $this->assertSame('failed', $freshCertificate->image_generation_status);
        $this->assertNotEmpty($freshCertificate->qr_code_path, 'QR runs before the template lookup and should have succeeded.');
        $this->assertNull($freshCertificate->pdf_path);
        $this->assertNull($freshCertificate->image_path);
    }

    public function test_the_show_page_renders_a_retry_action_for_a_failed_certificate(): void
    {
        $user = User::factory()->create();
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'qr_code_path' => 'certificates/1/qr/test.png',
            'pdf_path' => null,
            'image_path' => null,
            'image_generation_status' => 'failed',
        ]);

        $this->actingAs($user)
            ->get(route('certificates.show', $certificate))
            ->assertOk()
            ->assertSee('Retry Generation');
    }
}
