#!/usr/bin/env bash
set -euo pipefail
mkdir -p /root/instaparty-qa-binaries
cd /root/instaparty-qa-binaries
apt-get download mariadb-server-core mariadb-client-core libmariadb3 mariadb-common liburing2 > download.log 2>&1
for package in *.deb; do dpkg-deb -x "$package" extracted; done
export LD_LIBRARY_PATH="$PWD/extracted/usr/lib/x86_64-linux-gnu"
ldd extracted/usr/sbin/mariadbd
extracted/usr/sbin/mariadbd --no-defaults --version
