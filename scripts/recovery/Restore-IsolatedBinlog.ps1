param(
    [string]$RecoveryContainer = 'fandoogh-recovery-20261003',
    [string]$HelperContainer = 'fandoogh-recovery-tools-20261003',
    [string]$MainContainer = 'fandoogh-db-1',
    [string]$EvidenceDirectory = 'D:\app\fandoogh\artifacts\recovery-20261003'
)
$ErrorActionPreference = 'Stop'
function Invoke-DockerChecked([string[]]$DockerArguments) {
    & docker @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw "Docker command failed ($LASTEXITCODE). Stop and preserve evidence." }
}
if (-not $RecoveryContainer.StartsWith('fandoogh-recovery-')) { throw 'Only a dedicated recovery container is permitted.' }
$target = (& docker inspect $RecoveryContainer | ConvertFrom-Json)[0]
$source = (& docker inspect $MainContainer | ConvertFrom-Json)[0]
if (-not $target -or -not $source -or $target.Id -eq $source.Id) { throw 'Main and recovery container identities must differ.' }
if ($target.HostConfig.NetworkMode -ne 'none' -or $target.HostConfig.PortBindings.PSObject.Properties.Count -gt 0) { throw 'Recovery must have network none and no exposed ports.' }
$targetData = $target.Mounts | Where-Object Destination -eq '/var/lib/mysql'
$sourceData = $source.Mounts | Where-Object Destination -eq '/var/lib/mysql'
if (-not $targetData -or $targetData.Source -eq $sourceData.Source) { throw 'Recovery must use a distinct database volume.' }
$existing = & docker exec $RecoveryContainer mysql --protocol=socket -uroot -N -e 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="fandoogh";'
if ($LASTEXITCODE -ne 0 -or [int]$existing -ne 0) { throw 'Recovery database must be empty. Never overwrite an existing database.' }
$evidence = (Resolve-Path -LiteralPath $EvidenceDirectory).Path
foreach ($name in @('binlog.000030','binlog.000031','binlog.000032')) {
    $logPath = Join-Path $evidence $name
    if (-not (Test-Path -LiteralPath $logPath -PathType Leaf)) { throw "Missing preserved log $name" }
}
Invoke-DockerChecked -DockerArguments @('exec', $HelperContainer, 'mkdir', '-p', '/tmp/recovery')
foreach ($name in @('binlog.000030','binlog.000031','binlog.000032')) {
    Invoke-DockerChecked -DockerArguments @('cp', (Join-Path $evidence $name), ($HelperContainer + ':/tmp/recovery/' + $name))
}
# These boundaries are specific to the independently inspected 2026-10-03 incident.
# A different incident requires fresh read-only analysis, never reusing these positions.
Invoke-DockerChecked -DockerArguments @('exec', $HelperContainer, 'sh', '-c', 'mysqlbinlog --verify-binlog-checksum --database=fandoogh --skip-gtids --disable-log-bin --start-position=109668 --stop-position=127117 /tmp/recovery/binlog.000030 /tmp/recovery/binlog.000031 /tmp/recovery/binlog.000032 > /tmp/recovery/replay.sql')
$replay = Join-Path $evidence 'replay.sql'
Invoke-DockerChecked -DockerArguments @('cp', ($HelperContainer + ':/tmp/recovery/replay.sql'), $replay)
$content = Get-Content -LiteralPath $replay -Raw
if ([regex]::Matches($content, '(?im)^create table').Count -ne 25 -or $content -match '(?im)^drop table' -or $content -match '(?m)^# at 127117\r?$') { throw 'Replay boundary/schema validation failed.' }
$schemas = @([regex]::Matches($content, '(?m)^use `([^`]+)`') | ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique)
if ($schemas.Count -ne 1 -or $schemas[0] -ne 'fandoogh') { throw 'Replay must target only the isolated fandoogh schema.' }
Invoke-DockerChecked -DockerArguments @('cp', $replay, ($RecoveryContainer + ':/tmp/replay.sql'))
Invoke-DockerChecked -DockerArguments @('exec', $RecoveryContainer, 'sh', '-c', 'mysql --protocol=socket -uroot fandoogh < /tmp/replay.sql')
Write-Output 'Isolated replay completed. Verify table counts/constraints before requesting any main database action.'
