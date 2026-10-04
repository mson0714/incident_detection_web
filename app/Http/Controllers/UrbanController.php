<?php

namespace App\Http\Controllers;

use App\Models\{AirQualityReading, UrbanAlertEvent, UrbanForecast, UrbanModelMetric, UrbanPipelineRun, UrbanStation};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

class UrbanController extends Controller
{
    private function meta(): array
    {
        $latest = AirQualityReading::where('timestamp', '<=', now('UTC'))->max('timestamp');
        $asOf = $latest ? Carbon::parse($latest)->utc() : null;
        return ['mode' => config('urban.mode'), 'city' => config('urban.city'), 'timezone' => config('urban.timezone'), 'served_at' => now('UTC')->toISOString(), 'data_as_of' => $asOf?->toISOString()];
    }

    private function rule(): array
    {
        return ['rule_version' => config('urban.rule_version'), 'threshold' => (float) config('urban.threshold'), 'horizon_hour' => (int) config('urban.horizon_hour'), 'open_count' => (int) config('urban.open_count'), 'close_count' => (int) config('urban.close_count'), 'cooldown_hours' => (int) config('urban.cooldown_hours'), 'description' => 'Open after two consecutive valid h6 forecasts above threshold; close after two consecutive values at or below threshold. Data gaps reset evidence and remain unknown.'];
    }

    private function validReading(?AirQualityReading $reading): bool
    {
        $value = $reading?->pm25;
        return $reading && $reading->timestamp?->lte(now('UTC')) && $value !== null && is_finite((float) $value) && (float) $value >= 0 && (float) $value <= (float) config('urban.pm25_max', 500) && in_array(strtolower((string) $reading->quality_flag), ['ok', 'valid', 'synthetic'], true);
    }

    private function forecastPayload(UrbanForecast $forecast): array
    {
        $cutoff = now('UTC')->subMinutes((int) config('urban.forecast_freshness_minutes'));
        $value = $forecast->predicted_pm25;
        $metadataValid = $forecast->generated_at && $forecast->forecast_origin && $forecast->target_timestamp
            && $forecast->target_timestamp->equalTo($forecast->forecast_origin->copy()->addHours((int) $forecast->horizon_hour))
            && $forecast->generated_at->gte($forecast->forecast_origin)
            && $forecast->generated_at->lte(now('UTC'));
        $valueValid = $value !== null && is_finite((float) $value) && (float) $value >= 0 && (float) $value <= (float) config('urban.pm25_max', 500);
        $stale = !$metadataValid || !$valueValid || $forecast->generated_at->lt($cutoff) || $forecast->forecast_origin->lt($cutoff) || $forecast->status !== 'available' || !in_array(strtolower((string) $forecast->data_quality), ['ok', 'valid', 'synthetic'], true);
        return ['forecast_id' => $forecast->id, 'forecast_origin' => $forecast->forecast_origin?->utc()->toISOString(), 'generated_at' => $forecast->generated_at?->utc()->toISOString(), 'target_timestamp' => $forecast->target_timestamp?->utc()->toISOString(), 'horizon_hour' => (int) $forecast->horizon_hour, 'predicted_pm25' => $forecast->predicted_pm25, 'model_version' => $forecast->model_version, 'data_snapshot_id' => $forecast->data_snapshot_id, 'data_quality' => $forecast->data_quality, 'valid' => $metadataValid && $valueValid && $forecast->status === 'available' && in_array(strtolower((string) $forecast->data_quality), ['ok', 'valid', 'synthetic'], true), 'status' => $stale ? 'stale' : $forecast->status];
    }

    private function latestForecasts(UrbanStation $station)
    {
        $latest = $station->forecasts()->whereIn('horizon_hour', [1, 6, 24])
            ->orderByDesc('forecast_origin')->orderByDesc('generated_at')->orderByDesc('id')->first();
        if (!$latest) return collect();

        // Never backfill a missing horizon from a different origin or model run.
        return $station->forecasts()->where('forecast_origin', $latest->forecast_origin)
            ->where('model_version', $latest->model_version)->whereIn('horizon_hour', [1, 6, 24])
            ->orderBy('horizon_hour')->get();
    }

