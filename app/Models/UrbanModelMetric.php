<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanModelMetric extends Model {
    protected $table='urban_model_metrics'; protected $guarded=[];
    protected $casts=['train_cutoff'=>'datetime','is_synthetic'=>'boolean','metrics'=>'array'];
}
