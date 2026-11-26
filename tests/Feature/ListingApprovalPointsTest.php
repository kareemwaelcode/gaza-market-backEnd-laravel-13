<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Listing;
use App\Models\PointRule;
use App\Models\PointTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class ListingApprovalPointsTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    private function rule(int $points = 10, bool $active = true, ?int $limit = null): PointRule
    {
        return PointRule::create([
            'point_rule_key' => 'add_listing',
            'point_rule_name_en' => 'Add listing',
            'point_rule_name_ar' => 'إضافة إعلان',
            'point_rule_points' => $points,
            'point_rule_daily_limit' => $limit,
            'point_rule_is_active' => $active,
        ]);
    }

    private function actAsApiAdmin(): void
    {
        $admin = Admin::forceCreate([
            'full_name' => 'Api Admin',
            'email' => 'listing-admin@test.local',
            'mobile' => '0590000002',
            'password' => Hash::make('secret-pass-1'),
        ]);

        Passport::actingAs($admin, [], 'admins-api');
    }

    public function test_api_approval_awards_points_to_the_owner(): void
    {
        $this->rule(10);
        $this->actAsApiAdmin();
        $owner = $this->makeUser();
        $listing = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/api/listings/{$listing->id}/approve")->assertOk();

        $this->assertSame(Listing::STATUS_APPROVED, $listing->fresh()->listing_status);
        $this->assertSame(10, (int) $owner->fresh()->user_points_total);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $owner->id,
            'point_transaction_points' => 10,
            'point_transaction_source_type' => 'listing',
            'point_transaction_source_id' => $listing->id,
        ]);
    }

    public function test_cms_approval_awards_points_too(): void
    {
        $this->rule(7);
        $owner = $this->makeUser();
        $listing = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/cms/admins/listing_approve/{$listing->id}")->assertOk();

        $this->assertSame(7, (int) $owner->fresh()->user_points_total);
    }

    public function test_no_rule_or_inactive_rule_means_no_points_but_approval_still_works(): void
    {
        $this->actAsApiAdmin();
        $owner = $this->makeUser();
        $first = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/api/listings/{$first->id}/approve")->assertOk();
        $this->assertSame(Listing::STATUS_APPROVED, $first->fresh()->listing_status);

        $this->rule(10, false);
        $second = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/api/listings/{$second->id}/approve")->assertOk();

        $this->assertSame(0, (int) $owner->fresh()->user_points_total);
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_reapproving_after_reject_does_not_award_twice(): void
    {
        $this->rule(10);
        $this->actAsApiAdmin();
        $owner = $this->makeUser();
        $listing = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/api/listings/{$listing->id}/approve")->assertOk();
        $this->postJson("/api/listings/{$listing->id}/reject", ['listing_rejection_reason' => 'Not allowed'])->assertOk();
        $this->postJson("/api/listings/{$listing->id}/approve")->assertOk();

        $this->assertSame(10, (int) $owner->fresh()->user_points_total);
        $this->assertSame(1, PointTransaction::count());
    }

    public function test_refused_approval_does_not_award_points(): void
    {
        $this->rule(10);
        $this->actAsApiAdmin();
        $owner = $this->makeUser();
        $sold = $this->makeListing($owner, Listing::STATUS_SOLD);

        $this->postJson("/api/listings/{$sold->id}/approve")->assertStatus(409);

        $this->assertSame(0, (int) $owner->fresh()->user_points_total);
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_daily_limit_is_respected_but_approval_still_succeeds(): void
    {
        $this->rule(10, true, 1);
        $this->actAsApiAdmin();
        $owner = $this->makeUser();
        $first = $this->makeListing($owner, Listing::STATUS_PENDING);
        $second = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->postJson("/api/listings/{$first->id}/approve")->assertOk();
        $this->postJson("/api/listings/{$second->id}/approve")->assertOk();

        $this->assertSame(Listing::STATUS_APPROVED, $second->fresh()->listing_status);
        $this->assertSame(10, (int) $owner->fresh()->user_points_total);
    }
}