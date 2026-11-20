#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/backend"
if ! php -m | grep -qi '^zip$'; then
  printf 'PHP ZIP extension is required for XLSX import/export. Enable it before installing dependencies.\n' >&2
  exit 1
fi
composer install --no-interaction
if [ ! -f .env ]; then cp .env.example .env; fi
printf 'Configure a dedicated local MySQL database in backend/.env, then run:\n'
printf 'cd backend && php spark migrate --all && php spark db:seed AcademicDemoSeeder\n'
