<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * يمنع رجوع مشكلة "Auth guard [xxx] is not defined": أي اسم guard مستخدم بالكود
 * (auth:xxx أو Auth::guard('xxx')) لازم يكون معرّف بـ config('auth.guards').
 */
class AuthGuardsConfigTest extends TestCase
{
    public function test_default_guard_is_defined(): void
    {
        $this->assertArrayHasKey(config('auth.defaults.guard'), config('auth.guards'));
    }

    public function test_every_guard_used_in_code_is_defined(): void
    {
        $defined = array_keys(config('auth.guards'));

        foreach ($this->guardsUsedInCode() as $guard => $files) {
            $this->assertContains(
                $guard,
                $defined,
                "guard '{$guard}' مستخدم في: ".implode(', ', array_unique($files)).' لكنه غير معرّف في config/auth.php'
            );
        }
    }

    public function test_user_guard_is_web_and_admin_guards_are_kept(): void
    {
        $this->assertSame('users', config('auth.guards.web.provider'));
        $this->assertSame('admins', config('auth.guards.admins.provider'));
        $this->assertSame('passport', config('auth.guards.admins-api.driver'));
        $this->assertArrayNotHasKey('users', config('auth.guards'));
    }

    /** @return array<string, list<string>> اسم الـ guard => الملفات اللي استخدمته */
    private function guardsUsedInCode(): array
    {
        $found = [];
        $files = array_merge(
            File::allFiles(app_path()),
            File::allFiles(base_path('routes')),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = $file->getContents();
            $names = [];

            // 'auth:web' أو 'auth:admins,web' (middleware)
            if (preg_match_all("/['\"]auth:([A-Za-z0-9_,\-]+)['\"]/", $code, $m)) {
                foreach ($m[1] as $list) {
                    array_push($names, ...explode(',', $list));
                }
            }

            // Auth::guard('web') أو ->guard('web')
            if (preg_match_all("/guard\(\s*['\"]([A-Za-z0-9_\-]+)['\"]\s*\)/", $code, $m)) {
                array_push($names, ...$m[1]);
            }

            foreach ($names as $name) {
                $found[$name][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        return $found;
    }
}