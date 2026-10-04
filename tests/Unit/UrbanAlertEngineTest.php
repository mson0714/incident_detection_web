<?php

namespace Tests\Unit;

use App\Models\{UrbanAlertDecision, UrbanAlertEvent, UrbanForecast, UrbanStation};
use App\Services\UrbanAlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UrbanAlertEngineTest extends TestCase
{
    use RefreshDatabase;

    private function station(): UrbanStation
    {
        return UrbanStation::create([
            'id' => 'station-1', 'name' => 'Test', 'district' => 'D1',
            'latitude' => 10, 'longitude' => 106, 'status' => 'active',
        ]);
    }

    private function evaluate(UrbanStation $station, string $at, ?float $pm25, array $overrides = []): ?UrbanAlertEvent
    {
        $origin = Carbon::parse($at, 'UTC');
        $forecast = UrbanForecast::create(array_merge([
            'station_id' => $station->id, 'forecast_origin' => $origin,
            'generated_at' => $origin, 'target_timestamp' => $origin->copy()->addHours(6),
            'horizon_hour' => 6, 'predicted_pm25' => $pm25,
            'model_version' => 'v1', 'data_snapshot_id' => 'snap-'.$at,
            'data_quality' => 'synthetic', 'status' => 'available',
        ], $overrides));

        return app(UrbanAlertEngine::class)->evaluate($forecast, $origin);
    }

    public function test_two_high_origins_open_one_event_and_retries_are_idempotent(): void
    {
        $station = $this->station();
        $this->assertNull($this->evaluate($station, '2026-09-13 08:00', 40));
        $event = $this->evaluate($station, '2026-09-13 09:00', 41);
        $this->assertNotNull($event);
        $this->assertSame('open', $event->status);
        $this->assertSame(1, UrbanAlertEvent::where('status', 'open')->count());
    }

    public function test_two_low_origins_close_an_open_event(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40);
        $this->evaluate($station, '2026-09-13 09:00', 41);
        $this->evaluate($station, '2026-09-13 10:00', 30);
        $event = $this->evaluate($station, '2026-09-13 11:00', 31);

        $this->assertSame('closed', $event->status);
        $this->assertSame('resolved', $event->close_reason);
    }

    public function test_data_gap_becomes_unknown_instead_of_resolved(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40);
        $this->evaluate($station, '2026-09-13 09:00', 41);
        $event = $this->evaluate($station, '2026-09-13 20:00', 30);

        // A gap resets evidence but does not falsely resolve an open episode.
        $this->assertSame('open', $event->status);
        $this->assertNotSame('resolved', $event->close_reason);
        $this->assertSame('unknown', UrbanAlertDecision::latest('id')->value('decision'));
    }

    public function test_a_new_episode_is_recorded_during_cooldown_without_a_repeat_notification(): void
    {
        config(['urban.cooldown_hours' => 6]);
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 00:00', 40);
        $first = $this->evaluate($station, '2026-09-13 01:00', 41);
        $this->evaluate($station, '2026-09-13 02:00', 30);
        $this->evaluate($station, '2026-09-13 03:00', 31);
        $this->evaluate($station, '2026-09-13 04:00', 40);
        $second = $this->evaluate($station, '2026-09-13 05:00', 41);

        $this->assertSame('open', $first->status);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, UrbanAlertEvent::count());
        $this->assertSame(0, (int) $second->notification_count);
        $this->assertNull(UrbanAlertDecision::latest('id')->value('notification_at'));
    }

    public function test_reminders_resume_at_the_cooldown_boundary_and_retries_do_not_renotify(): void
    {
        config(['urban.cooldown_hours' => 3]);
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 00:00', 40);
        $opened = $this->evaluate($station, '2026-09-13 01:00', 41);
        $this->evaluate($station, '2026-09-13 02:00', 42);
        $beforeBoundary = $this->evaluate($station, '2026-09-13 03:00', 43);
        $this->assertSame(1, (int) $beforeBoundary->notification_count);

        $atBoundary = $this->evaluate($station, '2026-09-13 04:00', 44);
        $this->assertSame($opened->id, $atBoundary->id);
        $this->assertSame(2, (int) $atBoundary->notification_count);
        $this->assertSame('reminder', UrbanAlertDecision::latest('id')->value('decision'));
        $this->assertSame('2026-09-13 10:00:00', $atBoundary->target_timestamp->format('Y-m-d H:i:s'));

        $latest = UrbanForecast::latest('forecast_origin')->firstOrFail();
        app(UrbanAlertEngine::class)->evaluate($latest, Carbon::parse('2026-09-13 04:00', 'UTC'));
        $this->assertSame(2, (int) $atBoundary->fresh()->notification_count);
        $this->assertSame(2, UrbanAlertDecision::whereNotNull('notification_at')->count());
    }

    public function test_evaluating_the_same_forecast_twice_does_not_add_decisions_or_events(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40);
        $opened = $this->evaluate($station, '2026-09-13 09:00', 41);
        $decisionCount = UrbanAlertDecision::count();
        $eventCount = UrbanAlertEvent::count();

        $same = UrbanForecast::where('station_id', $station->id)
            ->where('forecast_origin', Carbon::parse('2026-09-13 09:00', 'UTC'))->firstOrFail();
        $again = app(UrbanAlertEngine::class)->evaluate($same, Carbon::parse('2026-09-13 09:00', 'UTC'));

        $this->assertSame($opened->id, $again?->id);
        $this->assertSame($decisionCount, UrbanAlertDecision::count());
        $this->assertSame($eventCount, UrbanAlertEvent::count());
    }

    public function test_an_out_of_order_forecast_does_not_rewind_alert_state(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40);
        $opened = $this->evaluate($station, '2026-09-13 09:00', 41);
        $decisionCount = UrbanAlertDecision::count();
        $eventCount = UrbanAlertEvent::count();

        $oldOrigin = Carbon::parse('2026-09-13 07:00', 'UTC');
        $old = UrbanForecast::create([
            'station_id' => $station->id, 'forecast_origin' => $oldOrigin,
            'generated_at' => $oldOrigin, 'target_timestamp' => $oldOrigin->copy()->addHours(6),
            'horizon_hour' => 6, 'predicted_pm25' => 80,
            'model_version' => 'v1', 'data_snapshot_id' => 'old-snapshot',
            'data_quality' => 'synthetic', 'status' => 'available',
        ]);
        $result = app(UrbanAlertEngine::class)->evaluate($old, $oldOrigin);

        $this->assertSame($opened->id, $result?->id);
        $this->assertSame('open', $opened->fresh()->status);
        $this->assertSame($eventCount, UrbanAlertEvent::count());
        $this->assertSame($decisionCount, UrbanAlertDecision::count());
    }

    public function test_an_invalid_prediction_breaks_the_low_streak_without_resolving_the_event(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40);
        $opened = $this->evaluate($station, '2026-09-13 09:00', 41);
        $this->evaluate($station, '2026-09-13 10:00', 20, ['data_quality' => 'invalid']);
        $afterOneValid = $this->evaluate($station, '2026-09-13 11:00', 21);

        $this->assertSame($opened->id, $afterOneValid?->id);
        $this->assertSame('open', $afterOneValid->status);
        $this->assertNull($afterOneValid->closed_at);

        $afterTwoValid = $this->evaluate($station, '2026-09-13 12:00', 22);
        $this->assertSame('closed', $afterTwoValid->status);
        $this->assertSame('resolved', $afterTwoValid->close_reason);
    }

    public function test_an_invalid_prediction_does_not_supply_the_first_high_evidence(): void
    {
        $station = $this->station();
        $this->evaluate($station, '2026-09-13 08:00', 40, ['data_quality' => 'invalid']);
        $this->evaluate($station, '2026-09-13 09:00', 41);

        $this->assertSame(0, UrbanAlertEvent::where('status', 'open')->count());

        $opened = $this->evaluate($station, '2026-09-13 10:00', 42);
        $this->assertSame('open', $opened?->status);
    }
}
