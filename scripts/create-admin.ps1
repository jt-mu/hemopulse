param([string]$Php = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
$credential = Get-Credential -Message 'First HemoPulse administrator: enter an email and a password of 12–72 bytes.'
if ($null -eq $credential) { exit 1 }
try {
    $env:HEMOPULSE_ADMIN_EMAIL = $credential.UserName
    $env:HEMOPULSE_ADMIN_PASSWORD = $credential.GetNetworkCredential().Password
    & $Php (Join-Path $PSScriptRoot 'create_admin.php')
    if ($LASTEXITCODE -ne 0) { throw 'Administrator setup did not complete.' }
} finally {
    Remove-Item Env:HEMOPULSE_ADMIN_EMAIL -ErrorAction SilentlyContinue
    Remove-Item Env:HEMOPULSE_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    $credential = $null
}
