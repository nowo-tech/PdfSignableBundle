#!/usr/bin/env sh
set -eu

RAW_FILE="${1:-coverage-ts.txt}"

if [ ! -f "$RAW_FILE" ]; then
  echo "ERROR: coverage output file not found: $RAW_FILE" >&2
  exit 1
fi

# Strip ANSI sequences and parse Vitest "All files" row.
VALUE="$(
  sed 's/\x1B\[[0-9;]*[A-Za-z]//g' "$RAW_FILE" \
    | awk '
      /^\s*All files\s*\|/ {
        n = split($0, parts, "|")
        if (n >= 6) {
          for (i = 2; i <= 5; i++) {
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", parts[i])
            gsub(/%/, "", parts[i])
            vals[i-1] = parts[i] + 0
          }
          min = vals[1]
          for (j = 2; j <= 4; j++) if (vals[j] < min) min = vals[j]
          printf "%.2f", min
          found = 1
          exit
        }
      }
      END {
        if (!found) exit 1
      }
    '
)"

if [ -z "${VALUE:-}" ]; then
  echo "ERROR: Could not extract TS coverage summary from ${RAW_FILE}" >&2
  exit 1
fi

if [ -t 1 ]; then
  RED="$(printf '\033[31m')"
  ORANGE="$(printf '\033[38;5;208m')"
  GREEN="$(printf '\033[32m')"
  RESET="$(printf '\033[0m')"
else
  RED=""
  ORANGE=""
  GREEN=""
  RESET=""
fi

COLOR="$GREEN"
if awk "BEGIN { exit !(${VALUE} < 50) }"; then
  COLOR="$RED"
elif awk "BEGIN { exit !(${VALUE} <= 85) }"; then
  COLOR="$ORANGE"
fi

printf 'Global TS coverage (min of Statements/Branches/Functions/Lines): %s%s%%%s\n' "$COLOR" "$VALUE" "$RESET"
