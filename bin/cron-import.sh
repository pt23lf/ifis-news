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
# HTTPS cron inherits the web-server PATH, where "php" can be an old CLI.
# Prefer a supported PHP CLI (>= 7.4) for arrow functions in bin/import.php.
php_supported() {
  [ -x "$1" ] && [ "$("$1" -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)" -ge 70400 ]
}
PHP_BIN="${PHP_BIN:-}"
if [ -n "$PHP_BIN" ] && ! php_supported "$PHP_BIN"; then PHP_BIN=""; fi
if [ -z "$PHP_BIN" ]; then
  for candidate in /usr/bin/php83 /usr/bin/php84 /usr/bin/php82 /usr/bin/php81 /usr/bin/php80 /usr/bin/php8.3 /usr/bin/php8.4 /usr/bin/php8.2 /usr/local/bin/php83 /usr/local/bin/php8.3 "$(command -v php 2>/dev/null || true)"; do
    if php_supported "$candidate"; then PHP_BIN="$candidate"; break; fi
  done
fi
if [ -z "$PHP_BIN" ]; then
  printf '[%s] ERROR: No PHP CLI >= 7.4 found. Check All-inkl CLI PHP path.\\n' "$(date -u +%FT%TZ)" >> storage/logs/import.log
  exit 1
fi
{
  printf '\n[%s] Import start\n' "$(date -u +%FT%TZ)"
  "$PHP_BIN" bin/import.php
  "$PHP_BIN" bin/import-gdelt.php || printf "[%s] GDELT import failed; previous observations retained\n" "$(date -u +%FT%TZ)"
  "$PHP_BIN" bin/import-ifis.php || printf "[%s] IFIS index refresh failed; previous index retained\\n" "$(date -u +%FT%TZ)"
  printf '[%s] Import finished\n' "$(date -u +%FT%TZ)"
} >> storage/logs/import.log 2>&1
