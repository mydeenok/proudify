<?php

namespace Tests\Feature;

use App\Livewire\Admin\ContactRequestsTable;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\AdminContactRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_contact_form(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact support');
    }

    public function test_a_guest_can_submit_a_contact_request_and_admins_are_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'organization' => 'Analytical Engines',
            'subject' => 'Need help issuing certificates',
            'message' => 'We are stuck on bulk upload mapping.',
        ])->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'ada@example.com',
            'subject' => 'Need help issuing certificates',
            'status' => ContactRequest::STATUS_OPEN,
        ]);

        Notification::assertSentTo($admin, AdminContactRequestNotification::class);
    }

    public function test_contact_request_still_creates_bell_notification_when_mail_fails(): void
    {
        config(['mail.default' => 'smtp']);
        config([
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 9,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.encryption' => null,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $admin = User::factory()->admin()->create();

        $this->post(route('contact.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'organization' => null,
            'subject' => 'Account help',
            'message' => 'Please reset my org access.',
        ])->assertRedirect(route('contact'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
            'type' => AdminContactRequestNotification::class,
        ]);

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->post(route('contact.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_admins_can_browse_and_update_contact_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $request = ContactRequest::factory()->create([
            'subject' => 'Billing question',
            'status' => ContactRequest::STATUS_OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index'))
            ->assertOk()
            ->assertSeeLivewire(ContactRequestsTable::class)
            ->assertSee('Billing question');

        $this->actingAs($admin)
            ->patch(route('admin.contact-requests.status', $request), [
                'status' => ContactRequest::STATUS_CLOSED,
            ])
            ->assertRedirect();

        $this->assertSame(ContactRequest::STATUS_CLOSED, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->handled_by);
    }

    public function test_non_admins_cannot_open_the_contact_inbox(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.contact-requests.index'))
            ->assertForbidden();
    }

    public function test_admin_table_search_filters_reactively(): void
    {
        $admin = User::factory()->admin()->create();
        ContactRequest::factory()->create(['subject' => 'Alpha subject']);
        ContactRequest::factory()->create(['subject' => 'Beta subject']);

        Livewire::actingAs($admin)
            ->test(ContactRequestsTable::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha subject')
            ->assertDontSee('Beta subject');
    }
}
