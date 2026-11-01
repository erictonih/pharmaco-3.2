<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$page_title  = 'Tableau croisé dynamique';
$active_page = 'tdc';
$breadcrumb  = ['Accueil'=>'index.php','TDC'=>''];
include 'sidebar.php';
?>

    <!-- Extra styles pour le TDC -->
    <style>
    .tdc-controls {
        display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;
        padding: 16px 20px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        margin-bottom: 16px;
    }
    .tdc-group { display:flex; flex-direction:column; gap:4px; }
    .tdc-group label { font-size:0.75rem; font-weight:600; color:var(--text-secondary); }
    .tdc-group input[type=text],
    .tdc-group select { padding:8px 12px; border:1px solid var(--border); border-radius:8px;
                        background:var(--bg-card); color:var(--text-primary);
                        font-family:'DM Sans',sans-serif; font-size:0.85rem; min-width:160px; }
    .tdc-group input[type=text]:focus,
    .tdc-group select:focus { outline:none; border-color:var(--accent); }
    #pivot-container {
        background:var(--bg-card); border:1px solid var(--border);
        border-radius:var(--radius); padding:16px; min-height:120px;
    }
    #debug {
        background:var(--bg-card); border:1px dashed var(--border);
        border-radius:var(--radius); padding:12px; margin-top:12px;
        font-size:0.8rem; color:var(--text-muted); white-space:pre-wrap;
        max-height:220px; overflow:auto;
    }
    #meta { font-size:0.8rem; color:var(--accent); font-weight:600; margin:8px 0; }
    table.pvtTable { border-collapse:collapse; width:100%; font-size:0.85rem; }
    table.pvtTable th, table.pvtTable td { border:1px solid var(--border); padding:7px 10px; }
    table.pvtTable th { background:var(--accent-soft); color:var(--accent); font-weight:600; }
    table.pvtTable tr:hover td { background:var(--accent-soft); }
    </style>

    <div class="dv-content dv-animate">

        <div class="dv-page-header">
            <h1><i class="bi bi-table" style="color:var(--accent);margin-right:8px;"></i>Tableau Croisé Dynamique</h1>
            <div class="dv-breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                <span>TDC</span>
            </div>
        </div>

        <!-- Contrôles -->
        <div class="tdc-controls dv-animate dv-animate-d1">
            <div class="tdc-group">
                <label>Source (endpoint)</label>
                <input id="source" type="text" value="data.php" style="width:200px;">
            </div>
            <div class="tdc-group">
                <label>Agrégateur</label>
                <select id="agg">
                    <option value="Count">Count</option>
                    <option value="Sum">Sum (Quantite)</option>
                    <option value="Average">Average (Quantite)</option>
                </select>
            </div>
            <div class="tdc-group" style="flex-direction:row; align-items:center; gap:8px; margin-top:16px;">
                <input type="checkbox" id="computeTranche" style="accent-color:var(--accent); width:15px; height:15px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); cursor:pointer;" for="computeTranche">Calculer Tranche_Âge</label>
            </div>
            <div class="tdc-group">
                <label>Unité âge</label>
                <select id="ageUnit">
                    <option value="months">Mois</option>
                    <option value="years">Années</option>
                </select>
            </div>
            <div class="tdc-group" style="flex-direction:row; align-items:flex-end; gap:8px;">
                <button id="load" class="dv-btn dv-btn-primary" style="height:38px;padding:0 16px;">
                    <i class="bi bi-play-fill"></i> Charger
                </button>
                <button id="exportCsv" class="dv-btn dv-btn-outline" style="height:38px;padding:0 14px;">
                    <i class="bi bi-download"></i> CSV
                </button>
                <button id="reset" class="dv-btn" style="height:38px;padding:0 14px;background:var(--bg-card);border:1px solid var(--border);color:var(--text-secondary);">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
            <div class="tdc-group" style="margin-left:auto; flex-direction:row; gap:8px; align-items:flex-end;">
                <button id="loadSample" class="dv-btn" style="font-size:0.75rem;padding:6px 12px;background:var(--accent-soft);color:var(--accent);border:1px solid var(--accent);">Exemple valide</button>
                <button id="loadEmpty"  class="dv-btn" style="font-size:0.75rem;padding:6px 12px;background:var(--bg-card);border:1px solid var(--border);color:var(--text-muted);">Vide</button>
            </div>
        </div>

        <!-- Colonnes par défaut -->
        <div class="tdc-controls dv-animate dv-animate-d1" style="padding:12px 20px;">
            <div class="tdc-group">
                <label>Ligne par défaut</label>
                <select id="rowsSelect"></select>
            </div>
            <div class="tdc-group">
                <label>Colonne par défaut</label>
                <select id="colsSelect"></select>
            </div>
        </div>

        <div id="meta" class="dv-animate dv-animate-d2"></div>

        <div id="pivot-container" class="dv-animate dv-animate-d2">
            <div style="color:var(--text-muted);font-size:0.85rem;padding:20px;text-align:center;">
                <i class="bi bi-table" style="font-size:2rem;opacity:0.3;"></i>
                <p style="margin-top:8px;">Cliquez sur <strong>Charger</strong> pour afficher le tableau croisé.</p>
            </div>
        </div>

        <div id="debug" class="dv-animate dv-animate-d3">Status : prêt</div>

    </div>

