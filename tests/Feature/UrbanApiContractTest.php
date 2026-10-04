<?php

namespace Tests\Feature;

use App\Models\{AirQualityReading, UrbanAlertEvent, UrbanForecast, UrbanModelMetric, UrbanPipelineRun, UrbanStation};
use Database\Seeders\UrbanDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UrbanApiContractTest extends TestCase
{
    use RefreshDatabase;

    private function station(string $id = 'station-1'): UrbanStation
    {
        return UrbanStation::create([
            'id' => $id,
            'name' => 'Ben Thanh',
            'district' => 'District 1',
            'latitude' => 10.772,
            'longitude' => 106.698,
            'status' => 'active',
        ]);
    }

    private function forecast(UrbanStation $station, int $horizon, float $value, ?Carbon $origin = null): UrbanForecast
    {
        $origin ??= Carbon::parse('2026-09-13 08:00:00', 'UTC');

        return UrbanForecast::create([
            'station_id' => $station->id,
            'forecast_origin' => $origin,
            'generated_at' => $origin,
            'target_timestamp' => $origin->copy()->addHours($horizon),
            'horizon_hour' => $horizon,
            'predicted_pm25' => $value,
            'model_version' => 'synthetic-v1',
            'data_snapshot_id' => 'snapshot-1',
            'data_quality' => 'synthetic',
            'status' => 'available',
        ]);
    }

    public function test_health_and_dashboard_expose_the_synthetic_contract(): void
    {
        $station = $this->station();
        AirQualityReading::create([
            'station_id' => $station->id,
            'timestamp' => now('UTC')->subMinutes(10),
            'pm25' => 28.5,
            'quality_flag' => 'ok',
        ]);
        foreach ([1, 6, 24] as $horizon) {
            $this->forecast($station, $horizon, 30 + $horizon);
        }

        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonStructure(['meta', 'summary', 'rule', 'stations', 'pipeline_summary'])
            ->assertJsonPath('summary.station_count', 1)
            ->assertJsonPath('rule.horizon_hour', 6);
    }

    public function test_invalid_filters_are_unprocessable_and_unknown_station_is_not_found(): void
    {
        $this->getJson('/api/forecast?station_id=nope&horizon=7')->assertStatus(422);
        $this->getJson('/api/alerts?status=bogus')->assertStatus(422);
        $this->getJson('/api/stations/does-not-exist')->assertNotFound();
    }

    public function test_current_alert_filter_matches_dashboard_rule_and_horizon(): void
    {
        $station = $this->station();
        UrbanAlertEvent::create(['station_id' => $station->id, 'status' => 'open', 'opened_at' => now('UTC'), 'last_seen_at' => now('UTC'), 'predicted_pm25' => 44, 'threshold' => 35, 'horizon_hour' => 6, 'rule_version' => config('urban.rule_version')]);
        UrbanAlertEvent::create(['station_id' => $station->id, 'status' => 'open', 'opened_at' => now('UTC'), 'last_seen_at' => now('UTC'), 'predicted_pm25' => 50, 'threshold' => 35, 'horizon_hour' => 24, 'rule_version' => 'old-rule']);

        $this->getJson('/api/alerts?status=open&current=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.horizon_hour', 6);
        $this->getJson('/api/alerts?status=open&current=0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('summary.open_alerts', 1);
    }

    public function test_future_reading_is_not_current_or_fresh(): void
    {
        $station = $this->station();
        AirQualityReading::create(['station_id' => $station->id, 'timestamp' => now('UTC')->addHour(), 'pm25' => 12, 'quality_flag' => 'ok']);

        $this->getJson('/api/stations/'.$station->id)->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.freshness_minutes', null);
    }

    public function test_forecast_without_horizon_returns_all_horizons_and_get_is_read_only(): void
    {
        $station = $this->station();
        foreach ([1, 6, 24] as $horizon) {
            $this->forecast($station, $horizon, 20 + $horizon);
        }
        $before = UrbanForecast::count();

        $response = $this->getJson('/api/forecast?station_id='.$station->id)->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertSame($before, UrbanForecast::count());
    }

    public function test_horizon_outputs_share_one_origin_and_have_explicit_targets(): void
    {
        $station = $this->station();
        $origin = Carbon::parse('2026-09-13 08:00:00', 'UTC');
        foreach ([1, 6, 24] as $horizon) {
            $this->forecast($station, $horizon, 20 + $horizon, $origin);
        }

        $rows = $this->getJson('/api/forecast?station_id='.$station->id)->assertOk()->json('data');
        $this->assertCount(3, $rows);
        $this->assertCount(1, array_unique(array_column($rows, 'forecast_origin')));
        foreach ($rows as $row) {
            $this->assertSame(
                Carbon::parse($row['forecast_origin'])->addHours($row['horizon_hour'])->toISOString(),
                Carbon::parse($row['target_timestamp'])->toISOString(),
            );
        }
    }

    public function test_hotspots_alerts_metrics_and_pipeline_are_readable_with_empty_data(): void
    {
        $this->getJson('/api/hotspots')->assertOk()->assertJsonStructure(['data', 'meta', 'rule']);
        $this->getJson('/api/alerts')->assertOk()->assertJsonStructure(['data', 'meta', 'rule']);
        $this->getJson('/api/model/metrics')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson('/api/pipeline/status')->assertOk()->assertJsonStructure(['data', 'summary', 'layers', 'meta']);
    }

    public function test_model_metrics_pass_through_optional_station_breakdown_without_inventing_one(): void
    {
        $withBreakdown = UrbanModelMetric::create([
            'version' => 'metric-01-with-stations',
            'algorithm' => 'xgboost',
            'status' => 'validated',
            'is_synthetic' => false,
            'metrics' => [
                'mae' => 4.2,
                'by_station' => [
                    'station-1' => ['mae' => 3.1, 'rmse' => 4.0],
                ],
            ],
        ]);
        $withoutBreakdown = UrbanModelMetric::create([
            'version' => 'metric-02-without-stations',
            'algorithm' => 'xgboost',
            'status' => 'validated',
            'is_synthetic' => false,
            'metrics' => ['mae' => 5.6],
        ]);

        $this->assertIsArray($withBreakdown->fresh()->metrics);
        $this->assertIsArray($withoutBreakdown->fresh()->metrics);

        $response = $this->getJson('/api/model/metrics')->assertOk();
        $response->assertJsonPath('data.0.version', 'metric-01-with-stations')
            ->assertJsonPath('data.0.station_metrics.station-1.mae', 3.1)
            ->assertJsonPath('data.1.version', 'metric-02-without-stations')
            ->assertJsonPath('data.1.station_metrics', null);
        $this->assertSame($withBreakdown->fresh()->metrics, $response->json('data.0.metrics'));
        $this->assertSame($withBreakdown->fresh()->metrics['by_station'], $response->json('data.0.station_metrics'));
        $this->assertSame($withoutBreakdown->fresh()->metrics, $response->json('data.1.metrics'));
    }

    public function test_stale_forecast_is_explicitly_marked_stale(): void
    {
        $station = $this->station();
        $this->forecast($station, 6, 55, now('UTC')->subDays(2));

        $this->getJson('/api/forecast?station_id='.$station->id.'&horizon=6')->assertOk()
            ->assertJsonPath('data.0.status', 'stale');
    }

    public function test_invalid_latest_reading_is_not_presented_as_current_or_safe(): void
    {
        $station = $this->station();
        AirQualityReading::create([
            'station_id' => $station->id,
            'timestamp' => now('UTC')->subMinutes(5),
            'pm25' => 18.0,
            'quality_flag' => 'invalid',
        ]);
        $this->forecast($station, 6, 12, now('UTC')->subMinutes(5));

        $this->getJson('/api/stations/'.$station->id)->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.risk', 'unknown')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_station_detail_only_returns_the_latest_common_origin_for_all_horizons(): void
    {
        $station = $this->station();
        $old = Carbon::parse('2026-09-13 06:00:00', 'UTC');
        $new = Carbon::parse('2026-09-13 08:00:00', 'UTC');
        foreach ([1, 6, 24] as $horizon) {
            $this->forecast($station, $horizon, 20 + $horizon, $old);
        }
        $this->forecast($station, 6, 70, $new);

        $rows = $this->getJson('/api/stations/'.$station->id)->assertOk()->json('forecasts');
        $this->assertCount(1, $rows);
        $this->assertSame(6, $rows[0]['horizon_hour']);
        $this->assertSame($new->toISOString(), Carbon::parse($rows[0]['forecast_origin'])->toISOString());
    }

    public function test_single_horizon_forecast_query_returns_only_its_latest_origin(): void
    {
        $station = $this->station();
        $old = Carbon::parse('2026-09-13 06:00:00', 'UTC');
        $new = Carbon::parse('2026-09-13 08:00:00', 'UTC');
        $this->forecast($station, 6, 22, $old);
        $this->forecast($station, 6, 44, $new);

        $rows = $this->getJson('/api/forecast?station_id='.$station->id.'&horizon=6')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame(44.0, (float) $rows[0]['predicted_pm25']);
        $this->assertSame($new->toISOString(), Carbon::parse($rows[0]['forecast_origin'])->toISOString());
    }

    public function test_demo_seed_can_be_replayed_without_creating_duplicates(): void
    {
        $this->seed(UrbanDemoSeeder::class);
        $counts = [UrbanStation::count(), AirQualityReading::count(), UrbanForecast::count()];

        $this->seed(UrbanDemoSeeder::class);

        $this->assertSame($counts, [UrbanStation::count(), AirQualityReading::count(), UrbanForecast::count()]);
    }

    public function test_station_forecast_arrays_share_one_origin_and_model_across_endpoints(): void
    {
        $station = $this->station();
        $origin = now('UTC')->startOfHour();
        foreach ([1, 6, 24] as $horizon) $this->forecast($station, $horizon, 20 + $horizon, $origin);
        $latest = $this->forecast($station, 6, 60, $origin->copy()->addHour());
        $latest->update(['model_version' => 'synthetic-v2']);

        foreach (['/api/stations' => 'data.0', '/api/dashboard' => 'stations.0', '/api/stations/'.$station->id => 'data'] as $url => $key) {
            $response = $this->getJson($url)->assertOk();
            $response->assertJsonCount(1, $key.'.forecasts')
                ->assertJsonPath($key.'.forecasts.0.model_version', 'synthetic-v2')
                ->assertJsonPath($key.'.forecasts.0.horizon_hour', 6);
        }
        $this->getJson('/api/forecast?station_id='.$station->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/forecast?station_id='.$station->id.'&horizon=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_alert_date_uses_local_midnight_and_excludes_the_next_midnight(): void
    {
        config(['urban.timezone' => 'Asia/Ho_Chi_Minh']);
        $station = $this->station();
        foreach (['2026-09-12 16:59:59', '2026-09-12 17:00:00', '2026-09-13 16:59:59', '2026-09-13 17:00:00'] as $at) {
            UrbanAlertEvent::create(['station_id' => $station->id, 'status' => 'open', 'opened_at' => Carbon::parse($at, 'UTC'), 'last_seen_at' => Carbon::parse($at, 'UTC'), 'threshold' => 35, 'rule_version' => 'test-rule']);
        }
        $response = $this->getJson('/api/alerts?date=2026-09-13')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(['2026-09-12T17:00:00.000000Z', '2026-09-13T16:59:59.000000Z'], array_column($response->json('data'), 'opened_at'));
    }

    public function test_pipeline_summary_does_not_hide_a_failure_after_success(): void
    {
        $finished = Carbon::parse('2026-09-13 08:00:05', 'UTC');
        UrbanPipelineRun::create(['stage' => 'forecast', 'status' => 'success', 'started_at' => $finished->copy()->subSeconds(5), 'finished_at' => $finished, 'records_processed' => 10]);
        UrbanPipelineRun::create(['stage' => 'forecast', 'status' => 'failed', 'started_at' => $finished->copy()->addHour(), 'finished_at' => $finished->copy()->addHour()->addSeconds(5), 'records_processed' => 0]);
        $this->getJson('/api/pipeline/status')->assertOk()
            ->assertJsonPath('summary.status', 'failed')->assertJsonPath('summary.last_success_at', $finished->toISOString());
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('pipeline_summary.status', 'failed')->assertJsonPath('pipeline_summary.last_success_at', $finished->toISOString());
    }
}
