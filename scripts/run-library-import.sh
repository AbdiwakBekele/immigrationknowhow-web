#!/usr/bin/env bash
set -euo pipefail

cd /var/www/hub

php artisan config:clear
php artisan library:bulk-import storage/app/import/library_books.csv storage/app/import/library-source --dry-run

echo ""
echo "Dry run complete. If everything looks good, run:"
echo "php artisan library:bulk-import storage/app/import/library_books.csv storage/app/import/library-source"