    private function stationPayload(UrbanStation $station): array
    {
        $reading = $station->readings()->where('timestamp', '<=', now('UTC'))->latest('timestamp')->first();
        $fresh = $reading ? max(0, round($reading->timestamp->diffInSeconds(now('UTC')) / 60, 1)) : null;
        $currentValid = $this->validReading($reading) && $fresh !== null && $fresh <= config('urban.freshness_minutes');
        $current = $currentValid ? ['timestamp' => $reading->timestamp->utc()->toISOString(), 'pm25' => $reading->pm25, 'temperature' => $reading->temperature, 'humidity' => $reading->humidity, 'wind_speed' => $reading->wind_speed, 'quality_flag' => $reading->quality_flag] : null;
        $snapshot = $this->latestForecasts($station);
        $forecast = $snapshot->firstWhere('horizon_hour', 6);
        $forecastData = $forecast ? $this->forecastPayload($forecast) : null;
        $total = $station->readings()->count();
        $missing = $station->readings()->where(function ($query) {
            $query->whereNull('pm25')
                ->orWhereRaw("LOWER(quality_flag) NOT IN ('ok', 'valid', 'synthetic')")
                ->orWhere('pm25', '<', 0)
                ->orWhere('pm25', '>', (float) config('urban.pm25_max', 500));
        })->count();
        $status = $station->status === 'active' && $currentValid ? 'active' : 'inactive';
        $risk = ($status === 'active' && $forecastData && $forecastData['status'] === 'available' && $forecastData['predicted_pm25'] !== null) ? ($forecastData['predicted_pm25'] > config('urban.threshold') ? 'elevated' : 'normal') : 'unknown';
        $forecasts = $snapshot->map(fn ($row) => $this->forecastPayload($row))->values();
        return ['id' => $station->id, 'name' => $station->name, 'district' => $station->district, 'latitude' => (float) $station->latitude, 'longitude' => (float) $station->longitude, 'status' => $status, 'current' => $current, 'forecast' => $forecastData, 'forecasts' => $forecasts, 'risk' => $risk, 'freshness_minutes' => $fresh, 'missing_rate' => $total ? round($missing / $total, 4) : 0];
    }

    public function dashboard()
    {
        $stations = UrbanStation::orderBy('id')->get()->map(fn ($station) => $this->stationPayload($station))->values();
        $fresh = array_values(array_filter($stations->pluck('freshness_minutes')->all(), fn ($value) => $value !== null));
        $pm = array_values(array_filter($stations->where('status', 'active')->pluck('current.pm25')->all(), fn ($value) => $value !== null));
        $last = UrbanPipelineRun::where('status', 'success')->latest('finished_at')->first();
        $latestRun = UrbanPipelineRun::latest('started_at')->latest('id')->first();
        $openAlerts = UrbanAlertEvent::where('status', 'open')
            ->where('rule_version', config('urban.rule_version'))
            ->where('horizon_hour', config('urban.horizon_hour'))
            ->count();
        return response()->json(['meta' => $this->meta(), 'summary' => ['station_count' => $stations->count(), 'online_count' => $stations->where('status', 'active')->count(), 'open_alerts' => $openAlerts, 'mean_pm25' => $pm ? round(array_sum($pm) / count($pm), 2) : null, 'data_freshness_minutes' => $fresh ? round(max($fresh), 1) : null], 'rule' => $this->rule(), 'stations' => $stations, 'pipeline_summary' => ['status' => $latestRun?->status ?? 'unknown', 'last_success_at' => $last?->finished_at?->utc()->toISOString()]]);
    }

    public function stations() { return response()->json(['data' => UrbanStation::orderBy('id')->get()->map(fn ($station) => $this->stationPayload($station))->values(), 'meta' => $this->meta(), 'rule' => $this->rule()]); }

