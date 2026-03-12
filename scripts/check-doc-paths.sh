#!/usr/bin/env bash
set -euo pipefail

missing=0

check_path() {
    local file="$1"
    local raw_path="$2"
    local path
    local dir

    path="${raw_path%%#*}"
    path="${path%%\?*}"

    if [ -z "$path" ]; then
        return
    fi

    case "$path" in
        http://*|https://*|mailto:*|tel:*|\#*)
            return
            ;;
    esac

    if [[ "$path" == app/* || "$path" == docs/* ]]; then
        if [ ! -e "$path" ]; then
            echo "Missing path referenced in ${file}: ${raw_path}"
            missing=1
        fi
        return
    fi

    if [[ "$path" == ./* || "$path" == ../* ]]; then
        dir="$(dirname "$file")"
        if ! (cd "$dir" && [ -e "$path" ]); then
            echo "Missing relative path referenced in ${file}: ${raw_path}"
            missing=1
        fi
    fi
}

while IFS= read -r file; do
    while IFS= read -r match; do
        path="${match#\`}"
        path="${path%\`}"
        check_path "$file" "$path"
    done < <(grep -oE '`(app|docs)/[^`]+`' "$file" || true)

    while IFS= read -r link; do
        path="${link#*](}"
        path="${path%)}"
        path="${path%% *}"
        check_path "$file" "$path"
    done < <(grep -oE '\[[^]]+\]\([^)]+\)' "$file" || true)
done < <(git ls-files 'README.md' 'docs/**/*.md' 'app/Containers/**/*.md')

exit "$missing"
