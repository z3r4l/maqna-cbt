# maqna-cbt

School/madrasah examination application. Verified delivery through S7 covers authentication, academic masters, question bank, exam publishing, attempt runner, grading, manual result release, imports, essay review, access codes, answer review, exports, audit, question revisions, equivalent A/B packages, proctor monitoring, and item analytics.

## Stack

- CodeIgniter 4.7.6, Shield 1.4.1, MySQL/MariaDB
- React 18, TypeScript, Vite 6, Tailwind 4
- KaTeX for offline math rendering, PhpSpreadsheet for XLSX, Dompdf for PDF
- Same-origin session API at `/api/v1`; the Vite dev proxy forwards `/api` to port 8081 by default

## Local setup

Create a dedicated local MySQL database. Never point the demo seeder or tests at production data.

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS maqna_cbt_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd backend
php -m  # ensure zip, dom, gd, mbstring and fileinfo are enabled
composer install
cp .env.example .env
# Set database.default.* in .env for your local database.
php spark migrate --all
php spark db:seed AcademicDemoSeeder
php -S 127.0.0.1:8081 -t public public/index.php
```

In another terminal:

```bash
cd frontend
npm ci
npm run dev -- --host 127.0.0.1
```

Open `http://127.0.0.1:5173`. The local seeder creates fake accounts named `admin_demo`, `teacher_demo`, `student_demo`, and `noemail_demo`; all use the fixture-only password `FixtureOnly123!`. Never use these accounts in production.

For a separate SMK sample bank, run `php spark db:seed SmkRplQuestionSeeder` from `backend/` after migrating. It creates the `SMK Contoh` school, class `X-RPL-1`, teacher `teacher_smk_rpl_demo` (same fixture-only password), and 20 **draft** Dasar RPL questions. Review and publish the questions before using them in an exam. The seeder can be rerun without duplicating the bank and refuses production environments.

On the supplied Windows Laragon PHP 8.5, `php_zip.dll` exists but is disabled in `php.ini`. For a local run without changing the global configuration, use `php -d extension=zip -S 127.0.0.1:8081 -t public public/index.php` and run Composer as `php -d extension=zip D:\laragon6\bin\composer\composer.phar install`. In deployment, enable the ZIP extension in the PHP runtime used by the web server. XLSX import/export returns 503 when it is unavailable; CSV and PDF remain available.

## Verification

```bash
cd backend
php spark migrate --all
php spark migrate --all
php vendor/bin/phpunit --no-coverage
python tests/run_all_smokes.py  # requires local server on 8081, MySQL CLI, fake fixtures, and ZIP
cd ../frontend
npm run build
npm run test:e2e         # requires local servers and Microsoft Edge or BROWSER_EXECUTABLE
```

`smoke_auth.py` modifies only fake local fixture accounts in `maqna_cbt_dev` and restores their passwords, active state, and school scope. It must not run against production.

## Operations

Run `php spark attempts:finalize-expired` regularly to close attempts whose server deadline passed. Schedule `php spark proctor:purge` daily; it removes tab visibility/focus events older than 30 days. The proctor screen refreshes every 15 seconds and shows a student as disconnected after 60 seconds without a heartbeat. Telemetry is informational and does not apply automatic sanctions. Item analytics requires at least five distinct students with completed, fully graded attempts; smaller samples are suppressed.

The old bearer-token overlay and mock exam runner were removed. For scope, security rules and actual progress, see `plan/control.json`, `plan/DECISIONS.md`, `plan/QA_REPORT.md`, and `plan/BLOCKERS.md`.
