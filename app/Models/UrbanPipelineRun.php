<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanPipelineRun extends Model {
    protected $table='urban_pipeline_runs'; protected $guarded=[];
    protected $casts=['started_at'=>'datetime','finished_at'=>'datetime','freshness_minutes'=>'float'];
}
