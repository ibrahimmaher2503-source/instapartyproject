#!/usr/bin/env bash
set -euo pipefail
cd /mnt/c/projects/instapartyproject
for archive in .e2e/risk-program/mysql/source.tar .e2e/risk-program/mysql/run-*/source.tar; do
  [ -f "$archive" ] || continue
  if tar -tf "$archive" database/database.sqlite >/dev/null 2>&1; then
    tar --delete -f "$archive" database/database.sqlite
    echo "Removed unused copied database from $archive"
  fi
done
for database_copy in /tmp/iqa-mysql-*/app/database/database.sqlite; do
  [ -f "$database_copy" ] || continue
  case "$database_copy" in /tmp/iqa-mysql-*/app/database/database.sqlite) rm -- "$database_copy";; *) exit 1;; esac
done
