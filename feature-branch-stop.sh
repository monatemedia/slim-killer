#!/usr/bin/env bash
# =============================================================
# feature-branch-stop.sh
# Closes the current short-lived branch:
#
#   1. checks it is a recognised branch type with nothing uncommitted
#   2. checks CHANGELOG.md was updated (you can choose to continue)
#   3. runs the quality gates that exist in the repo so far
#   4. pushes the branch, merges it into its target(s) with --no-ff
#   5. deletes the local and remote branch and returns you to `main`
#
#   ./feature-branch-stop.sh                # normal run
#   ./feature-branch-stop.sh --skip-gates   # skip step 3 (asks you to confirm)
#
# Gates run only when the tool is installed, so the script works from
# day one and picks each gate up as the project grows:
#   - Pint style check     vendor/bin/pint --test
#   - PHPStan              vendor/bin/phpstan analyse
#   - Pest test suite      vendor/bin/pest --testsuite=Unit
#   - Pest browser tests   --testsuite=Browser, when node_modules/playwright exists
#   - npm test             when package.json has a "test" script
# A failing gate stops the script before anything is pushed or merged.
#
# Adapted from the same script in the dealership project (which runs
# its gates inside Docker containers; this one runs them locally).
# =============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=feature-branch-types.sh
source "$SCRIPT_DIR/feature-branch-types.sh"

START_TIME=$(date +%s)
echo "==> Started: $(date '+%Y-%m-%d %H:%M:%S')"
trap 'echo "==> Finished: $(date "+%Y-%m-%d %H:%M:%S") (total: $(format_duration $(( $(date +%s) - START_TIME ))))"' EXIT

SKIP_GATES=0
while [ $# -gt 0 ]; do
    case "$1" in
        --skip-gates) SKIP_GATES=1 ;;
        -h | --help)
            echo "Usage: ./feature-branch-stop.sh [--skip-gates]"
            exit 0
            ;;
        *)
            echo "Error: Unknown option '$1'."
            echo "Usage: ./feature-branch-stop.sh [--skip-gates]"
            exit 1
            ;;
    esac
    shift
done

cd "$SCRIPT_DIR"

# 1. Work out the branch and its type.
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
TYPES_REGEX="$(echo "$ALL_BRANCH_TYPES" | tr ' ' '|')"

if [[ ! "$CURRENT_BRANCH" =~ ^($TYPES_REGEX)/.+ ]]; then
    echo "Error: '$CURRENT_BRANCH' is not a short-lived branch."
    echo "Expected a branch named <type>/<name>, where <type> is one of: $ALL_BRANCH_TYPES"
    exit 1
fi

TYPE="${CURRENT_BRANCH%%/*}"
SOURCE_BRANCH="$(branch_source_for_type "$TYPE")"
MERGE_TARGETS="$(branch_merge_targets_for_type "$TYPE")"

echo "==> Closing $CURRENT_BRANCH (type: $TYPE, merges into: $MERGE_TARGETS)"

# 2. Nothing uncommitted.
if [[ -n $(git status --porcelain) ]]; then
    echo "-------------------------------------------------------"
    echo "ERROR: You have uncommitted changes or untracked files."
    echo "Commit or stash them before closing the branch."
    echo "-------------------------------------------------------"
    git status -s
    exit 1
fi

# 3. Bring the branch and its source up to date.
echo "==> Fetching from origin..."
git fetch origin --quiet

if git show-ref --verify --quiet "refs/remotes/origin/$CURRENT_BRANCH"; then
    echo "==> Pulling any remote commits on $CURRENT_BRANCH..."
    git pull --ff-only origin "$CURRENT_BRANCH"
fi

