<?php

namespace Tests\Feature\Subscriptions;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference app's equivalent module was confirmed read-only at the
 * routing level. These tests exist specifically to prove the fix: real
 * server-side handlers now exist for edit/extend/cancel.
 */
class AdminUserSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_adjust_a_subscriptions_limits(): void
    {
        $admin = User::factory()->admin()->create();
        $userSubscription = UserSubscription::factory()->create(['certificates_limit' => 50]);

        $this->actingAs($admin)->patch(route('admin.user-subscriptions.update', $userSubscription), [
            'certificates_limit' => 200,
            'users_limit' => 200,
        ])->assertRedirect(route('admin.user-subscriptions.index'));

        $this->assertSame(200, $userSubscription->fresh()->certificates_limit);
    }

    public function test_an_admin_can_extend_a_subscriptions_end_date(): void
    {
        $admin = User::factory()->admin()->create();
        $userSubscription = UserSubscription::factory()->create(['end_date' => now()->addDays(5)]);
        $originalEndDate = $userSubscription->end_date->copy();

        $this->actingAs($admin)->post(route('admin.user-subscriptions.extend', $userSubscription), [
            'days' => 30,
        ]);

        $this->assertTrue($userSubscription->fresh()->end_date->equalTo($originalEndDate->addDays(30)));
    }

    public function test_an_admin_can_cancel_a_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $userSubscription = UserSubscription::factory()->create(['is_active' => true, 'auto_renew' => true]);

        $this->actingAs($admin)->patch(route('admin.user-subscriptions.cancel', $userSubscription));

        $userSubscription->refresh();
        $this->assertFalse($userSubscription->is_active);
        $this->assertFalse($userSubscription->auto_renew);
    }

    public function test_only_admins_can_manage_user_subscriptions(): void
    {
        $user = User::factory()->create();
        $userSubscription = UserSubscription::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.user-subscriptions.index'))
            ->assertForbidden();
    }
}
