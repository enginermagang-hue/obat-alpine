<?php

declare(strict_types=1);

/**
 * Pembanding skema DB referensi (MySQL `obat`) vs DB target.
 *
 * Pemakaian:
 *   php database/schema-diff.php                          # target SQLite dev (default)
 *   php database/schema-diff.php --target=mysql           # target MySQL obat_v2
 *   php database/schema-diff.php --sqlite=path/db.sqlite  # target SQLite lain
 *   php database/schema-diff.php obat obat_v2             # referensi & target MySQL lain
 *
 * Exit code 0 bila tidak ada selisih, 1 bila ada.
 * Kredensial MySQL dari env DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD.
 *
 * Catatan mode SQLite:
 * - Tipe kolom dinormalkan (MySQL `bigint unsigned` -> SQLite `integer`, `enum` -> `varchar`, dst).
 * - Nama constraint FK tidak dibandingkan (PRAGMA tidak menyimpannya).
 * - `RESTRICT` dan `NO ACTION` dianggap sama.
 * - Index pendamping FK (`*_foreign`) di MySQL tidak ada padanannya di SQLite -> diabaikan.
 */

$refDb = 'obat';
$target = 'sqlite';
$sqlitePath = __DIR__ . '/database.sqlite';
$newDb = 'obat_v2';

$positional = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, 9);
    } elseif (str_starts_with($arg, '--sqlite=')) {
        $sqlitePath = substr($arg, 9);
    } elseif (str_starts_with($arg, '--mysql-target=')) {
        $newDb = substr($arg, 15);
    } else {
        $positional[] = $arg;
    }
}
$refDb = $positional[0] ?? $refDb;
$newDb = $positional[1] ?? $newDb;

if (! in_array($target, ['sqlite', 'mysql'], true)) {
    fwrite(STDERR, "Target tidak dikenal: {$target} (pakai sqlite|mysql)\n");
    exit(2);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$ref = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $pdoOptions);
$targetPdo = $target === 'sqlite'
    ? new PDO('sqlite:' . $sqlitePath, null, null, $pdoOptions)
    : new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $pdoOptions);

/**
 * Warisan skema lama yang sengaja tidak dibuat di project baru.
 */
$skipColumns = [
    'laporan_lplpo.jenis_pengajuan',
];
$skipIndexes = [
    'pemakaian_obat.idx_pemakaian_header_v2',
];
$skipTables = [
    'settings',
];

function rows(PDO $pdo, string $sql): array
{
    return array_map(
        static fn (array $r): array => array_change_key_case($r, CASE_LOWER),
        $pdo->query($sql)->fetchAll()
    );
}

function normalizeDefault(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || strcasecmp($value, 'null') === 0) {
        return null;
    }
    if (preg_match("/^'(.*)'$/s", $value, $m)) {
        $value = str_replace("''", "'", $m[1]);
    }
    if (strcasecmp($value, 'CURRENT_TIMESTAMP') === 0) {
        return 'CURRENT_TIMESTAMP';
    }
    if (is_numeric($value)) {
        return (string) (0 + $value);
    }

    return $value;
}

function normalizeRule(string $rule): string
{
    $rule = strtoupper(trim($rule));

    return $rule === 'RESTRICT' ? 'NO ACTION' : $rule;
}

function expectedSqliteType(string $columnType): string
{
    $t = strtolower(trim($columnType));

    if (str_starts_with($t, 'enum(')) {
        return 'varchar';
    }
    if (str_starts_with($t, 'tinyint(1)')) {
        // Laravel menulis boolean sebagai tinyint(1); SQLite memberi affinity integer.
        return 'integer';
    }
    if (preg_match('/^(bigint|int|mediumint|smallint|tinyint)/', $t)) {
        return 'integer';
    }
    if (str_starts_with($t, 'varchar') || str_starts_with($t, 'char')) {
        return 'varchar';
    }
    if (str_starts_with($t, 'json')) {
        return 'text';
    }
    if (preg_match('/^(text|mediumtext|longtext)/', $t)) {
        return 'text';
    }
    if (str_starts_with($t, 'decimal') || str_starts_with($t, 'numeric')) {
        return 'numeric';
    }
    if (str_starts_with($t, 'float')) {
        return 'float';
    }
    if (str_starts_with($t, 'double')) {
        return 'double';
    }
    if (str_starts_with($t, 'datetime')) {
        return 'datetime';
    }
    if (str_starts_with($t, 'timestamp')) {
        return 'datetime';
    }
    if (str_starts_with($t, 'date')) {
        return 'date';
    }
    if (str_starts_with($t, 'time')) {
        return 'time';
    }

    return $t;
}

