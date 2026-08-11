<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function insertSession(string $id, int $userId, ?string $userAgent = null, ?string $ip = '127.0.0.1'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_the_security_tab_lists_a_users_active_sessions(): void
    {
        $user = User::factory()->create();
        $this->insertSession('other-device-session', $user->id, 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Active Sessions');
        $response->assertSee('Chrome on Windows');
    }

    public function test_a_user_can_sign_out_another_device(): void
    {
        $user = User::factory()->create();
        $this->insertSession('other-device-session', $user->id);

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy', 'other-device-session'))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
    }

    public function test_a_user_cannot_sign_out_another_users_session(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->insertSession('owners-session', $owner->id);

        $this->actingAs($intruder)->delete(route('profile.sessions.destroy', 'owners-session'));

        $this->assertDatabaseHas('sessions', ['id' => 'owners-session']);
    }

    public function test_logout_other_devices_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->actingAs($user)->delete(route('profile.sessions.destroy-others'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('logoutOtherDevices', ['password']);
    }

    public function test_logout_other_devices_succeeds_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->actingAs($user)->delete(route('profile.sessions.destroy-others'), [
            'password' => 'correct-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'other-sessions-revoked');
    }

    public function test_guests_cannot_manage_sessions(): void
    {
        $this->delete(route('profile.sessions.destroy', 'anything'))->assertRedirect(route('login'));
        $this->delete(route('profile.sessions.destroy-others'))->assertRedirect(route('login'));
    }
}
