<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicRoutesWithoutSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // الاختبارات بتشتغل بـ session array؛ هون بنفعّل driver الداتابيز عشان نقدر نعدّ صفوف sessions.
        config(['session.driver' => 'database']);
    }

    public function test_a_session_route_does_write_a_session_row_and_cookie(): void
    {
        // شاهد (control): لو هاد فشل، الاختبارات اللي تحته ما بتثبت شي
        $response = $this->getJson('/api/auth/me')->assertStatus(401);

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_public_read_routes_open_no_session_and_set_no_cookie(): void
    {
        foreach (['/api/prices', '/api/prices/ticker', '/api/products/search?q=سكر'] as $uri) {
            $response = $this->getJson($uri)->assertOk();

            $this->assertSame([], $response->headers->getCookies(), "$uri set a cookie");
            $this->assertSame(0, DB::table('sessions')->count(), "$uri wrote a session row");
        }
    }

    public function test_public_routes_responses_do_not_vary_by_logged_in_user(): void
    {
        $guest = $this->getJson('/api/prices')->assertOk()->getContent();

        $user = new User;
        $user->user_phone = '0591234567';
        $user->user_full_name = 'Test User';
        $user->save();

        $this->assertSame($guest, $this->actingAs($user)->getJson('/api/prices')->assertOk()->getContent());
    }
}