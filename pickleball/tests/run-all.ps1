$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot

function Find-Executable {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string]$SearchRoot,
        [Parameter(Mandatory = $true)][string]$FileName
    )

    $fromPath = Get-Command $Command -ErrorAction SilentlyContinue
    if ($fromPath) { return $fromPath.Source }

    $match = Get-ChildItem -LiteralPath $SearchRoot -Filter $FileName -File -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1
    if ($match) { return $match.FullName }

    throw "Unable to find $Command."
}

$node = Find-Executable -Command "node" -SearchRoot "C:\laragon\bin\nodejs" -FileName "node.exe"
$php = Find-Executable -Command "php" -SearchRoot "C:\laragon\bin\php" -FileName "php.exe"
$env:PHP_BIN = $php

$javascriptTests = Get-ChildItem -LiteralPath $PSScriptRoot -Filter "*.test.mjs" -File |
    Sort-Object Name |
    ForEach-Object FullName

Push-Location $projectRoot
try {
    & $node --test @javascriptTests
    if ($LASTEXITCODE -ne 0) { throw "JavaScript tests failed with exit code $LASTEXITCODE." }

    & $php (Join-Path $PSScriptRoot "tournament-actions.test.php")
    if ($LASTEXITCODE -ne 0) { throw "PHP action tests failed with exit code $LASTEXITCODE." }

    Write-Host "All website tests passed." -ForegroundColor Green
}
finally {
    Pop-Location
}
