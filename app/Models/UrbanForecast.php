<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanForecast extends Model {
    protected $table='urban_forecasts'; protected $guarded=[];
    protected $casts=['forecast_origin'=>'datetime','generated_at'=>'datetime','target_timestamp'=>'datetime','predicted_pm25'=>'float'];
    public function station(){return $this->belongsTo(UrbanStation::class,'station_id');}
    public function explanations(){return $this->hasMany(UrbanExplanation::class,'forecast_id');}
}
