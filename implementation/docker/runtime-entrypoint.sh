#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "Atlas runtime requires APP_KEY to be provided by the environment." >&2
    exit 78
fi

for writable_path in bootstrap/cache storage; do
    if [ ! -w "$writable_path" ]; then
        echo "Atlas runtime path is not writable: $writable_path" >&2
        exit 78
    fi
done

exec "$@"
