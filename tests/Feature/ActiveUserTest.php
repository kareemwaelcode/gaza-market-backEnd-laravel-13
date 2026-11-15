<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $status = 'active'): User
    {
        $user = new User;
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = 'Test User';
        $user->user_status = $status;
        $user->save();

        return $user;
    }

    /** اسم مفتاح الجلسة اللي بيخزّن id المستخدم المسجّل (يعني دخول حقيقي بالجلسة، مش actingAs). */
    private function sessionLogin(User $user): array
    {
        return [Auth::guard('web')->getName() => $user->id];
    }

    /** كل راوتات المسجّلين المحمية بـ active.user (الطريقة، المسار). */
    private function protectedRoutes(): array
    {
        return [
            ['GET', '/api/auth/me'],
            ['GET', '/api/prices/my-votes?price_ids[]=1'],
            ['POST', '/api/prices'],
            ['POST', '/api/prices/1/vote'],
            ['POST', '/api/products'],
            ['POST', '/api/market'],
            ['GET', '/api/market/saved'],
            ['POST', '/api/market/1/save'],
            ['DELETE', '/api/market/1/save'],
            ['POST', '/api/places'],
            ['GET', '/api/places/mine'],
            ['GET', '/api/dashboard/place'],
        ];
    }

    public function test_suspended_and_deleted_statuses_are_rejected_on_every_protected_route(): void
    {
        foreach (['suspended', 'deleted'] as $status) {
            foreach ($this->protectedRoutes() as [$method, $uri]) {
                $this->flushSession();
                $this->app['auth']->forgetGuards();

                $this->actingAs($this->makeUser($status))
                    ->json($method, $uri)
                    ->assertStatus(401)
                    ->assertExactJson(['code' => 'UNAUTHENTICATED']);
            }
        }
    }

    public function test_user_suspended_after_login_is_kicked_out_immediately_without_touching_the_sessions_table(): void
    {
        $user = $this->makeUser();

        // نفس الجلسة بتفضل مفتوحة؛ بس حالة المستخدم بالداتابيز بتتغير (محاكاة إيقاف من الـ CMS)
        $this->withSession($this->sessionLogin($user))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        DB::table('users')->where('id', $user->id)->update(['user_status' => 'suspended']);
        $this->app['auth']->forgetGuards();

        $this->withSession($this->sessionLogin($user))
            ->getJson('/api/prices/my-votes?price_ids[]=1')
            ->assertStatus(401)
            ->assertExactJson(['code' => 'UNAUTHENTICATED']);

        $this->app['auth']->forgetGuards();

        $this->withSession($this->sessionLogin($user))
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_reactivated_user_with_a_fresh_login_is_accepted_again(): void
    {
        $user = $this->makeUser('suspended');
        DB::table('users')->where('id', $user->id)->update(['user_status' => 'active']);

        $this->withSession($this->sessionLogin($user))
            ->getJson('/api/prices/my-votes?price_ids[]=1')
            ->assertOk();
    }

    public function test_guests_still_get_the_plain_auth_401_not_an_active_user_error(): void
    {
        $this->getJson('/api/prices/my-votes?price_ids[]=1')->assertStatus(401);
        $this->getJson('/api/dashboard/place')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_active_user_is_not_blocked(): void
    {
        $this->actingAs($this->makeUser())
            ->getJson('/api/prices/my-votes?price_ids[]=1')
            ->assertOk();
    }
}