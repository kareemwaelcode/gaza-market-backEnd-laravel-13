<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $this->get('/up')->assertStatus(200);
    }

    public function test_domain_root_is_not_served_by_laravel_and_cms_home_keeps_its_route_name(): void
    {
        $this->get('/')->assertNotFound();
        $this->get('/temp')->assertNotFound();

        $this->assertSame(url('/cms/admins'), route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }
}