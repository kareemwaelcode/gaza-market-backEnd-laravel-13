<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEndpointsThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = new User();
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = 'Throttle User';
        $user->save();

        return $user;
    }

    public function test_product_search_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/products/search?q=x')->assertStatus(400);
        }

        $this->getJson('/api/products/search?q=x')->assertStatus(429);
    }

    public function test_product_search_limit_is_not_shared_between_ips(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->getJson('/api/products/search?q=x')->assertStatus(400);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->getJson('/api/products/search?q=x')->assertStatus(400);
    }

    public function test_create_market_is_throttled_per_user(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/market', [])->assertStatus(400);
        }

        $this->actingAs($user)->postJson('/api/market', [])->assertStatus(429);

        $this->actingAs($this->makeUser())->postJson('/api/market', [])->assertStatus(400);
    }

    public function test_create_product_is_throttled_per_user(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/products', [])->assertStatus(400);
        }

        $this->actingAs($user)->postJson('/api/products', [])->assertStatus(429);

        $this->actingAs($this->makeUser())->postJson('/api/products', [])->assertStatus(400);
    }
}