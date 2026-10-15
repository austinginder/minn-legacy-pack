#!/usr/bin/env bash
# SPDX-License-Identifier: MIT
# Builds dist/minn-legacy-pack.zip from a commit (HEAD by default): the plugin
# folder as it installs, without bin/ and the repository's own files
# (.gitattributes export-ignore). Its version must be the tag's: v<Version>.
set -euo pipefail
cd "$( dirname "$0" )/.."
ref="${1:-HEAD}"
version="$( git show "${ref}:minn-legacy-pack.php" | sed -n 's/^ \* Version: *//p' | head -1 )"
[ -n "$version" ] || { echo "no Version header at ${ref}" >&2; exit 1; }
mkdir -p dist
rm -f dist/minn-legacy-pack.zip
git archive --format=zip --prefix=minn-legacy-pack/ -o dist/minn-legacy-pack.zip "$ref"
echo "dist/minn-legacy-pack.zip: version ${version} ($( du -h dist/minn-legacy-pack.zip | cut -f1 ))"
