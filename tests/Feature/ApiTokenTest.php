<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_generate_an_api_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'My integration',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('My integration', $user->tokens()->first()->name);
        $this->assertNotEmpty(session('plainTextToken'));
    }

    public function test_a_user_can_generate_a_token_with_website_metadata(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'CRM integration',
            'website_name' => 'Acme CRM',
            'website_url' => 'https://acme.example.com',
        ]);

        $token = $user->tokens()->first();
        $this->assertSame('Acme CRM', $token->website_name);
        $this->assertSame('https://acme.example.com', $token->website_url);
    }

    public function test_website_fields_are_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'No website',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull($user->tokens()->first()->website_name);
    }

    public function test_a_user_can_only_revoke_their_own_token(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $owner->createToken('test');

        $this->actingAs($intruder)->delete(route('profile.api-tokens.destroy', $token->accessToken->id));

        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_a_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->actingAs($user)->delete(route('profile.api-tokens.destroy', $token->accessToken->id))
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_guests_cannot_manage_api_tokens(): void
    {
        $this->post(route('profile.api-tokens.store'), ['name' => 'x'])->assertRedirect(route('login'));
    }

    public function test_the_api_access_tab_renders_an_existing_key_with_website_metadata(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CRM integration');
        $token->accessToken->update(['website_name' => 'Acme CRM', 'website_url' => 'https://acme.example.com']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('CRM integration');
        $response->assertSee('Acme CRM');
        $response->assertSee('https://acme.example.com');
        $response->assertSee('Unused');
        $response->assertSee('Base URL');
        $response->assertSee('Endpoints');
        $response->assertSee('Example request');
    }
}
