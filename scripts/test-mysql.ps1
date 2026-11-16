param(
    [string] $MySqlBinary = 'C:\wamp64\bin\mysql\mysql8.3.0\bin\mysql.exe',
    [string] $HostName = '127.0.0.1',
    [int] $Port = 3306,
    [string] $Database = 'hsi_family_office_test',
    [string] $Username = 'root',
    [string] $Password = '',
    [switch] $SkipTests
)

$ErrorActionPreference = 'Stop'

if ($Database -notmatch '^[A-Za-z0-9_]+_test$') {
    throw "Sécurité : le nom de la base MySQL doit se terminer par _test."
}

if (-not (Test-Path -LiteralPath $MySqlBinary)) {
    throw "Client MySQL introuvable : $MySqlBinary"
}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$createDatabaseSql = "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

$env:MYSQL_PWD = $Password

try {
    & $MySqlBinary `
        '--protocol=TCP' `
        "--host=$HostName" `
        "--port=$Port" `
        "--user=$Username" `
        "--execute=$createDatabaseSql"

    if ($LASTEXITCODE -ne 0) {
        throw "La création de la base MySQL de test a échoué."
    }

    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = $HostName
    $env:DB_PORT = [string] $Port
    $env:DB_DATABASE = $Database
    $env:DB_USERNAME = $Username
    $env:DB_PASSWORD = $Password
    $env:DB_ENGINE = 'InnoDB'
    $env:DB_URL = ''
    $env:CACHE_STORE = 'array'
    $env:SESSION_DRIVER = 'array'
    $env:QUEUE_CONNECTION = 'sync'

    Push-Location $projectRoot
    try {
        php artisan config:clear --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'config:clear a échoué.' }

        php artisan migrate:fresh --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Les migrations MySQL ont échoué.' }

        php artisan hsi:audit-critical-integrity --no-interaction
        if ($LASTEXITCODE -ne 0) { throw "L'audit d'intégrité MySQL a échoué." }

        php artisan migrate:rollback --step=1 --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Le rollback de la migration critique a échoué.' }

        php artisan migrate --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'La réapplication de la migration critique a échoué.' }

        php artisan hsi:audit-critical-integrity --no-interaction
        if ($LASTEXITCODE -ne 0) { throw "L'audit après réapplication a échoué." }

        if (-not $SkipTests) {
            php vendor/bin/phpunit --configuration phpunit.mysql.xml
            if ($LASTEXITCODE -ne 0) { throw 'La suite de tests MySQL a échoué.' }
        }
    }
    finally {
        Pop-Location
    }
}
finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
