#!/usr/bin/env bash
# Install the FrankenPHP dev kit into a Magento project root by creating
# symlinks from the project into this kit directory. Idempotent — safe to
# re-run. Refuses to overwrite real files (only replaces existing symlinks).
#
# Usage:
#   ./install.sh                     # install into the project root (two levels up)
#   ./install.sh /path/to/magento    # install into an explicit project root
#   ./install.sh --uninstall         # remove symlinks created by this script

set -euo pipefail

KIT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ─── Args ──────────────────────────────────────────────────────────────────
MODE="install"
TARGET=""
for arg in "$@"; do
  case "$arg" in
    --uninstall) MODE="uninstall" ;;
    -h|--help)
      sed -n '2,11p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    -*)
      echo "unknown option: $arg" >&2; exit 2 ;;
    *)
      TARGET="$arg" ;;
  esac
done

# Default target: walk up from kit dir until we find a Magento root
# (has app/etc/ and pub/), or fall back to two levels up.
if [[ -z "$TARGET" ]]; then
  TARGET="$KIT_DIR"
  while [[ "$TARGET" != "/" ]]; do
    TARGET="$(dirname "$TARGET")"
    if [[ -d "$TARGET/app/etc" && -d "$TARGET/pub" ]]; then break; fi
  done
  if [[ "$TARGET" == "/" ]]; then
    echo "Could not auto-detect Magento root. Pass it explicitly:" >&2
    echo "  $0 /path/to/magento" >&2
    exit 1
  fi
fi
TARGET="$(cd "$TARGET" && pwd)"

# Sanity check the target.
if [[ ! -d "$TARGET/app/etc" || ! -d "$TARGET/pub" ]]; then
  echo "Target does not look like a Magento root (missing app/etc or pub):" >&2
  echo "  $TARGET" >&2
  exit 1
fi

# ─── Link table: "<src under KIT_DIR>::<dst under TARGET>" ────────────────
# Order matters — parent dirs first so children resolve when listed.
LINKS=(
  "bin/mage-worker::bin/mage-worker"
  "bin/dev-watch::bin/dev-watch"
  "pub/frankenphp-worker.php::pub/frankenphp-worker.php"
  "docker-compose.yml::docker-compose.frankenphp.yml"
  "docker::docker"
)

# ─── Helpers ──────────────────────────────────────────────────────────────
relpath() {
  # macOS has no `realpath --relative-to`. Use python for portability.
  python3 -c 'import os,sys;print(os.path.relpath(sys.argv[1], sys.argv[2]))' "$1" "$2"
}

install_link() {
  local src_rel="$1" dst_rel="$2"
  local src_abs="$KIT_DIR/$src_rel"
  local dst_abs="$TARGET/$dst_rel"
  local dst_parent
  dst_parent="$(dirname "$dst_abs")"

  if [[ ! -e "$src_abs" ]]; then
    echo "  skip (source missing): $src_rel" >&2
    return
  fi

  mkdir -p "$dst_parent"
  local src_rel_to_parent
  src_rel_to_parent="$(relpath "$src_abs" "$dst_parent")"

  if [[ -L "$dst_abs" ]]; then
    # Already a symlink — update it (handles kit relocation).
    ln -sfn "$src_rel_to_parent" "$dst_abs"
    echo "  updated  $dst_rel -> $src_rel_to_parent"
  elif [[ -e "$dst_abs" ]]; then
    # Real file/dir in the way — refuse rather than clobber user work.
    echo "  REFUSE   $dst_rel exists and is not a symlink (leave it or back it up first)" >&2
    return 1
  else
    ln -s "$src_rel_to_parent" "$dst_abs"
    echo "  created  $dst_rel -> $src_rel_to_parent"
  fi
}

uninstall_link() {
  local dst_rel="$1"
  local dst_abs="$TARGET/$dst_rel"
  if [[ -L "$dst_abs" ]]; then
    rm -f "$dst_abs"
    echo "  removed  $dst_rel"
  elif [[ -e "$dst_abs" ]]; then
    echo "  skip     $dst_rel is not a symlink — leaving it alone" >&2
  fi
}

# ─── Run ──────────────────────────────────────────────────────────────────
echo "kit:    $KIT_DIR"
echo "target: $TARGET"
echo

if [[ "$MODE" == "install" ]]; then
  echo "installing symlinks:"
  fail=0
  for entry in "${LINKS[@]}"; do
    src="${entry%%::*}"
    dst="${entry##*::}"
    install_link "$src" "$dst" || fail=1
  done
  echo
  if (( fail )); then
    echo "Some links could not be created (see REFUSE lines above)." >&2
    exit 1
  fi
  echo "Done."
  echo
  echo "Next steps:"
  echo "  1. Bring the stack up:   docker compose -f docker-compose.frankenphp.yml up -d"
  echo "  2. Start the watcher:    ./bin/dev-watch"
else
  echo "removing symlinks:"
  for entry in "${LINKS[@]}"; do
    dst="${entry##*::}"
    uninstall_link "$dst"
  done
  echo
  echo "Done."
fi
