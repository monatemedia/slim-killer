#!/usr/bin/env bash
# =============================================================
# feature-branch-start.sh
# Creates a short-lived branch from `main` and pushes it to GitHub.
#
#   ./feature-branch-start.sh <name>            # feature/<name>
#   ./feature-branch-start.sh --fix <name>      # fix/<name>
#   ./feature-branch-start.sh --chore <name>    # chore/<name>
#   ./feature-branch-start.sh --docs <name>     # docs/<name>
#
# The type flag can go before or after <name>.
#
# Paired with feature-branch-stop.sh, which checks, merges and cleans
# up. Both read the branch rules from feature-branch-types.sh.
#
# Adapted from the same script in the dealership project.
# =============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=feature-branch-types.sh
source "$SCRIPT_DIR/feature-branch-types.sh"

START_TIME=$(date +%s)
echo "==> Started: $(date '+%Y-%m-%d %H:%M:%S')"
trap 'echo "==> Finished: $(date "+%Y-%m-%d %H:%M:%S") (total: $(format_duration $(( $(date +%s) - START_TIME ))))"' EXIT

usage() {
    echo "Usage: ./feature-branch-start.sh [--fix|--chore|--docs] <name>"
    echo "   (the flag can also go after <name>; no flag means feature/<name>)"
    exit 1
}

TYPE="feature"
NAME=""

# 1. Parse an optional type flag and the name, in any order.
while [ $# -gt 0 ]; do
    ARG="$1"
    case "$ARG" in
        --fix) TYPE="fix" ;;
        --chore) TYPE="chore" ;;
        --docs) TYPE="docs" ;;
        --feature) TYPE="feature" ;;
        -h | --help) usage ;;
        --*)
            echo "Error: Unknown option '$ARG'."
            usage
            ;;
        *)
            if [ -n "$NAME" ]; then
                echo "Error: Got two branch names ('$NAME' and '$ARG'). Pass only one."
                usage
            fi
            NAME="$ARG"
            ;;
    esac
    shift
done

if [ -z "$NAME" ]; then
    echo "Error: No branch name provided."
    usage
fi

if [[ ! "$NAME" =~ ^[a-z0-9][a-z0-9._-]*$ ]]; then
    echo "Error: '$NAME' is not a valid branch name. Use lowercase letters, digits, '.', '_' and '-'."
    exit 1
fi

BRANCH_NAME="$TYPE/$NAME"
SOURCE_BRANCH="$(branch_source_for_type "$TYPE")"

if [ -z "$SOURCE_BRANCH" ]; then
    echo "Error: Unknown branch type '$TYPE' (not in feature-branch-types.sh)."
    exit 1
fi

if git show-ref --verify --quiet "refs/heads/$BRANCH_NAME"; then
    echo "Error: Branch '$BRANCH_NAME' already exists locally."
    exit 1
fi

# 2. Refuse to start with uncommitted changes.
if [[ -n $(git status --porcelain) ]]; then
    echo "-------------------------------------------------------"
    echo "ERROR: You have uncommitted changes or untracked files."
    echo "Commit, stash or discard them before starting a new branch."
    echo "-------------------------------------------------------"
    git status -s
    exit 1
fi

# 3. Start from an up-to-date source branch.
echo "==> Checking out $SOURCE_BRANCH and pulling latest..."
git checkout "$SOURCE_BRANCH"
git pull origin "$SOURCE_BRANCH"

# 4. Create the branch and push it.
echo "==> Creating $BRANCH_NAME from $SOURCE_BRANCH..."
git checkout -b "$BRANCH_NAME" "$SOURCE_BRANCH"

echo "==> Pushing the new branch to GitHub and setting upstream tracking..."
git push -u origin "$BRANCH_NAME"

MERGE_TARGETS="$(branch_merge_targets_for_type "$TYPE")"

echo ""
echo "Branch '$BRANCH_NAME' created and ready."
echo "-------------------------------------------------------"
echo "Next steps:"
echo "1. Build your change on this branch, and add it to CHANGELOG.md under [Unreleased]."
echo "2. Commit and push:"
echo "   git add ."
echo "   git commit -m '$TYPE: <describe the change>'"
echo "   git push"
echo "3. When finished, run: ./feature-branch-stop.sh"
echo "   It checks the branch, merges into: $MERGE_TARGETS"
echo "   ...and returns you to: $SOURCE_BRANCH"