function normalizeSqliteType(string $declared): string
{
    $t = strtolower(trim($declared));
    // Laravel menulis "varchar check (...)" untuk enum.
    $t = explode(' ', $t)[0];
    $t = explode('(', $t)[0];

    return match ($t) {
        'int', 'bigint', 'smallint', 'tinyint', 'mediumint' => 'integer',
        default => $t === 'boolean' ? 'tinyint(1)' : $t,
    };
}

// ---------------------------------------------------------------- referensi MySQL

$refTables = array_column(rows($ref, "select table_name from information_schema.tables
    where table_schema = " . $ref->quote($refDb) . " and table_type = 'BASE TABLE' order by table_name"), 'table_name');

$refColumns = [];
foreach (rows($ref, 'select table_name, column_name, column_type, is_nullable, column_default, extra
        from information_schema.columns where table_schema = ' . $ref->quote($refDb)) as $r) {
    $refColumns[$r['table_name'] . '.' . $r['column_name']] = $r;
}

$refIndexes = [];
foreach (rows($ref, "select table_name, index_name, non_unique, seq_in_index, column_name
        from information_schema.statistics where table_schema = " . $ref->quote($refDb) . '
        order by table_name, index_name, seq_in_index') as $r) {
    $key = $r['table_name'] . '.' . $r['index_name'];
    $refIndexes[$key]['table'] = $r['table_name'];
    $refIndexes[$key]['unique'] = (int) $r['non_unique'] === 0;
    $refIndexes[$key]['columns'][] = $r['column_name'];
}

$refFks = [];
foreach (rows($ref, "select k.table_name, k.constraint_name, k.column_name, k.referenced_table_name,
        k.referenced_column_name, r.update_rule, r.delete_rule
        from information_schema.key_column_usage k
        join information_schema.referential_constraints r
          on r.constraint_schema = k.constraint_schema and r.constraint_name = k.constraint_name
        where k.table_schema = " . $ref->quote($refDb) . '
          and k.referenced_table_name is not null
        order by k.table_name, k.constraint_name, k.ordinal_position') as $r) {
    $key = $r['table_name'] . '.' . $r['constraint_name'];
    $refFks[$key]['table'] = $r['table_name'];
    $refFks[$key]['columns'][] = $r['column_name'];
    $refFks[$key]['ref_table'] = $r['referenced_table_name'];
    $refFks[$key]['ref_columns'][] = $r['referenced_column_name'];
    $refFks[$key]['on_update'] = $r['update_rule'];
    $refFks[$key]['on_delete'] = $r['delete_rule'];
}

// ---------------------------------------------------------------- target

$diffs = [];

if ($target === 'sqlite') {
    $newTables = array_values(array_filter(
        array_column(rows($targetPdo, "select name from sqlite_master where type = 'table' order by name"), 'name'),
        static fn (string $t): bool => ! str_starts_with($t, 'sqlite_')
    ));

    $newColumns = [];
    $newIndexes = [];
    $newFks = [];

    foreach ($newTables as $table) {
        $quoted = '"' . str_replace('"', '""', $table) . '"';

        foreach (rows($targetPdo, 'PRAGMA table_info(' . $quoted . ')') as $r) {
            $newColumns[$table . '.' . $r['name']] = [
                'table' => $table,
                'type' => normalizeSqliteType((string) $r['type']),
                'notnull' => (int) $r['notnull'] === 1,
                'default' => normalizeDefault($r['dflt_value'] === null ? null : (string) $r['dflt_value']),
                'pk' => (int) $r['pk'],
            ];
        }

        foreach (rows($targetPdo, 'PRAGMA index_list(' . $quoted . ')') as $idx) {
            $name = (string) $idx['name'];
            if (str_starts_with($name, 'sqlite_autoindex_') || (string) $idx['origin'] !== 'c') {
                continue;
            }
            $key = $table . '.' . $name;
            $newIndexes[$key] = [
                'table' => $table,
                'unique' => (int) $idx['unique'] === 1,
                'columns' => array_column(
                    rows($targetPdo, 'PRAGMA index_info(' . '"' . str_replace('"', '""', $name) . '"' . ')'),
                    'name'
                ),
            ];
        }

        $grouped = [];
        foreach (rows($targetPdo, 'PRAGMA foreign_key_list(' . $quoted . ')') as $fk) {
            $grouped[$fk['id']]['table'] = $table;
            $grouped[$fk['id']]['columns'][] = $fk['from'];
            $grouped[$fk['id']]['ref_table'] = $fk['table'];
            $grouped[$fk['id']]['ref_columns'][] = $fk['to'];
            $grouped[$fk['id']]['on_update'] = $fk['on_update'];
            $grouped[$fk['id']]['on_delete'] = $fk['on_delete'];
        }
        foreach ($grouped as $id => $fk) {
            $newFks[$table . '#' . $id] = $fk;
        }
    }
} else {
    $newTables = array_column(rows($targetPdo, "select table_name from information_schema.tables
        where table_schema = " . $targetPdo->quote($newDb) . " and table_type = 'BASE TABLE' order by table_name"), 'table_name');

    $newColumns = [];
    foreach (rows($targetPdo, 'select table_name, column_name, column_type, is_nullable, column_default, extra
            from information_schema.columns where table_schema = ' . $targetPdo->quote($newDb)) as $r) {
        $r['table'] = $r['table_name'];
        $newColumns[$r['table_name'] . '.' . $r['column_name']] = $r;
    }

    $newIndexes = [];
    foreach (rows($targetPdo, "select table_name, index_name, non_unique, seq_in_index, column_name
            from information_schema.statistics where table_schema = " . $targetPdo->quote($newDb) . '
            order by table_name, index_name, seq_in_index') as $r) {
        $key = $r['table_name'] . '.' . $r['index_name'];
        $newIndexes[$key]['table'] = $r['table_name'];
        $newIndexes[$key]['unique'] = (int) $r['non_unique'] === 0;
        $newIndexes[$key]['columns'][] = $r['column_name'];
    }

    $newFks = [];
    foreach (rows($targetPdo, "select k.table_name, k.constraint_name, k.column_name, k.referenced_table_name,
            k.referenced_column_name, r.update_rule, r.delete_rule
            from information_schema.key_column_usage k
            join information_schema.referential_constraints r
              on r.constraint_schema = k.constraint_schema and r.constraint_name = k.constraint_name
            where k.table_schema = " . $targetPdo->quote($newDb) . '
              and k.referenced_table_name is not null
            order by k.table_name, k.constraint_name, k.ordinal_position') as $r) {
        $key = $r['table_name'] . '.' . $r['constraint_name'];
        $newFks[$key]['table'] = $r['table_name'];
        $newFks[$key]['columns'][] = $r['column_name'];
        $newFks[$key]['ref_table'] = $r['referenced_table_name'];
        $newFks[$key]['ref_columns'][] = $r['referenced_column_name'];
        $newFks[$key]['on_update'] = $r['update_rule'];
        $newFks[$key]['on_delete'] = $r['delete_rule'];
    }
}

// ---------------------------------------------------------------- kolom

foreach ($newTables as $table) {
    if (in_array($table, $skipTables, true)) {
        continue;
    }
    foreach ($newColumns as $key => $col) {
        if ($col['table'] !== $table) {
            continue;
        }
        $refCol = $refColumns[$key] ?? null;
        if ($refCol === null) {
            $diffs[] = "KOLOM BARU    {$key}";

            continue;
        }

        if ($target === 'sqlite') {
            $expectedType = expectedSqliteType($refCol['column_type']);
            if ($expectedType !== $col['type']) {
                $diffs[] = "KOLOM TIPE    {$key}: ref={$refCol['column_type']} -> sqlite:{$expectedType}"
                    . " vs new:{$col['type']}";
            }
            $expectedNotNull = $refCol['is_nullable'] === 'NO';
            if ($expectedNotNull !== $col['notnull']) {
                $diffs[] = "KOLOM NULL    {$key}: ref notnull=" . (int) $expectedNotNull
                    . ' vs new=' . (int) $col['notnull'];
            }
            $refDefault = normalizeDefault($refCol['column_default'] === null ? null : (string) $refCol['column_default']);
            if ($refDefault !== $col['default']) {
                $diffs[] = "KOLOM DEFAULT {$key}: ref=" . var_export($refDefault, true)
                    . ' vs new=' . var_export($col['default'], true);
            }
            $isPk = false;
            foreach ($refIndexes as $idxKey => $idx) {
                if ($idxKey === $table . '.PRIMARY' && in_array($refCol['column_name'], $idx['columns'], true)) {
                    $isPk = true;
                }
            }
            if ($isPk !== ($col['pk'] > 0)) {
                $diffs[] = "KOLOM PK      {$key}: ref=" . (int) $isPk . ' vs new=' . (int) ($col['pk'] > 0);
            }
        } else {
            foreach (['column_type', 'is_nullable', 'column_default', 'extra'] as $field) {
                if ((string) $refCol[$field] !== (string) $col[$field]) {
                    $diffs[] = "KOLOM BEDA    {$key}.{$field}: ref=" . var_export($refCol[$field], true)
                        . ' vs new=' . var_export($col[$field], true);
                }
            }
        }
    }
}

foreach ($refColumns as $key => $col) {
    if (in_array($key, $skipColumns, true) || in_array($col['table_name'], $skipTables, true)) {
        continue;
    }
    if (in_array($col['table_name'], $newTables, true) && ! isset($newColumns[$key])) {
        $diffs[] = "KOLOM HILANG  {$key} ({$col['column_type']})";
    }
}

// ---------------------------------------------------------------- index

foreach ($newIndexes as $key => $idx) {
    if (! in_array($idx['table'], $newTables, true) || in_array($idx['table'], $skipTables, true)) {
        continue;
    }
    $refIdx = $refIndexes[$key] ?? null;
    if ($refIdx === null) {
        $diffs[] = "INDEX BARU    {$key} (" . implode(',', $idx['columns']) . ')';

        continue;
    }
    if ($refIdx['columns'] !== $idx['columns'] || $refIdx['unique'] !== $idx['unique']) {
        $diffs[] = "INDEX BEDA    {$key}: ref=[" . implode(',', $refIdx['columns']) . '] unique=' . (int) $refIdx['unique']
            . ' vs new=[' . implode(',', $idx['columns']) . '] unique=' . (int) $idx['unique'];
    }
}

foreach ($refIndexes as $key => $idx) {
    if ($idx['table'] === 'migrations' || in_array($idx['table'], $skipTables, true)) {
        continue;
    }
    if (! in_array($idx['table'], $newTables, true) || in_array($key, $skipIndexes, true)) {
        continue;
    }
    // Index pendamping FK tidak dibuat terpisah di SQLite.
    if ($target === 'sqlite' && ($key === $idx['table'] . '.PRIMARY' || str_ends_with($key, '_foreign'))) {
        continue;
    }
    if (! isset($newIndexes[$key])) {
        $diffs[] = "INDEX HILANG  {$key} (" . implode(',', $idx['columns']) . ')';
    }
}

// ---------------------------------------------------------------- foreign key

$fkSignature = static function (array $fk, bool $withName, string $key = ''): string {
    $sig = $fk['table'] . '|' . implode(',', $fk['columns']) . '->' . $fk['ref_table'] . '.' . implode(',', $fk['ref_columns'])
        . '|' . normalizeRule($fk['on_delete']) . '/' . normalizeRule($fk['on_update']);

    return $withName ? $key . ' => ' . $sig : $sig;
};

if ($target === 'sqlite') {
    $refSigs = [];
    foreach ($refFks as $key => $fk) {
        if (in_array($fk['table'], $newTables, true)) {
            $refSigs[$fkSignature($fk, false)] = $fkSignature($fk, true, $key);
        }
    }
    $newSigs = [];
    foreach ($newFks as $key => $fk) {
        $newSigs[$fkSignature($fk, false)] = $fkSignature($fk, true, $key);
    }

    foreach ($newSigs as $sig => $label) {
        if (! isset($refSigs[$sig])) {
            $diffs[] = "FK BARU       {$label}";
        }
    }
    foreach ($refSigs as $sig => $label) {
        if (! isset($newSigs[$sig])) {
            $diffs[] = "FK HILANG     {$label}";
        }
    }
} else {
    foreach ($newFks as $key => $fk) {
        if (! in_array($fk['table'], $newTables, true)) {
            continue;
        }
        $refFk = $refFks[$key] ?? null;
        if ($refFk === null) {
            $diffs[] = "FK BARU       {$key} -> {$fk['ref_table']}";

            continue;
        }
        if ($fkSignature($refFk, false) !== $fkSignature($fk, false)) {
            $diffs[] = "FK BEDA       {$key}: ref=" . $fkSignature($refFk, false) . ' vs new=' . $fkSignature($fk, false);
        }
    }
    foreach ($refFks as $key => $fk) {
        if (in_array($fk['table'], $newTables, true) && ! isset($newFks[$key])) {
            $diffs[] = "FK HILANG     {$key}";
        }
    }
}

// ---------------------------------------------------------------- laporan

$missingTables = array_values(array_diff($refTables, $newTables, $skipTables));

echo "Driver target     : {$target}" . ($target === 'sqlite' ? " ({$sqlitePath})" : " ({$newDb})") . PHP_EOL;
echo 'Tabel dibuat      : ' . count($newTables) . ' / referensi ' . count($refTables) . PHP_EOL;
if ($missingTables !== []) {
    echo 'Tabel belum ada   : ' . implode(', ', $missingTables) . PHP_EOL;
}
echo 'Selisih kolom/index/FK: ' . count($diffs) . PHP_EOL;

foreach ($diffs as $d) {
    echo ' - ' . $d . PHP_EOL;
}

exit($diffs === [] ? 0 : 1);
