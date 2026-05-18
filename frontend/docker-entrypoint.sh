#!/usr/bin/env sh
set -eu

cd /app

export npm_config_cache="${npm_config_cache:-/tmp/npm-cache}"
mkdir -p "$npm_config_cache"

if [ ! -x node_modules/.bin/vite ]; then
    npm ci
fi

exec "$@"
