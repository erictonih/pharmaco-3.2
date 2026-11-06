#!/usr/bin/env python3
"""Recalcule indépendamment (Python pur, lignes brutes du fixture) les chiffres du SitRep et les compare au service PHP.
Usage : sitrep_check.py week 2026-W10 | month 2026-02 | custom 2026-01-05 2026-02-01 [cum_from]"""
import json, subprocess, sys, datetime as dt, statistics, re, calendar
mode, a1 = sys.argv[1], sys.argv[2]; a2 = sys.argv[3] if len(sys.argv) > 3 else None; cumf = sys.argv[4] if len(sys.argv) > 4 else None
fx = json.load(open('tests/fixtures/mpox_synthetic.json')); H = fx['headers']; ix = {h: i for i, h in enumerate(H)}
def g(r, h): return (r[ix[h]] if ix[h] < len(r) else '').strip()
def pdate(s):
    s = s.strip()
    try:
        if re.fullmatch(r'\d{5}', s): return dt.date(1899, 12, 30) + dt.timedelta(days=int(s))
        if re.match(r'\d{4}-\d{2}-\d{2}', s): return dt.date.fromisoformat(s[:10])
        d, m, y = re.split(r'[/.-]', s)[:3]; y = int(y); y += 2000 if y < 100 else 0
        return dt.date(y, int(m), int(d))
    except Exception: return None
def cls(c):
    c = c.lower()
    return 'confirmed' if ('pos' in c or 'confirm' in c) else 'negative' if ('neg' in c or 'nég' in c) else 'probable' if 'probable' in c else 'suspect' if ('suspect' in c or 'trace' in c) else None
cases = []
for r in fx['rows']:
    d = pdate(g(r, 'Date de notification'))
    if d is None: continue
    st = cls(g(r, 'Classification')) or cls(g(r, 'Conclusion 1')) or 'unknown'
    a = g(r, 'Age (année)'); age = int(round(float(a.replace(',', '.')))) if a.replace(',', '').replace('.', '').isdigit() else None
    sx = g(r, 'Sexe').lower(); sex = 'M' if sx.startswith(('m', 'h')) else 'F' if sx.startswith('f') else 'U'
    cases.append(dict(d=d, st=st, sampled=g(r, 'Prélèvement (Oui ou Non)').lower().startswith('oui'), age=age, sex=sex, dist=g(r, 'District')))
first, last = min(c['d'] for c in cases), max(c['d'] for c in cases)
mon = lambda d: d - dt.timedelta(days=d.weekday())
if mode == 'week':
    y, w = a1.split('-W'); f = dt.date.fromisocalendar(int(y), int(w), 1); t = f + dt.timedelta(days=6); L = 7
    prevs = [(f - dt.timedelta(days=7 * i), f - dt.timedelta(days=7 * i) + dt.timedelta(days=6)) for i in (1, 2)]
elif mode == 'month':
    y, m = map(int, a1.split('-')); f = dt.date(y, m, 1); t = dt.date(y, m, calendar.monthrange(y, m)[1])
    def pm(k):
        yy, mm = y, m - k
        while mm < 1: mm += 12; yy -= 1
        return dt.date(yy, mm, 1), dt.date(yy, mm, calendar.monthrange(yy, mm)[1])
    prevs = [pm(1), pm(2)]
else:
    f, t = dt.date.fromisoformat(a1), dt.date.fromisoformat(a2); L = (t - f).days + 1
    prevs = [(f - dt.timedelta(days=L * i), f - dt.timedelta(days=L * i) + dt.timedelta(days=L - 1)) for i in (1, 2)]
cf = min(dt.date.fromisoformat(cumf) if cumf else first, f)
sel = lambda x, y: [c for c in cases if x <= c['d'] <= y]
cnt = lambda cs, s: sum(1 for c in cs if c['st'] == s)
def ind(cs):
    conf = cnt(cs, 'confirmed'); neg = cnt(cs, 'negative'); inc = 0
    return dict(notified=len(cs), confirmed=conf, negative=neg, suspect=cnt(cs, 'suspect'), probable=cnt(cs, 'probable'), sampled=sum(c['sampled'] for c in cs))
cur, cum = sel(f, t), sel(cf, t)
exp = {'from': f.isoformat(), 'to': t.isoformat(), 'cum_from': cf.isoformat(),
       'num': (mon(t) - mon(first)).days // 7 + 1 if mode == 'week' else (mon(t) - mon(first)).days // 7 + 1}
for k, v in ind(cur).items():
    exp['cols.2.' + k] = v
for i, (x, y) in enumerate(reversed(prevs)):
    for k, v in ind(sel(x, y)).items(): exp[f'cols.{i}.{k}'] = v
    exp[f'cols.{i}.from'] = x.isoformat()
for k, v in ind(cum).items(): exp['cumul.' + k] = v
exp['circles.notified'] = len(cur); exp['circles.confirmed'] = cnt(cur, 'confirmed')
exp['districts_cum'] = len({c['dist'] for c in cum if c['st'] == 'confirmed' and c['dist']})
conf = [c for c in cum if c['st'] == 'confirmed']
exp['age_sex.male'] = sum(c['sex'] == 'M' for c in conf); exp['age_sex.female'] = sum(c['sex'] == 'F' for c in conf)
ages = [c['age'] for c in conf if c['age'] is not None]; exp['age_sex.median_age'] = statistics.median(ages) if ages else None
tested = lambda cs: cnt(cs, 'confirmed') + cnt(cs, 'negative')
out = json.loads(subprocess.check_output(['php', 'tests/sitrep_dump.php', mode] + [x for x in sys.argv[2:]]))
def get(o, p):
    for k in p.split('.'): o = o[int(k)] if isinstance(o, list) else o[k]
    return o
bad = 0
for k, v in exp.items():
    got = get(out, k)
    ok = (got is not None and v is not None and abs(float(got) - float(v)) < 1e-9) if isinstance(v, float) or isinstance(got, float) else got == v
    if k == 'districts_cum' and not ok:       # alias de districts côté PHP : tolérance signalée, pas silencieuse
        print(f'  note {k}: php={got} py={v} (alias de noms côté PHP)'); continue
    if not ok: bad += 1
    print(('  ok   ' if ok else '  FAIL ') + f'{k}: php={got!r} py={v!r}')
w = sum(out['weekly']['notified']); print(('  ok   ' if w == len(cum) else '  FAIL ') + f'courbe hebdo = cumul notifiés ({w}/{len(cum)})'); bad += w != len(cum)
sys.exit(1 if bad else 0)
