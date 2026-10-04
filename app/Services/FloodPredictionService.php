<?php

namespace App\Services;

use App\Models\FloodPrediction;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FloodPredictionService
{
    public function record(array $values): FloodPrediction
    {
        $values['captured_at'] = Carbon::parse($values['captured_at'])->utc();
        $values['probabilities'] = [
            'normal' => (float) $values['probabilities']['normal'],
            'flooded' => (float) $values['probabilities']['flooded'],
        ];
        $values['label'] = $values['probabilities']['flooded'] > $values['probabilities']['normal'] ? 'flooded' : 'normal';
        $values['confidence'] = $values['probabilities'][$values['label']];
        $row = FloodPrediction::firstOrCreate(['request_id' => $values['request_id']], $values);

        // A retry is safe, but reusing an ID for different evidence is not.
        foreach (['camera_id', 'model_id', 'image_sha256', 'latency_ms', 'probabilities'] as $key) {
            if ($row->$key != $values[$key]) {
                throw new ConflictHttpException('request_id already identifies different evidence.');
            }
        }
        if (! $row->captured_at->equalTo($values['captured_at'])) {
            throw new ConflictHttpException('request_id already identifies a different timestamp.');
        }

        return $row;
    }
}
