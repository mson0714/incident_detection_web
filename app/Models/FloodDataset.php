<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloodDataset extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['summary' => 'array', 'is_synthetic' => 'boolean'];
}
