#!/usr/bin/env bash
set -euo pipefail

echo "Cleaning NexLoyal artifacts..."

rm -rf \
  node_modules \
  vendor \
  public/build \
  storage/logs \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  ai_service/__pycache__ \
  .venv \
  ai_service/.venv

echo "Cleanup complete."
