<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('urban_stations', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('name');
            $table->string('district')->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
        Schema::create('air_quality_readings', function (Blueprint $table) {
            $table->id();
            $table->string('station_id', 32);
            $table->dateTime('timestamp')->index();
            $table->decimal('pm25', 8, 2)->nullable();
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('humidity', 6, 2)->nullable();
            $table->decimal('wind_speed', 6, 2)->nullable();
            $table->string('quality_flag', 24)->default('ok');
            $table->timestamps();
            $table->foreign('station_id')->references('id')->on('urban_stations')->cascadeOnDelete();
            $table->unique(['station_id', 'timestamp']);
        });
        Schema::create('urban_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('station_id', 32);
            $table->dateTime('forecast_origin')->index();
            $table->dateTime('generated_at');
            $table->dateTime('target_timestamp')->index();
            $table->unsignedSmallInteger('horizon_hour');
            $table->decimal('predicted_pm25', 8, 2)->nullable();
            $table->string('model_version', 80);
            $table->string('data_snapshot_id', 80);
            $table->string('data_quality', 24)->default('synthetic');
            $table->string('status', 24)->default('available');
            $table->timestamps();
            $table->foreign('station_id')->references('id')->on('urban_stations')->cascadeOnDelete();
            $table->unique(['station_id', 'forecast_origin', 'horizon_hour', 'model_version'], 'forecast_identity_unique');
        });
        Schema::create('urban_alert_events', function (Blueprint $table) {
            $table->id();
            $table->string('station_id', 32);
            $table->enum('status', ['open', 'closed', 'unknown'])->index();
            $table->unsignedSmallInteger('horizon_hour')->default(6);
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('close_reason')->nullable();
            $table->decimal('predicted_pm25', 8, 2)->nullable();
            $table->decimal('threshold', 8, 2);
            $table->string('model_version', 80)->nullable();
            $table->string('rule_version', 40);
            $table->dateTime('target_timestamp')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->timestamps();
            $table->foreign('station_id')->references('id')->on('urban_stations')->cascadeOnDelete();
            $table->index(['station_id', 'status']);
        });
        Schema::create('urban_alert_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('station_id', 32);
            $table->foreignId('forecast_id')->nullable()->constrained('urban_forecasts')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('urban_alert_events')->nullOnDelete();
            $table->string('rule_version', 40)->nullable();
            $table->string('decision', 24);
            $table->string('reason', 120)->nullable();
            $table->dateTime('evaluated_at');
            $table->dateTime('notification_at')->nullable();
            $table->string('dedupe_key', 160)->unique();
            $table->timestamps();
            $table->foreign('station_id')->references('id')->on('urban_stations')->cascadeOnDelete();
            $table->index(['station_id', 'evaluated_at']);
        });
        Schema::create('urban_explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('urban_forecasts')->cascadeOnDelete();
            $table->string('feature', 80);
            $table->decimal('value', 12, 4)->nullable();
            $table->decimal('contribution', 12, 4)->nullable();
            $table->timestamps();
            $table->index('forecast_id');
        });
        Schema::create('urban_pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 40)->index();
            $table->string('status', 24)->index();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('records_processed')->default(0);
            $table->decimal('freshness_minutes', 10, 2)->nullable();
            $table->string('data_snapshot_id', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_model_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('version', 80)->unique();
            $table->string('algorithm', 100);
            $table->string('status', 40)->default('illustrative');
            $table->dateTime('train_cutoff')->nullable();
            $table->string('feature_version', 80)->nullable();
            $table->boolean('is_synthetic')->default(true);
            $table->json('metrics');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_explanations');
        Schema::dropIfExists('urban_alert_decisions');
        Schema::dropIfExists('urban_alert_events');
        Schema::dropIfExists('urban_forecasts');
        Schema::dropIfExists('air_quality_readings');
        Schema::dropIfExists('urban_pipeline_runs');
        Schema::dropIfExists('urban_model_metrics');
        Schema::dropIfExists('urban_stations');
    }
};
