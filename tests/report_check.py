#!/usr/bin/env python3
"""Recalcule indépendamment (Python pur, sur les lignes brutes du fixture) les chiffres du point de situation et les compare au service PHP."""
import json, subprocess, sys, datetime as dt, statistics
FROM, TO = sys.argv[1], sys.argv[2]
fx = json.load(open('tests/fixtures/mpox_synthetic.json')); H = fx['headers']; ix = {h: i for i, h in enumerate(H)}
def g(r, h): return (r[ix[h]] if ix[h] < len(r) else '').strip()
def pdate(s):
    import re
    s = s.strip()
    try:
        if re.fullmatch(r'\d{5}', s): return dt.date(1899, 12, 30) + dt.timedelta(days=int(s))      # numéro de série Sheets
        if re.match(r'\d{4}-\d{2}-\d{2}', s): return dt.date.fromisoformat(s[:10])
        d, m, y = re.split(r'[/.-]', s)[:3]; y = int(y); y += 2000 if y < 100 else 0
        return dt.date(y, int(m), int(d))
    except Exception: return None
cases = []
for r in fx['rows']:
    d = pdate(g(r, 'Date de notification'))
    if d is None: continue
    c = g(r, 'Classification').lower()
    st = 'confirmed' if ('pos' in c or 'confirm' in c) else 'negative' if ('neg' in c or 'nég' in c) else 'probable' if 'probable' in c else 'suspect' if ('suspect' in c or 'trace' in c) else None
    if st is None:
        cc = g(r, 'Conclusion 1').lower()
        st = 'confirmed' if ('pos' in cc or 'confirm' in cc) else 'negative' if ('neg' in cc or 'nég' in cc) else 'probable' if 'probable' in cc else 'suspect' if ('suspect' in cc or 'trace' in cc) else 'unknown'
    a = g(r, 'Age (année)'); age = int(round(float(a.replace(',', '.')))) if a.replace(',', '').replace('.', '').isdigit() else None
    sx = g(r, 'Sexe').lower(); sex = 'M' if sx.startswith(('m', 'h')) else 'F' if sx.startswith('f') else 'U'
    cases.append(dict(d=d, st=st, region=g(r, 'Region'), sampled=g(r, 'Prélèvement (Oui ou Non)').lower().startswith('oui'), age=age, sex=sex))
f, t = dt.date.fromisoformat(FROM), dt.date.fromisoformat(TO)
per = [c for c in cases if f <= c['d'] <= t]
monday = t - dt.timedelta(days=t.weekday()) - dt.timedelta(weeks=2)
rf = max(monday, f); rec = [c for c in per if c['d'] >= rf]
def cnt(cs, s): return sum(1 for c in cs if c['st'] == s)
exp = {
 'cumul.total': len(per), 'cumul.confirmed': cnt(per, 'confirmed'), 'cumul.negative': cnt(per, 'negative'),
 'cumul.suspect': cnt(per, 'suspect'), 'cumul.probable': cnt(per, 'probable'), 'cumul.sampled': sum(c['sampled'] for c in per),
 'recent.total': len(rec), 'recent.confirmed': cnt(rec, 'confirmed'),
 'day.counts.total': sum(1 for c in per if c['d'] == t),
}
confrec = [c for c in rec if c['st'] == 'confirmed']
exp['age_sex.male'] = sum(c['sex'] == 'M' for c in confrec); exp['age_sex.female'] = sum(c['sex'] == 'F' for c in confrec)
ages = [c['age'] for c in confrec if c['age'] is not None]
exp['age_sex.median_age'] = statistics.median(ages) if ages else None
exp['recent_from'] = rf.isoformat()
out = json.loads(subprocess.check_output(['php', 'tests/report_dump.php', FROM, TO]))
def get(o, p):
    for k in p.split('.'): o = o[k]
    return o
bad = 0
for k, v in exp.items():
    got = get(out, k)
    if isinstance(v, float) or isinstance(got, float): ok = got is not None and v is not None and abs(float(got) - float(v)) < 1e-9
    else: ok = got == v
    if not ok: bad += 1
    print(('  ok   ' if ok else '  FAIL ') + f'{k}: php={got!r} py={v!r}')
reg_total = sum(r['total'] for r in out['regions']['rows'])
print(('  ok   ' if reg_total == len(rec) else '  FAIL ') + f'somme des régions = recent.total ({reg_total} / {len(rec)})'); bad += reg_total != len(rec)
dp = sum(out['daily_period']['notified']); print(('  ok   ' if dp == len(per) else '  FAIL ') + f'courbe période = cas de la période ({dp} / {len(per)})'); bad += dp != len(per)
sys.exit(1 if bad else 0)
