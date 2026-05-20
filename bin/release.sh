#!/bin/bash
#
# Release pipeline for Graceful Error Pages.
#
# Usage: bash bin/release.sh <version>
# Example: bash bin/release.sh 1.1.0
#
# Steps:
#   1. Validate version format and working tree
#   2. Generate changelog from conventional commits (for review)
#   3. Pause for operator review
#   4. Bump version across all 7 locations
#   5. Apply changelog + upgrade notice to readme.txt
#   6. Sync package-lock.json
#   7. Validate readme.txt has the new version entry
#   8. Commit, tag, push
#

set -euo pipefail

VERSION="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
README_FILE="${PLUGIN_ROOT}/readme.txt"
PLUGIN_FILE="${PLUGIN_ROOT}/graceful-error-pages.php"

die() { echo "Error: $1" >&2; exit 1; }

step() {
	echo ""
	echo "--- Step $1: $2 ---"
}

# Step 0: Validate inputs.

if [ -z "$VERSION" ]; then
	echo "Usage: bash bin/release.sh <version>"
	echo "Example: bash bin/release.sh 1.1.0"
	exit 1
fi

if ! [[ "$VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
	die "Version must be semver (e.g. 1.1.0), got: $VERSION"
fi

cd "$PLUGIN_ROOT"

step 1 "Validate working tree"

if ! git diff --quiet HEAD 2>/dev/null; then
	die "Working tree has uncommitted changes. Commit or stash first."
fi

if git tag -l "v${VERSION}" | grep -q "v${VERSION}"; then
	die "Tag v${VERSION} already exists."
fi

LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || true)
if [ -z "$LAST_TAG" ]; then
	COMMIT_COUNT=$(git log --oneline --no-merges | wc -l | tr -d ' ')
	echo "  Previous tag: (none — first release)"
else
	COMMIT_COUNT=$(git log "${LAST_TAG}..HEAD" --oneline --no-merges | wc -l | tr -d ' ')
	echo "  Previous tag: ${LAST_TAG}"
fi
echo "  Commits since: ${COMMIT_COUNT}"
echo "  Target version: ${VERSION}"

if [ "$COMMIT_COUNT" = "0" ]; then
	die "No commits found. Nothing to release."
fi

step 2 "Generate changelog draft"

bash "${SCRIPT_DIR}/generate-changelog.sh" --version "${VERSION}"

echo ""
echo "  REVIEW REQUIRED"
echo "  The changelog above is auto-generated from commits."
echo "  Review it now. When you continue, it will be applied to readme.txt."
echo ""

# In non-interactive mode (e.g. when Claude runs this), continue automatically.
if [ -t 0 ]; then
	read -r -p "Press Enter to continue or Ctrl+C to abort... "
fi

step 3 "Bump version (7 locations)"

bash "${SCRIPT_DIR}/bump-version.sh" "${VERSION}"

step 4 "Sync package-lock.json"

npm install --package-lock-only --silent 2>/dev/null || true

step 5 "Validate readme.txt"

if ! grep -q "^= ${VERSION} =$" "$README_FILE"; then
	die "readme.txt is missing changelog entry for version ${VERSION}"
fi

for section in "Description" "Installation" "Frequently Asked Questions" "Screenshots" "Changelog" "Upgrade Notice"; do
	if ! grep -q "^== ${section} ==$" "$README_FILE"; then
		die "readme.txt is missing required section: == ${section} =="
	fi
done

echo "  readme.txt validated: changelog entry present, all sections intact."

step 6 "Commit and tag"

git add -A
git commit -m "release: v${VERSION}"
git tag -a "v${VERSION}" -m "Release ${VERSION}"

echo "  Committed and tagged v${VERSION}"

step 7 "Push"

git push origin main
git push origin "v${VERSION}"

echo ""
echo "--- Release v${VERSION} complete ---"
echo ""
echo "  The Release workflow will now run on GitHub."
echo "  Monitor: gh run list --limit 3"
echo ""
