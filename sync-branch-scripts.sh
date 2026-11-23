#!/usr/bin/env bash
# =============================================================
# sync-branch-scripts.sh
# Copies the branch scripts from here (the master copies) into other
# repositories, so every Slim Killer repository works the same way:
#
#   ./sync-branch-scripts.sh ../slim-killer-forms ../slim-killer-monate-media
#
# Copies feature-branch-start.sh, feature-branch-stop.sh and
# feature-branch-types.sh. Never touches a repository's own
# feature-branch-gates.sh, which says what "tested" means there.
# Commit the copies in each repository afterwards.
# =============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FILES="feature-branch-start.sh feature-branch-stop.sh feature-branch-types.sh"

if [ $# -eq 0 ]; then
    echo "Usage: ./sync-branch-scripts.sh <repository folder> [<repository folder> ...]"
    exit 1
fi

for TARGET in "$@"; do
    if [ ! -d "$TARGET/.git" ]; then
        echo "Skipped $TARGET: not a git repository."
        continue
    fi
    for FILE in $FILES; do
        cp "$SCRIPT_DIR/$FILE" "$TARGET/$FILE"
        chmod +x "$TARGET/$FILE" 2>/dev/null || true
    done
    echo "Copied the branch scripts to $TARGET."
    if [ ! -f "$TARGET/feature-branch-gates.sh" ]; then
        echo "    No feature-branch-gates.sh there: the stop script will run the gates it finds installed."
    fi
done

echo "Now commit them in each repository."
