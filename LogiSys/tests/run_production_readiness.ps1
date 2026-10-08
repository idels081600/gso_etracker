param(
    [int]$Port = 8127
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Stamp = Get-Date -Format 'yyyyMMddHHmmss'
$TestDatabase = "logisys_test_${Stamp}_$PID".ToLowerInvariant()
$TestAdminPassword = "T!Aa1-$([guid]::NewGuid().ToString('N'))"
$BaseUrl = "http://127.0.0.1:$Port"
$ServerOut = Join-Path $Root 'outputs/test-php-server.out.log'
$ServerErr = Join-Path $Root 'outputs/test-php-server.err.log'
$PhpResults = Join-Path $Root 'outputs/production-readiness-results.json'
$BrowserResults = Join-Path $Root 'outputs/browser-smoke-results.json'
$Report = Join-Path $Root 'outputs/production-readiness-report.md'
$Server = $null
$PhpExit = 1
$BrowserExit = 1

New-Item -ItemType Directory -Force -Path (Join-Path $Root 'outputs') | Out-Null

try {
    $env:TEST_ADMIN_PASSWORD = $TestAdminPassword
    Write-Host "Creating isolated database $TestDatabase"
    & php (Join-Path $PSScriptRoot 'clone_test_database.php') $TestDatabase
    if ($LASTEXITCODE -ne 0) { throw 'Could not clone the test database.' }

    $env:DB_NAME = $TestDatabase
    $Server = Start-Process -FilePath 'php' -ArgumentList '-S', "127.0.0.1:$Port", '-t', $Root -WorkingDirectory $Root -WindowStyle Hidden -PassThru -RedirectStandardOutput $ServerOut -RedirectStandardError $ServerErr
    Remove-Item Env:DB_NAME

    $Ready = $false
    for ($Attempt = 0; $Attempt -lt 40; $Attempt++) {
        try {
            $Response = Invoke-WebRequest -Uri "$BaseUrl/Logi_login.php" -UseBasicParsing -TimeoutSec 2
            if ($Response.StatusCode -eq 200) { $Ready = $true; break }
        } catch { Start-Sleep -Milliseconds 250 }
    }
    if (-not $Ready) { throw 'The isolated PHP test server did not start.' }

    $env:DB_NAME = $TestDatabase
    & php (Join-Path $PSScriptRoot 'production_readiness.php') $BaseUrl $PhpResults
    $PhpExit = $LASTEXITCODE
    Remove-Item Env:DB_NAME

    $env:TEST_BASE_URL = $BaseUrl
    $env:TEST_BROWSER_OUTPUT = $BrowserResults
    Push-Location $Root
    try { & node (Join-Path $PSScriptRoot 'browser_smoke.mjs'); $BrowserExit = $LASTEXITCODE }
    finally { Pop-Location }
    Remove-Item Env:TEST_BASE_URL
    Remove-Item Env:TEST_BROWSER_OUTPUT

    $Php = Get-Content $PhpResults -Raw | ConvertFrom-Json
    if (Test-Path $BrowserResults) {
        $Browser = Get-Content $BrowserResults -Raw | ConvertFrom-Json
    } else {
        $Browser = [pscustomobject]@{ results = @([pscustomobject]@{ area='Browser'; name='Browser smoke suite completed'; passed=$false; detail='The browser suite did not produce a result file. See test-php-server logs and console output.'; severity='required' }) }
    }
    $All = @($Php.results) + @($Browser.results)
    $Passed = @($All | Where-Object passed).Count
    $Failed = @($All | Where-Object { -not $_.passed }).Count
    $Blockers = @($All | Where-Object { -not $_.passed -and $_.severity -eq 'blocker' }).Count
    $ReadyForProduction = ($Failed -eq 0)
    $Lines = @(
        '# LogiSys Version 1 Production Readiness',
        '',
        "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')",
        '',
        "**Decision:** $(if($ReadyForProduction){'READY'}else{'NOT READY'})",
        '',
        "- Passed: $Passed",
        "- Failed: $Failed",
        "- Blocking failures: $Blockers",
        "- Test database: isolated clone (removed after the run)",
        '',
        '## Results',
        '',
        '| Area | Check | Result | Severity | Detail |',
        '|---|---|---|---|---|'
    )
    foreach ($Item in $All) {
        $Detail = ([string]$Item.detail).Replace('|','\|').Replace("`r",' ').Replace("`n",' ')
        $Lines += "| $($Item.area) | $($Item.name) | $(if($Item.passed){'PASS'}else{'FAIL'}) | $($Item.severity) | $Detail |"
    }
    $Lines += @('', '## Evidence', '', '- `production-readiness-results.json` - API, workflow, data, and security results', '- `browser-smoke-results.json` - responsive browser results', '- `inventory-ledger-mismatches.csv` - item-level balance discrepancies', '- `ib-monitoring-1440.png`, `ib-monitoring-1024.png`, `ib-monitoring-390.png` - rendered screenshots')
    Set-Content -Path $Report -Value $Lines -Encoding UTF8
    Write-Host "Report written to $Report"
} finally {
    Remove-Item Env:DB_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:TEST_BASE_URL -ErrorAction SilentlyContinue
    Remove-Item Env:TEST_BROWSER_OUTPUT -ErrorAction SilentlyContinue
    Remove-Item Env:TEST_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    if ($Server -and -not $Server.HasExited) { Stop-Process -Id $Server.Id -Force }
    & php (Join-Path $PSScriptRoot 'drop_test_database.php') $TestDatabase
}

if ($PhpExit -ne 0 -or $BrowserExit -ne 0) { exit 1 }
