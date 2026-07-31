<?php
/**
 * shp2geojson.php — Convertit DISTRICT.shp → district_cache.geojson
 * Version robuste : diagnostic complet + multi-chemins d'écriture + téléchargement fallback.
 */

// ══════════════════════════════════════════════════════════════════
//  MODE TÉLÉCHARGEMENT : ?download=1  (doit être AVANT tout output)
// ══════════════════════════════════════════════════════════════════
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $SHP = __DIR__ . '/DISTRICT.shp';
    $DBF = __DIR__ . '/DISTRICT.dbf';
    $SHX = __DIR__ . '/DISTRICT.shx';
    // Chercher le fichier déjà généré
    $candidates = [
        __DIR__ . '/district_cache.geojson',
        __DIR__ . '/cache/district_cache.geojson',
        sys_get_temp_dir() . '/district_cache.geojson',
    ];
    $json = null;
    foreach ($candidates as $c) {
        if (file_exists($c) && filesize($c) > 100) { $json = file_get_contents($c); break; }
    }
    if (!$json) {
        // Régénérer à la volée
        function readDbfQ(string $path): array {
            $fp = fopen($path,'rb'); fread($fp,1); fread($fp,3);
            $n = unpack('V',fread($fp,4))[1]; $hb = unpack('v',fread($fp,2))[1]; fread($fp,22);
            $fields=[]; for($i=0;$i<intdiv($hb-33,32);$i++){
                $name=rtrim(fread($fp,11),"\x00"); $type=fread($fp,1); fread($fp,4);
                $len=ord(fread($fp,1)); fread($fp,15); $fields[]=['n'=>$name,'t'=>$type,'l'=>$len];}
            fread($fp,1); $recs=[];
            for($r=0;$r<$n;$r++){$del=fread($fp,1);$rec=[];foreach($fields as $f){
                $raw=fread($fp,$f['l']); $val=mb_convert_encoding(trim($raw),'UTF-8','ISO-8859-1');
                $rec[$f['n']]=$val;} if($del!=='*') $recs[]=$rec;}
            fclose($fp); return $recs;}
        function readShpQ(string $sp,string $sx): array {
            $shp=fopen($sp,'rb'); $shx=fopen($sx,'rb'); fread($shp,100); fread($shx,100); $shapes=[];
            while(!feof($shx)){$or=fread($shx,4);$lr=fread($shx,4);if(strlen($or)<4||strlen($lr)<4)break;
                $off=unpack('N',$or)[1]*2; fseek($shp,$off); $h=fread($shp,12); if(strlen($h)<12)break;
                $st=unpack('V',substr($h,8,4))[1]; if($st===0){$shapes[]=null;continue;}
                if(!in_array($st,[5,15,25])){$rl=unpack('N',substr($h,4,4))[1]*2;fseek($shp,$off+8+$rl);$shapes[]=null;continue;}
                fread($shp,32); $np=unpack('V',fread($shp,4))[1]; $npt=unpack('V',fread($shp,4))[1];
                $parts=[]; for($p=0;$p<$np;$p++) $parts[]=unpack('V',fread($shp,4))[1];
                $pts=[]; for($p=0;$p<$npt;$p++){$xb=fread($shp,8);$yb=fread($shp,8);
                    if(strlen($xb)<8||strlen($yb)<8)break;$pts[]=[unpack('d',$xb)[1],unpack('d',$yb)[1]];}
                $rings=[]; for($p=0;$p<$np;$p++){$s=$parts[$p];$e=($p+1<$np)?$parts[$p+1]:$npt;$rings[]=array_slice($pts,$s,$e-$s);}
                if(count($rings)===1) $shapes[]=['type'=>'Polygon','coordinates'=>$rings];
                else $shapes[]=['type'=>'MultiPolygon','coordinates'=>array_map(fn($r)=>[$r],$rings)];}
            fclose($shp);fclose($shx);return $shapes;}
        try {
            $recs=$recs??readDbfQ($DBF); $shps=readShpQ($SHP,$SHX);
            $feats=[]; for($i=0;$i<min(count($recs),count($shps));$i++){if($shps[$i]===null)continue;$feats[]=['type'=>'Feature','geometry'=>$shps[$i],'properties'=>$recs[$i]];}
            $json=json_encode(['type'=>'FeatureCollection','features'=>$feats],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(Exception $e){http_response_code(500);header('Content-Type:text/plain');echo $e->getMessage();exit();}
    }
    header('Content-Type: application/geo+json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="district_cache.geojson"');
    header('Content-Length: '.strlen($json));
    echo $json; exit();
}

// ══════════════════════════════════════════════════════════════════
//  PAGE PRINCIPALE
// ══════════════════════════════════════════════════════════════════
header('Content-Type: text/html; charset=UTF-8');

$SHP_FILE = __DIR__ . '/DISTRICT.shp';
$DBF_FILE = __DIR__ . '/DISTRICT.dbf';
$SHX_FILE = __DIR__ . '/DISTRICT.shx';

// Chercher où écrire
$writeCandidates = [
    __DIR__ . '/district_cache.geojson',
    __DIR__ . '/cache/district_cache.geojson',
    sys_get_temp_dir() . '/district_cache.geojson',
];
$OUT_FILE = sys_get_temp_dir() . '/district_cache.geojson'; // fallback
foreach ($writeCandidates as $c) {
    $d = dirname($c);
    if ((is_dir($d) && is_writable($d)) || @mkdir($d,0755,true)) { $OUT_FILE=$c; break; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>SHP → GeoJSON · Mpox Madagascar</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0d1117;color:#c9d1d9;min-height:100vh;padding:24px 16px;}
.box{max-width:820px;margin:0 auto;}
.card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:22px;margin-bottom:16px;}
h1{font-size:1.1rem;color:#f0f6fc;margin-bottom:4px;}
.sub{font-size:.82rem;color:#8b949e;margin-bottom:18px;}
h2{font-size:.9rem;font-weight:700;color:#f0f6fc;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #30363d;}
.line{display:flex;gap:10px;align-items:flex-start;padding:5px 0;font-size:.85rem;line-height:1.4;}
.icon{min-width:22px;text-align:center;flex-shrink:0;}
.ok{color:#56d364;} .err{color:#f85149;} .warn{color:#e3b341;} .info{color:#8b949e;}
.highlight{color:#c9d1d9;}
.sep{border:none;border-top:1px solid #30363d;margin:16px 0;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border-radius:8px;
     text-decoration:none;font-weight:700;font-size:.9rem;margin-top:6px;margin-right:8px;}
.btn-green{background:#238636;color:#fff;} .btn-green:hover{background:#2ea043;}
.btn-blue {background:#1f6feb;color:#fff;} .btn-blue:hover{background:#388bfd;}
.alert{border-radius:8px;padding:12px 16px;font-size:.84rem;margin:12px 0;}
.alert-warn{background:#3d2f00;border:1px solid #9e6a03;color:#e3b341;}
.alert-err {background:#3d0000;border:1px solid #9e1c1c;color:#f85149;}
.alert-ok  {background:#0d2c12;border:1px solid #1a7f37;color:#56d364;}
code{background:#0d1117;padding:2px 6px;border-radius:4px;font-size:.82rem;color:#a5d6ff;}
.big-num{font-size:2rem;font-weight:800;color:#56d364;line-height:1;}
.stats-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin:12px 0;}
.stat-box{background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:12px;text-align:center;}
.stat-lbl{font-size:.72rem;color:#8b949e;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;}
</style>
</head>
<body>
<div class="box">

<div class="card">
<h1>🗺️ Conversion DISTRICT.shp → GeoJSON</h1>
<p class="sub">Mpox Madagascar · Génération du fond de carte</p>

<?php
// ── Fonctions ────────────────────────────────────────────────────
function readDbf(string $path): array {
    $fp = fopen($path,'rb');
    if(!$fp) throw new Exception("Impossible d'ouvrir DBF : $path");
    fread($fp,1); fread($fp,3);
    $numRec = unpack('V',fread($fp,4))[1];
    $hBytes = unpack('v',fread($fp,2))[1];
    fread($fp,22);
    $fields=[]; $nf=intdiv($hBytes-33,32);
    for($i=0;$i<$nf;$i++){
        $name=rtrim(fread($fp,11),"\x00"); $type=fread($fp,1); fread($fp,4);
        $len=ord(fread($fp,1)); fread($fp,15);
        $fields[]=['name'=>$name,'type'=>$type,'length'=>$len];
    }
    fread($fp,1);
    $recs=[];
    for($r=0;$r<$numRec;$r++){
        $del=fread($fp,1); $rec=[];
        foreach($fields as $f){
            $raw=fread($fp,$f['length']);
            $val=mb_convert_encoding(trim($raw),'UTF-8','ISO-8859-1');
            if($f['type']==='N'||$f['type']==='F') $val=is_numeric($val)?(float)$val:null;
            $rec[$f['name']]=$val;
        }
        if($del!=='*') $recs[]=$rec;
    }
    fclose($fp); return $recs;
}

function readShp(string $sp,string $sx): array {
    $shp=fopen($sp,'rb'); $shx=fopen($sx,'rb');
    if(!$shp||!$shx) throw new Exception("Impossible d'ouvrir SHP/SHX");
    fread($shp,100); fread($shx,100);
    $shapes=[];
    while(!feof($shx)){
        $or=fread($shx,4); $lr=fread($shx,4);
        if(strlen($or)<4||strlen($lr)<4) break;
        $off=unpack('N',$or)[1]*2;
        fseek($shp,$off);
        $h=fread($shp,12); if(strlen($h)<12) break;
        $st=unpack('V',substr($h,8,4))[1];
        if($st===0){$shapes[]=null;continue;}
        if(!in_array($st,[5,15,25])){
            $rl=unpack('N',substr($h,4,4))[1]*2; fseek($shp,$off+8+$rl); $shapes[]=null; continue;}
        fread($shp,32);
        $np=unpack('V',fread($shp,4))[1]; $npt=unpack('V',fread($shp,4))[1];
        $parts=[]; for($p=0;$p<$np;$p++) $parts[]=unpack('V',fread($shp,4))[1];
        $pts=[]; for($p=0;$p<$npt;$p++){
            $xb=fread($shp,8); $yb=fread($shp,8);
            if(strlen($xb)<8||strlen($yb)<8) break;
            $pts[]=[unpack('d',$xb)[1],unpack('d',$yb)[1]];
        }
        // Anneaux → polygones/trous
        $rings=[];
        for($p=0;$p<$np;$p++){
            $s=$parts[$p]; $e=($p+1<$np)?$parts[$p+1]:$npt;
            $rings[]=array_slice($pts,$s,$e-$s);
        }
        $polys=[]; $cur=null;
        foreach($rings as $ring){
            // Aire signée pour détecter orientation
            $area=0; $n=count($ring);
            for($i=0;$i<$n;$i++){$j=($i+1)%$n; $area+=$ring[$i][0]*$ring[$j][1]-$ring[$j][0]*$ring[$i][1];}
            $ext=($area>0);
            if($ext||$cur===null){if($cur!==null) $polys[]=$cur; $cur=[$ring];}
            else $cur[]=$ring;
        }
        if($cur!==null) $polys[]=$cur;
        if(count($polys)===1) $shapes[]=['type'=>'Polygon','coordinates'=>$polys[0]];
        else $shapes[]=['type'=>'MultiPolygon','coordinates'=>$polys];
    }
    fclose($shp); fclose($shx); return $shapes;
}

// ── Diagnostic ──────────────────────────────────────────────────
echo '<h2>1. Diagnostic</h2>';

// PHP / OS
line('info','ℹ️','PHP '.PHP_VERSION.' · '.PHP_OS);
line('info','ℹ️','Dossier : '.__DIR__);
line('info','ℹ️','Écriture cible : '.$OUT_FILE);

// Fichiers SHP
$allFound=true;
foreach(['DISTRICT.shp'=>$SHP_FILE,'DISTRICT.dbf'=>$DBF_FILE,'DISTRICT.shx'=>$SHX_FILE] as $nm=>$p){
    if(file_exists($p)) line('ok','✅',"$nm — ".round(filesize($p)/1024).' Ko');
    else { line('err','❌',"MANQUANT : $p"); $allFound=false; }
}

// Droits écriture
$wd=dirname($OUT_FILE);
if(is_writable($wd)) line('ok','✅',"Écriture autorisée dans : $wd");
else line('warn','⚠️',"Dossier non accessible en écriture : $wd → le fichier sera téléchargeable");

function line(string $cls,string $icon,string $msg):void{
    echo '<div class="line"><span class="icon">'.$icon.'</span><span class="'.$cls.'">'.htmlspecialchars($msg).'</span></div>'."\n";
}

if(!$allFound){
    echo '<div class="alert alert-err" style="margin-top:12px;">
    ❌ Fichiers SHP manquants. Déposez DISTRICT.shp, DISTRICT.dbf, DISTRICT.shx dans <code>'
    .htmlspecialchars(__DIR__).'</code></div>';
    echo '</div></div></body></html>'; exit();
}

echo '<hr class="sep">';
echo '<h2>2. Conversion</h2>';

// ── Conversion ──────────────────────────────────────────────────
$geojsonStr=null; $convertErr=null; $numFeatures=0; $nameCol=null; $sample=[];
try {
    $records=readDbf($DBF_FILE);
    $cols=array_keys($records[0]??[]);
    line('ok','✅',count($records).' enregistrements DBF');
    line('info','ℹ️','Colonnes : '.implode(', ',$cols));

    foreach(['NOM_DISTRI','NOM_DIST','NOMDIST','District','DISTRICT','NAME_2','NAME','NOM','nom','name'] as $c)
        if(in_array($c,$cols)){$nameCol=$c;break;}
    if($nameCol) line('ok','✅',"Colonne nom district : $nameCol");
    else line('warn','⚠️',"Colonne nom non trouvée — utilisation de la 1ère colonne : ".($cols[0]??'?'));

    $shapes=readShp($SHP_FILE,$SHX_FILE);
    line('ok','✅',count($shapes).' géométries SHP');

    $features=[]; $skip=0;
    for($i=0;$i<min(count($records),count($shapes));$i++){
        if($shapes[$i]===null){$skip++;continue;}
        $features[]=['type'=>'Feature','geometry'=>$shapes[$i],'properties'=>$records[$i]];
    }
    if($skip) line('warn','⚠️',"$skip géométries nulles ignorées");
    $numFeatures=count($features);
    line('ok','✅',"$numFeatures features GeoJSON générées");

    // Aperçu
    foreach(array_slice($features,0,5) as $f){
        $p=$f['properties'];
        $nm=$nameCol?($p[$nameCol]??'?'):(array_values($p)[0]??'?');
        $sample[]=$nm;
    }

    $geojsonStr=json_encode(['type'=>'FeatureCollection','features'=>$features],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!$geojsonStr) throw new Exception("json_encode : ".json_last_error_msg());
    line('ok','✅','JSON encodé — '.round(strlen($geojsonStr)/1024).' Ko');
} catch(Exception $e){
    $convertErr=$e->getMessage();
    line('err','❌',$convertErr);
}

// ── Écriture ────────────────────────────────────────────────────
$written=false; $inTmp=false;
if($geojsonStr){
    $bytes=@file_put_contents($OUT_FILE,$geojsonStr);
    if($bytes!==false){
        $written=true;
        $inTmp=(strpos($OUT_FILE,sys_get_temp_dir())===0);
        if($inTmp) line('warn','⚠️','Écrit dans /tmp (hors racine web) — utilisez le bouton télécharger');
        else line('ok','✅','Fichier écrit : '.basename($OUT_FILE)." ($bytes octets)");
    } else {
        line('err','❌','Écriture impossible — utilisez le bouton télécharger ci-dessous');
    }
}
?>

<?php if($sample): ?>
<hr class="sep">
<h2>3. Aperçu</h2>
<div class="stats-row">
  <div class="stat-box"><div class="big-num"><?=$numFeatures?></div><div class="stat-lbl">Districts</div></div>
  <div class="stat-box"><div class="big-num"><?=round(strlen($geojsonStr??'')/1024)?> Ko</div><div class="stat-lbl">Taille GeoJSON</div></div>
  <div class="stat-box"><div class="big-num"><?=$nameCol??'auto'?></div><div class="stat-lbl">Colonne nom</div></div>
</div>
<div style="font-size:.82rem;color:#8b949e;margin-top:4px;">
  5 premiers districts : <span style="color:#c9d1d9;"><?=htmlspecialchars(implode(', ',$sample))?></span>
</div>
<?php endif; ?>

<hr class="sep">
<h2>4. Résultat</h2>

<?php if($geojsonStr && $written && !$inTmp): ?>
<div class="alert alert-ok">✅ <strong>Succès !</strong> Le fichier <code>district_cache.geojson</code> est prêt sur le serveur.</div>
<a href="carto_mpox.php" class="btn btn-green">🗺️ Ouvrir la Cartographie Mpox</a>

<?php elseif($geojsonStr): ?>
<div class="alert alert-warn">
  ⚠️ <strong>Le serveur ne peut pas écrire le fichier directement.</strong><br>
  Téléchargez le GeoJSON ci-dessous, puis déposez-le à la racine de votre site sous le nom
  <code>district_cache.geojson</code> (via FTP/FileManager).
</div>
<a href="shp2geojson.php?download=1" class="btn btn-blue">⬇️ Télécharger district_cache.geojson</a>
<a href="carto_mpox.php" class="btn btn-green">🗺️ Tester la cartographie</a>
<div style="font-size:.8rem;color:#8b949e;margin-top:10px;">
  Après dépôt du fichier via FTP : revenez sur <a href="carto_mpox.php" style="color:#58a6ff;">carto_mpox.php</a>
</div>

<?php else: ?>
<div class="alert alert-err">
  ❌ <strong>Conversion échouée.</strong> <?=htmlspecialchars($convertErr??'')?><br><br>
  Alternative : convertissez sur
  <a href="https://mapshaper.org/" target="_blank" style="color:#58a6ff;">mapshaper.org</a>
  (importez DISTRICT.shp → exportez GeoJSON) et déposez le fichier sous le nom
  <code>district_cache.geojson</code> à la racine de votre site.
</div>
<?php endif; ?>

</div><!-- /card -->
</div><!-- /box -->
</body>
</html>