#!/usr/bin/env bash
set -euo pipefail
if [[ $# -ne 1 ]]; then
  echo 'Aufruf: bash bin/sync-network-data.sh /privater/pfad/ifis-network-data' >&2
  exit 1
fi
data_dir=$1
code_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
git -C "$data_dir" diff --quiet
git -C "$data_dir" diff --cached --quiet
git -C "$data_dir" pull --ff-only origin main
php "$code_dir/bin/import-network-data.php" "$data_dir"
