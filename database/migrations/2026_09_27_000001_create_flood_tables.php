<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flood_cameras', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source_type', 16); // video or rtsp; credentials never stored here
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('flood_datasets', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('source');
            $table->string('license', 255);
            $table->char('manifest_sha256', 64)->unique();
            $table->boolean('is_synthetic')->default(false);
            $table->json('summary');
            $table->timestamps();
        });
        Schema::create('flood_models', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('dataset_id', 64);
            $table->foreign('dataset_id')->references('id')->on('flood_datasets')->restrictOnDelete();
            $table->string('algorithm', 100);
            $table->char('artifact_sha256', 64)->unique();
            $table->json('metrics');
            $table->timestamps();
        });
        Schema::create('flood_predictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('camera_id', 64);
            $table->string('model_id', 64);
            $table->foreign('camera_id')->references('id')->on('flood_cameras')->restrictOnDelete();
            $table->foreign('model_id')->references('id')->on('flood_models')->restrictOnDelete();
            $table->dateTime('captured_at', 6);
            $table->char('image_sha256', 64);
            $table->string('label', 16);
            $table->double('confidence');
            $table->json('probabilities');
            $table->unsignedInteger('latency_ms');
            $table->timestamps();
            $table->index(['camera_id', 'captured_at']);
            $table->index(['model_id', 'captured_at']);
        });
        // Reserved for a later temporal event engine; a frame is not an alert.
        Schema::create('flood_events', function (Blueprint $table) {
            $table->id();
            $table->string('camera_id', 64);
            $table->foreign('camera_id')->references('id')->on('flood_cameras')->restrictOnDelete();
            $table->foreignId('evidence_prediction_id')->constrained('flood_predictions')->restrictOnDelete();
            $table->string('rule_version', 64);
            $table->string('status', 16)->index();
            $table->dateTime('opened_at', 6);
            $table->dateTime('closed_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['flood_events', 'flood_predictions', 'flood_models', 'flood_datasets', 'flood_cameras'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
