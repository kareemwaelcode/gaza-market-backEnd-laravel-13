<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $this->get('/up')->assertStatus(200);
    }

    // جذر الدومين للفرونت: لارافل ما بيخدمه، ولوحة الـ CMS بتضل على /cms/admins باسم dashboard
    public function test_domain_root_is_not_served_by_laravel_and_cms_home_keeps_its_route_name(): void
    {
        $this->get('/')->assertNotFound();
        $this->get('/temp')->assertNotFound();

        $this->assertSame(url('/cms/admins'), route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }
}