#!/usr/bin/env bash
set -euo pipefail

LANG_DIR="${1:-languages}"
POT_FILE="${2:-languages/messages.pot}"

if [[ ! -d "$LANG_DIR" ]]; then
  echo "[i18n] language directory not found: $LANG_DIR" >&2
  exit 0
fi

if [[ ! -f "$POT_FILE" ]]; then
  echo "[i18n] POT file not found: $POT_FILE" >&2
  exit 1
fi

found_any=false
while IFS= read -r -d '' po_file; do
  found_any=true
  msgmerge --quiet --update --backup=off "$po_file" "$POT_FILE"
done < <(find "$LANG_DIR" -type f -name '*.po' -print0)

if [[ "$found_any" = false ]]; then
  echo "[i18n] no .po files found under $LANG_DIR" >&2
fi
