/* Point de situation : modèle de diapositives -> aperçu HTML / PDF (html2canvas + jsPDF) / PowerPoint natif (PptxGenJS).
   Données : window.REPORT (calculées côté serveur par ReportService). Aucune donnée n'est envoyée à un tiers. */
(function () {
  'use strict';
  const R = window.REPORT;
  if (!R || R.empty) return;

  const GRAY = '#a6a6a6', BLUE = '#1f4fd8', RED = '#e11d1d', NAVY = '#4472c4', ORANGE = '#ed7d31', SKY = '#5b9bd5';
  const MAPCOL = ['#fde0d2', '#fcb79a', '#f7886a', '#e0503d', '#a50f15'];
  const MAPCLS = [[1, 5, '1 - 5'], [6, 20, '6 - 20'], [21, 50, '21 - 50'], [51, 100, '51 - 100'], [101, Infinity, '> 100']];
  const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  const W = 13.333, H = 7.5;                                    // pouces (16:9) ; 1 po = 96 px à l'écran
  const PX = 96;

  // ── Formats ────────────────────────────────────────────────────────────────
  const nf = n => n == null ? '—' : Number(n).toLocaleString('fr-FR').replace(/[  ]/g, ' ');
  const dec = (n, d) => n == null ? '—' : Number(n).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }).replace(/[  ]/g, ' ');
  const fdate = iso => iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4);
  const fshort = iso => parseInt(iso.slice(8, 10), 10) + '/' + parseInt(iso.slice(5, 7), 10) + '/' + iso.slice(0, 4);
  const flong = iso => parseInt(iso.slice(8, 10), 10) + ' ' + MOIS[parseInt(iso.slice(5, 7), 10) - 1] + ' ' + iso.slice(0, 4);
  const cap = s => s.replace(/ (\S)/, (m, c) => ' ' + c.toUpperCase());
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function weekRange(a, b) {                                     // « S51_2025 à S41_2026 » ou « S39 à S41_2026 »
    const [wa, ya] = a.split('_'), [wb, yb] = b.split('_');
    return (ya === yb ? wa : a) + ' à ' + b;
  }
  const PERIOD = weekRange(R.label.from, R.label.to);
  const RECENT = weekRange(R.label.recent_from, R.label.to);
  const nWeeks = (() => {
    const mon = iso => { const d = new Date(iso + 'T00:00:00Z'); return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate() - ((d.getUTCDay() + 6) % 7)); };
    return Math.round((mon(R.to) - mon(R.recent_from)) / (7 * 864e5)) + 1;
  })();
  const NB = 'NB: Données incluses dans le point de situation concernent celles reçues avant 12 heures du ' + fdate(R.to);
  const FILE = 'Point_situation_mpox_' + R.label.to;
  const key = s => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '');

  // ── Modèle des diapositives ────────────────────────────────────────────────
  function spec() {
    const c = R.cumul, d = R.day.counts, S = [];
    S.push({ type: 'cover' });
    S.push({
      type: 'situation', title: 'Situation mpox, ' + PERIOD + ', Madagascar',
      left: [
        { k: 'h', t: 'Ce jour ' + fdate(R.to), color: '#e8710a' },
        { k: 'l1', t: 'Nombre total de nouveaux cas' },
        { k: 'l2', t: 'notifié    : ' + nf(d.total) }, { k: 'l2', t: 'prélevé   : ' + nf(d.sampled) + '*' }, { k: 'l2', t: 'confirmé  : ' + nf(d.confirmed) },
        { k: 'sp' },
        { k: 'l1', t: 'Nombre total de décès : ' + nf(R.day.deaths) }, { k: 'l2', t: 'Confirmés : ' + nf(R.day.deaths_confirmed) },
        { k: 'sp' }, { k: 'p', t: '*Prélèvements : ' + nf(d.sampled) },
      ],
      right: [
        { k: 'h', t: 'Du ' + flong(R.from) + ' au ' + fdate(R.to), color: '#2e7d32' },
        { k: 'l1', t: 'Nombre total de cas notifiés : ' + nf(c.total) }, { k: 'l2', t: 'Confirmés : ' + nf(c.confirmed) },
        { k: 'l2', t: 'Nombre total des cas guéris : ', red: nf(c.recovered) },
        { k: 'sp' },
        { k: 'l1', t: 'Nombre total de décès' }, { k: 'l2', t: 'Confirmés : ' + nf(c.deaths_confirmed) },
        { k: 'l2', t: 'Létalité : ' + (c.cfr == null ? '—' : dec(c.cfr, 2) + '%') + (c.deaths_confirmed ? ' dont ' + dec(c.deaths_pvvih_pct, 1) + '% PVVIH, ' + dec(c.deaths_u5_pct, 1) + '% enfant <5ans' : '') },
        { k: 'sp' },
        { k: 'l1', t: 'Testés = ' + nf(c.tested) }, { k: 'p', t: '- Taux de positivité ' + (c.positivity == null ? '—' : dec(c.positivity, 2) + ' %') + ' (' + nf(c.confirmed) + '/ ' + nf(c.tested) + ')' },
      ],
    });
    // Décès (paginé : 22 lignes par diapositive)
    const D = R.deaths, per = 22, pages = Math.max(1, Math.ceil(D.length / per));
    for (let p = 0; p < pages; p++) {
      S.push({ type: 'deaths', title: 'Situation décès mpox, ' + PERIOD + (pages > 1 ? ' (' + (p + 1) + '/' + pages + ')' : ''),
        rows: D.slice(p * per, (p + 1) * per), total: p === pages - 1 ? D.length : null, empty: D.length === 0 });
    }
    S.push({ type: 'curve', title: 'Cas notifiés et confirmés de mpox, selon la date de notification, ' + weekRange(R.label.from, R.label.to).replace(' à ', ' à ') + ', Madagascar, n = ' + nf(c.total), series: R.daily_period, nb: NB });
    S.push({ type: 'curve', title: 'Cas notifiés et confirmés de mpox, selon la date de notification, ' + RECENT + ', Madagascar, n = ' + nf(R.recent.total), series: R.daily_recent, nb: NB });
    S.push({ type: 'regions', title: 'Cas notifiés de mpox, région notifiant, ' + RECENT + ', Madagascar, n = ' + nf(R.recent.total), data: R.regions });
    const A = R.age_sex;
    S.push({ type: 'agesex', title: 'Cas confirmés de mpox, selon la tranche d’âge et le sexe, ' + RECENT + ', Madagascar, n = ' + nf(A.n), data: A,
      lines: ['Sex-ratio H/F (' + A.male + '/' + A.female + ') = ' + (A.sex_ratio == null ? '—' : dec(A.sex_ratio, 2)),
              'Age médian = ' + (A.median_age == null ? '—' : nf(A.median_age) + ' ans [' + A.min_age + ' - ' + A.max_age + ']')] });
    S.push({ type: 'map', title: 'Cas confirmés de Mpox, selon la résidence, ' + PERIOD + ', Madagascar' });
    S.push({ type: 'thanks' });
    return S;
  }

  // ── Carte (canvas, sans fond de tuiles : choroplèthe sur le GeoJSON des districts) ──
  let GEO = null;
  function classOf(n) { for (let i = 0; i < MAPCLS.length; i++) if (n >= MAPCLS[i][0] && n <= MAPCLS[i][1]) return i; return -1; }
  function drawMap(cv, counts) {
    const w = cv.width, h = cv.height, g = cv.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, w, h);
    if (!GEO) { g.fillStyle = '#6b7280'; g.font = '16px Arial'; g.fillText('Carte indisponible', 20, 30); return; }
    let x0 = 1e9, x1 = -1e9, y0 = 1e9, y1 = -1e9; const k0 = Math.cos(19 * Math.PI / 180);
    const each = (geom, fn) => { const polys = geom.type === 'Polygon' ? [geom.coordinates] : geom.coordinates; polys.forEach(p => p.forEach(fn)); };
    GEO.features.forEach(f => each(f.geometry, ring => ring.forEach(([lo, la]) => { const x = lo * k0, y = -la; x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); })));
    const pad = 10, sc = Math.min((w - 2 * pad) / (x1 - x0), (h - 2 * pad - 96) / (y1 - y0));
    const ox = (w - (x1 - x0) * sc) / 2, oy = pad;
    GEO.features.forEach(f => {
      const n = (counts[key(f.properties.NAME_3)] || {}).confirmed || 0, i = classOf(n);
      g.fillStyle = i < 0 ? '#ffffff' : MAPCOL[i]; g.strokeStyle = '#555'; g.lineWidth = Math.max(0.4, w / 900);
      each(f.geometry, ring => { g.beginPath(); ring.forEach(([lo, la], j) => { const x = ox + (lo * k0 - x0) * sc, y = oy + (-la - y0) * sc; j ? g.lineTo(x, y) : g.moveTo(x, y); }); g.closePath(); g.fill(); g.stroke(); });
    });
    // légende
    const fs = Math.max(11, Math.round(w / 34)), ly = h - 84;
    g.fillStyle = '#111'; g.font = 'bold ' + fs + 'px Arial'; g.fillText('Légende — cas confirmés', 8, ly);
    g.font = fs + 'px Arial';
    MAPCLS.forEach((c, i) => { const cx = 8 + (i % 3) * (w / 3.1), cy = ly + 10 + Math.floor(i / 3) * (fs + 6); g.fillStyle = MAPCOL[i]; g.fillRect(cx, cy, fs, fs * 0.8); g.strokeStyle = '#555'; g.strokeRect(cx, cy, fs, fs * 0.8); g.fillStyle = '#111'; g.fillText(c[2], cx + fs + 5, cy + fs * 0.78); });
  }
  function mapCanvas(counts, wpx, hpx) { const cv = document.createElement('canvas'); cv.width = wpx; cv.height = hpx; drawMap(cv, counts); return cv; }

  // ── Rendu HTML (aperçu + PDF) ──────────────────────────────────────────────
  const pxs = v => Math.round(v * PX) + 'px';
  function box(parent, x, y, w, h, css, html) {
    const e = document.createElement('div');
    e.style.cssText = 'position:absolute;left:' + pxs(x) + ';top:' + pxs(y) + ';width:' + pxs(w) + ';height:' + pxs(h) + ';' + (css || '');
    if (html != null) e.innerHTML = html; parent.appendChild(e); return e;
  }
  function img(parent, src, x, y, w, h) { const i = document.createElement('img'); i.src = src; i.crossOrigin = 'anonymous'; i.style.cssText = 'position:absolute;left:' + pxs(x) + ';top:' + pxs(y) + ';width:' + pxs(w) + ';height:' + pxs(h); parent.appendChild(i); return i; }
  function frame(s) {
    const d = document.createElement('div'); d.className = 'rp-slide'; d.style.cssText = 'position:relative;width:' + pxs(W) + ';height:' + pxs(H) + ';background:#fff;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#111;';
    if (s.type !== 'cover' && s.type !== 'thanks') {
      box(d, 0.35, 0.25, 11.55, 0.95, 'background:' + GRAY + ';color:#fff;font-weight:bold;font-size:' + (s.title.length > 95 ? 24 : 28) + 'px;line-height:1.15;display:flex;align-items:center;justify-content:center;text-align:center;padding:0 14px;box-sizing:border-box;border-radius:4px', esc(s.title));
      img(d, 'sary/rapport-logo.jpg', 12.05, 0.2, 0.96, 0.98);
    }
    return d;
  }
  const LINE = { h: 'font-size:26px;font-weight:bold;margin:2px 0 10px', l1: 'font-size:23px;margin:6px 0 2px;padding-left:26px;text-indent:-26px', l2: 'font-size:23px;margin:3px 0;padding-left:62px;text-indent:-24px', p: 'font-size:21px;margin:4px 0', sp: 'height:18px' };
  function lines(parent, x, y, w, h, bg, arr) {
    const b = box(parent, x, y, w, h, 'background:' + bg + ';padding:16px 26px;box-sizing:border-box;line-height:1.25');
    arr.forEach(l => {
      const p = document.createElement('div'); p.style.cssText = LINE[l.k] + (l.color ? ';color:' + l.color : '');
      const pre = l.k === 'l1' ? '➢&nbsp; ' : l.k === 'l2' ? '▪&nbsp; ' : '';
      p.innerHTML = pre + esc(l.t || '') + (l.red != null ? '<span style="color:#e11d1d;font-weight:bold">' + esc(l.red) + '</span>' : '');
      b.appendChild(p);
    });
  }
  function table(parent, x, y, w, head, rows, opt) {
    opt = opt || {}; const fs = opt.fs || 12, rh = opt.rh || 22;
    const t = document.createElement('table');
    t.style.cssText = 'position:absolute;left:' + pxs(x) + ';top:' + pxs(y) + ';width:' + pxs(w) + ';border-collapse:collapse;font-size:' + fs + 'px;table-layout:fixed';
    t.innerHTML = '<thead>' + head + '</thead><tbody>' + rows + '</tbody>';
    t.querySelectorAll('th').forEach(e => e.style.cssText += ';background:' + (opt.hbg || NAVY) + ';color:#fff;border:1px solid #1f2937;padding:2px 4px;text-align:center;height:' + rh + 'px;font-weight:bold');
    t.querySelectorAll('td').forEach(e => e.style.cssText += ';border:1px solid #1f2937;padding:1px 4px;height:' + rh + 'px;text-align:' + (e.dataset.a || 'center') + (e.dataset.b ? ';font-weight:bold' : '') + (e.dataset.bg ? ';background:' + e.dataset.bg : ''));
    parent.appendChild(t); return t;
  }
  function td(v, o) { o = o || {}; return '<td' + (o.a ? ' data-a="' + o.a + '"' : '') + (o.b ? ' data-b="1"' : '') + (o.bg ? ' data-bg="' + o.bg + '"' : '') + '>' + esc(v) + '</td>'; }
  function chart(parent, x, y, w, h, cfg) {
    const cv = document.createElement('canvas'); cv.width = Math.round(w * PX); cv.height = Math.round(h * PX); cv.style.cssText = 'position:absolute;left:' + pxs(x) + ';top:' + pxs(y) + ';width:' + pxs(w) + ';height:' + pxs(h);
    parent.appendChild(cv);
    cfg.options = Object.assign({ responsive: false, animation: false, devicePixelRatio: 1.5 }, cfg.options || {});
    new Chart(cv, cfg); return cv;
  }

  function renderSlide(s) {
    const d = frame(s);
    if (s.type === 'cover') {
      img(d, 'sary/rapport-banner.png', 0, 0, W, 2.222);
      box(d, 2.7, 3.05, 7.9, 3.55, 'background:' + GRAY + ';color:#fff;border-radius:46px;text-align:center;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:10px 30px;box-sizing:border-box;line-height:1.2',
        '<div style="font-size:44px;font-weight:bold">Point de situation<br>mpox à Madagascar</div><div style="font-size:40px;margin-top:6px">' + esc(PERIOD) + '</div><div style="font-size:24px;margin-top:8px">DVSSER, ' + esc(cap(flong(R.to))) + '</div>');
      box(d, 2.7, 6.7, 7.9, 0.4, 'text-align:center;font-size:16px;color:#6b7280', 'Période analysée : du ' + fdate(R.from) + ' au ' + fdate(R.to));
    } else if (s.type === 'situation') {
      lines(d, 0, 1.4, 6.55, 6.1, '#fbe8dc', s.left); lines(d, 6.55, 1.4, 6.783, 6.1, '#e5eedc', s.right);
    } else if (s.type === 'deaths') {
      const head = '<tr><th>Date de décès</th><th>Districts</th><th>Nombre de décès</th><th>Age</th><th>Sexe</th><th>Comorbidité</th></tr>';
      let rows = s.rows.map(r => '<tr>' + td(fdate(r.date)) + td(r.district) + td(r.n) + td(r.age) + td(r.sex) + td(r.comorbidity) + '</tr>').join('');
      if (s.total != null) rows += '<tr>' + td('') + td('TOTAL', { b: 1, a: 'left' }) + td(s.total, { b: 1 }) + td('') + td('') + td('') + '</tr>';
      if (s.empty) rows = '<tr><td colspan="6" style="text-align:center;padding:30px">Aucun décès sur la période sélectionnée.</td></tr>';
      table(d, 1.1, 1.4, 11.1, head, rows, { fs: 13, rh: 23 });
    } else if (s.type === 'curve') {
      const sr = s.series, n = sr.dates.length, lab = sr.dates.map(x => parseInt(x.slice(8, 10), 10) + '/' + parseInt(x.slice(5, 7), 10) + '/' + x.slice(0, 4));
      chart(d, 0.5, 1.4, 12.3, 5.2, { type: 'bar', data: { labels: lab, datasets: [
        { label: 'Notifié', data: sr.notified, backgroundColor: BLUE, grouped: false, order: 2, barPercentage: n > 120 ? 1 : 0.8, categoryPercentage: 1 },
        { label: 'Confirmé', data: sr.confirmed, backgroundColor: RED, grouped: false, order: 1, barPercentage: n > 120 ? 1 : 0.8, categoryPercentage: 1 }] },
        options: { plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, font: { size: 13 } } } },
          scales: { x: { title: { display: true, text: 'Date de notification', font: { size: 13 } }, ticks: { maxRotation: 90, minRotation: 90, autoSkip: true, maxTicksLimit: n > 60 ? 40 : 60, font: { size: 10 } }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 12 } } } } } });
      box(d, 0.5, 6.75, 12.3, 0.4, 'font-size:15px;font-weight:bold;font-style:italic;text-align:center', esc(s.nb));
    } else if (s.type === 'regions') {
      const head = '<tr><th rowspan="2" style="width:22%">Région</th><th rowspan="2">Notifiés</th><th rowspan="2">Prélevés</th><th rowspan="2">Testés</th><th colspan="3">Résultats Tests</th><th rowspan="2">Suspects</th><th rowspan="2">Probables</th></tr><tr><th>Confirmés</th><th>Négatifs</th><th>Non concluants</th></tr>';
      const row = (r, b) => '<tr>' + td(String(r.region).toUpperCase(), { a: 'left', b, bg: b ? '#8faadc' : '' }) + [r.total, r.sampled, r.tested, r.confirmed, r.negative, r.inconclusive, r.suspect, r.probable].map(v => td(v, { b, bg: b ? '#8faadc' : '' })).join('') + '</tr>';
      const rows = s.data.rows.map(r => row(r, false)).join('') + row(s.data.total, true);
      const n = s.data.rows.length + 3, rh = Math.max(15, Math.min(22, Math.floor(5.9 * PX / n)));
      table(d, 1.0, 1.35, 11.3, head, rows, { fs: rh < 19 ? 11 : 12, rh });
    } else if (s.type === 'agesex') {
      const G = Object.keys(s.data.groups);
      chart(d, 1.4, 1.45, 10.4, 4.6, { type: 'bar', data: { labels: G, datasets: [
        { label: 'masculin', data: G.map(k => s.data.groups[k].M), backgroundColor: SKY }, { label: 'féminin', data: G.map(k => s.data.groups[k].F), backgroundColor: ORANGE }] },
        options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 14 } } }, title: { display: true, text: 'Confirmé', font: { size: 15 }, color: '#6b7280' } },
          scales: { x: { ticks: { font: { size: 14 } }, grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0, font: { size: 13 } } } } } });
      box(d, 1.2, 6.2, 10.8, 1.1, 'font-size:22px;line-height:1.6', s.lines.map(t => '▪&nbsp; ' + esc(t)).join('<br>'));
    } else if (s.type === 'map') {
      box(d, 0.35, 1.35, 4.3, 0.4, 'font-size:17px;font-weight:bold', 'Cas positif du ' + fdate(R.to) + ' (n =' + nf(R.kpi.day_confirmed) + ')');
      const m1 = mapCanvas(R.map_day, 840, 1060); m1.style.cssText = 'position:absolute;left:' + pxs(0.3) + ';top:' + pxs(1.8) + ';width:' + pxs(4.2) + ';height:' + pxs(5.3); d.appendChild(m1);
      const K = R.kpi;
      box(d, 4.75, 1.35, 4.2, 1.7, 'font-size:15px;font-weight:bold;line-height:1.55;white-space:pre', esc('Cas positifs ' + nWeeks + ' dernières semaines  = ' + nf(K.recent_confirmed) + '\nCas positifs cumulés       = ' + nf(K.cumul_confirmed) + '\nDistricts actifs                    = ' + nf(K.districts_active) + '\nDistricts touchés               = ' + nf(K.districts_touched)));
      const head = '<tr><th>District Hot spot</th><th>Nombre de cas</th><th>Proportion</th></tr>';
      let rows = R.hotspots.map(h => '<tr>' + td(h.district, { a: 'left' }) + td(h.cases) + td(h.pct + '%') + '</tr>').join('');
      if (!rows) rows = '<tr><td colspan="3" style="text-align:center">Aucun cas confirmé récent</td></tr>';
      table(d, 4.75, 3.4, 4.1, head, rows, { fs: 14, rh: 44, hbg: '#fff' }).querySelectorAll('th').forEach(e => { e.style.color = '#111'; e.style.background = '#fff'; e.style.border = '0'; e.style.borderBottom = '2px solid #111'; });
      const m2 = mapCanvas(R.map, 880, 1180); m2.style.cssText = 'position:absolute;left:' + pxs(8.6) + ';top:' + pxs(1.35) + ';width:' + pxs(4.5) + ';height:' + pxs(6.0); d.appendChild(m2);
    } else if (s.type === 'thanks') {
      img(d, 'sary/rapport-banner.png', 0, 0, W, 2.222);
      box(d, 0, 3.4, W, 1.5, 'text-align:center;font-size:56px', 'MISAOTRA TOMPOKO');
    }
    return d;
  }

  // ── Aperçu ────────────────────────────────────────────────────────────────
  function preview(slides) {
    const mount = document.getElementById('deck'); mount.innerHTML = '';
    slides.forEach((s, i) => {
      const wrap = document.createElement('div'); wrap.className = 'rp-wrap';
      const inner = document.createElement('div'); inner.className = 'rp-scale';
      inner.appendChild(renderSlide(s)); wrap.appendChild(inner);
      const cap = document.createElement('div'); cap.className = 'rp-cap'; cap.textContent = 'Diapositive ' + (i + 1) + ' / ' + slides.length;
      mount.appendChild(wrap); mount.appendChild(cap);
    });
    fit();
  }
  function fit() {
    document.querySelectorAll('.rp-wrap').forEach(w => {
      const k = w.clientWidth / (W * PX); const inner = w.firstChild;
      inner.style.transform = 'scale(' + k + ')'; w.style.height = (H * PX * k) + 'px';
    });
  }
  window.addEventListener('resize', fit);

  // ── Export PDF ────────────────────────────────────────────────────────────
  async function exportPdf(setMsg) {
    const slides = spec(), host = document.createElement('div');
    host.style.cssText = 'position:fixed;left:-20000px;top:0;width:' + pxs(W) + ';';
    document.body.appendChild(host);
    const { jsPDF } = window.jspdf, pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [W * PX, H * PX], compress: true });
    try {
      for (let i = 0; i < slides.length; i++) {
        setMsg('PDF : diapositive ' + (i + 1) + ' / ' + slides.length + '…');
        host.innerHTML = ''; const el = renderSlide(slides[i]); host.appendChild(el);
        await Promise.all([...el.querySelectorAll('img')].map(im => im.decode ? im.decode().catch(() => {}) : null));
        const cv = await html2canvas(el, { scale: 1.5, backgroundColor: '#ffffff', useCORS: true, logging: false });
        if (i) pdf.addPage([W * PX, H * PX], 'landscape');
        pdf.addImage(cv.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, W * PX, H * PX);
      }
    } finally { host.remove(); }
    pdf.save(FILE + '.pdf');
  }

  // ── Export PowerPoint (objets natifs modifiables) ──────────────────────────
  async function dataUrl(u) { const b = await (await fetch(u)).blob(); return await new Promise(r => { const f = new FileReader(); f.onload = () => r(f.result); f.readAsDataURL(b); }); }
  const hex = c => c.replace('#', '');
  async function exportPptx(setMsg) {
    setMsg('PowerPoint : préparation…');
    const pptx = new PptxGenJS(); pptx.layout = 'LAYOUT_WIDE';            // 13,33 x 7,5 po
    pptx.title = 'Point de situation mpox ' + PERIOD; pptx.company = 'DVSSER — Ministère de la Santé Publique';
    const [logo, banner] = await Promise.all([dataUrl('sary/rapport-logo.jpg'), dataUrl('sary/rapport-banner.png')]);
    const F = 'Arial';
    const head = (sl, s) => {
      sl.addText(s.title, { x: 0.35, y: 0.25, w: 11.55, h: 0.95, fill: { color: hex(GRAY) }, color: 'FFFFFF', bold: true, fontFace: F, fontSize: s.title.length > 95 ? 18 : 21, align: 'center', valign: 'middle', fit: 'shrink', margin: 8 });
      sl.addImage({ data: logo, x: 12.05, y: 0.2, w: 0.96, h: 0.98 });
    };
    spec().forEach((s, idx) => {
      setMsg('PowerPoint : diapositive ' + (idx + 1) + '…');
      const sl = pptx.addSlide();
      if (s.type === 'cover') {
        sl.addImage({ data: banner, x: 0, y: 0, w: W, h: 2.222 });
        sl.addShape(pptx.ShapeType.roundRect, { x: 2.7, y: 3.05, w: 7.9, h: 3.55, fill: { color: hex(GRAY) }, rectRadius: 0.45, line: { color: hex(GRAY) } });
        sl.addText([
          { text: 'Point de situation', options: { fontSize: 32, bold: true, breakLine: true } }, { text: 'mpox à Madagascar', options: { fontSize: 32, bold: true, breakLine: true } },
          { text: PERIOD, options: { fontSize: 30, breakLine: true } }, { text: 'DVSSER, ' + cap(flong(R.to)), options: { fontSize: 18 } }],
          { x: 2.7, y: 3.05, w: 7.9, h: 3.55, color: 'FFFFFF', fontFace: F, align: 'center', valign: 'middle' });
        sl.addText('Période analysée : du ' + fdate(R.from) + ' au ' + fdate(R.to), { x: 2.7, y: 6.7, w: 7.9, h: 0.4, fontSize: 12, color: '6B7280', align: 'center', fontFace: F });
      } else if (s.type === 'situation') {
        head(sl, s);
        [[0, 1.4, 6.55, 6.1, 'fbe8dc', s.left], [6.55, 1.4, 6.783, 6.1, 'e5eedc', s.right]].forEach(([x, y, w, h, bg, arr]) => {
          sl.addShape(pptx.ShapeType.rect, { x, y, w, h, fill: { color: bg }, line: { color: bg } });
          const runs = [];
          arr.forEach(l => {
            if (l.k === 'sp') { runs.push({ text: ' ', options: { fontSize: 8, breakLine: true } }); return; }
            const o = { fontSize: l.k === 'h' ? 20 : l.k === 'p' ? 15 : 17, breakLine: true, bold: l.k === 'h', color: l.color ? hex(l.color) : '111111' };
            if (l.k === 'l1') { o.bullet = { code: '27A2' }; o.indentLevel = 0; } if (l.k === 'l2') { o.bullet = { code: '25AA' }; o.indentLevel = 1; }
            if (l.red != null) runs.push({ text: l.t + l.red, options: o });
            else runs.push({ text: l.t, options: o });
          });
          sl.addText(runs, { x: x + 0.15, y: y + 0.1, w: w - 0.3, h: h - 0.2, fontFace: F, valign: 'top', paraSpaceAfter: 4 });
        });
      } else if (s.type === 'deaths') {
        head(sl, s);
        const th = t => ({ text: t, options: { bold: true, color: 'FFFFFF', fill: { color: hex(NAVY) }, align: 'center', valign: 'middle' } });
        const rows = [['Date de décès', 'Districts', 'Nombre de décès', 'Age', 'Sexe', 'Comorbidité'].map(th)];
        if (s.empty) rows.push([{ text: 'Aucun décès sur la période sélectionnée.', options: { colspan: 6, align: 'center' } }]);
        s.rows.forEach(r => rows.push([fdate(r.date), r.district, String(r.n), String(r.age), r.sex, r.comorbidity].map(t => ({ text: t, options: { align: 'center' } }))));
        if (s.total != null && !s.empty) rows.push([{ text: '' }, { text: 'TOTAL', options: { bold: true, align: 'left' } }, { text: String(s.total), options: { bold: true, align: 'center' } }, { text: '' }, { text: '' }, { text: '' }]);
        sl.addTable(rows, { x: 1.1, y: 1.4, w: 11.1, colW: [1.6, 3.0, 1.5, 1.0, 0.9, 3.1], rowH: 0.24, fontFace: F, fontSize: 10, border: { type: 'solid', color: '1F2937', pt: 0.75 }, valign: 'middle', margin: [0, 0.05, 0, 0.05] });
      } else if (s.type === 'curve') {
        head(sl, s); const sr = s.series, n = sr.dates.length, lab = sr.dates.map(x => parseInt(x.slice(8, 10), 10) + '/' + parseInt(x.slice(5, 7), 10) + '/' + x.slice(0, 4));
        sl.addChart(pptx.charts.BAR, [{ name: 'Notifié', labels: lab, values: sr.notified }, { name: 'Confirmé', labels: lab, values: sr.confirmed }], {
          x: 0.5, y: 1.4, w: 12.3, h: 5.2, barDir: 'col', barGrouping: 'clustered', barOverlapPct: 100, barGapWidthPct: n > 120 ? 10 : 40, chartColors: [hex(BLUE), hex(RED)],
          showLegend: true, legendPos: 't', legendFontSize: 11, catAxisLabelFontSize: 8, catAxisLabelRotate: 270, catAxisLabelFrequency: Math.max(1, Math.ceil(n / 45)),
          valAxisLabelFontSize: 10, valAxisMinVal: 0, showCatAxisTitle: true, catAxisTitle: 'Date de notification', catAxisTitleFontSize: 11, valGridLine: { color: 'E5E7EB', size: 0.5 }, catGridLine: { style: 'none' } });
        sl.addText(s.nb, { x: 0.5, y: 6.75, w: 12.3, h: 0.4, fontSize: 12, bold: true, italic: true, align: 'center', fontFace: F });
      } else if (s.type === 'regions') {
        head(sl, s); const D = s.data;
        const hc = (t, o) => ({ text: t, options: Object.assign({ bold: true, fill: { color: 'B4C7E7' }, align: 'center', valign: 'middle' }, o || {}) });
        const rows = [[hc('Région', { rowspan: 2 }), hc('Notifiés', { rowspan: 2 }), hc('Prélevés', { rowspan: 2 }), hc('Testés', { rowspan: 2 }), hc('Résultats Tests', { colspan: 3 }), hc('Suspects', { rowspan: 2 }), hc('Probables', { rowspan: 2 })],
                      [hc('Confirmés'), hc('Négatifs'), hc('Non concluants')]];
        const line = (r, b) => [{ text: String(r.region).toUpperCase(), options: { align: 'left', bold: b, fill: { color: b ? '8FAADC' : 'FFFFFF' } } }].concat([r.total, r.sampled, r.tested, r.confirmed, r.negative, r.inconclusive, r.suspect, r.probable].map(v => ({ text: String(v), options: { align: 'center', bold: b, fill: { color: b ? '8FAADC' : 'FFFFFF' } } })));
        D.rows.forEach(r => rows.push(line(r, false))); rows.push(line(D.total, true));
        const rh = Math.min(0.22, 5.9 / (D.rows.length + 3));
        sl.addTable(rows, { x: 1.0, y: 1.35, w: 11.3, colW: [2.6, 1.0, 1.0, 1.0, 1.1, 1.1, 1.5, 1.0, 1.0], rowH: rh, fontFace: F, fontSize: rh < 0.19 ? 8 : 9, border: { type: 'solid', color: '1F2937', pt: 0.5 }, valign: 'middle', margin: [0, 0.04, 0, 0.04] });
      } else if (s.type === 'agesex') {
        head(sl, s); const G = Object.keys(s.data.groups);
        sl.addChart(pptx.charts.BAR, [{ name: 'masculin', labels: G, values: G.map(k => s.data.groups[k].M) }, { name: 'féminin', labels: G, values: G.map(k => s.data.groups[k].F) }], {
          x: 1.4, y: 1.45, w: 10.4, h: 4.6, barDir: 'col', barGrouping: 'clustered', chartColors: [hex(SKY), hex(ORANGE)], barGapWidthPct: 60, showLegend: true, legendPos: 'b', legendFontSize: 12,
          catAxisLabelFontSize: 12, valAxisLabelFontSize: 11, showTitle: true, title: 'Confirmé', titleFontSize: 13, titleColor: '6B7280', valGridLine: { color: 'E5E7EB', size: 0.5 }, catGridLine: { style: 'none' } });
        sl.addText(s.lines.map(t => ({ text: t, options: { bullet: { code: '25AA' }, breakLine: true } })), { x: 1.2, y: 6.2, w: 10.8, h: 1.1, fontSize: 18, fontFace: F, valign: 'top' });
      } else if (s.type === 'map') {
        head(sl, s); const K = R.kpi;
        sl.addText('Cas positif du ' + fdate(R.to) + ' (n =' + nf(K.day_confirmed) + ')', { x: 0.35, y: 1.35, w: 4.3, h: 0.4, fontSize: 13, bold: true, fontFace: F });
        sl.addImage({ data: mapCanvas(R.map_day, 840, 1060).toDataURL('image/png'), x: 0.3, y: 1.8, w: 4.2, h: 5.3 });
        sl.addText([['Cas positifs ' + nWeeks + ' dernières semaines', K.recent_confirmed], ['Cas positifs cumulés', K.cumul_confirmed], ['Districts actifs', K.districts_active], ['Districts touchés', K.districts_touched]]
          .map(([a, b]) => ({ text: a + ' = ' + nf(b), options: { breakLine: true } })), { x: 4.75, y: 1.35, w: 4.2, h: 1.7, fontSize: 12, bold: true, fontFace: F, valign: 'top' });
        const hh = t => ({ text: t, options: { bold: true, align: 'center', color: '111111', border: [{ type: 'none' }, { type: 'none' }, { type: 'solid', color: '111111', pt: 2 }, { type: 'none' }] } });
        const rows = [[hh('District Hot spot'), hh('Nombre de cas'), hh('Proportion')]];
        R.hotspots.forEach(h => rows.push([{ text: h.district, options: { align: 'left' } }, { text: String(h.cases), options: { align: 'center' } }, { text: h.pct + '%', options: { align: 'center' } }]));
        if (!R.hotspots.length) rows.push([{ text: 'Aucun cas confirmé récent', options: { colspan: 3, align: 'center' } }]);
        sl.addTable(rows, { x: 4.6, y: 3.4, w: 3.9, colW: [1.9, 1.0, 1.0], rowH: 0.46, fontFace: F, fontSize: 11, border: { type: 'none' }, valign: 'middle' });
        sl.addImage({ data: mapCanvas(R.map, 880, 1180).toDataURL('image/png'), x: 8.6, y: 1.35, w: 4.5, h: 6.0 });
      } else if (s.type === 'thanks') {
        sl.addImage({ data: banner, x: 0, y: 0, w: W, h: 2.222 });
        sl.addText('MISAOTRA TOMPOKO', { x: 0, y: 3.4, w: W, h: 1.5, fontSize: 40, align: 'center', fontFace: F });
      }
    });
    await pptx.writeFile({ fileName: FILE + '.pptx' });
  }

  // ── Démarrage ─────────────────────────────────────────────────────────────
  const msg = document.getElementById('rpMsg'), setMsg = t => { msg.textContent = t || ''; };
  function busy(on) { document.querySelectorAll('[data-export]').forEach(b => b.disabled = on); }
  document.querySelectorAll('[data-export]').forEach(b => b.addEventListener('click', async () => {
    busy(true);
    try { await (b.dataset.export === 'pdf' ? exportPdf(setMsg) : exportPptx(setMsg)); setMsg('Téléchargement lancé.'); }
    catch (e) { console.error(e); setMsg('Échec de la génération : ' + (e && e.message ? e.message : e)); }
    busy(false);
  }));
  fetch('Districts.geojson').then(r => r.json()).then(g => { GEO = g; }).catch(() => { GEO = null; }).then(() => { preview(spec()); setMsg(''); busy(false); });
  busy(true); setMsg('Chargement de l’aperçu…');
})();
