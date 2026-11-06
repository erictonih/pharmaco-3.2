#!/usr/bin/env bash
# SitRep : chiffres du SitRepService recalculés indépendamment en Python (semaine, mois, période libre).
set -u; cd "$(dirname "$0")/.."; export APP_ENV=local; rc=0
for a in "week 2026-W10" "week 2026-W02" "month 2026-02" "month 2026-01" "custom 2026-01-05 2026-02-01" "custom 2025-12-01 2026-03-15 2025-12-20"; do
  echo "SitRep $a"; python3 tests/sitrep_check.py $a || rc=1
done
exit $rc
