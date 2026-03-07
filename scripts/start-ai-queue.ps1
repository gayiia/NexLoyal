Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

$aiServiceDir = Join-Path $repoRoot "ai_service"
if (-not (Test-Path $aiServiceDir)) {
    throw "AI service directory not found at: $aiServiceDir"
}

$venvPython = Join-Path $aiServiceDir ".venv\Scripts\python.exe"
$aiCommand = if (Test-Path $venvPython) {
    "cd ai_service && `"$venvPython`" -m uvicorn main:app --host 127.0.0.1 --port 8001"
} else {
    Write-Host "AI virtual environment not found at ai_service\.venv. Falling back to system Python." -ForegroundColor Yellow
    "cd ai_service && python -m uvicorn main:app --host 127.0.0.1 --port 8001"
}

$commands = @(
    "php artisan queue:work --tries=1",
    $aiCommand
)

& npx concurrently `
    --kill-others-on-fail `
    --names "queue,ai" `
    --prefix-colors "yellow,cyan" `
    $commands
