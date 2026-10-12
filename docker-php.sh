#!/usr/bin/env bash
# Run the app with PHP in Docker (MySQL already on host port 3307).
set -euo pipefail
cd "$(dirname "$0")"

echo "Starting PHP at http://localhost:8080 ..."
docker run --rm -it \
  --name pb-php \
  -p 8080:8080 \
  -v "$(pwd)":/app \
  -w /app \
  php:8.2-cli \
  bash -c "docker-php-ext-install mysqli >/dev/null && php -S 0.0.0.0:8080"
