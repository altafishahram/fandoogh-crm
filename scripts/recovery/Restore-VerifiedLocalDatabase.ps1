param([switch]$ConfirmVerifiedLocalRestore)
$ErrorActionPreference = 'Stop'
# Incident-specific, LOCAL ONLY. Never reuse for another database or incident.
if (-not $ConfirmVerifiedLocalRestore) { throw 'Explicit local restore switch is required.' }
$mainName = 'fandoogh-db-1'
$sourceName = 'fandoogh-recovery-20261003'
$evidence = 'D:\app\fandoogh\artifacts\recovery-20261003'
$dump = Join-Path $evidence 'recovered-fandoogh-before-incident.sql'
$expectedHash = '389C2A2C2685639E5A27775E2F4BA17D627A52C69691286A0F739F2DA380BCA0'
function Docker([string[]]$Arguments) {
    $result = & docker.exe @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Docker operation failed; preserve evidence and stopped ingress.' }
    return $result
}
function Query([string]$Container, [string]$Sql) {
    # Password stays inside the container; no credentials are printed or interpolated locally.
    if ($Container -eq $mainName) {
        return Docker -Arguments @('exec', $Container, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=socket -uroot -N -B -e "$1"', 'query', $Sql)
    }
    return Docker -Arguments @('exec', $Container, 'mysql', '--protocol=socket', '-uroot', '-N', '-B', '-e', $Sql)
}
if ((Get-FileHash -LiteralPath $dump -Algorithm SHA256).Hash -ne $expectedHash) { throw 'Recovered dump hash differs.' }
$main = (Docker -Arguments @('inspect', $mainName) | ConvertFrom-Json)[0]
$source = (Docker -Arguments @('inspect', $sourceName) | ConvertFrom-Json)[0]
$mainVolume = @($main.Mounts | Where-Object Destination -eq '/var/lib/mysql')
$sourceVolume = @($source.Mounts | Where-Object Destination -eq '/var/lib/mysql')
if (-not $main.Id.StartsWith('6af52') -or -not $source.Id.StartsWith('77535e26')) { throw 'Container identity differs from incident evidence.' }
if ($main.Config.Labels.'com.docker.compose.project' -ne 'fandoogh' -or $main.Config.Labels.'com.docker.compose.service' -ne 'db') { throw 'Target is not the original local compose database.' }
if ($mainVolume.Count -ne 1 -or $mainVolume[0].Name -ne 'fandoogh_mysql_data' -or $sourceVolume.Count -ne 1 -or $mainVolume[0].Source -eq $sourceVolume[0].Source) { throw 'Volume identity/isolation mismatch.' }
if ($source.HostConfig.NetworkMode -ne 'none' -or @($source.HostConfig.PortBindings.PSObject.Properties).Count -gt 0) { throw 'Recovery source must stay isolated.' }
if ($main.State.Status -ne 'running' -or $source.State.Status -ne 'running') { throw 'Both verified containers must be running.' }
$stoppedIngress = @()
foreach ($name in @('fandoogh-nginx-1','fandoogh-app-1')) {
    $container = (Docker -Arguments @('inspect', $name) | ConvertFrom-Json)[0]
    if ($container.Config.Labels.'com.docker.compose.project' -ne 'fandoogh') { throw 'Unexpected ingress identity.' }
    if ($container.State.Running) { $null = Docker -Arguments @('stop', $name); $stoppedIngress += $name }
}
$tables = @(Query $mainName 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA="fandoogh" AND TABLE_TYPE="BASE TABLE" ORDER BY TABLE_NAME;')
if ($tables.Count -lt 25) { throw 'Unexpected current schema; investigate before overwriting.' }
$currentCounts = [ordered]@{}
foreach ($table in $tables) {
    if ($table -notmatch '^[a-z_]+$') { throw 'Unexpected table identifier.' }
    $count = [long](Query $mainName ('SELECT COUNT(*) FROM fandoogh.`' + $table + '`;'))
    $currentCounts[$table] = $count
    if ($table -notin @('migrations','sessions') -and $count -gt 0) { throw "New domain records in $table; STOP without overwriting." }
}
$stamp = [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss',[Globalization.CultureInfo]::InvariantCulture)
$backup = Join-Path $evidence "main-before-verified-restore-$stamp.sql"
$null = Docker -Arguments @('exec', $mainName, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --protocol=socket -uroot --single-transaction --routines --events --triggers --hex-blob --set-gtid-purged=OFF --databases fandoogh --result-file=/tmp/fandoogh-before-verified-restore.sql')
$null = Docker -Arguments @('cp', ($mainName + ':/tmp/fandoogh-before-verified-restore.sql'), $backup)
if ((Get-Item -LiteralPath $backup).Length -lt 1000) { throw 'Current-state snapshot is unexpectedly small.' }
$backupHash = (Get-FileHash -LiteralPath $backup -Algorithm SHA256).Hash
[ordered]@{
    captured_utc = [DateTime]::UtcNow.ToString('o',[Globalization.CultureInfo]::InvariantCulture)
    target_container = $main.Id; target_volume = $mainVolume[0].Name
    source_container = $source.Id; source_volume = $sourceVolume[0].Name
    before_counts = $currentCounts; snapshot = [IO.Path]::GetFileName($backup)
    snapshot_sha256 = $backupHash; ingress_left_stopped = $stoppedIngress
} | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $evidence "before-restore-$stamp.json") -Encoding utf8
$null = Docker -Arguments @('cp', $dump, ($mainName + ':/tmp/fandoogh-recovered-verified.sql'))
# Destruction is limited to the literal verified local schema, after the empty-domain guard and full snapshot.
$null = Query $mainName 'DROP DATABASE `fandoogh`; CREATE DATABASE `fandoogh` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;'
$null = Docker -Arguments @('exec', $mainName, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot fandoogh < /tmp/fandoogh-recovered-verified.sql')
$sourceTables = @(Query $sourceName 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA="fandoogh" AND TABLE_TYPE="BASE TABLE" ORDER BY TABLE_NAME;')
$restoredTables = @(Query $mainName 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA="fandoogh" AND TABLE_TYPE="BASE TABLE" ORDER BY TABLE_NAME;')
if ($sourceTables.Count -ne 25 -or ($sourceTables -join "`n") -ne ($restoredTables -join "`n")) { throw 'Restored table set mismatch.' }
$restoredCounts = [ordered]@{}
foreach ($table in $sourceTables) {
    $sql = 'SELECT COUNT(*) FROM fandoogh.`' + $table + '`;'
    $expected = [long](Query $sourceName $sql)
    $actual = [long](Query $mainName $sql)
    if ($actual -ne $expected) { throw "Row count mismatch: $table" }
    $restoredCounts[$table] = $actual
}
# Canonical complete schema and row dumps compare all columns, indexes, checks, FKs and values without printing private records.
$canonicalHashes = [ordered]@{}
foreach ($container in @($sourceName,$mainName)) {
    $command = 'mysqldump --protocol=socket -uroot --single-transaction --skip-comments --order-by-primary --hex-blob --skip-extended-insert --set-gtid-purged=OFF fandoogh --result-file=/tmp/fandoogh-canonical-verification.sql'
    if ($container -eq $mainName) { $command = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" ' + $command }
    $null = Docker -Arguments @('exec', $container, 'sh', '-c', $command)
    $path = Join-Path $evidence "$container-canonical-$stamp.sql"
    $null = Docker -Arguments @('cp', ($container + ':/tmp/fandoogh-canonical-verification.sql'), $path)
    $canonicalHashes[$container] = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash
}
& (Join-Path $PSScriptRoot 'Verify-RestoredLocalDatabase.ps1')
