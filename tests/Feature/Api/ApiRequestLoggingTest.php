<?php

namespace Tests\Feature\Api;

use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_api_call_is_logged_against_its_token(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test');

        $this->withHeader('Authorization', "Bearer {$tokenResult->plainTextToken}")
            ->withHeader('Origin', 'https://example.com')
            ->getJson(route('api.certificates.index'));

        $log = ApiRequestLog::firstOrFail();
        $this->assertSame($tokenResult->accessToken->id, $log->personal_access_token_id);
        $this->assertSame('GET', $log->method);
        $this->assertSame(200, $log->status_code);
        $this->assertSame('https://example.com', $log->origin);
    }

    public function test_an_unauthenticated_call_is_not_logged(): void
    {
        $this->getJson(route('api.certificates.index'));

        $this->assertSame(0, ApiRequestLog::count());
    }
}
