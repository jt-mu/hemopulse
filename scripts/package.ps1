$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$releasePath = Join-Path $projectRoot 'deployment\hemopulse-group-demo.zip'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [System.IO.File]::Open($releasePath, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $files = @(Get-ChildItem -LiteralPath $projectRoot -File -Force | Where-Object { $_.Extension -eq '.php' })
    $files += Get-Item -LiteralPath (Join-Path $projectRoot '.htaccess'), (Join-Path $projectRoot 'README.md')
    foreach ($folder in @('api','assets','backend','config','css','database','docs','images','includes','js','scripts','vendor','tests')) {
        $files += Get-ChildItem -LiteralPath (Join-Path $projectRoot $folder) -Recurse -File -Force
    }
    $files += Get-Item -LiteralPath (Join-Path $projectRoot 'deployment\schema.sql'), (Join-Path $projectRoot 'deployment\GROUP_SETUP.md'), (Join-Path $projectRoot 'deployment\.htaccess')
    $files += Get-Item -LiteralPath (Join-Path $projectRoot 'storage\.htaccess')
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($projectRoot.Length + 1).Replace('\','/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $relative) | Out-Null
    }
} finally {
    $archive.Dispose()
    $stream.Dispose()
}
Write-Output $releasePath
