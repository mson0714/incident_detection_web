<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanExplanation extends Model {
    protected $table='urban_explanations'; protected $guarded=[];
    protected $casts=['value'=>'float','contribution'=>'float'];
    public function forecast(){return $this->belongsTo(UrbanForecast::class,'forecast_id');}
}
