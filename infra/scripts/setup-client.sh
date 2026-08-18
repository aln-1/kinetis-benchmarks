#!/bin/sh
set -e

if ! command -v wrk >/dev/null 2>&1; then
    sudo apt-get update -y
    sudo apt-get install -y wrk
fi

wrk --version 2>&1 | head -1 || true
echo "Client setup complete."
