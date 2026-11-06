<?php

namespace Tests\Feature;

use Tests\TestCase;

/** T-01 y parte de T-16: no necesita base de datos. */
final class ApiContractSmokeTest extends TestCase
{
    public function test_health_responds_ok(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_protected_route_without_token_uses_the_single_error_body(): void
    {
        $this->getJson('/api/v1/books')
            ->assertStatus(401)
            ->assertJsonStructure(['code', 'message', 'details'])
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_unknown_route_uses_the_single_error_body(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertStatus(404)
            ->assertJsonStructure(['code', 'message', 'details'])
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_login_validation_error_uses_the_single_error_body(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['code', 'message', 'details' => [['field', 'issue']]]);
    }
}
