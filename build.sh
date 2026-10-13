#!/bin/sh
# Builds mailtemplateeditor.zip, ready for "Modules > Module Manager > Upload a module".
set -e
cd "$(dirname "$0")"
php tests/run.php
rm -f mailtemplateeditor.zip
zip -rq mailtemplateeditor.zip mailtemplateeditor -x 'mailtemplateeditor/backups/*.bak' 'mailtemplateeditor/backups/*/*'
echo "Created mailtemplateeditor.zip"
