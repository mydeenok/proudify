<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AdminNewRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registering_creates_a_pending_otp_user_and_does_not_authenticate(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'organization_name' => 'Acme University',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('pending_otp', $user->status);
        $this->assertNotNull($user->otp_code);
        $this->assertGuest();
        $response->assertRedirect(route('otp.verify'));
    }

    public function test_full_onboarding_flow_registers_verifies_and_approves(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'organization_name' => 'Acme University',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        // Gate 1 -> 2: wrong OTP is rejected, session still pending_otp.
        $this->post(route('otp.verify.store'), ['otp_code' => '000000'])
            ->assertSessionHasErrors('otp_code');
        $this->assertSame('pending_otp', $user->fresh()->status);

        // Correct OTP moves the account into the approval queue, still can't log in.
        $rawOtp = $this->extractRawOtpFromDatabase($user);
        $this->post(route('otp.verify.store'), ['otp_code' => $rawOtp])
            ->assertRedirect(route('otp.pending-approval'));
        $this->assertSame('pending_approval', $user->fresh()->status);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Gate 3: admin approves, user can now log in.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('admin.users.approve', $user))
            ->assertRedirect();
        $this->assertSame('active', $user->fresh()->status);

        // actingAs() leaves the test client authenticated as the admin, and
        // /login is behind 'guest' middleware, so drop that session before
        // attempting the user's own login.
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_verifying_otp_notifies_every_admin_of_the_new_registration(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'organization_name' => 'Acme University',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $rawOtp = $this->extractRawOtpFromDatabase($user);

        $this->post(route('otp.verify.store'), ['otp_code' => $rawOtp]);

        Notification::assertSentTo($admin, AdminNewRegistrationNotification::class);
        Notification::assertSentTo($otherAdmin, AdminNewRegistrationNotification::class);
        Notification::assertNotSentTo($user, AdminNewRegistrationNotification::class);
    }

    /**
     * The OTP is hashed at rest, so tests regenerate a known code directly
     * via the service rather than trying to reverse the hash.
     */
    private function extractRawOtpFromDatabase(User $user): string
    {
        $code = '654321';

        $user->forceFill(['otp_code' => bcrypt($code)])->save();

        return $code;
    }
}
