# Prépare une archive de déploiement HSI Family Office (Plesk).
$ErrorActionPreference = "Stop"
$projectRoot = (Resolve-Path (Split-Path $PSScriptRoot -Parent)).Path
$outputDirectory = Join-Path $projectRoot "deploy\output"
$archivePath = Join-Path $outputDirectory "hsi-family-office-release.zip"
$stagingDirectory = Join-Path ([System.IO.Path]::GetTempPath()) "hsi-deploy-$([guid]::NewGuid().ToString('N'))"

New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
New-Item -ItemType Directory -Force -Path $stagingDirectory | Out-Null

try {
    $includedTopLevel = @(
        'app',
        'bootstrap',
        'config',
        'database',
        'lang',
        'public',
        'resources',
        'routes',
        'storage',
        'vendor',
        'artisan',
        'composer.json',
        'composer.lock',
        '.env.production.example'
    )

    foreach ($relativePath in $includedTopLevel) {
        $sourcePath = Join-Path $projectRoot $relativePath
        if (-not (Test-Path -LiteralPath $sourcePath)) {
            throw "Élément requis absent du paquet de déploiement : $relativePath"
        }

        Copy-Item -LiteralPath $sourcePath -Destination $stagingDirectory -Recurse -Force
    }

    $sensitiveFiles = Get-ChildItem -LiteralPath $stagingDirectory -Recurse -Force -File |
        Where-Object {
            $_.Name -eq '.env' -or
            $_.Extension -eq '.sqlite' -or
            $_.FullName -like "*\storage\logs\*" -or
            $_.FullName -like "*\storage\framework\cache\data\*" -or
            $_.FullName -like "*\storage\framework\sessions\*" -or
            $_.FullName -like "*\storage\framework\views\*" -or
            $_.FullName -like "*\bootstrap\cache\*.php"
        }
    $sensitiveFiles | Remove-Item -Force

    $forbiddenFiles = Get-ChildItem -LiteralPath $stagingDirectory -Recurse -Force -File |
        Where-Object { $_.Name -eq '.env' -or $_.Extension -eq '.sqlite' -or $_.FullName -like "*\storage\logs\*" }
    if ($forbiddenFiles) {
        throw "L'archive contient encore un fichier local ou sensible."
    }

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::Open(
        $archivePath,
        [System.IO.Compression.ZipArchiveMode]::Create
    )

    try {
        Get-ChildItem -LiteralPath $stagingDirectory -Recurse -Force -File |
            ForEach-Object {
                $entryName = $_.FullName.Substring($stagingDirectory.Length).TrimStart([char[]]@('\', '/')).Replace('\', '/')

                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive,
                    $_.FullName,
                    $entryName,
                    [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
    }
    finally {
        $archive.Dispose()
    }
    Write-Host "Archive créée : $archivePath"
}
finally {
    if (Test-Path -LiteralPath $stagingDirectory) {
        Remove-Item -LiteralPath $stagingDirectory -Recurse -Force
    }
}
