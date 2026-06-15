param(
    [string]$TaskName = "GSO Tent Status Auto Update",
    [int]$IntervalMinutes = 5
)

$ErrorActionPreference = "Stop"

if ($IntervalMinutes -lt 1) {
    throw "IntervalMinutes must be at least 1."
}

$php = (Get-Command php.exe -ErrorAction Stop).Source
$runner = (Resolve-Path (Join-Path $PSScriptRoot "run_tent_status_auto_update.php")).Path

$action = New-ScheduledTaskAction -Execute $php -Argument "`"$runner`"" -WorkingDirectory $PSScriptRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes) `
    -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Moves installed tent requests due today to For Retrieval." `
    -Force

Write-Output "Scheduled task '$TaskName' will run every $IntervalMinutes minute(s)."
