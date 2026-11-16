<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Dashboard\Concerns\MakesClinicPlaces;
use Tests\TestCase;

/** حدود الحجز العام: 5 طلبات/10 دقائق للـ IP و3/ساعة للرقم. */
class AppointmentThrottleTest extends TestCase
{
    use MakesClinicPlaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPlaces();
        $this->freezeNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function book(string $slug, array $override)
    {
        return $this->postJson('/api/places/'.$slug.'/appointments', $this->payload($override));
    }

    public function test_a_phone_number_is_limited_to_three_requests_per_hour(): void
    {
        $slug = $this->placeOf($this->makeOwner('clinic', 'paid'))->place_slug;

        foreach (['09:00', '10:00', '11:00'] as $time) {
            $this->book($slug, ['datetime' => $this->at($time), 'whatsapp' => '596000000'])->assertCreated();
        }

        $this->book($slug, ['datetime' => $this->at('12:00'), 'whatsapp' => '596000000'])->assertStatus(429);

        // رقم آخر من نفس الـ IP لسا مسموح (الحد 5 للـ IP: استُهلك 4).
        $this->book($slug, ['datetime' => $this->at('13:00'), 'whatsapp' => '596000001'])->assertCreated();
    }

    public function test_an_ip_is_limited_to_five_requests_per_ten_minutes(): void
    {
        $slug = $this->placeOf($this->makeOwner('clinic', 'paid'))->place_slug;

        foreach (range(1, 5) as $i) {
            $this->book($slug, ['datetime' => $this->at(sprintf('%02d:00', 8 + $i)), 'whatsapp' => '59600000'.$i])->assertCreated();
        }

        $this->book($slug, ['datetime' => $this->at('15:00'), 'whatsapp' => '596000009'])
            ->assertStatus(429)->assertHeader('Retry-After');

        // بعد انقضاء النافذة يعود الحجز.
        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->book($slug, ['datetime' => $this->at('15:00'), 'whatsapp' => '596000009'])->assertCreated();
    }
}