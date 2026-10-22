#!/bin/bash
# Buduje paczkę Slackware (.txz) z zawartości source/ dla pluginu unifi-site-scan.
# Uruchamiać na serwerze unRAID (lub systemie z makepkg) w katalogu repo.
set -e

VERSION="${1:?Podaj wersję, np. ./pkg_build.sh 2026.10.22}"
NAME="unifi-site-scan"
BUILD_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$BUILD_DIR/source"
OUT_DIR="$BUILD_DIR/releases"
PKG_FILE="$OUT_DIR/${NAME}-${VERSION}.txz"

mkdir -p "$OUT_DIR"

if ! command -v makepkg >/dev/null 2>&1; then
    echo "Brak 'makepkg' — uruchom ten skrypt na serwerze unRAID (lub Slackware)." >&2
    exit 1
fi

cd "$SRC_DIR"
makepkg -l y -c n "$PKG_FILE"

MD5=$(md5sum "$PKG_FILE" | awk '{print $1}')
echo ""
echo "Zbudowano: $PKG_FILE"
echo "MD5: $MD5"
echo ""
echo "Wklej powyższe MD5 do pliku ${NAME}.plg -> <!ENTITY packageMD5 \"$MD5\">"
