#!/usr/bin/env bash
set -euo pipefail

output_file="${1:-docs/containers-index.md}"
last_updated="${2:-auto-generated}"
containers_root="app/Containers/AppSection"

note_for_container() {
    case "$1" in
        AuditLog) echo "API + config + operations notes" ;;
        Authentication) echo "Auth routes + Passport/token config + tests" ;;
        Authorization) echo "RBAC routes + permission config + tests" ;;
        Blog) echo "Posts/categories/tags CRUD + translation flows" ;;
        CustomField) echo "Field group CRUD and custom field boxes" ;;
        Device) echo "Signature, security, production baseline" ;;
        Gallery) echo "Gallery CRUD + translation update" ;;
        Language) echo "Language management + default/current language" ;;
        LanguageAdvanced) echo "Advanced localization and slug translation" ;;
        Media) echo "Media routes/config/tests" ;;
        Member) echo "Auth/profile/social/admin flows" ;;
        MetaBox) echo "Shared metadata support container" ;;
        Page) echo "Page CRUD + translation flows" ;;
        RequestLog) echo "Log routes/config notes" ;;
        Revision) echo "Revision listing/history support" ;;
        Setting) echo "General/media/appearance/phone settings" ;;
        Slug) echo "Slug generation and uniqueness support" ;;
        System) echo "System commands/cache/info" ;;
        Tools) echo "Data synchronize import/export endpoints" ;;
        Translation) echo "Locale/group/json translation management" ;;
        User) echo "User admin/profile routes + tests" ;;
        *) echo "Container README available" ;;
    esac
}

{
    echo "### Containers Index"
    echo
    echo "Last updated: \`${last_updated}\`"
    echo
    echo "| Container | README | Status | Notes |"
    echo "|---|---|---|---|"

    while IFS= read -r container; do
        readme_path="${containers_root}/${container}/README.md"

        if [ -f "$readme_path" ]; then
            status='Ready'
            readme_cell="\`${readme_path}\`"
            notes="$(note_for_container "$container")"
        else
            status='Pending'
            readme_cell='-'
            notes='Add container README'
        fi

        echo "| \`${container}\` | ${readme_cell} | \`${status}\` | ${notes} |"
    done < <(find "$containers_root" -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | sort)

    echo
    echo "### Status Legend"
    echo
    echo "- \`Ready\`: Container has a dedicated README with actionable guidance."
    echo "- \`Pending\`: Container README is missing and should be created."
} > "$output_file"
