<?php
namespace App\Services;

use App\Models\UrbanForecast;

class UrbanForecastService
{
    /** Forecast identity is immutable: repeated ingestion returns the original row. */
    public function record(array $attributes): UrbanForecast
    {
        $attributes['model_version'] = $attributes['model_version'] ?? 'unknown';
        $key = ['station_id'=>$attributes['station_id'], 'forecast_origin'=>$attributes['forecast_origin'], 'horizon_hour'=>$attributes['horizon_hour'], 'model_version'=>$attributes['model_version'] ?? 'unknown'];
        return UrbanForecast::firstOrCreate($key, $attributes);
    }
}
