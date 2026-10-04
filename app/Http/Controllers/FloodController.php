<?php

namespace App\Http\Controllers;

use App\Models\{FloodCamera, FloodDataset, FloodModel, FloodPrediction};
use App\Services\FloodPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FloodController extends Controller
{
    private function idRules(string $table): array
    {
        return ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', Rule::unique($table, 'id')];
    }

    public function health()
    {
        try {
            DB::connection()->getPdo();
            foreach (['flood_cameras', 'flood_datasets', 'flood_models', 'flood_predictions', 'flood_events'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new \RuntimeException('Migration required');
                }
            }
            return response()->json(['status' => 'ok', 'schema' => 'ok', 'version' => 'v1']);
        } catch (\Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }

    public function cameras(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);
        return response()->json(FloodCamera::orderBy('id')->paginate(20));
    }

    public function storeCamera(Request $request)
    {
        $values = $request->validate([
            'id' => $this->idRules('flood_cameras'), 'name' => 'required|string|max:255',
            'source_type' => 'required|in:video,rtsp', 'active' => 'sometimes|boolean',
            'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        return response()->json(['data' => FloodCamera::create($values)], 201);
    }

    public function datasets(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);
        return response()->json(FloodDataset::orderBy('id')->paginate(20));
    }

    public function storeDataset(Request $request)
    {
        $values = $request->validate([
            'id' => $this->idRules('flood_datasets'), 'source' => 'required|string|max:255',
            'license' => 'required|string|max:255', 'is_synthetic' => 'required|boolean',
            'manifest_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/', 'unique:flood_datasets,manifest_sha256'],
            'summary' => 'required|array',
            'summary.train_count' => 'required|integer|min:1',
            'summary.val_count' => 'required|integer|min:1',
            'summary.test_count' => 'required|integer|min:1',
        ]);
        return response()->json(['data' => FloodDataset::create($values)], 201);
    }

    public function models(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);
        return response()->json(FloodModel::with('dataset')->orderBy('id')->paginate(20));
    }

    public function storeModel(Request $request)
    {
        $values = $request->validate([
            'id' => $this->idRules('flood_models'), 'dataset_id' => 'required|string|exists:flood_datasets,id',
            'algorithm' => 'required|string|max:100',
            'artifact_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/', 'unique:flood_models,artifact_sha256'],
            'metrics' => 'required|array',
            'metrics.val_macro_f1' => 'required|numeric|between:0,1',
            'metrics.test_macro_f1' => 'nullable|numeric|between:0,1',
        ]);
        return response()->json(['data' => FloodModel::create($values)], 201);
    }

    public function predictions(Request $request)
    {
        $values = $request->validate([
            'camera_id' => 'sometimes|string|exists:flood_cameras,id',
            'model_id' => 'sometimes|string|exists:flood_models,id',
            'label' => 'sometimes|in:normal,flooded', 'page' => 'sometimes|integer|min:1',
        ]);
        $query = FloodPrediction::query();
        foreach (['camera_id', 'model_id', 'label'] as $key) {
            if (isset($values[$key])) { $query->where($key, $values[$key]); }
        }
        return response()->json($query->with('model.dataset')->orderByDesc('captured_at')->orderByDesc('id')->paginate(20));
    }

    public function storePrediction(Request $request, FloodPredictionService $service)
    {
        $values = $request->validate([
            'request_id' => 'required|uuid',
            'camera_id' => ['required', 'string', Rule::exists('flood_cameras', 'id')->where('active', true)],
            'model_id' => 'required|string|exists:flood_models,id',
            'captured_at' => ['required', 'date', 'before_or_equal:now', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/'],
            'image_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'probabilities' => 'required|array:normal,flooded',
            'probabilities.normal' => 'required|numeric|between:0,1',
            'probabilities.flooded' => 'required|numeric|between:0,1',
            'latency_ms' => 'required|integer|between:0,3600000',
        ]);
        if (abs(array_sum($values['probabilities']) - 1) > 0.000001) {
            throw ValidationException::withMessages(['probabilities' => 'Probabilities must sum to one.']);
        }
        $prediction = $service->record($values);
        return response()->json(['data' => $prediction, 'replayed' => ! $prediction->wasRecentlyCreated], $prediction->wasRecentlyCreated ? 201 : 200);
    }
}
