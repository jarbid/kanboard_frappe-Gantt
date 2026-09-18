#!/usr/bin/env bash
#
# Rebuild the vendored Frappe Gantt bundle from upstream source.
#
# The plugin ships a pre-built copy of the library in Assets/vendor so that
# Kanboard installations never need a JavaScript toolchain. This script
# regenerates that copy, which is how the vendored files should be upgraded.
#
# Usage: tools/build-vendor.sh [git-ref]
#
set -euo pipefail

REF="${1:-$(sed -n 's/^FRAPPE_GANTT_REF=//p' "$(dirname "$0")/../Assets/vendor/VERSION" 2>/dev/null)}"
REF="${REF:-5c83708d300a99d9142edea0d4d52f421e3a2e68}"

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

echo "==> Cloning frappe/gantt @ ${REF}"
git clone --quiet https://github.com/frappe/gantt "$WORK_DIR/gantt"
git -C "$WORK_DIR/gantt" checkout --quiet "$REF"

COMMIT="$(git -C "$WORK_DIR/gantt" rev-parse HEAD)"
VERSION="$(node -p "require('$WORK_DIR/gantt/package.json').version")"

# Fixes carried on top of upstream. Each patch must stay minimal and be
# described in README.md so the deviation from upstream is never a surprise.
if compgen -G "$PLUGIN_DIR/tools/patches/*.patch" > /dev/null; then
    echo "==> Applying local patches"
    for patch in "$PLUGIN_DIR"/tools/patches/*.patch; do
        echo "    $(basename "$patch")"
        git -C "$WORK_DIR/gantt" apply "$patch"
    done
fi

echo "==> Building (npm install && vite build)"
(cd "$WORK_DIR/gantt" && npm install --no-audit --no-fund --silent && npx vite build >/dev/null)

echo "==> Copying dist into Assets/vendor"
install -Dm644 "$WORK_DIR/gantt/dist/frappe-gantt.umd.js" "$PLUGIN_DIR/Assets/vendor/frappe-gantt.umd.js"
install -Dm644 "$WORK_DIR/gantt/dist/frappe-gantt.css"    "$PLUGIN_DIR/Assets/vendor/frappe-gantt.css"
install -Dm644 "$WORK_DIR/gantt/license.txt"              "$PLUGIN_DIR/Assets/vendor/LICENSE.frappe-gantt.txt"

cat > "$PLUGIN_DIR/Assets/vendor/VERSION" <<EOF
FRAPPE_GANTT_REF=${REF}
FRAPPE_GANTT_VERSION=${VERSION}
FRAPPE_GANTT_COMMIT=${COMMIT}
EOF

echo "==> Vendored frappe-gantt ${VERSION} (${COMMIT})"