# 4. CHANGELOG.md check.
echo "==> Checking whether CHANGELOG.md was updated on this branch..."
MERGE_BASE="$(git merge-base "origin/$SOURCE_BRANCH" "$CURRENT_BRANCH")"
if git diff --quiet "$MERGE_BASE" "$CURRENT_BRANCH" -- CHANGELOG.md; then
    echo "WARNING: CHANGELOG.md has no changes on this branch."
    read -r -p "Continue anyway? Only if nothing here is worth a changelog entry. [y/N] " ANSWER
    if [[ ! "$ANSWER" =~ ^[Yy]$ ]]; then
        echo "Aborting. Add an entry under [Unreleased] in CHANGELOG.md, commit, and re-run."
        exit 1
    fi
else
    echo "==> CHANGELOG.md was updated."
fi

# 5. Quality gates.
run_gate() {
    local label="$1"
    shift
    echo "==> Gate: $label"
    if ! "$@"; then
        echo "-------------------------------------------------------"
        echo "GATE FAILED: $label"
        echo "Nothing was pushed or merged. Fix it, commit, and re-run."
        echo "-------------------------------------------------------"
        exit 1
    fi
}

if [ "$SKIP_GATES" -eq 1 ]; then
    read -r -p "Skip all quality gates? Type SKIP to confirm: " CONFIRM
    if [ "$CONFIRM" != "SKIP" ]; then
        echo "Aborting."
        exit 1
    fi
    echo "==> Gates skipped."
else
    GATES_RUN=0
    if [ -f vendor/bin/pint ]; then
        run_gate "Pint (style)" php vendor/bin/pint --test
        GATES_RUN=$((GATES_RUN + 1))
    fi
    if [ -f vendor/bin/phpstan ]; then
        run_gate "PHPStan" php vendor/bin/phpstan analyse --no-progress
        GATES_RUN=$((GATES_RUN + 1))
    fi
    if [ -f vendor/bin/pest ]; then
        run_gate "Pest" php vendor/bin/pest --testsuite=Unit
        GATES_RUN=$((GATES_RUN + 1))
        # Browser tests need Playwright and its browsers (npm install, npx playwright install).
        if [ -d tests/Browser ] && [ -d node_modules/playwright ]; then
            run_gate "Pest (browser)" php vendor/bin/pest --testsuite=Browser
            GATES_RUN=$((GATES_RUN + 1))
        fi
    fi
    if [ -f package.json ] && grep -q '"test"[[:space:]]*:' package.json; then
        run_gate "npm test" npm test
        GATES_RUN=$((GATES_RUN + 1))
    fi
    if [ "$GATES_RUN" -eq 0 ]; then
        echo "==> No gates installed yet (no Pint, PHPStan, Pest or npm test). Continuing."
    fi
fi

# 6. Push the branch.
echo "==> Pushing $CURRENT_BRANCH to origin..."
git push -u origin "$CURRENT_BRANCH"

# 7. Merge into each target.
for TARGET in $MERGE_TARGETS; do
    echo "==> Merging $CURRENT_BRANCH into $TARGET..."
    git checkout "$TARGET"
    git pull --ff-only origin "$TARGET"
    if ! git merge --no-ff "$CURRENT_BRANCH" -m "Merge branch '$CURRENT_BRANCH' into $TARGET"; then
        echo "-------------------------------------------------------"
        echo "MERGE CONFLICT merging $CURRENT_BRANCH into $TARGET."
        echo "Resolve the conflicts, commit, push $TARGET, then delete the branch by hand:"
        echo "   git branch -d $CURRENT_BRANCH"
        echo "   git push origin --delete $CURRENT_BRANCH"
        echo "-------------------------------------------------------"
        exit 1
    fi
    git push origin "$TARGET"
done

# 8. Clean up.
echo "==> Deleting local branch $CURRENT_BRANCH..."
git branch -d "$CURRENT_BRANCH"

echo "==> Deleting remote branch $CURRENT_BRANCH..."
git push origin --delete "$CURRENT_BRANCH" || echo "    (remote branch already gone)"

# 9. Return to the source branch.
git checkout "$SOURCE_BRANCH"

echo ""
echo "Done: $CURRENT_BRANCH merged into $MERGE_TARGETS and deleted. You are on $SOURCE_BRANCH."
