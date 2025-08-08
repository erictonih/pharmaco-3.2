<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau croisé dynamique</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            margin: 18px;
            background: #f4f6fb;
            color: #222;
        }
        h2 {
            color: #0d6efd;
            margin-bottom: 18px;
            letter-spacing: 0.5px;
        }
        #controls, #controls2 {
            border: 1px solid #d0d7de;
            background: #f8fafc;
        }
        #controls label, #controls2 label {
            font-weight: 600;
            color: #333;
        }
        #controls input[type=text], #controls select, #controls2 select {
            border: 1px solid #b6c2d1;
            background: #fff;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 1em;
            margin-right: 6px;
        }
        #controls input[type=text]:focus, #controls select:focus, #controls2 select:focus {
            outline: 2px solid #0d6efd;
            border-color: #0d6efd;
        }
        .btn {
            font-weight: 500;
            background: linear-gradient(90deg, #e3f2fd 80%, #cfe2ff 100%);
            border: 1px solid #90caf9;
            color: #0d6efd;
        }
        .btn:hover {
            background: #d1e7dd;
            border-color: #198754;
            color: #198754;
        }
        #pivot-container {
            border: 1px solid #d0d7de;
            background: #fff;
            box-shadow: 0 2px 8px #e3e3e3;
        }
        #meta {
            font-size: 1.05em;
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 8px;
        }
        #debug {
            font-size: 0.97em;
            color: #444;
            background: #fffbe6;
            border: 1px dashed #f7c873;
        }
        table.pvtTable th, table.pvtTable td {
            font-size: 1em;
            padding: 8px 10px;
        }
        table.pvtTable th {
            background: #e2eafc;
            color: #0d6efd;
            font-weight: bold;
        }
        table.pvtTable td {
            background: #f7fafd;
        }
        table.pvtTable tr:hover td {
            background: #e9ecef;
        }
        #controls .small, #controls2 .small {
            font-size: 0.98em;
            color: #555;
        }
        #controls > div, #controls2 > div {
            min-width: 180px;
        }
        @media (max-width: 700px) {
            body {
            margin: 4px;
            }
            h2 {
            font-size: 1.2em;
            }
            #controls, #controls2 {
            flex-direction: column;
            gap: 8px;
            padding: 6px 4px;
            }
            #pivot-container {
            padding: 2px;
            }
            #meta {
            font-size: 0.98em;
            }
            label, select, input[type=text] {
            font-size: 0.97em;
            }
        }
        #controls {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            background: #e9ecef;
            padding: 10px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 4px #ddd;
        }
        #output {
            margin-top: 14px;
        }
        .btn {
            padding: 7px 12px;
            border-radius: 6px;
            border: 1px solid #888;
            cursor: pointer;
            background: linear-gradient(90deg, #f5f5f5 80%, #e2e2e2 100%);
            transition: background 0.2s;
        }
        .btn:hover {
            background: #d1e7dd;
            border-color: #198754;
            color: #198754;
        }
        .small {
            font-size: 0.92em;
            color: #555;
        }
        #debug {
            margin-top: 12px;
            padding: 12px;
            border: 1px dashed #bbb;
            background: #fdf6e3;
            white-space: pre-wrap;
            max-height: 260px;
            overflow: auto;
            font-size: 0.95em;
            color: #444;
            border-radius: 6px;
        }
        label {
            display: inline-block;
            margin-right: 8px;
            font-weight: 500;
        }
        select, input[type=text] {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
            min-width: 80px;
            background: #fff;
        }
        #pivot-container {
            margin-top: 12px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 6px #eee;
            padding: 10px;
            min-height: 80px;
        }
        #meta {
            margin-top: 4px;
            margin-bottom: 2px;
            color: #0d6efd;
            font-weight: 500;
        }
        #controls2 {
            margin-top: 8px;
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            background: #f1f3f5;
            padding: 8px 10px;
            border-radius: 6px;
        }
        @media (max-width: 700px) {
            #controls, #controls2 {
                flex-direction: column;
                gap: 8px;
            }
            #pivot-container {
                padding: 4px;
            }
        }
        table.pvtTable {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
            font-size: 0.98em;
        }
        table.pvtTable th, table.pvtTable td {
            border: 1px solid #bbb;
            padding: 6px 8px;
            text-align: center;
        }
        table.pvtTable th {
            background: #e2eafc;
            font-weight: bold;
        }
        table.pvtTable tr:nth-child(even) td {
            background: #f7fafd;
        }
        table.pvtTable tr:hover td {
            background: #e9ecef;
        }
    </style>
