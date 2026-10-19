#!/usr/bin/env bash
set -euo pipefail
cd /mnt/c/projects/instapartyproject
project=$PWD
output="$project/.e2e/risk-program/mysql/run-$(date +%s)-$$"
mkdir -p "$output"
runtime=$(mktemp -d /tmp/iqa-mysql-XXXXXXXX)
chmod 700 "$runtime"
mkdir -p "$runtime/data" "$runtime/app"
cleanup() {
  if [ -S "$runtime/mysql.sock" ]; then mysqladmin --no-defaults --socket="$runtime/mysql.sock" -u root shutdown >/dev/null 2>&1 || true; fi
  # Keep evidence/source for independent review; never remove shared databases.
}
trap cleanup EXIT INT TERM
if [ "${1:-mysql}" = mariadb ]; then
  export LD_LIBRARY_PATH=/root/instaparty-qa-binaries/extracted/usr/lib/x86_64-linux-gnu
  daemon=/root/instaparty-qa-binaries/extracted/usr/sbin/mariadbd
  /root/instaparty-qa-binaries/extracted/usr/bin/mariadb-install-db --no-defaults --user=root --auth-root-authentication-method=normal --basedir=/root/instaparty-qa-binaries/extracted/usr --datadir="$runtime/data" > "$output/initialize.log" 2>&1
else
  daemon=mysqld
  "$daemon" --no-defaults --initialize-insecure --user=root --datadir="$runtime/data" > "$output/initialize.log" 2>&1
fi
"$daemon" --no-defaults --user=root --datadir="$runtime/data" --socket="$runtime/mysql.sock" --pid-file="$runtime/mysql.pid" --skip-networking --default-time-zone='+00:00' --log-error="$runtime/mysql.log" &
for attempt in $(seq 1 60); do
  if mysqladmin --no-defaults --socket="$runtime/mysql.sock" -u root ping >/dev/null 2>&1; then break; fi
  sleep 1
done
mysqladmin --no-defaults --socket="$runtime/mysql.sock" -u root ping >/dev/null
database="instaparty_qa_$(basename "$runtime" | tr -cd 'a-zA-Z0-9' | tr 'A-Z' 'a-z')"
mysql --no-defaults --socket="$runtime/mysql.sock" -u root -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Copy code only, no .env, private uploads or existing application database.
tar.exe -cf "${output#"$project/"}/source.tar" --exclude=bootstrap/cache --exclude=public/storage app bootstrap config lang routes resources public database/factories database/seeders database/migrations tests/Fakes tests/TestCase.php qa-audit/risk-program/tests artisan composer.json
tar -xf "$output/source.tar" -C "$runtime/app"
vendor_archive=$(find .e2e/app -maxdepth 1 -name 'vendor-*.tar' -print -quit)
test -n "$vendor_archive"
tar -xf "$vendor_archive" -C "$runtime/app" vendor
cp "$project/qa-audit/risk-program/phpunit.mysql.xml" "$runtime/app/phpunit.qa.xml"
mkdir -p "$runtime/app/storage/framework/"{cache/data,sessions,views} "$runtime/app/storage/logs" "$runtime/app/bootstrap/cache"
printf '# Generated disposable test environment; configuration is supplied by the runner.\n' > "$runtime/app/.env"
export APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE="$database" DB_SOCKET="$runtime/mysql.sock" DB_USERNAME=root DB_PASSWORD='' DB_URL=''
export APP_KEY="base64:$(openssl rand -base64 32)" QA_OUTPUT="$output" QA_RUNTIME="$runtime"
export APP_CONFIG_CACHE="$runtime/app/bootstrap/cache/config.php" APP_SERVICES_CACHE="$runtime/app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$runtime/app/bootstrap/cache/packages.php"
export APP_ROUTES_CACHE="$runtime/app/bootstrap/cache/routes.php" APP_EVENTS_CACHE="$runtime/app/bootstrap/cache/events.php" MAIL_MAILER=array QUEUE_CONNECTION=sync CACHE_STORE=file SESSION_DRIVER=array
export BROADCAST_CONNECTION=null SCOUT_DRIVER=null NIGHTWATCH_ENABLED=false TELESCOPE_ENABLED=false PULSE_ENABLED=false CATALOG_DIGITAL_SERVICES_ENABLED=true
php -r '$data=["php"=>PHP_VERSION,"database"=>getenv("DB_DATABASE"),"socket_only"=>true,"runtime"=>getenv("QA_RUNTIME"),"output"=>getenv("QA_OUTPUT")]; file_put_contents(getenv("QA_OUTPUT")."/environment.json", json_encode($data, JSON_PRETTY_PRINT)); file_put_contents(dirname(getenv("QA_OUTPUT"))."/last-run.json",json_encode($data));'
cd "$runtime/app"
set +e
selection=()
if [ -n "${QA_TEST_FILTER:-}" ]; then selection=(--filter "$QA_TEST_FILTER"); fi
php vendor/bin/pest --configuration phpunit.qa.xml "${QA_TEST_FILE:-qa-audit/risk-program/tests/FinancialPilotTest.php}" "${selection[@]}" --display-warnings --log-junit "$output/junit.xml" > "$output/run.log" 2>&1
exit_code=$?
set -e
cat "$output/run.log"
exit "$exit_code"
