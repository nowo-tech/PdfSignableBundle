#!/usr/bin/env bash
# REQ-TWIG-005 — fail when Twig templates use raw <form> / <input> / <textarea> / <select>.
# Scope: bundle views and demo templates.
# Allowlist (form-theme exception): src/Resources/views/form/_signature_box_type_widget.html.twig is the body of the
# signature_box_widget theme block (form/theme.html.twig); its client-side image picker (<input type="file">, no name)
# is part of the widget implementation.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

dirs=()
[[ -d src/Resources/views ]] && dirs+=(src/Resources/views)
while IFS= read -r d; do dirs+=("$d"); done < <(find demo -type d -name templates -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null || true)

if [[ ${#dirs[@]} -eq 0 ]]; then
  echo "check-no-raw-html-form: no Twig templates — OK"
  exit 0
fi

matches="$(grep -RInE '<(form|input|textarea|select)([[:space:]>]|$)' "${dirs[@]}" --include='*.twig' \
  | grep -v '^src/Resources/views/form/_signature_box_type_widget\.html\.twig:' || true)"
if [[ -n "$matches" ]]; then
  echo "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):" >&2
  echo "$matches" >&2
  exit 1
fi

echo "check-no-raw-html-form: OK"
