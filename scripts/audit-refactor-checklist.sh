#!/usr/bin/env bash
set -euo pipefail

output_file="${1:-.ai/botble/tools/data-synchronize/audit-report.md}"
report_dir="$(dirname "$output_file")"
mkdir -p "$report_dir"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

scan_roots=(app/Containers/AppSection app/Ship)
duplicate_signature_allowlist=(
    "GET /"
)

scan_service_locator() {
    rg \
      --line-number \
      --no-heading \
      --color=never \
      --glob '!**/Tests/**' \
      --glob '!**/Data/Factories/**' \
      --glob '!**/Data/Seeders/**' \
      --glob '!**/Data/Migrations/**' \
      --glob '!**/*.blade.php' \
      -e '\bapp\s*\(' \
      -e '\bresolve\s*\(' \
      -e 'Container::getInstance\(\)->make\s*\(' \
      -e '\bApp::make\s*\(' \
      "${scan_roots[@]}" || true
}

scan_raw_query() {
    rg \
      --line-number \
      --no-heading \
      --color=never \
      --glob '!**/Tests/**' \
      --glob '!**/Data/Migrations/**' \
      -e 'DB::(select|statement|unprepared|raw)\s*\(' \
      -e '(->)(whereRaw|orWhereRaw|havingRaw|orderByRaw|groupByRaw)\s*\(' \
      -e 'DB::table\s*\(' \
      "${scan_roots[@]}" || true
}

scan_route_without_middleware() {
    while IFS= read -r route_file; do
        [ -f "$route_file" ] || continue

        route_count="$(grep -Ec 'Route::(get|post|put|patch|delete|match|any)\(' "$route_file" || true)"
        [ "$route_count" -gt 0 ] || continue

        middleware_count="$(grep -Ec -- '->middleware\(' "$route_file" || true)"
        if [ "$middleware_count" -eq 0 ]; then
            printf '%s:%s\n' "$route_file" "$route_count"
        fi
    done < <(find app/Containers/AppSection -type f -path '*/UI/API/Routes/*.php' | sort)
}

scan_duplicate_route_paths() {
    rg \
      --no-filename \
      --only-matching \
      --color=never \
      --glob '**/UI/API/Routes/*.php' \
      -e "Route::(get|post|put|patch|delete|match|any)\\('[^']+'" \
      app/Containers/AppSection \
    | sed -E "s/Route::(get|post|put|patch|delete|match|any)\\('([^']+)'.*/\\1 \\2/" \
    | awk '{print toupper($1) " " $2}' \
    | sort \
    | uniq -cd \
    | awk '$1 > 1' || true
}

scan_duplicate_where_constraints() {
    rg \
      --no-filename \
      --only-matching \
      --color=never \
      --glob '**/UI/API/Routes/*.php' \
      -e "(->)where\\([^)]*\\)" \
      app/Containers/AppSection \
    | sort \
    | uniq -cd \
    | awk '$1 > 1' || true
}

scan_service_locator > "$tmp_dir/service_locator.txt"
scan_raw_query > "$tmp_dir/raw_query.txt"
scan_route_without_middleware > "$tmp_dir/routes_no_middleware.txt"
scan_duplicate_route_paths > "$tmp_dir/duplicate_route_paths_raw.txt"
scan_duplicate_where_constraints > "$tmp_dir/duplicate_where_constraints.txt"

touch "$tmp_dir/duplicate_route_paths.txt"
touch "$tmp_dir/duplicate_route_paths_ignored.txt"
while IFS= read -r duplicate_line; do
    [ -n "$duplicate_line" ] || continue

    signature="$(sed -E 's/^[[:space:]]*[0-9]+[[:space:]]+//' <<< "$duplicate_line")"
    ignored=false
    for allowlisted_signature in "${duplicate_signature_allowlist[@]}"; do
        if [ "$signature" = "$allowlisted_signature" ]; then
            ignored=true
            break
        fi
    done

    if [ "$ignored" = true ]; then
        echo "$duplicate_line" >> "$tmp_dir/duplicate_route_paths_ignored.txt"
    else
        echo "$duplicate_line" >> "$tmp_dir/duplicate_route_paths.txt"
    fi
