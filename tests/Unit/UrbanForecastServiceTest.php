<?php

namespace Tests\Unit;

use App\Models\{UrbanForecast, UrbanStation};
use App\Services\UrbanForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UrbanForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_is_idempotent_and_does_not_mutate_an_existing_forecast(): void
    {
        $station = UrbanStation::create([
            'id' => 'station-1', 'name' => 'Test', 'district' => 'D1',
            'latitude' => 10, 'longitude' => 106, 'status' => 'active',
        ]);
        $origin = Carbon::parse('2026-09-13 08:00:00', 'UTC');
        $payload = [
            'station_id' => $station->id,
            'forecast_origin' => $origin,
            'generated_at' => $origin,
            'target_timestamp' => $origin->copy()->addHours(6),
            'horizon_hour' => 6,
            'predicted_pm25' => 42,
            'model_version' => 'v1', 'data_snapshot_id' => 'snap-a',
            'data_quality' => 'synthetic', 'status' => 'available',
        ];

        $first = app(UrbanForecastService::class)->record($payload);
        $retry = app(UrbanForecastService::class)->record(array_merge($payload, [
            'predicted_pm25' => 99, 'data_snapshot_id' => 'snap-b',
        ]));

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, UrbanForecast::count());
        $this->assertSame(42.0, (float) $retry->fresh()->predicted_pm25);
        $this->assertSame('v1', $retry->fresh()->model_version);

        $newModel = app(UrbanForecastService::class)->record(array_merge($payload, [
            'predicted_pm25' => 99, 'model_version' => 'v2', 'data_snapshot_id' => 'snap-b',
        ]));
        $this->assertNotSame($first->id, $newModel->id);
        $this->assertSame(2, UrbanForecast::count());
    }
}
