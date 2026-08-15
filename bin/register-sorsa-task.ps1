param(
    [string]$PhpPath = "php.exe",
    [string]$ProjectPath = (Split-Path -Parent $PSScriptRoot),
    [string]$TaskName = "Hackview Sorsa Batch",
    [switch]$Remove
)

$ErrorActionPreference = "Stop"
$ProjectPath = (Resolve-Path $ProjectPath).Path

if ($Remove) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Removed scheduled task: $TaskName"
    exit 0
}

$phpCommand = Get-Command $PhpPath -ErrorAction Stop
$action = New-ScheduledTaskAction `
    -Execute $phpCommand.Source `
    -Argument "artisan sorsa:batch" `
    -WorkingDirectory $ProjectPath
$trigger = New-ScheduledTaskTrigger -Daily -At "12:00AM"
$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2)

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Runs Hackview's configured Sorsa discovery batch at midnight." `
    -Force | Out-Null

Write-Host "Registered scheduled task: $TaskName"
Write-Host "Project path: $ProjectPath"
Write-Host "PHP executable: $($phpCommand.Source)"
