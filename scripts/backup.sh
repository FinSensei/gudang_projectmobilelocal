#!/bin/bash
# Backup database gudang.
#   bash /home/arfin/gudang/backup.sh <timestamp> [folder_tujuan]
# Default folder: /mnt/d/Backup Gudang  (D:\Backup Gudang)
set -u

cd /home/arfin/gudang || exit 1

STAMP="${1:-$(date +%Y-%m-%d_%H%M)}"
OUTDIR="${2:-/mnt/d/Backup Gudang}"

mkdir -p "$OUTDIR" 2>/dev/null
if [ ! -d "$OUTDIR" ]; then
  echo "ERROR: folder tujuan tidak bisa dibuat: $OUTDIR"
  exit 4
fi

OUT="$OUTDIR/backup-${STAMP}.sql"

PW="$(grep '^DB_PASSWORD=' .env | cut -d= -f2)"
if [ -z "$PW" ]; then
  echo "ERROR: DB_PASSWORD tidak ditemukan di .env"
  exit 2
fi

./vendor/bin/sail exec -T mysql mysqldump -usail -p"$PW" gudang > "$OUT" 2>/dev/null
RC=$?

if [ $RC -ne 0 ] || [ ! -s "$OUT" ]; then
  echo "ERROR: backup gagal (rc=$RC)"
  rm -f "$OUT"
  exit 3
fi

echo "OK $(du -h "$OUT" | cut -f1) $OUT"
exit 0
