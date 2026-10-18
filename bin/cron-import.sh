#!/bin/sh
# IFIS News: scheduled RSS import. Run via All-inkl KAS cron every 30 minutes.
set -eu
ROOT="/www/htdocs/w0221385/news.ifis.net/repo"
cd "$ROOT"
mkdir -p storage/logs
# Prevent overlapping imports; mkdir is atomic on the same filesystem.
LOCK="$ROOT/storage/.import-cron.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
  # Recover only a clearly stale lock (> 2 hours); avoid removing an active run.
  if [ -f "$LOCK/started" ] && [ "$(find "$LOCK/started" -mmin +120 -print)" ]; then
    rm -rf "$LOCK"
    mkdir "$LOCK" 2>/dev/null || exit 0
  else
    exit 0
  fi
fi
date -u +%FT%TZ > "$LOCK/started"
trap 'rm -rf "$LOCK"' EXIT HUP INT TERM
PHP_BIN="${PHP_BIN:-$(command -v php)}"
{
  printf '\n[%s] Import start\n' "$(date -u +%FT%TZ)"
  "$PHP_BIN" bin/import.php
  printf '[%s] Import finished\n' "$(date -u +%FT%TZ)"
} >> storage/logs/import.log 2>&1
