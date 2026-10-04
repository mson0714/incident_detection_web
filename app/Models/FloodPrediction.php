<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloodPrediction extends Model
{
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $casts = ['captured_at' => 'immutable_datetime', 'confidence' => 'float', 'probabilities' => 'array'];

    public function camera() { return $this->belongsTo(FloodCamera::class, 'camera_id'); }
    public function model() { return $this->belongsTo(FloodModel::class, 'model_id'); }
}
