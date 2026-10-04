<?php

namespace Tests\Feature;

use App\Models\{FloodCamera, FloodDataset, FloodModel, FloodPrediction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FloodApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['flood.api_token' => 'test-token']);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    public function test_health_is_public_but_mutations_require_token(): void
    {
        $this->getJson('/api/v1/flood/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->postJson('/api/v1/flood/cameras', ['id' => 'cam-1', 'name' => 'Test', 'source_type' => 'video'])->assertUnauthorized();
    }

    public function test_prediction_is_derived_and_idempotent(): void
    {
        FloodCamera::create(['id' => 'cam-1', 'name' => 'Test', 'source_type' => 'video', 'active' => true]);
        FloodDataset::create(['id' => 'ds-1', 'source' => 'test', 'license' => 'CC0', 'manifest_sha256' => str_repeat('a', 64), 'is_synthetic' => true, 'summary' => ['train_count' => 1, 'val_count' => 1, 'test_count' => 1]]);
        FloodModel::create(['id' => 'model-1', 'dataset_id' => 'ds-1', 'algorithm' => 'test', 'artifact_sha256' => str_repeat('b', 64), 'metrics' => ['val_macro_f1' => 0.8]]);
        $payload = ['request_id' => (string) Str::uuid(), 'camera_id' => 'cam-1', 'model_id' => 'model-1', 'captured_at' => '2026-09-13T07:00:00.123Z', 'image_sha256' => str_repeat('c', 64), 'probabilities' => ['normal' => 0.2, 'flooded' => 0.8], 'latency_ms' => 42];

        $this->withHeaders($this->headers())->postJson('/api/v1/flood/predictions', $payload)->assertCreated()->assertJsonPath('data.label', 'flooded');
        $this->withHeaders($this->headers())->postJson('/api/v1/flood/predictions', $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, FloodPrediction::count());
    }
}
