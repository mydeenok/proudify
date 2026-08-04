<?php

namespace Tests\Feature;

use App\Models\CertificateBatch;
use App\Models\User;
use App\Notifications\BulkUploadCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_route(): void
    {
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'total_rows' => 10,
            'succeeded_rows' => 10,
            'failed_rows' => 0,
        ]);
        $user->notify(new BulkUploadCompletedNotification($batch));
        $notification = $user->notifications()->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('bulk-upload.status', ['batch' => $batch->id]));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_opening_an_already_read_notification_still_redirects_without_error(): void
    {
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $user->notify(new BulkUploadCompletedNotification($batch));
        $notification = $user->notifications()->firstOrFail();
        $notification->markAsRead();
        $originalReadAt = $notification->fresh()->read_at;

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('bulk-upload.status', ['batch' => $batch->id]));
        $this->assertEquals($originalReadAt, $notification->fresh()->read_at);
    }

    public function test_opening_a_notification_without_a_stored_route_falls_back_to_dashboard(): void
    {
        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => BulkUploadCompletedNotification::class,
            'data' => ['title' => 'No route here', 'body' => 'Just a heads up.'],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_a_user_cannot_open_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $owner->id]);
        $owner->notify(new BulkUploadCompletedNotification($batch));
        $notification = $owner->notifications()->firstOrFail();

        $response = $this
            ->actingAs($intruder)
            ->get(route('notifications.open', $notification->id));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guests_cannot_open_notifications_or_mark_all_read(): void
    {
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $user->notify(new BulkUploadCompletedNotification($batch));
        $notification = $user->notifications()->firstOrFail();

        $this->get(route('notifications.open', $notification->id))->assertRedirect(route('login'));
        $this->post(route('notifications.mark-all-read'))->assertRedirect(route('login'));
    }

    public function test_mark_all_read_marks_every_unread_notification_as_read(): void
    {
        $user = User::factory()->create();
        $batchOne = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $batchTwo = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $user->notify(new BulkUploadCompletedNotification($batchOne));
        $user->notify(new BulkUploadCompletedNotification($batchTwo));

        $this->assertSame(2, $user->fresh()->unreadNotifications()->count());

        $response = $this
            ->actingAs($user)
            ->post(route('notifications.mark-all-read'));

        $response->assertRedirect();
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