done < "$tmp_dir/duplicate_route_paths_raw.txt"

service_locator_count="$(wc -l < "$tmp_dir/service_locator.txt" | tr -d ' ')"
raw_query_count="$(wc -l < "$tmp_dir/raw_query.txt" | tr -d ' ')"
routes_no_middleware_count="$(wc -l < "$tmp_dir/routes_no_middleware.txt" | tr -d ' ')"
duplicate_route_paths_count="$(wc -l < "$tmp_dir/duplicate_route_paths.txt" | tr -d ' ')"
ignored_duplicate_route_paths_count="$(wc -l < "$tmp_dir/duplicate_route_paths_ignored.txt" | tr -d ' ')"
duplicate_where_constraints_count="$(wc -l < "$tmp_dir/duplicate_where_constraints.txt" | tr -d ' ')"

generated_at="$(date '+%Y-%m-%d %H:%M:%S %z')"

{
    echo "# Refactor Audit Report"
    echo
    echo "Generated at: \`${generated_at}\`"
    echo
    echo "## Summary"
    echo
    echo "- Service locator candidates: \`${service_locator_count}\`"
    echo "- Raw query candidates: \`${raw_query_count}\`"
    echo "- API route files without explicit middleware: \`${routes_no_middleware_count}\`"
    echo "- Duplicate API route signatures (method + path, count > 1): \`${duplicate_route_paths_count}\`"
    echo "- Ignored duplicate API route signatures (allowlist): \`${ignored_duplicate_route_paths_count}\`"
    echo "- Duplicate route where constraints (count > 1): \`${duplicate_where_constraints_count}\`"
    echo

    echo "## Service Locator Candidates"
    echo
    if [ "$service_locator_count" -eq 0 ]; then
        echo "No findings."
    else
        echo '```text'
        sed -n '1,400p' "$tmp_dir/service_locator.txt"
        echo '```'
    fi
    echo

    echo "## Raw Query Candidates"
    echo
    if [ "$raw_query_count" -eq 0 ]; then
        echo "No findings."
    else
        echo '```text'
        sed -n '1,400p' "$tmp_dir/raw_query.txt"
        echo '```'
    fi
    echo

    echo "## API Route Files Without Explicit Middleware"
    echo
    if [ "$routes_no_middleware_count" -eq 0 ]; then
        echo "No findings."
    else
        echo "Format: \`<route_file>:<route_count>\`"
        echo
        echo '```text'
        sed -n '1,400p' "$tmp_dir/routes_no_middleware.txt"
        echo '```'
    fi
    echo

    echo "## Duplicate API Route Signatures"
    echo
    if [ "$duplicate_route_paths_count" -eq 0 ]; then
        echo "No findings."
    else
        echo "Format: \`<count> <METHOD PATH>\`"
        echo
        echo '```text'
        sed -n '1,200p' "$tmp_dir/duplicate_route_paths.txt"
        echo '```'
    fi
    echo

    echo "## Ignored Duplicate API Route Signatures"
    echo
    if [ "$ignored_duplicate_route_paths_count" -eq 0 ]; then
        echo "No findings."
    else
        echo "Format: \`<count> <METHOD PATH>\`"
        echo
        echo '```text'
        sed -n '1,200p' "$tmp_dir/duplicate_route_paths_ignored.txt"
        echo '```'
    fi
    echo

    echo "## Duplicate Route Where Constraints"
    echo
    if [ "$duplicate_where_constraints_count" -eq 0 ]; then
        echo "No findings."
    else
        echo "Format: \`<count> <constraint_expression>\`"
        echo
        echo '```text'
        sed -n '1,200p' "$tmp_dir/duplicate_where_constraints.txt"
        echo '```'
    fi
    echo

    echo "## Notes"
    echo
    echo "- Report is heuristic; each finding requires manual review before refactor."
    echo "- Excludes tests/migrations/seeders/factories for service-locator scan."
    echo "- Route middleware check is file-level and may produce false positives when middleware is applied by route groups/providers."
} > "$output_file"

echo "Generated refactor audit report: $output_file"