<!-- Libs TDC -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/d3_renderers.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/export_renderers.min.js"></script>
<script>
function log(t){var e=document.getElementById('debug');e.textContent=new Date().toISOString()+' — '+t+'\n\n'+e.textContent;}
function showMeta(t){document.getElementById('meta').textContent=t;}

function tryNum(v){if(v===null||v===undefined)return v;if(typeof v==='number')return v;
    if(typeof v==='string'){var s=v.trim().replace(/\s+/g,'').replace(',','.');
        if(/^[-+]?[0-9]*\.?[0-9]+$/.test(s)){var n=Number(s);return Number.isInteger(n)?parseInt(n,10):n;}}return v;}

function normRow(row){var r={};for(var k in row){var v=row[k];if(v===null)v='';r[k]=tryNum(v);}return r;}

function tranche(age,unit){var n=Number(age);if(isNaN(n))return 'Non renseigné';
    if(unit==='months'){if(n>=2&&n<=6)return '2-6 mois';if(n>=7&&n<=11)return '6-11 mois';
        if(n>=12&&n<=59)return '1-5 ans';if(n>=60&&n<=143)return '5-12 ans';
        if(n>=144&&n<=299)return '12-25 ans';return '25 ans et plus';}
    if(n<1)return 'Moins de 1 an';if(n<=5)return '1-5 ans';if(n<=12)return '5-12 ans';
    if(n<=25)return '12-25 ans';return '25 ans et plus';}

function renderPivot(data){
    try{
        if(data&&data.error){$('#pivot-container').html('<div class="dv-alert dv-alert-error"><i class="bi bi-exclamation-triangle"></i>'+data.error+'</div>');return;}
        if(!Array.isArray(data)) data=[data];
        var norm=data.map(normRow);
        var ct=document.getElementById('computeTranche').checked;
        var au=document.getElementById('ageUnit').value;
        if(ct) norm.forEach(function(r){r.Tranche_Age=r.Age!==undefined&&r.Age!==''?tranche(r.Age,au):'Non renseigné';});
        if(norm.length===0){$('#pivot-container').html('<div class="dv-alert dv-alert-warning"><i class="bi bi-exclamation"></i>Aucune donnée</div>');showMeta('Tableau vide');return;}
        var cols=Object.keys(norm[0]);
        populateSels(cols);
        var agg=document.getElementById('agg').value;
        var aggName='Count',vals=[];
        if(agg==='Sum'||agg==='Average'){aggName=agg;if(cols.indexOf('Quantite')!==-1)vals=['Quantite'];}
        var defRow=document.getElementById('rowsSelect').value||(cols.indexOf('Genre')!==-1?'Genre':cols[0]);
        var defCol=document.getElementById('colsSelect').value||(cols.indexOf('Tranche_Age')!==-1?'Tranche_Age':cols.indexOf('Age')!==-1?'Age':(cols[1]||cols[0]));
        $('#pivot-container').pivotUI(norm,{
            rows:[defRow],cols:[defCol],aggregatorName:aggName,vals:vals,
            renderers:$.extend($.pivotUtilities.renderers,$.pivotUtilities.d3_renderers,$.pivotUtilities.export_renderers),
            rendererName:'Table',localeStrings:{renderError:'Erreur de rendu',computeError:'Erreur calcul'}
        },true);
        showMeta('Chargé : '+data.length+' lignes — '+cols.join(', '));
        log('Aperçu :\n'+JSON.stringify(norm.slice(0,5),null,2));
    }catch(e){$('#pivot-container').html('<div class="dv-alert dv-alert-error"><i class="bi bi-exclamation-triangle"></i>'+e.message+'</div>');log('Erreur : '+e.stack);}
}

