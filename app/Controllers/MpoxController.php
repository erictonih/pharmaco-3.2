<?php
namespace App\Controllers;
final class MpoxController extends BaseController {
 public function index(){
  $stats=['hommes'=>0,'femmes'=>0,'months'=>[],'decesHommes'=>[],'decesFemmes'=>[]];
  $cache=WRITEPATH.'cache/mpox_data.json';
  if(is_file($cache) && ($rows=json_decode((string)file_get_contents($cache),true)) && count($rows)>1){
   $headers=array_shift($rows);foreach($rows as $row){$r=array_combine($headers,array_pad($row,count($headers),''));$e=mb_strtolower(trim((string)($r['Evolution']??'')),'UTF-8');if(!str_contains($e,'déc')&&!str_contains($e,'dec'))continue;$sex=mb_strtolower(trim((string)($r['Sexe']??'')),'UTF-8');$month=date('Y-m',strtotime((string)($r['Date de notification']??''))?:time());if(!isset($stats['months'][$month])){$stats['months'][$month]=['h'=>0,'f'=>0];}if(str_contains($sex,'masc')||$sex==='m'){$stats['hommes']++;$stats['months'][$month]['h']++;}elseif(str_contains($sex,'fém')||str_contains($sex,'fem')||$sex==='f'){$stats['femmes']++;$stats['months'][$month]['f']++;}}
  }
  ksort($stats['months']);$stats['decesHommes']=array_column($stats['months'],'h');$stats['decesFemmes']=array_column($stats['months'],'f');return view('pages/mpox',$stats+['title'=>'Analyse Mpox']);
 }
}
