<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    protected bool $actAsAdmin = false;

    public function test_guest_is_redirected_to_the_admin_login_page(): void
    {
        $this->get('/cms/admins')
            ->assertRedirect(route('view.login', ['guard' => 'admins']));

        $this->get('/cms/admins/point_rule')
            ->assertRedirect(route('view.login', ['guard' => 'admins']));
    }

    public function test_guest_json_request_gets_401(): void
    {
        $this->postJson('/cms/admins/point_rule_update/1', [])->assertUnauthorized();
        $this->deleteJson('/cms/admins/user/1')->assertUnauthorized();
    }

    public function test_signed_in_admin_reaches_the_dashboard(): void
    {
        $this->signInAdmin();

        $this->get('/cms/admins')->assertOk();
    }
}