function populateSels(cols){
    var rs=document.getElementById('rowsSelect'),cs=document.getElementById('colsSelect');
    rs.innerHTML='';cs.innerHTML='';
    cols.forEach(function(c){
        var o1=document.createElement('option');o1.value=c;o1.textContent=c;rs.appendChild(o1);
        var o2=document.createElement('option');o2.value=c;o2.textContent=c;cs.appendChild(o2);
    });
}

function loadUrl(url){
    log('Fetch : '+url);showMeta('Chargement…');$('#pivot-container').empty();
    $.ajax({url:url,method:'GET',dataType:'json',timeout:10000})
     .done(function(d){renderPivot(d);})
     .fail(function(xhr,st,err){var m='Erreur HTTP : '+st+(err?' - '+err:'');
        if(xhr.responseText)m+='\n'+xhr.responseText.substring(0,400);
        log(m);$('#pivot-container').html('<div class="dv-alert dv-alert-error"><i class="bi bi-exclamation-triangle"></i>'+m+'</div>');showMeta('Erreur HTTP');});
}

var SAMPLE=[
    {Age:'4',Genre:'Femme',Medicament:'Paracétamol',Quantite:'10',DateAchat:'2025-07-01',District:'Antananarivo'},
    {Age:'8',Genre:'Homme',Medicament:'Ibuprofène',Quantite:'5',DateAchat:'2025-06-15',District:'Antsirabe'},
    {Age:'24',Genre:'Femme',Medicament:'Vitamine',Quantite:'2',DateAchat:'2025-05-20',District:'Toamasina'},
    {Age:'72',Genre:'Homme',Medicament:'Sirop',Quantite:'1',DateAchat:'2025-03-12',District:'Mahajanga'}
];

$(function(){
    $('#load').on('click',function(){var url=$('#source').val().trim();if(!url)return;loadUrl(url);});
    $('#agg').on('change',function(){$('#load').click();});
    $('#reset').on('click',function(){$('#pivot-container').html('<div style="color:var(--text-muted);text-align:center;padding:20px;"><i class="bi bi-table" style="font-size:2rem;opacity:0.3;"></i></div>');document.getElementById('debug').textContent='Status : réinitialisé';showMeta('Réinitialisé');$('#rowsSelect,#colsSelect').html('');});
    $('#exportCsv').on('click',function(){
        var tbl=$('#pivot-container table.pvtTable');if(!tbl.length){alert('Aucun tableau');return;}
        var csv=tbl.find('tr').toArray().map(function(tr){return $(tr).find('th,td').toArray().map(function(td){return '"'+$(td).text().replace(/"/g,'""')+'"';}).join(',');}).join('\n');
        var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
        var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='pivot_export.csv';a.style.display='none';document.body.appendChild(a);a.click();a.remove();
    });
    $('#loadSample').on('click',function(){renderPivot(SAMPLE);});
    $('#loadEmpty').on('click',function(){renderPivot([]);});
    try{$('#load').click();}catch(e){}
});
</script>

<?php include 'footer_shared.php'; ?>