</head>
<body>
  <h2>Tableau croisé dynamique</h2>

  <div id="controls">
    <div>
      <label for="source" >Source données (endpoint) :</label>
      <input id="source" type="text" value="data.php" style="width:240px">
      <button id="load" class="btn">Charger</button>
    </div>

    <div>
      <label for="agg">Agrégateur :</label>
      <select id="agg" class="small">
        <option value="Count">Count</option>
        <option value="Sum">Sum (Quantite)</option>
        <option value="Average">Average (Quantite)</option>
      </select>
    </div>

    <div>
      <label><input type="checkbox" id="computeTranche"> Calculer Tranche_Age</label>
      <label for="ageUnit">Unité Age :</label>
      <select id="ageUnit" class="small"><option value="months">mois</option><option value="years">années</option></select>
    </div>

    <div>
      <button id="exportCsv" class="btn">Exporter CSV</button>
      <button id="reset" class="btn">Réinitia = m.Idliser</button>
    </div>

    <div style="margin-left:auto">
      <!-- Test cases (ajoutés) -->
      <span class="small">Tests:</span>
      <button id="loadSample" class="btn small">Charger exemple valide</button>
      <button id="loadEmpty" class="btn small">Exemple vide</button>
      <button id="loadObj" class="btn small">Exemple objet unique</button>
    </div>
  </div>

  <div id="meta" class="small"></div>

  <div id="controls2" style="margin-top:6px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div>
      <label for="rowsSelect">Ligne par défaut :</label>
      <select id="rowsSelect"></select>
      <label for="colsSelect">Colonne par défaut :</label>
      <select id="colsSelect"></select>
    </div>
  </div>

  <div id="output">
    <div id="pivot-container"></div>
    <div id="debug" aria-live="polite">Status: prêt</div>
  </div>

  <!-- libs -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/d3_renderers.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/export_renderers.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <script>
  // --- Utilitaires ---
  function logDebug(txt){
      const now = new Date().toISOString();
      const el = document.getElementById('debug');
      el.textContent = now + " — " + txt + '\n\n' + el.textContent;
  }

  function showMeta(txt){ document.getElementById('meta').textContent = txt; }

  function tryParseNumber(v){
      if(v === null || v === undefined) return v;
      if(typeof v === 'number') return v;
      // remplacer virgule décimale
      if(typeof v === 'string'){
          const s = v.trim().replace(/\s+/g,'');
          // accepter les chiffres, virgule ou point
          const s2 = s.replace(',','.');
          if(s2.match(/^[-+]?[0-9]*\.?[0-9]+$/)){
              const n = Number(s2);
              return Number.isInteger(n) ? parseInt(n,10) : n;
          }
      }
      return v;
  }

  function normalizeRow(row){
      const r = {};
      for(const k in row){
          let v = row[k];
          if(v === null) v = '';
          r[k] = tryParseNumber(v);
      }
      return r;
  }

  function computeTrancheAgeFromValue(ageValue, unit){
      // unit: 'months' or 'years'
      const n = Number(ageValue);
      if(isNaN(n)) return 'Non renseigné';

      if(unit === 'months'){
          // Mappage non-ambigu: (2-6), (7-11), (12-59), (60-143), (144-299), (>=300)
          if(n >= 2 && n <= 6) return '2-6 mois';
          if(n >= 7 && n <= 11) return '6-11 mois';
          if(n >= 12 && n <= 59) return '1-5 ans';
          if(n >= 60 && n <= 143) return '5-12 ans';
          if(n >= 144 && n <= 299) return '12-25 ans';
          return '25 ans et plus';
      } else {
          // age en années (ex: 0.5 -> moins d'1 an)
          if(n < 1) return 'Moins de 1 an';
          if(n >= 1 && n <= 5) return '1-5 ans';
          if(n > 5 && n <= 12) return '5-12 ans';
          if(n > 12 && n <= 25) return '12-25 ans';
          return '25 ans et plus';
      }
  }

  // --- Chargement et rendu pivot ---
  function safePivotUI(dataArray, options){
      // Vérifier que pivot libs sont chargées
      if(!$.pivotUtilities){
          const msg = 'Erreur: PivotTable.js non chargé (vérifier CDN).';
          logDebug(msg);
          document.getElementById('pivot-container').innerHTML = '<div style="color:red">'+msg+'</div>';
          return;
      }

      // Détruire pivot précédent
      $('#pivot-container').empty();

      try{
          $('#pivot-container').pivotUI(dataArray, options, true);
      }catch(e){
          const errMsg = 'Erreur pendant pivotUI : ' + (e && e.message ? e.message : String(e));
          logDebug(errMsg + '\n' + (e && e.stack ? e.stack : 'no stack'));
          document.getElementById('pivot-container').innerHTML = '<div style="color:red">'+errMsg+'</div>';
      }
  }

  function populateColsSelect(cols){
      const rowsSelect = document.getElementById('rowsSelect');
      const colsSelect = document.getElementById('colsSelect');
      rowsSelect.innerHTML = '';
      colsSelect.innerHTML = '';
      cols.forEach(c => {
          const o1 = document.createElement('option'); o1.value = c; o1.textContent = c; rowsSelect.appendChild(o1);
          const o2 = document.createElement('option'); o2.value = c; o2.textContent = c; colsSelect.appendChild(o2);
      });
  }

  function loadAndRender(url){
      logDebug('Fetch: ' + url);
      showMeta('Chargement en cours...');
      $('#pivot-container').empty();

      $.ajax({
          url: url,
          method: 'GET',
          dataType: 'json',
          timeout: 10000
      }).done(function(data){
          try{
              // Si endpoint renvoie {error: '...'}
              if(data && data.error){
                  const msg = 'Endpoint error: ' + data.error;
                  logDebug(msg);
                  document.getElementById('pivot-container').innerHTML = '<div style="color:red">'+msg+'</div>';
                  showMeta('Erreur endpoint');
                  return;
              }

              // Si ce n'est pas un tableau, essayer de normaliser
              if(!Array.isArray(data)){
                  // cas: objet unique -> transformer en tableau
                  if(typeof data === 'object' && data !== null){
                      data = [data];
                      logDebug('La réponse JSON était un objet unique ; converti en tableau d\'une ligne.');
                  } else {
                      const msg = 'Réponse inattendue : attendu un tableau JSON.';
                      logDebug(msg + '\n' + JSON.stringify(data));
                      document.getElementById('pivot-container').innerHTML = '<div style="color:red">'+msg+'</div>';
                      showMeta('Réponse inattendue');
                      return;
                  }
              }

              logDebug('Nombre de lignes reçues: ' + data.length);

              // Normaliser les lignes
              const norm = data.map(normalizeRow);

              // Option: calculer Tranche_Age si demandé
              const computeTranche = document.getElementById('computeTranche').checked;
              const ageUnit = document.getElementById('ageUnit').value;
              if(computeTranche){
                  norm.forEach(r => {
                      if(r.Age !== undefined && r.Age !== null && r.Age !== ''){
                          r.Tranche_Age = computeTrancheAgeFromValue(r.Age, ageUnit);
                      } else {
                          r.Tranche_Age = 'Non renseigné';
                      }
                  });
              }

              if(norm.length === 0){
                  const msg = 'Aucune donnée (tableau vide). Chargez un exemple de test ou vérifiez l\'endpoint.';
                  logDebug(msg);
                  document.getElementById('pivot-container').innerHTML = '<div style="color:orange">'+msg+'</div>';
                  showMeta('Tableau vide');

                  // proposer de charger un exemple
                  return;
              }

              // Détecter colonnes
              const cols = Object.keys(norm[0]);
              populateColsSelect(cols);

              // Choisir agrégateur / vals
              const aggChoice = document.getElementById('agg').value;
              let aggregatorName = 'Count';
              let vals = [];
              if(aggChoice === 'Sum' || aggChoice === 'Average'){
                  aggregatorName = (aggChoice === 'Sum')? 'Sum' : 'Average';
                  if(cols.indexOf('Quantite') !== -1){ vals = ['Quantite']; }
                  else if(cols.indexOf('Quantity') !== -1){ vals = ['Quantity']; }
              }

              // Options pivot
              const defaultRow = document.getElementById('rowsSelect').value || (cols.indexOf('Genre') !== -1 ? 'Genre' : cols[0]);
              const defaultCol = document.getElementById('colsSelect').value || (cols.indexOf('Tranche_Age') !== -1 ? 'Tranche_Age' : (cols.indexOf('Age') !== -1 ? 'Age' : cols[1] || cols[0]));

              const options = {
                  rows: [defaultRow],
                  cols: [defaultCol],
                  aggregatorName: aggregatorName,
                  vals: vals,
                  renderers: $.extend($.pivotUtilities.renderers, $.pivotUtilities.d3_renderers, $.pivotUtilities.export_renderers),
                  rendererName: 'Table',
                  onRefresh: function(config){ window.lastConfig = config; },
                  localeStrings: {renderError: 'Erreur de rendu', computeError: 'Erreur calcul'}
              };

              // Rendre pivot
              safePivotUI(norm, options);
              showMeta('Chargé: ' + data.length + ' lignes — colonnes détectées: ' + cols.join(', '));

              // afficher aperçu JSON court
              const preview = JSON.stringify(norm.slice(0,10), null, 2);
              logDebug('Aperçu (max10):\n' + preview);

          }catch(e){
              logDebug('Erreur interne après fetch: ' + (e.message || e));
              document.getElementById('pivot-container').innerHTML = '<div style="color:red">Erreur interne: ' + (e.message || e) + '</div>';
          }
      }).fail(function(xhr, status, err){
          let msg = 'Erreur HTTP: ' + status + (err ? (" - " + err) : '');
          if(xhr && xhr.responseText){
              msg += '\nRéponse serveur: ' + xhr.responseText.substring(0,500);
          }
          logDebug(msg);
          document.getElementById('pivot-container').innerHTML = '<div style="color:red">'+msg+'</div>';
          showMeta('Erreur HTTP');
      });
  }

  // Variante: charger à partir d'un objet JS (test)
  function loadAndRenderFromData(data){
      try{
          if(data && data.error){
              document.getElementById('pivot-container').innerHTML = '<div style="color:red">Endpoint error: '+data.error+'</div>';
              return;
          }
          if(!Array.isArray(data)) data = [data];
          const norm = data.map(normalizeRow);
          // calcul tranche si demandé
          const computeTranche = document.getElementById('computeTranche').checked;
          const ageUnit = document.getElementById('ageUnit').value;
          if(computeTranche){ norm.forEach(r=> r.Tranche_Age = computeTrancheAgeFromValue(r.Age, ageUnit)); }
          if(norm.length === 0){
              document.getElementById('pivot-container').innerHTML = '<div style="color:orange">Jeu de données vide (test)</div>';
              return;
          }
          const cols = Object.keys(norm[0]);
          populateColsSelect(cols);
          const aggChoice = document.getElementById('agg').value;
          let aggregatorName = 'Count'; let vals = [];
          if(aggChoice === 'Sum' || aggChoice === 'Average'){ aggregatorName = (aggChoice === 'Sum')? 'Sum' : 'Average'; if(cols.indexOf('Quantite') !== -1) vals = ['Quantite']; }
          const defaultRow = document.getElementById('rowsSelect').value || (cols.indexOf('Genre') !== -1 ? 'Genre' : cols[0]);
          const defaultCol = document.getElementById('colsSelect').value || (cols.indexOf('Tranche_Age') !== -1 ? 'Tranche_Age' : (cols.indexOf('Age') !== -1 ? 'Age' : cols[1] || cols[0]));
          const options = { rows:[defaultRow], cols:[defaultCol], aggregatorName: aggregatorName, vals: vals, renderers: $.extend($.pivotUtilities.renderers, $.pivotUtilities.d3_renderers, $.pivotUtilities.export_renderers), rendererName:'Table', onRefresh:function(c){window.lastConfig=c;} };
          safePi>Source donvotUI(norm, options);
          showMeta('Chargé (test): ' + norm.length + ' lignes — colonnes: ' + cols.join(', '));
          logDebug('Test load: ' + JSON.stringify(norm.slice(0,10), null, 2));
      }catch(e){ document.getElementById('pivot-container').innerHTML = '<div style="color:red">Erreur interne: ' + (e.message || e) + '</div>'; logDebug('Erreur test load: ' + (e.stack || e)); }
  }

  // Export CSV
  function exportPivotCSV(){
      try{
          const table = document.querySelector('#pivot-container table.pvtTable');
          if(!table){ alert('Aucun tableau présent pour l\'export.'); return; }
          const rows = Array.from(table.querySelectorAll('tr'));
          const csv = rows.map(tr => Array.from(tr.querySelectorAll('th,td')).map(td=> '"'+ td.innerText.replace(/"/g,'""') +'"').join(',')).join('\n');
          const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a'); a.href=url; a.download='pivot_export.csv'; a.style.display='none'; document.body.appendChild(a); a.click(); URL.revokeObjectURL(url); a.remove();
      }catch(e){ alert('Erreur export: ' + (e.message || e)); logDebug('Erreur export: ' + (e.stack || e)); }
  }

  // --- Samples (test cases) ---
  const SAMPLE_VALID = [
      {Age: '4', Genre: 'Femme', Medicament: 'Paracétamol', Quantite: '10', DateAchat: '2025-07-01', Commune: 'Antananarivo'},
      {Age: '8', Genre: 'Homme', Medicament: 'Ibuprofène', Quantite: '5', DateAchat: '2025-06-15', Commune: 'Antsirabe'},
      {Age: '24', Genre: 'Femme', Medicament: 'Vitamine', Quantite: '2', DateAchat: '2025-05-20', Commune: 'Toamasina'},
      {Age: '72', Genre: 'Homme', Medicament: 'Sirop', Quantite: '1', DateAchat: '2025-03-12', Commune: 'Mahajanga'},
      {Age: '200', Genre: 'Femme', Medicament: 'Antibiotique', Quantite: '3', DateAchat: '2025-04-03', Commune: 'Fianarantsoa'}
  ];
  const SAMPLE_EMPTY = [];
  const SAMPLE_OBJ = {Age: '12', Genre: 'Homme', Medicament: 'Exemple', Quantite: '1', DateAchat: '2025-01-01', Commune: 'ExempleVille'};

  // --- Events ---
  $(function(){
      $('#load').on('click', function(){ const url = $('#source').val().trim(); if(!url){ alert('Saisir l\'endpoint (ex: data.php)'); return; } loadAndRender(url); });
      $('#agg').on('change', function(){ $('#load').click(); });
      $('#reset').on('click', function(){ $('#pivot-container').innerHTML = ''; document.getElementById('debug').textContent = 'Status: réinitialisé'; showMeta('Réinitialisé'); $('#rowsSelect').innerHTML=''; $('#colsSelect').innerHTML=''; });
      $('#exportCsv').on('click', exportPivotCSV);

      $('#loadSample').on('click', function(){ loadAndRenderFromData(SAMPLE_VALID); });
      $('#loadEmpty').on('click', function(){ loadAndRenderFromData(SAMPLE_EMPTY); });
      $('#loadObj').on('click', function(){ loadAndRenderFromData(SAMPLE_OBJ); });

      // auto load once (silent) -- only if data.php exists; we call but failures are shown
      try{ $('#load').click(); }catch(e){/* ignore */}
  });
  </script>
</body>
</html>
