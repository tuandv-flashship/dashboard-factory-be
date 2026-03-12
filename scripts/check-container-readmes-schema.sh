#!/usr/bin/env bash
set -euo pipefail

required_sections=(
    "### Scope"
    "### API Routes"
    "### Main Config"
    "### Operational Notes"
    "### Tests"
    "### Change Log"
)

errors=0

for file in app/Containers/AppSection/*/README.md; do
    [ -f "$file" ] || continue

    first_heading="$(grep -m1 -E '^### ' "$file" || true)"
    if [[ ! "$first_heading" =~ ^###\ .+\ Container$ ]]; then
        echo "Invalid title heading in ${file}: expected '### <Name> Container'"
        errors=1
    fi

    for section in "${required_sections[@]}"; do
        if ! grep -Fxq "$section" "$file"; then
            echo "Missing section in ${file}: ${section}"
            errors=1
        fi
    done
done

exit "$errors"
