<?php
require __DIR__ . '/../src/bootstrap.php';
use Pharmaco\Auth\Auth;
use Pharmaco\Services\{ServiceFactory, EpiAnalyticsService};
Auth::requireLogin();

$ctx = ServiceFactory::context($_GET);
$page_title = 'Carte des districts'; $active_page = 'carte';
include __DIR__ . '/_layout.php';
if ($ctx['ds']):
    $geo = (new EpiAnalyticsService($ctx['ds']))->geography();
    $f = $ctx['filters'];
?>
<form class="dv-card filters" method="get" style="padding:14px">
  <div><label for="from">Du</label><input class="dv-input" type="date" id="from" name="from" value="<?= h($f['from'] ?? '') ?>"></div>
  <div><label for="to">Au</label><input class="dv-input" type="date" id="to" name="to" value="<?= h($f['to'] ?? '') ?>"></div>
  <div><label for="metric">Indicateur</label><select class="dv-select" id="metric"><option value="total">Cas notifiés</option><option value="confirmed">Cas confirmés</option><option value="deaths">Décès</option></select></div>
  <button class="dv-btn dv-btn-primary">Filtrer</button><?php if ($f): ?><a class="dv-btn dv-btn-outline" href="carte.php">Réinitialiser</a><?php endif; ?>
</form>
<div class="grid" style="grid-template-columns:minmax(0,3fr) minmax(0,1fr)">
  <div class="dv-card" style="padding:0;overflow:hidden"><div id="map" style="height:620px"></div></div>
  <div class="dv-card"><div class="dv-card-header">Top districts</div><ol id="top" style="padding-left:18px;font-size:.88rem;line-height:1.9"></ol>
    <div id="unmatched" class="muted" style="margin-top:12px"></div></div>
</div>
<link rel="stylesheet" href="vendor/leaflet/leaflet.css">
<script src="vendor/leaflet/leaflet.js"></script>
<script>
const DATA=<?= json_js($geo['map']) ?>;
const key=s=>(s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'');
const map=L.map('map',{zoomSnap:.25}).setView([-19,46.5],5.5);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',{attribution:'© OpenStreetMap, © CARTO',maxZoom:12}).addTo(map);
const COLORS=['#f1f5f9','#fde68a','#fbbf24','#f97316','#dc2626','#7f1d1d'];
let layer,gj;
function breaks(vals){const m=Math.max(...vals,1);return [0,1,Math.ceil(m*.2),Math.ceil(m*.4),Math.ceil(m*.7)];}
function color(v,b){if(!v)return COLORS[0];let i=1;for(let j=1;j<b.length;j++)if(v>=b[j])i=j+1;return COLORS[Math.min(i,5)];}
function draw(){
  const metric=document.getElementById('metric').value;
  const vals=Object.values(DATA).map(d=>d[metric]);const b=breaks(vals);
  if(layer)map.removeLayer(layer);
  layer=L.geoJSON(gj,{style:f=>{const d=DATA[key(f.properties.NAME_3)];return{fillColor:color(d?d[metric]:0,b),weight:.6,color:'#64748b',fillOpacity:.85};},
    onEachFeature:(f,l)=>{const d=DATA[key(f.properties.NAME_3)];const n=f.properties.NAME_3;
      l.bindTooltip('<b>'+n+'</b><br>'+(d?('Cas : '+d.total+' · Confirmés : '+d.confirmed+' · Décès : '+d.deaths):'Aucun cas notifié'));}}).addTo(map);
  const top=Object.values(DATA).sort((a,c)=>c[metric]-a[metric]).filter(d=>d[metric]>0).slice(0,12);
  document.getElementById('top').innerHTML=top.map(d=>'<li>'+d.name.replace(/</g,'&lt;')+' — <b>'+d[metric]+'</b></li>').join('')||'<li class="muted">Aucun cas</li>';
}
fetch('Districts.geojson').then(r=>r.json()).then(g=>{gj=g;
  const known=new Set(g.features.map(f=>key(f.properties.NAME_3)));
  const miss=Object.entries(DATA).filter(([k])=>!known.has(k)).map(([,d])=>d.name+' ('+d.total+')');
  if(miss.length)document.getElementById('unmatched').textContent='Districts du Sheet absents de la carte : '+miss.join(', ');
  draw();map.fitBounds(layer.getBounds());}).catch(()=>{document.getElementById('map').innerHTML='<p class="muted" style="padding:20px">Fond de carte indisponible.</p>';});
document.getElementById('metric').addEventListener('change',draw);
</script>
<?php endif; include __DIR__ . '/_footer.php';
