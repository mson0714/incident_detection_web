<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanAlertEvent extends Model {
    protected $table='urban_alert_events'; protected $guarded=[];
    protected $casts=['opened_at'=>'datetime','last_seen_at'=>'datetime','closed_at'=>'datetime','target_timestamp'=>'datetime','predicted_pm25'=>'float','threshold'=>'float'];
    public function station(){return $this->belongsTo(UrbanStation::class,'station_id');}
}
