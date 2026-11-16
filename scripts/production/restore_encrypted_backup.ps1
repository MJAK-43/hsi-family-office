param(
    [Parameter(Mandatory = $true)]
    [string] $ArchivePath,

    [Parameter(Mandatory = $true)]
    [string] $KeyPath,

    [string] $OutputDirectory,

    [string] $SevenZipPath = 'C:\Program Files\7-Zip\7z.exe',

    [switch] $VerifyOnly
)

$ErrorActionPreference = 'Stop'

$archive = (Resolve-Path -LiteralPath $ArchivePath).Path
$key = (Resolve-Path -LiteralPath $KeyPath).Path

if (-not (Test-Path -LiteralPath $SevenZipPath)) {
    throw "7-Zip introuvable : $SevenZipPath"
}

if (-not $VerifyOnly) {
    if (-not $OutputDirectory -or $OutputDirectory -notmatch '_restore$') {
        throw 'Le dossier de sortie doit être explicite et se terminer par _restore.'
    }

    if (Test-Path -LiteralPath $OutputDirectory) {
        throw 'Le dossier de restauration existe déjà.'
    }
}

$protectedValue = (Get-Content -LiteralPath $key -Raw).Trim()
$securePassword = $protectedValue | ConvertTo-SecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)

    if ($VerifyOnly) {
        & $SevenZipPath t "-p$password" $archive | Out-Null
    }
    else {
        New-Item -ItemType Directory -Path $OutputDirectory | Out-Null
        $currentPrincipal = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
        icacls $OutputDirectory /inheritance:r /grant:r "${currentPrincipal}:(OI)(CI)F" 'SYSTEM:(OI)(CI)F' | Out-Null

        & $SevenZipPath x "-p$password" "-o$OutputDirectory" $archive | Out-Null
    }

    if ($LASTEXITCODE -ne 0) {
        throw '7-Zip n’a pas pu vérifier ou extraire la sauvegarde.'
    }
}
finally {
    if ($bstr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }

    $password = $null
}

if (-not $VerifyOnly) {
    $checksumFile = Join-Path $OutputDirectory 'SHA256SUMS'
    $failures = 0

    Get-Content -LiteralPath $checksumFile | ForEach-Object {
        if ($_ -match '^([a-f0-9]{64})\s+(.+)$') {
            $expected = $Matches[1]
            $fileName = $Matches[2]
            $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath (Join-Path $OutputDirectory $fileName)).Hash.ToLowerInvariant()

            if ($actual -ne $expected) {
                $failures++
            }
        }
    }

    if ($failures -ne 0) {
        throw "La restauration contient $failures empreinte(s) invalide(s)."
    }
}

if ($VerifyOnly) {
    Write-Output 'BackupVerification=OK'
}
else {
    Write-Output "BackupRestore=$OutputDirectory"
}
