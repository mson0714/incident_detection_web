<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloodCamera extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];

    public function predictions() { return $this->hasMany(FloodPrediction::class, 'camera_id'); }
}