    public function station(string $id)
    {
        $station = UrbanStation::find($id);
        if (!$station) return response()->json(['message' => 'Station not found'], 404);
        $data = $this->stationPayload($station);
        $history = $station->readings()->orderByDesc('timestamp')->limit(72)->get()->sortBy('timestamp')->values()->map(fn ($reading) => ['timestamp' => $reading->timestamp->utc()->toISOString(), 'pm25' => $reading->pm25, 'quality_flag' => $reading->quality_flag, 'valid' => $this->validReading($reading)])->values();
        $snapshot = $this->latestForecasts($station);
        $forecasts = $snapshot->map(fn ($forecast) => $this->forecastPayload($forecast))->values();
        $latestH6 = $snapshot->firstWhere('horizon_hour', 6);
        $explanations = $latestH6 ? $latestH6->explanations->map(fn ($explanation) => ['feature' => $explanation->feature, 'value' => $explanation->value, 'contribution' => $explanation->contribution])->values() : collect();
        return response()->json(['data' => $data, 'history' => $history, 'forecasts' => $forecasts, 'events' => $station->events()->latest('last_seen_at')->get()->map(fn ($event) => $this->event($event))->values(), 'explanations' => $explanations, 'meta' => $this->meta(), 'rule' => $this->rule()]);
    }

    public function forecast(Request $request)
    {
        $values = $request->validate(['station_id' => 'required|string|exists:urban_stations,id', 'horizon' => 'nullable|integer|in:1,6,24']);
        $snapshot = $this->latestForecasts(UrbanStation::findOrFail($values['station_id']));
        if (isset($values['horizon'])) $snapshot = $snapshot->where('horizon_hour', (int) $values['horizon']);
        return response()->json(['data' => $snapshot->map(fn ($forecast) => $this->forecastPayload($forecast))->values(), 'meta' => $this->meta(), 'rule' => $this->rule()]);
    }

    public function hotspots(Request $request)
    {
        $request->validate(['horizon' => 'nullable|integer|in:6']);
        $data = UrbanStation::orderBy('id')->get()->map(fn ($station) => $this->stationPayload($station))->filter(fn ($station) => $station['risk'] === 'elevated')->sortByDesc(fn ($station) => $station['forecast']['predicted_pm25'])->values();
        return response()->json(['data' => $data, 'rule' => $this->rule(), 'meta' => $this->meta()]);
    }

