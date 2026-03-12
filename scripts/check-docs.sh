#!/usr/bin/env bash
set -euo pipefail

bash scripts/generate-containers-index.sh docs/containers-index.md auto-generated

git diff --exit-code -- docs/containers-index.md

bash scripts/check-container-readmes-schema.sh

bash scripts/check-doc-paths.sh

if command -v markdownlint-cli2 >/dev/null 2>&1; then
    markdownlint-cli2 README.md 'docs/**/*.md' 'app/Containers/**/*.md'
else
    echo "markdownlint-cli2 not found, skipping local markdown lint."
fi

if command -v lychee >/dev/null 2>&1; then
    lychee \
      --no-progress \
      --exclude-file .lycheeignore \
      --accept 200,206,429 \
      --max-concurrency 8 \
      README.md \
      docs/**/*.md \
      app/Containers/**/*.md
else
    echo "lychee not found, skipping local link check."
fi

echo "Docs checks passed."
