#!/usr/bin/env bash
set -euo pipefail
task_root="$(cd "$(dirname "$0")/.." && pwd)"
task_node_version=24.15.0
task_archive="node-v${task_node_version}-linux-x64.tar.xz"
task_runtime="/opt/node-v${task_node_version}-linux-x64"
if ! command -v curl >/dev/null || ! command -v php >/dev/null || ! php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && extension_loaded("dom") ? 0 : 1);'; then
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y curl xz-utils php-cli php-sqlite3 php-mbstring php-xml php-curl php-intl php-zip
fi
if [[ ! -x "$task_runtime/bin/node" ]]; then
    task_download="$(mktemp -d)"
    curl -fsSL "https://nodejs.org/dist/v${task_node_version}/${task_archive}" -o "$task_download/$task_archive"
    curl -fsSL "https://nodejs.org/dist/v${task_node_version}/SHASUMS256.txt" -o "$task_download/SHASUMS256.txt"
    (cd "$task_download" && grep " ${task_archive}$" SHASUMS256.txt | sha256sum -c -)
    tar -xJf "$task_download/$task_archive" -C /opt
    ln -sf "$task_runtime/bin/node" /usr/local/bin/node
    ln -sf "$task_runtime/bin/npm" /usr/local/bin/npm
    ln -sf "$task_runtime/bin/npx" /usr/local/bin/npx
fi
export PATH="$task_runtime/bin:$PATH"
cd "$task_root"
# Keep the Windows install intact while adding the two Linux-only optional bindings.
mkdir -p .e2e/native
for task_binding in parser transform; do
    task_version="$(node -p "require('./node_modules/oxc-${task_binding}/package.json').version")"
    task_package="@oxc-${task_binding}/binding-linux-x64-gnu"
    task_tarball="$(npm pack "${task_package}@${task_version}" --pack-destination .e2e/native --silent)"
    mkdir -p "node_modules/$task_package"
    tar -xzf ".e2e/native/$task_tarball" -C "node_modules/$task_package" --strip-components=1
done
node --version
php --version | head -n 1
npx @e2e-dev/web install chromium --with-deps
