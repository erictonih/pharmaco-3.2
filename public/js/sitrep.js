/* SitRep mpox : aperçu A4 (HTML) -> PDF (html2canvas + jsPDF) et Word natif (docx.js : textes et tableaux modifiables, graphiques/cartes en images).
   Données : window.SITREP (calculées côté serveur par SitRepService). Aucune donnée n'est envoyée à un tiers. */
(function () {
  'use strict';
  const R = window.SITREP;
  if (!R || R.empty) return;

  const MAPCOL = ['#fde0d2', '#fcb79a', '#f7886a', '#e0503d', '#a50f15'];
  const MAPCLS = [[1, 5, '1 - 5'], [6, 20, '6 - 20'], [21, 50, '21 - 50'], [51, 100, '51 - 100'], [101, Infinity, '> 100']];
  const nf = n => n == null ? 'ND' : Number(n).toLocaleString('fr-FR').replace(/[  ]/g, ' ');
  const dec = (n, d) => n == null ? 'ND' : Number(n).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }).replace(/[  ]/g, ' ');
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const key = s => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '');
  const fdate = iso => iso.slice(8, 10) + '-' + iso.slice(5, 7) + '-' + iso.slice(0, 4);
  const FILE = 'SitRep_mpox_N' + R.num + '_' + fdate(R.to);
  const CUM = R.cum_label;
  const cumN = R.cumul.notified;

  // ── Textes par défaut (actions qualitatives : repris du SitRep N°46, à mettre à jour) ──
  const DEF = {
    a_coord: 'Réunion régulière (bihebdomadaire) dirigée par l’Incident Manager et le Directeur de la Veille Sanitaire, de la Surveillance Épidémiologique et Riposte pour améliorer la stratégie de lutte\nRéunion de coordination hebdomadaire avec les responsables au niveau des régions',
    a_surv: 'Préparation de la finalisation du plan stratégique national de la surveillance\nLarge diffusion de l’info veille mpox et Ebola\nTraitement et vérification des signaux via 910 et autres sources : aucun signalement',
    a_pe: 'Contrôle sanitaire au départ et à l’arrivée des passagers au niveau des points d’entrée : aucun signalement',
    a_lab: 'Suivi des indicateurs',
    p_surv: 'Suivi des contacts à renforcer\nHarmonisation des données de laboratoire, PEC, surveillance à renforcer (analyses basées sur des listes linéaires)',
    p_lab: 'Acquisition de cartouches GeneXpert et de kits de prélèvement pour la pérennisation des activités de diagnostic de la mpox',
    p_log: 'Acheminement de cartouches GeneXpert depuis les CHRD vers le COUSP Central\nRedéploiement de surstocks de cartouches GeneXpert venant des laboratoires de CHU/CHRR/CHRD au COUSP Central\nEnvoi des intrants de santé des CTI',
  };
  const MAN = [['pcr', 'Testés / PCR'], ['gx', 'Testés / GeneXpert'], ['vacc', 'Vaccinés (dose 1)'], ['compl', 'Complétude de rapportage']];
  const COLS = R.cols.map(c => c.label).concat(['Cumul']);
  const STORE = 'sitrep_manual_v1';
  let S = { man: {}, txt: {} };
  try { const v = JSON.parse(localStorage.getItem(STORE) || 'null'); if (v && v.man && v.txt) S = v; } catch (e) { /* stockage indisponible : valeurs en mémoire seulement */ }
  const save = () => { try { localStorage.setItem(STORE, JSON.stringify(S)); } catch (e) { /* ignoré */ } };
  const txt = k => (S.txt[k] != null && S.txt[k] !== '' ? S.txt[k] : (k === 'p1' ? R.narrative.p1 : k === 'p2' ? R.narrative.p2 : DEF[k] || ''));
  const man = (k, i) => { const v = ((S.man[k] || [])[i] || '').trim(); return v === '' ? 'ND' : v; };
  const lines = s => String(s).split('\n').map(x => x.trim()).filter(Boolean);

  // ── Figures (canvas -> PNG) ──────────────────────────────────────────────
  let GEO = null;
  const classOf = n => { for (let i = 0; i < MAPCLS.length; i++) if (n >= MAPCLS[i][0] && n <= MAPCLS[i][1]) return i; return -1; };
  function drawMap(cv, counts) {
    const w = cv.width, h = cv.height, g = cv.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, w, h);
    if (!GEO) { g.fillStyle = '#6b7280'; g.font = '16px Arial'; g.fillText('Carte indisponible', 20, 30); return; }
    let x0 = 1e9, x1 = -1e9, y0 = 1e9, y1 = -1e9; const k0 = Math.cos(19 * Math.PI / 180);
    const each = (geom, fn) => { (geom.type === 'Polygon' ? [geom.coordinates] : geom.coordinates).forEach(p => p.forEach(fn)); };
    GEO.features.forEach(f => each(f.geometry, ring => ring.forEach(([lo, la]) => { const x = lo * k0, y = -la; x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); })));
    const pad = 10, sc = Math.min((w - 2 * pad) / (x1 - x0), (h - 2 * pad - 96) / (y1 - y0));
    const ox = (w - (x1 - x0) * sc) / 2, oy = pad;
    GEO.features.forEach(f => {
      const n = (counts[key(f.properties.NAME_3)] || {}).confirmed || 0, i = classOf(n);
      g.fillStyle = i < 0 ? '#ffffff' : MAPCOL[i]; g.strokeStyle = '#555'; g.lineWidth = Math.max(0.4, w / 900);
      each(f.geometry, ring => { g.beginPath(); ring.forEach(([lo, la], j) => { const x = ox + (lo * k0 - x0) * sc, y = oy + (-la - y0) * sc; j ? g.lineTo(x, y) : g.moveTo(x, y); }); g.closePath(); g.fill(); g.stroke(); });
    });
    const fs = Math.max(11, Math.round(w / 34)), ly = h - 84;
    g.fillStyle = '#111'; g.font = 'bold ' + fs + 'px Arial'; g.fillText('Légende — cas confirmés', 8, ly); g.font = fs + 'px Arial';
    MAPCLS.forEach((c, i) => { const cx = 8 + (i % 3) * (w / 3.1), cy = ly + 10 + Math.floor(i / 3) * (fs + 6); g.fillStyle = MAPCOL[i]; g.fillRect(cx, cy, fs, fs * 0.8); g.strokeStyle = '#555'; g.strokeRect(cx, cy, fs, fs * 0.8); g.fillStyle = '#111'; g.fillText(c[2], cx + fs + 5, cy + fs * 0.78); });
  }
  const mapPng = counts => { const cv = document.createElement('canvas'); cv.width = 520; cv.height = 760; drawMap(cv, counts); return cv.toDataURL('image/png'); };
  const valueLabels = { id: 'vl', afterDatasetsDraw(ch, a, o) {
    if (!o.on) return; const g = ch.ctx; g.save(); g.font = '12px Arial'; g.fillStyle = '#111'; g.textAlign = 'center';
    ch.data.datasets.forEach((d, i) => ch.getDatasetMeta(i).data.forEach((b, j) => { if (d.data[j] > 0) g.fillText(d.data[j], b.x, b.y - 4); })); g.restore(); } };
  function chartPng(w, h, cfg) {
    const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
    cfg.options = Object.assign({ animation: false, responsive: false, devicePixelRatio: 1 }, cfg.options); cfg.plugins = [valueLabels];
    const c = new Chart(cv.getContext('2d'), cfg); const url = cv.toDataURL('image/png'); c.destroy(); return url;
  }
  function circlesPng() {
    const C = R.circles, items = [[C.notified, 'Nouveaux cas notifiés'], [C.confirmed, 'Confirmés'], [C.probable, 'Cas probables'], [C.suspect, 'Cas suspects'], [C.negative, 'Négatifs'], [C.deaths, 'Décès']];
    const cv = document.createElement('canvas'); cv.width = 200; cv.height = 6 * 150; const g = cv.getContext('2d'); g.textAlign = 'center';
    items.forEach(([n, l], i) => {
      const cy = i * 150 + 65; g.fillStyle = '#3f9e34'; g.beginPath(); g.arc(100, cy, 56, 0, 6.2832); g.fill();
      g.fillStyle = '#fff'; g.font = 'bold 44px Arial'; g.fillText(String(n), 100, cy + 15);
      g.fillStyle = '#1b1b1b'; g.font = 'bold 15px Arial'; g.fillText(l, 100, cy + 82);
    });
    return cv.toDataURL('image/png');
  }
  let FIG = {};
  function makeFigures() {
    const W = R.weekly, A = R.age_sex;
    FIG.c1 = chartPng(1000, 420, { type: 'bar', data: { labels: W.weeks, datasets: [
      { label: 'Notification', data: W.notified, backgroundColor: '#a6a6a6' }, { label: 'Confirmés', data: W.confirmed, backgroundColor: '#1f4fd8' }] },
      options: { plugins: { vl: { on: false }, legend: { position: 'top' } }, scales: { x: { ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: 10 } } }, y: { beginAtZero: true } } } });
    FIG.c5 = chartPng(900, 460, { type: 'bar', data: { labels: Object.keys(A.groups), datasets: [
      { label: 'Hommes', data: Object.values(A.groups).map(v => v.M), backgroundColor: '#4472c4' }, { label: 'Femmes', data: Object.values(A.groups).map(v => v.F), backgroundColor: '#ed7d31' }] },
      options: { plugins: { vl: { on: true }, legend: { position: 'top' } }, scales: { y: { beginAtZero: true, grace: '10%' } } } });
    FIG.m2 = mapPng(R.map_cur); FIG.m3 = mapPng(R.map_prev); FIG.m4 = mapPng(R.map_cum); FIG.circ = circlesPng();
  }

  // ── Contenu commun (aperçu et Word) ───────────────────────────────────────
  const IND = [  // [libellé, fonction(col, index) -> texte]
    ['Cas notifiés', c => nf(c.notified)], ['Cas prélevés', c => nf(c.sampled)],
    ['Testés / PCR', (c, i) => man('pcr', i)], ['Testés / GeneXpert', (c, i) => man('gx', i)], ['Cas testés (résultat disponible)', c => nf(c.tested)],
    ['Cas confirmés', c => nf(c.confirmed)], ['Cas suspects', c => nf(c.suspect)], ['Cas probables', c => nf(c.probable)],
    ['Taux de positivité', c => c.positivity == null ? 'ND' : dec(c.positivity, 2) + ' %'],
    ['Vaccinés (dose 1)', (c, i) => man('vacc', i)], ['Prise en charge hospitalière', c => nf(c.hospitalised)], ['Guéris', c => nf(c.recovered)],
    ['Décès', c => nf(c.deaths)], ['Complétude de rapportage', (c, i) => man('compl', i)],
  ];
  const allCols = () => R.cols.concat([R.cumul]);
  const ID = [['Intitulé de l’incident', 'Épidémie de la mpox à Madagascar'], ['Périodicité du SitRep', R.periodicity], ['Période de collecte', R.period_text], ['Numéro du SitRep', String(R.num)]];
  const T = {
    fig1: 'Fig.1 : Évolution hebdomadaire des cas confirmés et notifiés de mpox, ' + CUM + ', Madagascar, n = ' + nf(cumN),
    fig2: 'Fig.2 : Districts avec cas confirmés, période courante', fig3: 'Fig.3 : Districts avec cas confirmés, période précédente', fig4: 'Fig.4 : Cas confirmés cumulés par district, ' + CUM,
    fig5: 'Fig.5 : Répartition des cas confirmés par tranche d’âge et par sexe, ' + CUM + ', n = ' + nf(R.age_sex.n),
    sr: 'Sex-ratio H/F (' + nf(R.age_sex.male) + '/' + nf(R.age_sex.female) + ') = ' + (R.age_sex.sex_ratio == null ? 'ND' : dec(R.age_sex.sex_ratio, 2)),
    med: 'Âge médian = ' + (R.age_sex.median_age == null ? 'ND' : nf(R.age_sex.median_age) + ' ans' + (R.age_sex.min_label ? ' [' + R.age_sex.min_label + ' – ' + R.age_sex.max_label + ']' : '')),
    d2: 'Districts touchés : ' + R.districts_cur, d3: 'Districts touchés : ' + R.districts_prev, d4: 'Districts touchés : ' + R.districts_cum,
  };

  // ── Aperçu HTML (pages A4) ────────────────────────────────────────────────
  const band = (t, c) => '<div class="sr-band" style="' + (c ? 'background:' + c : '') + '">' + esc(t) + '</div>';
  const bullets = k => '<ul style="margin:3px 0 8px 18px;padding:0">' + lines(txt(k)).map(l => '<li>' + esc(l) + '</li>').join('') + '</ul>';
  const fig = (src, cap, w, extra) => '<div style="text-align:center"><img src="' + src + '" style="width:' + w + 'px;margin:0 auto">' + (extra ? '<div style="font-weight:700">' + esc(extra) + '</div>' : '') + '<div style="font-size:10.5px;font-style:italic;margin:3px 0 8px">' + esc(cap) + '</div></div>';
  function pages() {
    const cols = allCols();
    const head = '<tr><th class="sr-h" style="text-align:left">Indicateurs</th>' + COLS.map((c, i) => '<th class="sr-h">' + esc(i === 3 ? CUM : c) + '</th>').join('') + '</tr>';
    const rows = IND.map(([l, fn]) => '<tr><td style="font-weight:700;background:#dff0f7">' + esc(l) + '</td>' + cols.map((c, i) => '<td style="text-align:center;background:#fff">' + esc(fn(c, i)) + '</td>').join('') + '</tr>').join('');
    const flow1 = '<div class="sr-in"><div style="display:flex;align-items:center;justify-content:space-between;height:70px">'
      + '<img src="sary/rapport-logo.jpg" style="height:66px"><img src="sary/sitrep-republique.png" style="height:56px"><img src="sary/sitrep-oms.png" style="height:56px"></div>'
      + '<div style="background:#00a3c4;color:#fff;text-align:center;padding:6px;margin:6px 0 10px"><div style="font-size:22px;font-weight:700;letter-spacing:2px">MADAGASCAR</div><div style="font-size:17px;font-weight:700">RAPPORT DE SITUATION SUR LA MPOX</div></div>'
      + '<div style="display:flex;gap:10px;align-items:flex-start"><div style="flex:1">'
      + '<table style="width:100%">' + ID.map(([a, b]) => '<tr><td style="font-weight:700;background:#dff0f7;width:36%;padding:6px">' + esc(a) + '</td><td style="background:#fff;padding:6px">' + esc(b) + '</td></tr>').join('') + '</table>'
      + '<table style="width:100%;margin-top:8px">' + head + rows + '</table></div>'
      + '<img src="' + FIG.circ + '" style="width:112px;flex:none"></div>'
      + '<div style="margin-top:10px">' + band('POINTS SAILLANTS') + '<div style="background:#fff;padding:7px 9px;text-align:justify"><p style="margin:0 0 6px">' + esc(txt('p1')) + '</p><p style="margin:0">' + esc(txt('p2')) + '</p></div></div>'
      + '<div style="font-size:9.5px;margin-top:5px">ND : non disponible dans la liste linéaire. Taux de positivité = cas confirmés / cas testés (résultat disponible). Pastilles et colonnes de période : cas notifiés pendant la période.</div></div>';
    const p2 = '<div class="sr-in">' + band('SITUATION ÉPIDÉMIOLOGIQUE') + '<div style="height:8px"></div>' + fig(FIG.c1, T.fig1, 700)
      + '<div style="display:flex;gap:14px;justify-content:center">' + fig(FIG.m2, T.fig2, 340, T.d2) + fig(FIG.m3, T.fig3, 340, T.d3) + '</div></div>';
    const p3 = '<div class="sr-in"><div style="display:flex;gap:14px"><div style="flex:none">' + fig(FIG.m4, T.fig4, 330, T.d4) + '</div><div style="flex:1">'
      + '<img src="' + FIG.c5 + '" style="width:100%"><div style="font-size:10.5px;font-style:italic;margin:3px 0 10px;text-align:center">' + esc(T.fig5) + '</div>'
      + '<div style="font-weight:700;font-size:13px">' + esc(T.sr) + '</div><div style="font-weight:700;font-size:13px;margin-top:4px">' + esc(T.med) + '</div></div></div></div>';
    const p4 = '<div class="sr-in">' + band('ACTIONS DE SANTÉ PUBLIQUE / INTERVENTIONS DE RÉPONSE') + '<div style="background:#fff;padding:6px 10px">'
      + '<b>Coordination (planification, administration et finance)</b>' + bullets('a_coord') + '<b>Surveillance</b>' + bullets('a_surv') + '<b>Points d’entrée</b>' + bullets('a_pe') + '<b>Laboratoire</b>' + bullets('a_lab') + '</div>'
      + '<div style="height:10px"></div>' + band('ACTIONS PRIORITAIRES') + '<div style="background:#fff;padding:6px 10px"><b>Surveillance</b>' + bullets('p_surv') + '<b>Laboratoire</b>' + bullets('p_lab') + '<b>Logistique</b>' + bullets('p_log') + '</div>'
      + '<img src="sary/sitrep-partenaires.png" style="width:100%;margin-top:14px"></div>';
    return [flow1, p2, p3, p4];
  }
  const doc = document.getElementById('srDoc');
  function preview() {
    doc.innerHTML = pages().map(h => '<div class="sr-page">' + h + '</div>').join('');
    const over = [];
    doc.querySelectorAll('.sr-page').forEach((p, i) => { const inn = p.firstElementChild; inn.style.cssText = 'position:absolute;left:0;right:0;top:0;padding:30px 38px'; if (inn.offsetHeight > p.clientHeight + 2) { p.classList.add('over'); over.push(i + 1); } });
    setMsg(over.length ? 'Attention : le contenu dépasse la page ' + over.join(', ') + ' (raccourcissez les textes).' : '');
  }

  // ── Saisies manuelles ──────────────────────────────────────────────────────
  const manBox = document.getElementById('srMan');
  manBox.innerHTML = '<span></span>' + COLS.map(c => '<b>' + esc(c) + '</b>').join('') + MAN.map(([k, l]) => '<span>' + esc(l) + '</span>' + COLS.map((c, i) => '<input class="dv-input" data-mk="' + k + '" data-mi="' + i + '" value="' + esc((S.man[k] || [])[i] || '') + '" placeholder="ND" maxlength="12" aria-label="' + esc(l + ' ' + c) + '">').join('')).join('');
  manBox.addEventListener('input', e => { const t = e.target; if (!t.dataset.mk) return; (S.man[t.dataset.mk] = S.man[t.dataset.mk] || [])[+t.dataset.mi] = t.value; save(); preview(); });
  document.querySelectorAll('textarea[data-k]').forEach(t => {
    t.value = txt(t.dataset.k);
    t.addEventListener('input', () => { S.txt[t.dataset.k] = t.value; if (t.dataset.k === 'p1' || t.dataset.k === 'p2') { /* paragraphes générés : non conservés d'une période à l'autre */ } else save(); preview(); });
  });

  // ── Export PDF ─────────────────────────────────────────────────────────────
  const msg = document.getElementById('srMsg'), setMsg = t => { msg.textContent = t || ''; };
  const busy = on => document.querySelectorAll('[data-export]').forEach(b => b.disabled = on);
  async function exportPdf() {
    const { jsPDF } = window.jspdf, pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait', compress: true });
    const ps = doc.querySelectorAll('.sr-page');
    for (let i = 0; i < ps.length; i++) {
      setMsg('Génération du PDF… page ' + (i + 1) + '/' + ps.length);
      const cv = await html2canvas(ps[i], { scale: 2, useCORS: true, backgroundColor: '#eaf3fa', logging: false });
      if (i) pdf.addPage('a4', 'portrait');
      pdf.addImage(cv.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, 210, 297);
    }
    pdf.save(FILE + '.pdf');
  }

  // ── Export Word ────────────────────────────────────────────────────────────
  const D = window.docx;
  const bytes = async src => { if (src.startsWith('data:')) { const b = atob(src.split(',')[1]), u = new Uint8Array(b.length); for (let i = 0; i < b.length; i++) u[i] = b.charCodeAt(i); return u; } return new Uint8Array(await (await fetch(src)).arrayBuffer()); };
  const dims = src => new Promise(r => { const i = new Image(); i.onload = () => r([i.naturalWidth, i.naturalHeight]); i.onerror = () => r([1, 1]); i.src = src; });
  async function pic(src, w, type) { const [iw, ih] = await dims(src); return new D.ImageRun({ type: type || 'png', data: await bytes(src), transformation: { width: w, height: Math.round(w * ih / iw) } }); }
  const NB = { style: D.BorderStyle.NONE, size: 0, color: 'FFFFFF' }, NOB = { top: NB, bottom: NB, left: NB, right: NB };
  const LB = { style: D.BorderStyle.SINGLE, size: 4, color: '7A8A99' }, ALLB = { top: LB, bottom: LB, left: LB, right: LB };
  const run = (t, o) => new D.TextRun(Object.assign({ text: t, font: 'Arial', size: 20 }, o));
  const par = (t, o, po) => new D.Paragraph(Object.assign({ children: [run(t, o)], spacing: { after: 80 } }, po));
  const cell = (children, w, o) => new D.TableCell(Object.assign({ children: Array.isArray(children) ? children : [children], width: { size: w, type: D.WidthType.DXA }, borders: ALLB, margins: { top: 40, bottom: 40, left: 80, right: 80 }, verticalAlign: D.VerticalAlign.CENTER }, o));
  const fill = f => ({ shading: { type: D.ShadingType.CLEAR, fill: f, color: 'auto' } });
  const bandP = (t, f) => new D.Paragraph({ children: [run(t, { bold: true, color: 'FFFFFF', size: 22 })], shading: { type: D.ShadingType.CLEAR, fill: f || '4DA63D', color: 'auto' }, spacing: { before: 120, after: 80 }, indent: { left: 60 } });
  const bulletsW = k => lines(txt(k)).map(l => new D.Paragraph({ children: [run(l)], numbering: { reference: 'bul', level: 0 }, spacing: { after: 40 } }));
  const cap = t => new D.Paragraph({ children: [run(t, { italics: true, size: 18 })], alignment: D.AlignmentType.CENTER, spacing: { after: 140 } });
  const centered = c => new D.Paragraph({ children: [c], alignment: D.AlignmentType.CENTER });
  async function exportDocx() {
    setMsg('Génération du Word…');
    const TW = 9638, cols = allCols();
    const logos = new D.Table({ width: { size: TW, type: D.WidthType.DXA }, columnWidths: [2400, 4838, 2400], borders: { ...NOB, insideHorizontal: NB, insideVertical: NB }, rows: [new D.TableRow({ children: [
      cell(new D.Paragraph({ children: [await pic('sary/rapport-logo.jpg', 62, 'jpg')] }), 2400, { borders: NOB }),
      cell(new D.Paragraph({ alignment: D.AlignmentType.CENTER, children: [await pic('sary/sitrep-republique.png', 230)] }), 4838, { borders: NOB }),
      cell(new D.Paragraph({ alignment: D.AlignmentType.RIGHT, children: [await pic('sary/sitrep-oms.png', 130)] }), 2400, { borders: NOB })] })] });
    const title = new D.Table({ width: { size: TW, type: D.WidthType.DXA }, columnWidths: [TW], rows: [new D.TableRow({ children: [cell([
      new D.Paragraph({ alignment: D.AlignmentType.CENTER, children: [run('MADAGASCAR', { bold: true, color: 'FFFFFF', size: 40 })] }),
      new D.Paragraph({ alignment: D.AlignmentType.CENTER, children: [run('RAPPORT DE SITUATION SUR LA MPOX', { bold: true, color: 'FFFFFF', size: 32 })] })], TW, { ...fill('00A3C4'), borders: NOB })] })] });
    const idT = new D.Table({ width: { size: 7300, type: D.WidthType.DXA }, columnWidths: [2600, 4700], rows: ID.map(([a, b]) => new D.TableRow({ children: [cell(par(a, { bold: true }, { spacing: { after: 0 } }), 2600, fill('DFF0F7')), cell(par(b, {}, { spacing: { after: 0 } }), 4700, fill('FFFFFF'))] })) });
    const cw = [2300, 1100, 1100, 1100, 1700];                       // somme 7300
    const indT = new D.Table({ width: { size: 7300, type: D.WidthType.DXA }, columnWidths: cw, rows: [
      new D.TableRow({ tableHeader: true, children: ['Indicateurs'].concat(COLS.map((c, i) => i === 3 ? CUM : c)).map((t, i) => cell(par(t, { bold: true, color: 'FFFFFF', size: 17 }, { alignment: i ? D.AlignmentType.CENTER : D.AlignmentType.LEFT, spacing: { after: 0 } }), cw[i], fill('00A3C4'))) }),
      ...IND.map(([l, fn]) => new D.TableRow({ children: [cell(par(l, { bold: true, size: 17 }, { spacing: { after: 0 } }), cw[0], fill('DFF0F7'))].concat(cols.map((c, i) => cell(par(fn(c, i), { size: 17 }, { alignment: D.AlignmentType.CENTER, spacing: { after: 0 } }), cw[i + 1], fill('FFFFFF')))) }))] });
    const left = new D.TableCell({ children: [idT, new D.Paragraph({ spacing: { after: 100 } }), indT], width: { size: 7500, type: D.WidthType.DXA }, borders: NOB });
    const right = new D.TableCell({ children: [new D.Paragraph({ alignment: D.AlignmentType.CENTER, children: [await pic(FIG.circ, 112)] })], width: { size: 2138, type: D.WidthType.DXA }, borders: NOB });
    const layout = new D.Table({ width: { size: TW, type: D.WidthType.DXA }, columnWidths: [7500, 2138], borders: { ...NOB, insideHorizontal: NB, insideVertical: NB }, rows: [new D.TableRow({ children: [left, right] })] });
    const sp = () => new D.Paragraph({ spacing: { after: 80 } });
    const pageBreak = () => new D.Paragraph({ children: [new D.PageBreak()] });
    const two = async (a, b) => new D.Table({ width: { size: TW, type: D.WidthType.DXA }, columnWidths: [TW / 2, TW / 2], borders: { ...NOB, insideHorizontal: NB, insideVertical: NB }, rows: [new D.TableRow({ children: await Promise.all([a, b].map(async ([src, c, d]) => new D.TableCell({ borders: NOB, width: { size: TW / 2, type: D.WidthType.DXA }, children: [centered(await pic(src, 250)), new D.Paragraph({ alignment: D.AlignmentType.CENTER, children: [run(d, { bold: true })] }), cap(c)] }))) })] });
    const kids = [logos, sp(), title, sp(), layout, bandP('POINTS SAILLANTS'), par(txt('p1'), {}, { alignment: D.AlignmentType.JUSTIFIED }), par(txt('p2'), {}, { alignment: D.AlignmentType.JUSTIFIED }),
      par('ND : non disponible dans la liste linéaire. Taux de positivité = cas confirmés / cas testés (résultat disponible).', { size: 16 }),
      pageBreak(), bandP('SITUATION ÉPIDÉMIOLOGIQUE'), centered(await pic(FIG.c1, 600)), cap(T.fig1), await two([FIG.m2, T.fig2, T.d2], [FIG.m3, T.fig3, T.d3]),
      pageBreak(), centered(await pic(FIG.m4, 280)), par(T.d4, { bold: true }, { alignment: D.AlignmentType.CENTER }), cap(T.fig4),
      centered(await pic(FIG.c5, 520)), cap(T.fig5), par(T.sr, { bold: true, size: 24 }), par(T.med, { bold: true, size: 24 }),
      pageBreak(), bandP('ACTIONS DE SANTÉ PUBLIQUE / INTERVENTIONS DE RÉPONSE'),
      par('Coordination (planification, administration et finance)', { bold: true }), ...bulletsW('a_coord'), par('Surveillance', { bold: true }), ...bulletsW('a_surv'),
      par('Points d’entrée', { bold: true }), ...bulletsW('a_pe'), par('Laboratoire', { bold: true }), ...bulletsW('a_lab'),
      bandP('ACTIONS PRIORITAIRES'), par('Surveillance', { bold: true }), ...bulletsW('p_surv'), par('Laboratoire', { bold: true }), ...bulletsW('p_lab'), par('Logistique', { bold: true }), ...bulletsW('p_log'),
      centered(await pic('sary/sitrep-partenaires.png', 640))];
    const wd = new D.Document({
      creator: 'DVSSER', title: 'SitRep mpox N°' + R.num, background: { color: 'EAF3FA' },
      numbering: { config: [{ reference: 'bul', levels: [{ level: 0, format: D.LevelFormat.BULLET, text: '•', alignment: D.AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 260 } } } }] }] },
      sections: [{ properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1000, bottom: 1000, left: 1134, right: 1134 } } }, children: kids }],
    });
    const blob = await D.Packer.toBlob(wd), a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = FILE + '.docx'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }

  document.querySelectorAll('[data-export]').forEach(b => b.addEventListener('click', async () => {
    busy(true);
    try { await (b.dataset.export === 'pdf' ? exportPdf() : exportDocx()); setMsg('Téléchargement lancé.'); }
    catch (e) { console.error(e); setMsg('Échec de la génération : ' + (e && e.message ? e.message : e)); }
    busy(false);
  }));
  busy(true); setMsg('Chargement de l’aperçu…');
  fetch('Districts.geojson').then(r => r.json()).then(g => { GEO = g; }).catch(() => { GEO = null; })
    .then(() => { makeFigures(); preview(); setMsg(''); busy(false); window.SITREP_READY = true; });
})();
