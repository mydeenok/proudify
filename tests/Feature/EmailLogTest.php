<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_mail_delivery_is_logged(): void
    {
        $user = User::factory()->create();

        Event::dispatch(new NotificationSent($user, new AccountApprovedNotification, 'mail', null));

        $this->assertDatabaseHas('email_logs', [
            'notification_class' => AccountApprovedNotification::class,
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);
    }

    public function test_a_failed_mail_delivery_is_logged_with_the_error(): void
    {
        $user = User::factory()->create();
        $exception = new \RuntimeException('Unable to send an email: Forbidden (code 401).');

        Event::dispatch(new NotificationFailed($user, new AccountApprovedNotification, 'mail', ['exception' => $exception]));

        $this->assertDatabaseHas('email_logs', [
            'notification_class' => AccountApprovedNotification::class,
            'recipient_email' => $user->email,
            'status' => 'failed',
            'error_message' => 'Unable to send an email: Forbidden (code 401).',
        ]);
    }

    public function test_database_channel_events_are_not_logged_as_emails(): void
    {
        $user = User::factory()->create();

        Event::dispatch(new NotificationSent($user, new AccountApprovedNotification, 'database', null));
        Event::dispatch(new NotificationFailed($user, new AccountApprovedNotification, 'database', ['exception' => new \RuntimeException('n/a')]));

        $this->assertSame(0, EmailLog::count());
    }

    public function test_admin_analytics_page_shows_email_delivery_stats(): void
    {
        $admin = User::factory()->admin()->create();
        EmailLog::factory()->count(3)->create(['status' => 'sent']);
        EmailLog::factory()->count(1)->create(['status' => 'failed']);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Email Delivery')
            ->assertSee('75%'); // 3 sent / 4 total
    }
}
