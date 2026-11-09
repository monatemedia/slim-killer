#!/usr/bin/env bash
# =============================================================
# feature-branch-types.sh
# Shared branch-type rules for feature-branch-start.sh and
# feature-branch-stop.sh. Sourced by both (not run directly), so the
# two scripts can never disagree about what a branch type means.
# Neither script stores any state: both work everything out from the
# current branch's name prefix and this file.
#
# Every type starts from `main` and merges back into `main`. A release
# is a version tag on `main` (vMAJOR.MINOR.PATCH); see README.md,
# "Branching and Releases". A `hotfix` type (start from a release tag)
# can be added here later, once clients run tagged releases.
#
# Adapted from the same scripts in the dealership project.
# =============================================================

# All recognised branch types, space-separated. Add a new type here and
# in the two case statements below; both scripts pick it up.
ALL_BRANCH_TYPES="feature fix chore docs"

# branch_source_for_type <type> - the branch a new branch of this type is
# created from, and the branch feature-branch-stop.sh returns to.
branch_source_for_type() {
    case "$1" in
        feature | fix | chore | docs) echo "main" ;;
        *) echo "" ;;
    esac
}

# branch_merge_targets_for_type <type> - space-separated list of branches
# this type merges into when closed. A list, so a type with two targets
# could be added without touching feature-branch-stop.sh's merge loop.
branch_merge_targets_for_type() {
    case "$1" in
        feature | fix | chore | docs) echo "main" ;;
        *) echo "" ;;
    esac
}

# commit_prefix_for_type <type> - the conventional commit prefix for this type
# (AGENTS.md lists them): a feature branch's commits start with "feat:".
commit_prefix_for_type() {
    case "$1" in
        feature) echo "feat" ;;
        *) echo "$1" ;;
    esac
}

# is_valid_release_tag <name> - succeeds for vMAJOR.MINOR.PATCH.
is_valid_release_tag() {
    [[ "$1" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

# format_duration <seconds> - "XmYs", or "Ys" under a minute. Both scripts
# print their total run time through an EXIT trap, so the time still
# shows when a run stops early (a failed gate, uncommitted changes).
format_duration() {
    local total_seconds="$1"
    local minutes=$((total_seconds / 60))
    local seconds=$((total_seconds % 60))
    if [ "$minutes" -gt 0 ]; then
        echo "${minutes}m${seconds}s"
    else
        echo "${seconds}s"
    fi
}
