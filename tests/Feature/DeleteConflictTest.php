<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Regions;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteConflictTest extends TestCase
{
    use RefreshDatabase;

    private function region(): Regions
    {
        $region = new Regions();
        $region->region_slug = 'gaza';
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        return $region;
    }

    private function area(Regions $region): Area
    {
        $area = new Area();
        $area->area_slug = 'rimal';
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        return $area;
    }

    public function test_region_with_areas_cannot_be_deleted(): void
    {
        $region = $this->region();
        $this->area($region);

        $this->deleteJson("/cms/admins/region/{$region->id}")
            ->assertStatus(409)
            ->assertJson(['icon' => 'error']);

        $this->assertNotNull(Regions::find($region->id));
    }

    public function test_region_without_areas_can_be_deleted(): void
    {
        $region = $this->region();

        $this->deleteJson("/cms/admins/region/{$region->id}")->assertOk();

        $this->assertNull(Regions::find($region->id));
    }

    public function test_area_with_users_cannot_be_deleted(): void
    {
        $area = $this->area($this->region());
        UserFactory::new()->create(['area_id' => $area->id]);

        $this->deleteJson("/cms/admins/area/{$area->id}")
            ->assertStatus(409)
            ->assertJson(['icon' => 'error']);

        $this->assertNotNull(Area::find($area->id));
    }

    public function test_area_without_users_can_be_deleted(): void
    {
        $area = $this->area($this->region());

        $this->deleteJson("/cms/admins/area/{$area->id}")->assertOk();

        $this->assertNull(Area::find($area->id));
    }
}