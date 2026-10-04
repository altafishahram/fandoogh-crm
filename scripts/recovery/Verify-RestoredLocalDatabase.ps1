$ErrorActionPreference = 'Stop'
$evidence = 'D:\app\fandoogh\artifacts\recovery-20261003'
$actualMain = & docker.exe inspect --format '{{.Id}}' fandoogh-db-1
if ($LASTEXITCODE -ne 0 -or $actualMain -ne '6af52e1458f8e8351d445f5c704ac9ff8f558593a429b9f0f540a37f8253a826') { throw 'Original local database identity changed.' }
$actualSource = & docker.exe inspect --format '{{.Id}}' fandoogh-recovery-20261003
if ($LASTEXITCODE -ne 0 -or $actualSource -ne '77535e26f7b52644090f89a133417372effc3cbfaffa03166b79f34d0159111e') { throw 'Isolated source identity changed.' }
function Query([string]$Container, [string]$Sql) {
    if ($Container -eq 'fandoogh-db-1') {
        $result = & docker.exe exec $Container sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=socket -uroot -N -B -e "$1"' query $Sql
    } else { $result = & docker.exe exec $Container mysql --protocol=socket -uroot -N -B -e $Sql }
    if ($LASTEXITCODE -ne 0) { throw 'Read-only database verification failed.' }
    return $result
}
function Hash([string]$Value) {
    return [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Value)))
}
$metadataQueries = [ordered]@{
    schemas = 'SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME="fandoogh";'
    tables = 'SELECT TABLE_NAME,TABLE_TYPE,ENGINE,ROW_FORMAT,TABLE_COLLATION,CREATE_OPTIONS,IFNULL(AUTO_INCREMENT,0) FROM information_schema.TABLES WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME;'
    columns = 'SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,IFNULL(HEX(COLUMN_DEFAULT),"<NULL>"),EXTRA,IFNULL(CHARACTER_SET_NAME,"<NULL>"),IFNULL(COLLATION_NAME,"<NULL>"),GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME,ORDINAL_POSITION;'
    indexes = 'SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,IFNULL(COLUMN_NAME,"<NULL>"),IFNULL(COLLATION,"<NULL>"),IFNULL(SUB_PART,0),INDEX_TYPE,IS_VISIBLE,IFNULL(EXPRESSION,"<NULL>") FROM information_schema.STATISTICS WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;'
    constraints = 'SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE,ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME,CONSTRAINT_NAME;'
    checks = 'SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA="fandoogh" ORDER BY CONSTRAINT_NAME;'
    key_columns = 'SELECT TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,IFNULL(REFERENCED_TABLE_NAME,"<NULL>"),IFNULL(REFERENCED_COLUMN_NAME,"<NULL>") FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION;'
    references = 'SELECT TABLE_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA="fandoogh" ORDER BY TABLE_NAME,CONSTRAINT_NAME;'
}
$metadata = [ordered]@{}
foreach ($key in $metadataQueries.get_Keys()) {
    $source = @(Query 'fandoogh-recovery-20261003' $metadataQueries[$key]) -join "`n"
    $main = @(Query 'fandoogh-db-1' $metadataQueries[$key]) -join "`n"
    if ($source -cne $main) { throw "Effective metadata mismatch in $key." }
    $metadata[$key] = Hash $main
}
$counts = [ordered]@{}
foreach ($table in @(Query 'fandoogh-recovery-20261003' 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA="fandoogh" ORDER BY TABLE_NAME;')) {
    if ($table -notmatch '^[a-z_]+$') { throw 'Unexpected identifier.' }
    $sql = 'SELECT COUNT(*) FROM fandoogh.`' + $table + '`;'
    $source = [long](Query 'fandoogh-recovery-20261003' $sql)
    $main = [long](Query 'fandoogh-db-1' $sql)
    if ($source -ne $main) { throw "Row count differs for $table." }
    $counts[$table] = $main
}
$sourceDump = Get-ChildItem -LiteralPath $evidence -Filter 'fandoogh-recovery-20261003-canonical-*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$mainDump = Get-ChildItem -LiteralPath $evidence -Filter 'fandoogh-db-1-canonical-*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$sourceRows = @(Get-Content -LiteralPath $sourceDump.FullName | Where-Object { $_.StartsWith('INSERT INTO ') }) -join "`n"
$mainRows = @(Get-Content -LiteralPath $mainDump.FullName | Where-Object { $_.StartsWith('INSERT INTO ') }) -join "`n"
if ($sourceRows -cne $mainRows) { throw 'Private row canonical representations differ.' }
$fkSql = @'
SELECT CONCAT('SELECT COUNT(*) FROM fandoogh.`',TABLE_NAME,'` c LEFT JOIN fandoogh.`',REFERENCED_TABLE_NAME,'` p ON ',GROUP_CONCAT(CONCAT('c.`',COLUMN_NAME,'` = p.`',REFERENCED_COLUMN_NAME,'`') ORDER BY ORDINAL_POSITION SEPARATOR ' AND '),' WHERE ',GROUP_CONCAT(CONCAT('c.`',COLUMN_NAME,'` IS NOT NULL') ORDER BY ORDINAL_POSITION SEPARATOR ' AND '),' AND p.`',SUBSTRING_INDEX(GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION),',',1),'` IS NULL;') FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='fandoogh' AND REFERENCED_TABLE_NAME IS NOT NULL GROUP BY TABLE_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME ORDER BY TABLE_NAME,CONSTRAINT_NAME;
'@
$foreignChecks = @(Query 'fandoogh-db-1' $fkSql)
if ($foreignChecks.Count -ne 38) { throw 'Unexpected foreign-key count.' }
foreach ($sql in $foreignChecks) { if ([long](Query 'fandoogh-db-1' $sql) -ne 0) { throw 'Orphan row detected.' } }
$backup = Get-ChildItem -LiteralPath $evidence -Filter 'main-before-verified-restore-*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$result = [ordered]@{
    completed_utc = [DateTime]::UtcNow.ToString('o',[Globalization.CultureInfo]::InvariantCulture)
    target_container = '6af52e1458f8e8351d445f5c704ac9ff8f558593a429b9f0f540a37f8253a826'
    target_volume = 'fandoogh_mysql_data'; source_container = '77535e26f7b52644090f89a133417372effc3cbfaffa03166b79f34d0159111e'
    snapshot = $backup.Name; snapshot_sha256 = (Get-FileHash -LiteralPath $backup.FullName -Algorithm SHA256).Hash
    recovered_dump_sha256 = (Get-FileHash -LiteralPath (Join-Path $evidence 'recovered-fandoogh-before-incident.sql') -Algorithm SHA256).Hash
    tables = $counts.Count; rows = $counts; metadata_sha256 = $metadata
    canonical_rows_sha256 = Hash $mainRows; foreign_keys_checked = 38; foreign_key_orphans = 0
    comparison_note = 'Effective metadata and all canonical row INSERTs match; raw DDL rendering adds redundant explicit charset/collation clauses on target.'
    ingress = 'app and nginx stopped pending additive migrations'
}
$result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $evidence 'verified-local-restore-result.json') -Encoding utf8
Write-Output 'Verified: all 25 tables, effective complete schema, canonical row data, and 38 foreign keys match isolated recovery source; zero orphans.'
