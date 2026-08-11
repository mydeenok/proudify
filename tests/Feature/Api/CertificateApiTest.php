<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_can_list_its_own_certificates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        Certificate::factory()->count(3)->create(['user_id' => $user->id]);
        Certificate::factory()->count(2)->create(); // another user's, must not appear

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.index'));

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_a_token_can_fetch_one_of_its_own_certificates_by_uuid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'verification_code' => 'ABC12345',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.show', $certificate->uuid));

        $response->assertOk()
            ->assertJsonPath('data.uuid', $certificate->uuid)
            ->assertJsonPath('data.verification_code', 'ABC12345');
    }

    public function test_a_token_can_fetch_one_of_its_own_certificates_by_verification_code(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'verification_code' => 'ABC12345',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.show', 'ABC12345'));

        $response->assertOk()->assertJsonPath('data.uuid', $certificate->uuid);
    }

    /**
     * Guards against a real bug caught during review: an unwrapped
     * orWhere('verification_code', ...) chained after certificates()'s own
     * user_id constraint would OR against the whole query rather than
     * being confined by it, leaking any tenant's certificate that happens
     * to share the same code lookup value.
     */
    public function test_a_token_cannot_fetch_another_users_certificate_by_verification_code(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('test')->plainTextToken;
        Certificate::factory()->create(['user_id' => $owner->id, 'verification_code' => 'SHARED123']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.show', 'SHARED123'))
            ->assertNotFound();
    }

    /**
     * The load-bearing security property of this API: a token belonging to
     * one tenant must never be able to see or even confirm the existence
     * of another tenant's certificate, by uuid or otherwise.
     */
    public function test_a_token_cannot_fetch_another_users_certificate(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('test')->plainTextToken;
        $certificate = Certificate::factory()->create(['user_id' => $owner->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.show', $certificate->uuid))
            ->assertNotFound();
    }

    public function test_a_token_can_fetch_its_own_certificate_image(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('certificates/1/test.jpg', 'fake-image-bytes');
        $certificate = Certificate::factory()->create(['user_id' => $user->id, 'image_path' => 'certificates/1/test.jpg']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get(route('api.certificates.image', $certificate->uuid))
            ->assertOk();
    }

    public function test_a_token_cannot_fetch_another_users_certificate_image(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('test')->plainTextToken;
        $certificate = Certificate::factory()->create(['user_id' => $owner->id, 'image_path' => 'certificates/1/test.jpg']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get(route('api.certificates.image', $certificate->uuid))
            ->assertNotFound();
    }

    public function test_an_unknown_uuid_returns_404(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.show', 'does-not-exist'))
            ->assertNotFound();
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson(route('api.certificates.index'))->assertUnauthorized();
    }

    public function test_a_revoked_token_can_no_longer_authenticate(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test');
        $token = $tokenResult->plainTextToken;

        $user->tokens()->where('id', $tokenResult->accessToken->id)->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.certificates.index'))
            ->assertUnauthorized();
    }
}
