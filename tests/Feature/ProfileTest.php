<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'organization_name' => 'New Org',
                'phone' => '1234567890',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Updated', $user->first_name);
        $this->assertSame('Name', $user->last_name);
        $this->assertSame('New Org', $user->organization_name);
    }

    public function test_email_cannot_be_changed_via_profile_update(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)->patch('/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'organization_name' => $user->organization_name,
            'email' => 'changed@example.com',
        ]);

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_a_user_can_upload_organization_logos_and_a_signature(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.organization.update'), [
            'org_logos' => [UploadedFile::fake()->image('logo.png')],
            'signature' => UploadedFile::fake()->image('signature.png'),
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertCount(1, $user->org_logos);
        Storage::disk('public')->assertExists($user->org_logos[0]);
        Storage::disk('public')->assertExists($user->signature_path);
    }

    public function test_a_user_can_remove_an_existing_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $existingLogoPath = UploadedFile::fake()->image('old.png')->store('organization-logos', 'public');
        $user->update(['org_logos' => [$existingLogoPath]]);

        $this->actingAs($user)->post(route('profile.organization.update'), [
            'remove_logos' => [$existingLogoPath],
        ]);

        $this->assertEmpty($user->fresh()->org_logos);
        Storage::disk('public')->assertMissing($existingLogoPath);
    }

    public function test_uploading_a_new_signature_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldSignaturePath = UploadedFile::fake()->image('old-sig.png')->store('signatures', 'public');
        $user->update(['signature_path' => $oldSignaturePath]);

        $this->actingAs($user)->post(route('profile.organization.update'), [
            'signature' => UploadedFile::fake()->image('new-sig.png'),
        ]);

        $user->refresh();
        Storage::disk('public')->assertMissing($oldSignaturePath);
        Storage::disk('public')->assertExists($user->signature_path);
        $this->assertNotSame($oldSignaturePath, $user->signature_path);
    }
}
