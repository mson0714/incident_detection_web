<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloodModel extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['metrics' => 'array'];

    public function dataset() { return $this->belongsTo(FloodDataset::class, 'dataset_id'); }
}
