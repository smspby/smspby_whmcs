#!/usr/bin/env bash
set -euo pipefail

module_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
archive_dir="$(mktemp -d "$module_dir/.zip.XXXXXXXX")"
trap 'rm -rf -- "$archive_dir"' EXIT

cd "$module_dir"
zip -q -r "$archive_dir/smspby_whmcs.zip" \
    smspby_whmcs.php smsclass.php hooks.php templater.php whmcs.json logo.png \
    hooks lang smspby/vendor templates \
    -x '*/.DS_Store' '*/dbcompatfunctions.php' '*/hookfunctions.php' '*/dbfunctions.php'
mv -f -- "$archive_dir/smspby_whmcs.zip" "$module_dir/smspby_whmcs.zip"
