<?php

namespace Tests\Feature;

use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return UserFactory::new()->create($overrides);
    }

    private function rule(array $overrides = []): PointRule
    {
        return PointRule::create($overrides + [
            'point_rule_key' => 'add_price',
            'point_rule_name_en' => 'Add price',
            'point_rule_name_ar' => 'إضافة سعر',
            'point_rule_points' => 10,
            'point_rule_daily_limit' => 5,
            'point_rule_is_active' => true,
            'point_rule_sort_order' => 1,
        ]);
    }

    private function transaction(User $user, PointRule $rule, array $overrides = []): PointTransaction
    {
        return PointTransaction::create($overrides + [
            'user_id' => $user->id,
            'point_rule_id' => $rule->id,
            'point_transaction_points' => 10,
            'point_transaction_source_type' => 'product_price',
            'point_transaction_source_id' => 85,
        ]);
    }

    public function test_index_page_lists_transactions(): void
    {
        $user = $this->user(['user_full_name' => 'Ahmad']);
        $this->transaction($user, $this->rule());

        $this->get('/cms/admins/point_transaction')
            ->assertOk()
            ->assertSee('Ahmad')
            ->assertSee('Add price')
            ->assertSee('+10')
            ->assertSee('product_price #85');
    }

    public function test_index_shows_transactions_of_soft_deleted_user(): void
    {
        $user = $this->user(['user_full_name' => 'Deleted Person']);
        $this->transaction($user, $this->rule());
        $user->delete();

        $this->get('/cms/admins/point_transaction')
            ->assertOk()
            ->assertSee('Deleted Person');
    }

    public function test_points_can_be_negative(): void
    {
        $user = $this->user();
        $tx = $this->transaction($user, $this->rule(), [
            'point_transaction_points' => -5,
            'point_transaction_source_type' => null,
            'point_transaction_source_id' => null,
        ]);

        $this->assertSame(-5, $tx->fresh()->point_transaction_points);
        $this->get('/cms/admins/point_transaction')->assertOk()->assertSee('-5');
    }

    public function test_created_at_is_set_and_there_is_no_updated_at(): void
    {
        $tx = $this->transaction($this->user(), $this->rule())->fresh();

        $this->assertNotNull($tx->point_transaction_created_at);
        $this->assertArrayNotHasKey('updated_at', $tx->getAttributes());
    }

    public function test_same_source_cannot_award_the_same_rule_twice(): void
    {
        $user = $this->user();
        $rule = $this->rule();
        $this->transaction($user, $rule);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->transaction($user, $rule);
    }

    public function test_different_source_or_user_is_allowed(): void
    {
        $user = $this->user();
        $rule = $this->rule();
        $this->transaction($user, $rule);

        $this->transaction($user, $rule, ['point_transaction_source_id' => 86]);
        $this->transaction($this->user(), $rule);

        $this->assertSame(3, PointTransaction::count());
    }

    public function test_transactions_without_source_do_not_conflict(): void
    {
        $user = $this->user();
        $rule = $this->rule();
        $noSource = ['point_transaction_source_type' => null, 'point_transaction_source_id' => null];

        $this->transaction($user, $rule, $noSource);
        $this->transaction($user, $rule, $noSource);

        $this->assertSame(2, PointTransaction::count());
    }

    public function test_user_and_rule_with_transactions_cannot_be_force_deleted(): void
    {
        $user = $this->user();
        $rule = $this->rule();
        $this->transaction($user, $rule);

        try {
            $rule->delete();
            $this->fail('Rule with transactions must not be deletable.');
        } catch (QueryException) {
            $this->assertDatabaseHas('point_rules', ['id' => $rule->id]);
        }

        try {
            $user->forceDelete();
            $this->fail('User with transactions must not be force-deletable.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }
}