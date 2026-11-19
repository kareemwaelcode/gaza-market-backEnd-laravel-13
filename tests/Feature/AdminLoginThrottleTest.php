<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_is_throttled_after_repeated_failures(): void
    {
        $credentials = ['email' => 'nobody@test.local', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admins/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/admins/login', $credentials)->assertStatus(429);
    }

    public function test_the_account_limit_does_not_block_other_emails_from_the_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admins/login', ['email' => 'a@test.local', 'password' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/admins/login', ['email' => 'b@test.local', 'password' => 'x'])->assertUnauthorized();
    }
}