<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\CertificateBatch;
use App\Models\User;
use App\Notifications\BulkUploadCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_render_the_notification_bell(): void
    {
        Livewire::test(NotificationBell::class)
            ->assertForbidden();
    }

    public function test_bell_shows_unread_count_badge(): void
    {
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $user->notify(new BulkUploadCompletedNotification($batch));

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSee('1', false)
            ->assertSee('Bulk upload completed', false);
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $user->id]);
        $user->notify(new BulkUploadCompletedNotification($batch));

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllRead')
            ->assertDontSeeHtml('bg-error');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
