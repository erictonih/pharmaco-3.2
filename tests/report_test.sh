#!/usr/bin/env bash
# Point de situation : chiffres du ReportService recalculés indépendamment en Python, sur 3 périodes.
set -u; cd "$(dirname "$0")/.."; rc=0
for p in "2025-12-01 2026-03-15" "2025-10-29 2026-03-31" "2026-02-01 2026-03-20"; do
  echo "Période $p"; python3 tests/report_check.py $p || rc=1
done
exit $rc
