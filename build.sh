#!/usr/bin/env bash
# Build the installable package with 7-Zip.
#   bash build.sh            -> com_removesampledata_v<version>_<YYYYMMDD>_<HHMM>.zip (working copy)
#   bash build.sh --release  -> also copies it to com_removesampledata_v<version>.zip and prints the SHA-256
set -euo pipefail
cd "$(dirname "$0")"

SEVENZIP="${SEVENZIP:-/c/Program Files/7-Zip/7z.exe}"
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' com_removesampledata.xml | head -1)
[ -n "$VERSION" ] || { echo "No <version> in manifest" >&2; exit 1; }

# Every package carries the full changelog in both formats, plus the licence.
for f in CHANGELOG.md CHANGELOG.html LICENSE NOTICE README.md changelog.xml; do
  [ -f "$f" ] || { echo "Missing $f - refusing to build" >&2; exit 1; }
done
grep -q "Version $VERSION" CHANGELOG.md   || { echo "CHANGELOG.md has no entry for $VERSION" >&2; exit 1; }
grep -q "Version $VERSION" CHANGELOG.html || { echo "CHANGELOG.html has no entry for $VERSION" >&2; exit 1; }
grep -q "<version>$VERSION</version>" changelog.xml || { echo "changelog.xml has no entry for $VERSION" >&2; exit 1; }

STAMP=$(date +%Y%m%d_%H%M)
OUT="com_removesampledata_v${VERSION}_${STAMP}.zip"
rm -f "$OUT"
"$SEVENZIP" a -tzip -mx=9 "$OUT" \
  com_removesampledata.xml script.php admin media \
  CHANGELOG.md CHANGELOG.html changelog.xml LICENSE NOTICE README.md >/dev/null
echo "built $OUT"

if [ "${1:-}" = "--release" ]; then
  CLEAN="com_removesampledata_v${VERSION}.zip"
  cp -f "$OUT" "$CLEAN"
  echo "release $CLEAN"
  sha256sum "$CLEAN"
fi
