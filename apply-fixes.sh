#!/usr/bin/env bash
set -euo pipefail

# apply-fixes.sh - runs Rector, PHP-CS-Fixer and dynamic property fixer.
# Non-interactive, intended to be executed by CI or locally.

echo "Starting auto-fix process..."

# Run Rector (process src if exists, otherwise whole repo)
if [ -d src ]; then
  vendor/bin/rector process src || true
else
  vendor/bin/rector process || true
fi

# Run PHP-CS-Fixer
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php || true

# Run dynamic property fixer (tools/dynamic_property_fixer.php must exist)
if [ -f tools/dynamic_property_fixer.php ]; then
  php tools/dynamic_property_fixer.php .
else
  echo "tools/dynamic_property_fixer.php not found — skipping dynamic property fixer"
fi

echo "Auto-fix steps finished."