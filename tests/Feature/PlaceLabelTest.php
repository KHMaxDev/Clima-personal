<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlaceLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_picks_the_closest_result_to_the_coordinates(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    [
                        'name' => 'Chetumal',
                        'admin1' => 'Quintana Roo',
                        'country' => 'México',
                        'latitude' => 18.51,
                        'longitude' => -88.30,
                        'timezone' => 'America/Cancun',
                    ],
                    [
                        'name' => 'Cancún',
                        'admin1' => 'Quintana Roo',
                        'country' => 'México',
                        'latitude' => 21.16,
                        'longitude' => -86.85,
                        'timezone' => 'America/Cancun',
                    ],
                ],
            ], 200),
        ]);

        $place = app(PlaceLabelResolver::class)->resolve('Cancun', 21.16, -86.85);

        $this->assertSame('Cancún', $place['city']);
        $this->assertSame('Quintana Roo', $place['state']);
        $this->assertSame('México', $place['country']);
    }

    public function test_resolve_falls_back_to_the_name_when_geocoding_fails(): void
    {
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response('error', 500)]);

        $place = app(PlaceLabelResolver::class)->resolve('Tulum', 20.21, -87.46);

        $this->assertSame('Tulum', $place['city']);
        $this->assertNull($place['state']);
        $this->assertNull($place['country']);
    }

    public function test_resolve_returns_current_location_when_no_name_is_given(): void
    {
        Http::fake();

        $place = app(PlaceLabelResolver::class)->resolve(null, 21.16, -86.85);

        $this->assertSame('Ubicación actual', $place['city']);
        $this->assertNull($place['state']);
        Http::assertNothingSent();
    }

    public function test_endpoint_returns_place_for_authenticated_user(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Cancún',
                    'admin1' => 'Quintana Roo',
                    'country' => 'México',
                    'latitude' => 21.16,
                    'longitude' => -86.85,
                    'timezone' => 'America/Cancun',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/weather/place?name=Cancun&latitude=21.16&longitude=-86.85')
            ->assertOk()
            ->assertJsonPath('place.city', 'Cancún')
            ->assertJsonPath('place.state', 'Quintana Roo');
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/weather/place?name=Cancun')->assertUnauthorized();
    }

    public function test_endpoint_validates_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/weather/place?name='.str_repeat('a', 130))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
