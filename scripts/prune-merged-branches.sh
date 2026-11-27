#!/usr/bin/env bash
#
# Delete the remote branches whose pull request was merged into main.
#
# Run it from your own machine, where the GitHub token is not behind a proxy
# that refuses ref deletions:
#
#     bash scripts/prune-merged-branches.sh            # show what it would do
#     bash scripts/prune-merged-branches.sh --delete    # actually do it
#
# Needs the gh CLI, signed in: gh auth login
#
# It works the list out for itself rather than trusting a list somebody wrote
# down, and a branch is only deleted when all three of these hold:
#
#   its pull request was merged,
#   it was merged into main and nowhere else,
#   the branch tip is still the exact commit that was merged.
#
# The third is the one that matters. A branch whose PR merged and which then
# had more commits pushed to it still has work on it, and deleting it would
# throw that work away. Anything that fails any check is left alone and listed.

set -euo pipefail

REPO="${REPO:-Gray123017/avesta.soulutiions}"
BASE="${BASE:-main}"
DO_IT="${1:-}"

command -v gh >/dev/null || { echo "gh is not installed: https://cli.github.com"; exit 1; }

# Checked by asking for the repository rather than by 'gh auth status', which
# reports a failure in environments where the token arrives some other way.
if ! gh api "repos/$REPO" --jq .full_name >/dev/null 2>&1; then
  echo "Cannot read $REPO. Sign in with 'gh auth login', or set REPO=owner/name."
  exit 1
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

echo "Reading pull requests for $REPO ..."
gh api --paginate "repos/$REPO/pulls?state=all&per_page=100" \
  --jq '.[] | select(.merged_at != null) | [.head.ref, .head.sha, .base.ref] | @tsv' \
  > "$tmp/merged.tsv"

# A pull request that was never merged, whether it is still open or was closed
# without merging. Either way its commits are not in main, so the branch is the
# only copy of that work.
gh api --paginate "repos/$REPO/pulls?state=all&per_page=100" \
  --jq '.[] | select(.merged_at == null) | [.head.ref, .state] | @tsv' \
  | sort -u > "$tmp/unmerged.tsv"

echo "Reading remote branches ..."
gh api --paginate "repos/$REPO/branches?per_page=100" --jq '.[] | [.name, .commit.sha] | @tsv' \
  > "$tmp/branches.tsv"

: > "$tmp/go.txt"; : > "$tmp/skip.txt"

while IFS=$'\t' read -r name tip; do
  [ "$name" = "$BASE" ] && continue

  # A pull request that was never merged means this branch holds the only copy
  # of that work, open or closed.
  state="$(awk -F'\t' -v n="$name" '$1 == n {print $2; exit}' "$tmp/unmerged.tsv" || true)"
  if [ -n "$state" ]; then
    if [ "$state" = "open" ]; then
      printf '%s\ta pull request still open\n' "$name" >> "$tmp/skip.txt"
    else
      printf '%s\ta pull request closed without merging\n' "$name" >> "$tmp/skip.txt"
    fi
    continue
  fi

  rows="$(awk -F'\t' -v n="$name" '$1 == n' "$tmp/merged.tsv" || true)"
  if [ -z "$rows" ]; then
    printf '%s\tno merged pull request\n' "$name" >> "$tmp/skip.txt"; continue
  fi

  # Merged anywhere other than main? Leave it; its commits may not be in main.
  if printf '%s\n' "$rows" | awk -F'\t' -v b="$BASE" '$3 != b' | grep -q .; then
    printf '%s\tmerged into something other than %s\n' "$name" "$BASE" >> "$tmp/skip.txt"; continue
  fi

  # The tip has to be a commit that was actually merged.
  if printf '%s\n' "$rows" | awk -F'\t' -v t="$tip" '$2 == t' | grep -q .; then
    printf '%s\n' "$name" >> "$tmp/go.txt"
  else
    printf '%s\tpushed to after it was merged (%s)\n' "$name" "${tip:0:7}" >> "$tmp/skip.txt"
  fi
done < "$tmp/branches.tsv"

go=$(wc -l < "$tmp/go.txt" | tr -d ' ')
skip=$(wc -l < "$tmp/skip.txt" | tr -d ' ')

echo
echo "Keeping $skip branch(es):"
if [ "$skip" -gt 0 ]; then
  while IFS=$'\t' read -r n why; do printf '  %-52s %s\n' "$n" "$why"; done < "$tmp/skip.txt"
fi

echo
echo "Merged into $BASE, tip unchanged since — $go branch(es) to delete:"
sed 's/^/  /' "$tmp/go.txt"

if [ "$DO_IT" != "--delete" ]; then
  echo
  echo "Nothing has been changed. Run it again with --delete to go ahead:"
  echo "    bash scripts/prune-merged-branches.sh --delete"
  exit 0
fi

echo
read -r -p "Delete those $go branches from $REPO? [y/N] " answer
case "$answer" in
  y|Y|yes|YES) ;;
  *) echo "Left alone."; exit 0 ;;
esac

ok=0; bad=0
while read -r name; do
  if gh api -X DELETE "repos/$REPO/git/refs/heads/$name" >/dev/null 2>&1; then
    ok=$((ok + 1)); printf '  deleted  %s\n' "$name"
  else
    bad=$((bad + 1)); printf '  FAILED   %s\n' "$name"
  fi
done < "$tmp/go.txt"

echo
echo "Deleted $ok, failed $bad."
echo "A deleted branch can be restored from its pull request page for a while"
echo "afterwards, so a mistake here is recoverable."
echo
echo "Then tidy your own copy:  git fetch --prune"
