<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\UrbanDemoSeeder;

Artisan::command('urban:demo', function () {
    (new UrbanDemoSeeder())->run();
    $this->info('Synthetic urban demo loaded. No existing readings or forecasts were overwritten.');
})->purpose('Load additive synthetic urban demo data');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
