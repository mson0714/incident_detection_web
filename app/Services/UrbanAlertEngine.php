<?php
namespace App\Services;

use App\Models\{UrbanForecast, UrbanAlertEvent, UrbanAlertDecision, UrbanStation};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UrbanAlertEngine
{
    public function evaluate(UrbanForecast $forecast, ?Carbon $evaluatedAt = null): ?UrbanAlertEvent
    {
        if ((int)$forecast->horizon_hour !== (int)config('urban.horizon_hour')) return null;
        $at = ($evaluatedAt ?: now('UTC'))->copy()->utc();
        return DB::transaction(function () use ($forecast, $at) {
            // All evaluators for a station serialize before reading dedupe or event state.
            UrbanStation::whereKey($forecast->station_id)->lockForUpdate()->firstOrFail();
            $eventQuery = UrbanAlertEvent::where('station_id', $forecast->station_id)
                ->where('rule_version', config('urban.rule_version'))
                ->where('horizon_hour', config('urban.horizon_hour'));
            if (UrbanAlertDecision::where('forecast_id', $forecast->id)->where('rule_version', config('urban.rule_version'))->exists()) {
                return (clone $eventQuery)->latest('id')->first();
            }
            $lastProcessed = UrbanAlertDecision::query()
                ->join('urban_forecasts', 'urban_forecasts.id', '=', 'urban_alert_decisions.forecast_id')
                ->where('urban_alert_decisions.station_id', $forecast->station_id)
                ->where('urban_alert_decisions.rule_version', config('urban.rule_version'))
                ->where('urban_forecasts.model_version', $forecast->model_version)
                ->max('urban_forecasts.forecast_origin');
            if ($lastProcessed && Carbon::parse($forecast->forecast_origin)->lte(Carbon::parse($lastProcessed))) {
                return (clone $eventQuery)->latest('id')->first();
            }
            $all = UrbanForecast::where('station_id',$forecast->station_id)->where('model_version', $forecast->model_version)->where('horizon_hour',config('urban.horizon_hour'))->where('forecast_origin','<=',$forecast->forecast_origin)->orderByDesc('forecast_origin')->limit(24)->get()->sortBy('forecast_origin')->values();
            $event = (clone $eventQuery)->latest('id')->first();
            $lastNotification = UrbanAlertDecision::where('station_id', $forecast->station_id)
                ->where('rule_version', config('urban.rule_version'))->max('notification_at');
            $mayNotify = !$lastNotification || Carbon::parse($lastNotification)
                ->lte($at->copy()->subHours(max(0, (int) config('urban.cooldown_hours'))));
            $notificationAt = null;
            $latest = $all->last(); $streak = [];
            $isValid = static function ($f) use ($at): bool {
                if (!$f || $f->predicted_pm25 === null || !is_finite((float) $f->predicted_pm25)
                    || (float) $f->predicted_pm25 < 0
                    || (float) $f->predicted_pm25 > (float) config('urban.pm25_max', 500)
                    || $f->status !== 'available'
                    || !in_array(strtolower((string) $f->data_quality), ['ok', 'valid', 'synthetic'], true)) return false;
                if (!$f->forecast_origin || !$f->generated_at || !$f->target_timestamp) return false;
                if (!$f->target_timestamp->equalTo($f->forecast_origin->copy()->addHours((int) $f->horizon_hour))) return false;
                return $f->generated_at->gte($f->forecast_origin) && $f->generated_at->lte($at);
            };
            if ($latest && $latest->id === $forecast->id && $isValid($latest)) {
                $streak[] = $latest;
                for ($i=$all->count()-2; $i>=0 && count($streak)<2; $i--) {
                    $candidate=$all[$i]; $last=$streak[count($streak)-1];
                    $seconds = Carbon::parse($candidate->forecast_origin)->diffInSeconds(Carbon::parse($last->forecast_origin));
                    if (abs($seconds - ((int) config('urban.expected_cycle_hours')) * 3600) > 0.0001 || !$isValid($candidate) || !$isValid($last)) break;
                    array_unshift($streak,$candidate);
                }
            }
            $contiguous=count($streak)>=2; $above=$contiguous && collect($streak)->every(fn($f)=>$f->predicted_pm25>config('urban.threshold')); $below=$contiguous && collect($streak)->every(fn($f)=>$f->predicted_pm25<=config('urban.threshold'));
            $gap=!$contiguous && $all->count()>1; $stale = $latest && (($latest->forecast_origin && $latest->forecast_origin->lt($at->copy()->subMinutes(config('urban.forecast_freshness_minutes')))) || ($latest->generated_at && $latest->generated_at->lt($at->copy()->subMinutes(config('urban.forecast_freshness_minutes')))) || !$isValid($latest)); $type='noop'; $reason='insufficient_evidence';
            if ($gap || $stale || !$latest || $latest->predicted_pm25===null) {
                $type='unknown'; $reason='data_gap_or_missing';
                if (!$event || $event->status!=='open') $event=UrbanAlertEvent::updateOrCreate(['station_id'=>$forecast->station_id,'status'=>'unknown','horizon_hour'=>config('urban.horizon_hour'),'rule_version'=>config('urban.rule_version')],['last_seen_at'=>$latest?->forecast_origin,'target_timestamp'=>$latest?->target_timestamp,'threshold'=>config('urban.threshold'),'model_version'=>$latest?->model_version,'predicted_pm25'=>$latest?->predicted_pm25]);
            } elseif ($above && (!$event || $event->status!=='open')) {
                // Record a new episode even when its notification is still cooling down.
                $notificationAt = $mayNotify ? $at : null;
                $type='open'; $reason=$mayNotify ? 'two_consecutive_above_threshold' : 'opened_notification_suppressed'; $event=UrbanAlertEvent::create(['station_id'=>$forecast->station_id,'status'=>'open','opened_at'=>$latest->forecast_origin,'last_seen_at'=>$latest->forecast_origin,'threshold'=>config('urban.threshold'),'horizon_hour'=>config('urban.horizon_hour'),'rule_version'=>config('urban.rule_version'),'model_version'=>$latest->model_version,'target_timestamp'=>$latest->target_timestamp,'predicted_pm25'=>$latest->predicted_pm25,'notification_count'=>$mayNotify ? 1 : 0]);
            } elseif ($below && $event && $event->status==='open') { $type='close'; $reason='resolved'; $event->update(['status'=>'closed','closed_at'=>$latest->forecast_origin,'last_seen_at'=>$latest->forecast_origin,'target_timestamp'=>$latest->target_timestamp,'close_reason'=>$reason,'predicted_pm25'=>$latest->predicted_pm25]); }
            elseif ($event && $event->status==='open') {
                $event->update(['last_seen_at'=>$latest->forecast_origin,'target_timestamp'=>$latest->target_timestamp,'predicted_pm25'=>$latest->predicted_pm25]);
                if ($above && $mayNotify) { $notificationAt = $at; $type = 'reminder'; $reason = 'notification_cooldown_elapsed'; $event->increment('notification_count'); }
            }
            UrbanAlertDecision::firstOrCreate(['dedupe_key'=>$forecast->station_id.':'.$forecast->id.':'.config('urban.rule_version')],['station_id'=>$forecast->station_id,'forecast_id'=>$forecast->id,'event_id'=>$event?->id,'rule_version'=>config('urban.rule_version'),'decision'=>$type,'reason'=>$reason,'evaluated_at'=>$at,'notification_at'=>$notificationAt]);
            return $event;
        }, 3);
    }
}
