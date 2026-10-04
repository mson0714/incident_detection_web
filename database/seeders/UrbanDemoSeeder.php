<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\{UrbanStation, AirQualityReading, UrbanForecast, UrbanAlertEvent, UrbanExplanation, UrbanPipelineRun, UrbanModelMetric};
use App\Services\UrbanAlertEngine;

class UrbanDemoSeeder extends Seeder
{
    public function run(): void
    {
        Carbon::setTestNow(Carbon::now('UTC')->startOfHour());
        $stations = [
            ['ST001','Ben Thanh','District 1',10.7721,106.6983], ['ST002','Thu Duc Gateway','Thu Duc City',10.8506,106.7719],
            ['ST003','Tan Son Nhat','Tan Binh',10.8188,106.6519], ['ST004','Phu My Hung','District 7',10.7298,106.7218],
            ['ST005','Binh Tan West','Binh Tan',10.7520,106.6035], ['ST006','Can Gio Coast','Can Gio',10.4114,106.9547],
        ];
        foreach ($stations as [$id,$name,$district,$lat,$lon]) UrbanStation::updateOrCreate(['id'=>$id],['name'=>$name.' (synthetic)','district'=>$district,'latitude'=>$lat,'longitude'=>$lon,'status'=>$id === 'ST006' ? 'inactive' : 'active']);
        // Keep the latest synthetic snapshot within the dashboard freshness window.
        // Keep the demo forecast inside the configured freshness window on a fresh install.
        $origin=now('UTC')->subMinutes(20);
        foreach ($stations as $i=>[$id]) {
            // Keep one station intentionally unavailable so the dashboard demonstrates
            // the report's unknown/stale state instead of implying full coverage.
            if ($id === 'ST006') continue;
            for($h=47;$h>=0;$h--) {
                $ts=now('UTC')->subHours($h); $pm=24 + (($i*3)%9) + (($h*7)%13) + sin($h/5)*4; if($id==='ST002' && $h<8)$pm=42+($h%3); if($id==='ST005' && $h<4)$pm=null;
                AirQualityReading::firstOrCreate(['station_id'=>$id,'timestamp'=>$ts],['pm25'=>$pm===null?null:round($pm,2),'temperature'=>round(27+sin($h/8)*2,2),'humidity'=>round(68+($h%7),2),'wind_speed'=>round(1.2+($h%5)/3,2),'quality_flag'=>$pm===null?'missing':'ok']);
            }
            foreach ([1,6,24] as $horizon) {
                $pred= $horizon===6 ? ($id==='ST002'?48:($id==='ST004'?38:28+$i)) : 25+$i+$horizon/10;
                $f=UrbanForecast::firstOrCreate(['station_id'=>$id,'forecast_origin'=>$origin,'horizon_hour'=>$horizon],['generated_at'=>$origin,'target_timestamp'=>$origin->copy()->addHours($horizon),'predicted_pm25'=>round($pred,2),'model_version'=>'demo-synthetic-v1','data_snapshot_id'=>'demo-'.now('UTC')->format('YmdHi'),'data_quality'=>'synthetic','status'=>'available']);
                UrbanExplanation::firstOrCreate(['forecast_id'=>$f->id,'feature'=>'recent_pm25'],['value'=>$pred,'contribution'=>round($pred-30,2)]);
            }
        }
        // Add two h6 cycles per station to demonstrate open/closed/unknown states.
        foreach (['ST002'=>[48,49,24,22],'ST003'=>[40,41,30,29]] as $id=>$vals) {
            foreach ($vals as $n=>$pred) {
                $o=$origin->copy()->subHours((count($vals)-1-$n)); $f=UrbanForecast::firstOrCreate(['station_id'=>$id,'forecast_origin'=>$o,'horizon_hour'=>6,'model_version'=>'demo-synthetic-v1'],['generated_at'=>$o,'target_timestamp'=>$o->copy()->addHours(6),'predicted_pm25'=>$pred,'model_version'=>'demo-synthetic-v1','data_snapshot_id'=>'demo-cycle','data_quality'=>'synthetic','status'=>'available']);
                // Replay with the forecast origin as evaluation time so historical demo cycles
                // exercise the event rules without being rejected by runtime freshness checks.
                app(UrbanAlertEngine::class)->evaluate($f, $o);
            }
        }
        UrbanPipelineRun::firstOrCreate(['stage'=>'forecast','started_at'=>$origin],['status'=>'success','finished_at'=>$origin->copy()->addSeconds(8),'records_processed'=>count($stations)*48,'freshness_minutes'=>20,'data_snapshot_id'=>'demo-'.now('UTC')->format('YmdHi'),'duration_ms'=>8000]);
        UrbanModelMetric::updateOrCreate(['version'=>'demo-synthetic-v1'],['algorithm'=>'Seasonal baseline (illustrative)','status'=>'illustrative','train_cutoff'=>$origin->copy()->subDay(),'feature_version'=>'demo-features-v1','is_synthetic'=>true,'metrics'=>['horizons'=>[['horizon_hour'=>1,'mae'=>4.2,'rmse'=>5.8,'smape'=>12.4],['horizon_hour'=>6,'mae'=>5.1,'rmse'=>6.9,'smape'=>14.8],['horizon_hour'=>24,'mae'=>6.8,'rmse'=>8.7,'smape'=>19.1]],'by_station'=>['ST001'=>['mae'=>4.0,'rmse'=>5.5,'smape'=>11.9],'ST002'=>['mae'=>5.6,'rmse'=>7.4,'smape'=>15.7],'ST003'=>['mae'=>4.7,'rmse'=>6.3,'smape'=>13.8],'ST004'=>['mae'=>5.0,'rmse'=>6.7,'smape'=>14.2],'ST005'=>['mae'=>6.2,'rmse'=>8.1,'smape'=>17.4],'ST006'=>['mae'=>7.1,'rmse'=>9.2,'smape'=>20.5]]],'description'=>'Synthetic demo metrics only; not measured experiments or health guidance.']);
        Carbon::setTestNow();
    }
}
