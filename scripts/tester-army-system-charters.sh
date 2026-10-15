#!/usr/bin/env bash
set -uo pipefail
task_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$task_root"
mkdir -p .e2e/system-audit/explore
if curl -fsS http://127.0.0.1:18090/up >/dev/null 2>&1; then
  echo 'Port 18090 is occupied; stop this audit instead of reusing an unknown app.'
  exit 1
fi
node tests/tester-army/serve.mjs 18090 > .e2e/system-audit/explore/shared-app.log 2>&1 &
task_server=$!
trap 'kill "$task_server" 2>/dev/null || true' EXIT INT TERM
task_ready=0
for ((task_attempt=0; task_attempt<900; task_attempt++)); do
  if curl -fsS http://127.0.0.1:18090/up >/dev/null 2>&1; then task_ready=1; break; fi
  if ! kill -0 "$task_server" 2>/dev/null; then echo 'Shared app exited before readiness.'; exit 1; fi
  sleep 1
done
if [[ "$task_ready" != 1 ]]; then echo 'Shared app did not become ready.'; exit 1; fi
run_charter() {
  local task_slug="$1" task_target="$2" task_goal="$3" task_status
  echo "Starting charter: $task_slug"
  E2E_AUDIT_TARGET="$task_target" npx e2e explore "$task_goal" --config e2e.explore.config.ts --target "$task_target" \
    --max-steps 3 --timeout 300000 --reporter list,markdown --output ".e2e/system-audit/explore/$task_slug" \
    < /dev/null > ".e2e/system-audit/explore/$task_slug.log" 2>&1
  task_status=$?
  echo "$task_status" > ".e2e/system-audit/explore/$task_slug.status"
  echo "Finished charter: $task_slug (exit $task_status)"
}
run_charter discovery desktop 'Starting at /en/search, search each seeded product by name, exercise product-type and price filters, open the matching service, and cross-check names, prices and filtering against its details; report broken behavior with reproduction steps.' &
task_jobs=("$!")
run_charter arabic-vendor desktop 'Starting at /ar/vendors, find the seeded vendor, open its profile, inspect governorate labels and the rental/sale/digital filter; verify each result belongs to this vendor and Arabic direction and labels work; report reproducible inconsistencies.' &
task_jobs+=("$!")
run_charter auth desktop 'Starting at /en/auth/login, try submitting empty fields and one unknown account with an incorrect password, then inspect /ar/auth/register with empty and malformed fields without completing registration; check visible validation, localization and recovery links.' &
task_jobs+=("$!")
run_charter planner desktop 'Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the start and a past start, and inspect Arabic equivalents without submitting a booking; report invalid transitions or missing validation.' &
task_jobs+=("$!")
for task_job in "${task_jobs[@]}"; do wait "$task_job" || true; done
run_charter mobile mobile 'At 390px width starting at /ar, exercise navigation, search and vendor/service details, check readable RTL text, usable controls and horizontal overflow, then switch to English; report reproducible responsive or navigation problems.'
