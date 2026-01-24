Write-Host "Cleaning NexLoyal artifacts..."

$paths = @(
    "node_modules",
    "vendor",
    "public/build",
    "storage/logs",
    "storage/framework/cache",
    "storage/framework/sessions",
    "storage/framework/views",
    "ai_service/__pycache__",
    ".venv",
    "ai_service/.venv"
)

foreach ($path in $paths) {
    if (Test-Path $path) {
        Remove-Item -Recurse -Force $path
    }
}

Write-Host "Cleanup complete."