    public function alerts(Request $request)
    {
        $values = $request->validate(['status' => 'nullable|in:open,closed,unknown', 'date' => 'nullable|date_format:Y-m-d', 'station_id' => 'nullable|string|exists:urban_stations,id', 'page' => 'nullable|integer|min:1', 'current' => 'nullable|boolean', 'horizon' => 'nullable|integer|in:1,6,24', 'rule_version' => 'nullable|string|max:120']);
        $query = UrbanAlertEvent::with('station')->latest('last_seen_at')->when(isset($values['status']), fn ($q) => $q->where('status', $values['status']))->when(isset($values['station_id']), fn ($q) => $q->where('station_id', $values['station_id']));
        if (filter_var($values['current'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('rule_version', config('urban.rule_version'))->where('horizon_hour', config('urban.horizon_hour'));
        } else {
            $query->when(isset($values['rule_version']), fn ($q) => $q->where('rule_version', $values['rule_version']))->when(isset($values['horizon']), fn ($q) => $q->where('horizon_hour', $values['horizon']));
        }
        if (isset($values['date'])) {
            $day = Carbon::createFromFormat('!Y-m-d', $values['date'], config('urban.timezone'));
            $nextDay = $day->copy()->addDay()->utc();
            $query->where('opened_at', '>=', $day->utc())->where('opened_at', '<', $nextDay);
        }
        $page = $query->paginate(20);
        return response()->json(['data' => collect($page->items())->map(fn ($event) => $this->event($event))->values(), 'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()], 'rule' => $this->rule()]);
    }

    private function event(UrbanAlertEvent $event): array { return ['id' => $event->id, 'station_id' => $event->station_id, 'station_name' => $event->station?->name, 'status' => $event->status, 'horizon_hour' => $event->horizon_hour, 'opened_at' => $event->opened_at?->utc()->toISOString(), 'last_seen_at' => $event->last_seen_at?->utc()->toISOString(), 'closed_at' => $event->closed_at?->utc()->toISOString(), 'close_reason' => $event->close_reason, 'predicted_pm25' => $event->predicted_pm25, 'threshold' => $event->threshold, 'model_version' => $event->model_version, 'rule_version' => $event->rule_version, 'target_timestamp' => $event->target_timestamp?->utc()->toISOString(), 'notification_count' => $event->notification_count]; }

    private function metricPayload(UrbanModelMetric $metric): array
    {
        $metrics = $metric->metrics;
        $stationMetrics = is_array($metrics) && array_key_exists('by_station', $metrics)
            ? $metrics['by_station']
            : null;

        return [
            'version' => $metric->version,
            'algorithm' => $metric->algorithm,
            'status' => $metric->status,
            'train_cutoff' => $metric->train_cutoff?->utc()->toISOString(),
            'feature_version' => $metric->feature_version,
            'is_synthetic' => (bool) $metric->is_synthetic,
            'metrics' => $metrics,
            'station_metrics' => $stationMetrics,
            'description' => $metric->description,
        ];
    }

    public function metrics()
    {
        return response()->json([
            'data' => UrbanModelMetric::orderBy('version')->get()->map(fn ($metric) => $this->metricPayload($metric)),
            'meta' => $this->meta(),
        ]);
    }

    public function pipeline()
    {
        $runs = UrbanPipelineRun::latest('started_at')->latest('id')->limit(20)->get();
        $latest = $runs->first();
        $last = UrbanPipelineRun::where('status', 'success')->latest('finished_at')->first();
        $stageRuns = $runs->groupBy(fn ($run) => strtolower((string) $run->stage))->map->first();
        $layerStatus = function (array $aliases) use ($stageRuns): string {
            foreach ($aliases as $alias) if ($stageRuns->has($alias)) return (string) $stageRuns->get($alias)->status;
            return 'ok';
        };
        return response()->json([
            'data' => $runs,
            'summary' => ['status' => $latest?->status ?? 'unknown', 'last_success_at' => $last?->finished_at?->utc()->toISOString(), 'records_processed' => $latest?->records_processed ?? 0, 'freshness_minutes' => $latest?->freshness_minutes],
            'layers' => [
                ['name' => 'bronze', 'description' => 'Raw synthetic observations', 'record_count' => AirQualityReading::count(), 'status' => $layerStatus(['bronze', 'raw', 'ingest', 'ingestion'])],
                ['name' => 'silver', 'description' => 'Validated quality readings', 'record_count' => AirQualityReading::whereIn(DB::raw('LOWER(quality_flag)'), ['ok', 'valid', 'synthetic'])->count(), 'status' => $layerStatus(['silver', 'quality'])],
                ['name' => 'gold', 'description' => 'Forecasts and alert events', 'record_count' => UrbanForecast::count() + UrbanAlertEvent::count(), 'status' => $layerStatus(['gold'])],
                ['name' => 'forecast', 'description' => 'Persisted PM2.5 predictions', 'record_count' => UrbanForecast::count(), 'status' => $layerStatus(['forecast', 'prediction'])],
                ['name' => 'alert-engine', 'description' => 'Rule-based event decisions', 'record_count' => UrbanAlertEvent::count(), 'status' => $layerStatus(['alert-engine', 'alert_engine', 'alert'])],
            ],
            'meta' => $this->meta(),
        ]);
    }

    public function health() { try { DB::connection()->getPdo(); if (!Schema::hasTable('urban_stations')) throw new \RuntimeException('Urban schema is not migrated'); return response()->json(['status' => 'ok', 'database' => 'ok', 'schema' => 'ok', 'mode' => config('urban.mode')]); } catch (\Throwable) { return response()->json(['status' => 'error', 'database' => 'unavailable'], 503); } }
}
