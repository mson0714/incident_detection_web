<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanStation extends Model {
    protected $table = 'urban_stations';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['latitude'=>'float','longitude'=>'float'];
    public function readings(){ return $this->hasMany(AirQualityReading::class,'station_id'); }
    public function forecasts(){ return $this->hasMany(UrbanForecast::class,'station_id'); }
    public function events(){ return $this->hasMany(UrbanAlertEvent::class,'station_id'); }
}
