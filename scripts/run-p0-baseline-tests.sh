#!/usr/bin/env bash
set -euo pipefail

report_file="${1:-.ai/botble/tools/data-synchronize/p0-baseline-test-report.md}"
report_dir="$(dirname "$report_file")"
mkdir -p "$report_dir"

php_bin="${PHP_BIN:-php}"
php_ini="${PHP_INI:-}"
php_cmd=("$php_bin")
if [ -n "$php_ini" ]; then
  php_cmd+=(-c "$php_ini")
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

test_targets=(
  "app/Containers/AppSection/Authentication/Tests/Functional/API"
  "app/Containers/AppSection/Authorization/Tests/Functional/API"
  "app/Containers/AppSection/User/Tests/Functional/API"
  "app/Containers/AppSection/RequestLog/Tests/Functional/API"
  "app/Containers/AppSection/AuditLog/Tests/Functional/API"
  "app/Containers/AppSection/Member/Tests/Functional/API"
  "app/Containers/AppSection/Device/Tests/Functional/API"
)

available_targets=()
for target in "${test_targets[@]}"; do
  if [ -d "$target" ]; then
    available_targets+=("$target")
  fi
done

if [ "${#available_targets[@]}" -eq 0 ]; then
  {
    echo "# P0 Baseline Test Report"
    echo
    echo "No available Functional/API test directories found for P0 containers."
  } > "$report_file"

  echo "Generated P0 baseline report: $report_file"
  exit 0
fi

generated_at="$(date '+%Y-%m-%d %H:%M:%S %z')"
pass_count=0
fail_count=0

for target in "${available_targets[@]}"; do
  slug="$(echo "$target" | tr '/ ' '__')"
  output_file="$tmp_dir/${slug}.log"

  if "${php_cmd[@]}" artisan test "$target" --colors=never > "$output_file" 2>&1; then
    echo "PASS $target" >> "$tmp_dir/summary.txt"
    pass_count=$((pass_count + 1))
  else
    echo "FAIL $target" >> "$tmp_dir/summary.txt"
    fail_count=$((fail_count + 1))
  fi
done

{
  echo "# P0 Baseline Test Report"
  echo
  echo "Generated at: \`${generated_at}\`"
  echo
  echo "## Summary"
  echo
  echo "- Total targets: \`${#available_targets[@]}\`"
  echo "- Passed: \`${pass_count}\`"
  echo "- Failed: \`${fail_count}\`"
  echo

  echo "## Target Results"
  echo
  while IFS= read -r row; do
    status="${row%% *}"
    target="${row#* }"
    if [ "$status" = "PASS" ]; then
      echo "- [x] \`${target}\`"
    else
      echo "- [ ] \`${target}\`"
    fi
  done < "$tmp_dir/summary.txt"
  echo

  echo "## Failed Output (truncated)"
  echo
  if [ "$fail_count" -eq 0 ]; then
    echo "No failures."
  else
    while IFS= read -r row; do
      status="${row%% *}"
      target="${row#* }"
      [ "$status" = "FAIL" ] || continue

      slug="$(echo "$target" | tr '/ ' '__')"
      output_file="$tmp_dir/${slug}.log"

      echo "### \`${target}\`"
      echo
      echo '```text'
      sed -n '1,220p' "$output_file"
      echo '```'
      echo
    done < "$tmp_dir/summary.txt"
  fi
} > "$report_file"

echo "Generated P0 baseline report: $report_file"

if [ "$fail_count" -gt 0 ]; then
  exit 1
fi
