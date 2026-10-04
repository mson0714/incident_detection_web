<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanAlertDecision extends Model {
    protected $table='urban_alert_decisions'; protected $guarded=[];
    protected $casts=['evaluated_at'=>'datetime', 'notification_at'=>'datetime'];
}
