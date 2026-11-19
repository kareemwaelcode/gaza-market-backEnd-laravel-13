<?php

namespace Tests;

use App\Models\Admin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $actAsAdmin = true;

    protected function signInAdmin(): Admin
    {
        $admin = (new Admin)->forceFill([
            'id' => 1,
            'full_name' => 'Test Admin',
            'email' => 'admin@test.local',
            'mobile' => '0590000000',
        ]);

        $this->app['auth']->guard('admins')->setUser($admin);

        return $admin;
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $needsAdmin = $this->actAsAdmin && $this->isProtectedCmsPath($uri);

        if ($needsAdmin) {
            $this->signInAdmin();
        }

        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if ($needsAdmin) {
            $this->app['auth']->shouldUse(config('auth.defaults.guard'));
        }

        return $response;
    }

    private function isProtectedCmsPath(string $uri): bool
    {
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');

        return ($path === 'cms/admins' || str_starts_with($path, 'cms/admins/'))
            && $path !== 'cms/admins/login';
    }
}