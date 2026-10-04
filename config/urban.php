<?php
return [
    'mode' => env('URBAN_MODE', 'synthetic'),
    'city' => env('URBAN_CITY', 'Ho Chi Minh City'),
    'timezone' => env('URBAN_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'threshold' => (float) env('URBAN_THRESHOLD', 35.0),
    'horizon_hour' => 6,
    'open_count' => 2,
    'close_count' => 2,
    'cooldown_hours' => (int) env('URBAN_COOLDOWN_HOURS', 6),
    'rule_version' => env('URBAN_RULE_VERSION', 'pm25-two-consecutive-v1'),
    'freshness_minutes' => (int) env('URBAN_FRESHNESS_MINUTES', 90),
    'forecast_freshness_minutes' => (int) env('URBAN_FORECAST_FRESHNESS_MINUTES', 90),
    'expected_cycle_hours' => (int) env('URBAN_EXPECTED_CYCLE_HOURS', 1),
    // Operational plausibility guard; values outside this range become unknown.
    'pm25_max' => (float) env('URBAN_PM25_MAX', 500),
];
