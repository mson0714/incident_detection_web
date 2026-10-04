<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AirQualityReading extends Model {
    protected $table='air_quality_readings'; protected $guarded=[];
    protected $casts=['timestamp'=>'datetime','pm25'=>'float','temperature'=>'float','humidity'=>'float','wind_speed'=>'float'];
    public function station(){return $this->belongsTo(UrbanStation::class,'station_id');}
}
