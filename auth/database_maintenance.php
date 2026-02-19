<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function database_backup_config(): array
{
    static $config = null;
    if (!is_array($config)) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function database_backup_driver(): string
{
    $config = database_backup_config();
    $driver = strtolower((string)($config['db']['driver'] ?? 'sqlite'));

    if ($driver === '') {
        return 'sqlite';
    }

    return $driver;
}

function database_backup_extension_for_driver(string $driver): string
{
    return $driver === 'sqlite' ? 'sqlite' : 'sql';
}

function database_backup_driver_from_filename(string $fileName): ?string
{
    $fileName = strtolower(trim($fileName));
    if ($fileName === '') {
        return null;
    }

    if (str_ends_with($fileName, '.sqlite')) {
        return 'sqlite';
    }

    if (str_ends_with($fileName, '.sql')) {
        return 'mysql';
    }

    return null;
}

function database_backup_directory(): string
{
    return __DIR__ . '/../data/backups';
}

function database_backup_settings_path(): string
{
    return __DIR__ . '/../data/backup_settings.json';
}

function database_backup_lock_path(): string
{
    return __DIR__ . '/../data/backup_auto.lock';
}

function database_backup_default_settings(): array
{
    return [
        'enabled' => false,
        'interval_hours' => 24,
        'retention_count' => 14,
        'last_attempt_at' => null,
        'last_success_at' => null,
        'last_error' => null,
    ];
}

function normalize_database_backup_settings(array $raw): array
{
    $defaults = database_backup_default_settings();

    $enabled = (bool)($raw['enabled'] ?? $defaults['enabled']);

    $intervalHours = (int)($raw['interval_hours'] ?? $defaults['interval_hours']);
    if ($intervalHours < 1) {
        $intervalHours = 1;
    } elseif ($intervalHours > 720) {
        $intervalHours = 720;
    }

    $retentionCount = (int)($raw['retention_count'] ?? $defaults['retention_count']);
    if ($retentionCount < 1) {
        $retentionCount = 1;
    } elseif ($retentionCount > 365) {
        $retentionCount = 365;
    }

    $lastAttemptAt = is_string($raw['last_attempt_at'] ?? null) ? trim((string)$raw['last_attempt_at']) : '';
    if ($lastAttemptAt === '') {
        $lastAttemptAt = null;
    }

    $lastSuccessAt = is_string($raw['last_success_at'] ?? null) ? trim((string)$raw['last_success_at']) : '';
    if ($lastSuccessAt === '') {
        $lastSuccessAt = null;
    }

    $lastError = is_string($raw['last_error'] ?? null) ? trim((string)$raw['last_error']) : '';
    if ($lastError === '') {
        $lastError = null;
    } else {
        $lastError = substr($lastError, 0, 500);
    }

    return [
        'enabled' => $enabled,
        'interval_hours' => $intervalHours,
        'retention_count' => $retentionCount,
        'last_attempt_at' => $lastAttemptAt,
        'last_success_at' => $lastSuccessAt,
        'last_error' => $lastError,
    ];
}

function load_database_backup_settings(): array
{
    $path = database_backup_settings_path();
    if (!is_file($path)) {
        return database_backup_default_settings();
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return database_backup_default_settings();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return database_backup_default_settings();
    }

    return normalize_database_backup_settings($decoded);
}

function save_database_backup_settings(array $settings): void
{
    $normalized = normalize_database_backup_settings($settings);

    $path = database_backup_settings_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create backup settings directory.');
    }

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Unable to encode backup settings.');
    }

    $tempPath = $path . '.tmp';
    $bytes = file_put_contents($tempPath, $json, LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException('Unable to write backup settings file.');
    }

    if (!@rename($tempPath, $path) && !@copy($tempPath, $path)) {
        @unlink($tempPath);
        throw new RuntimeException('Unable to finalize backup settings file.');
    }

    if (is_file($tempPath)) {
        @unlink($tempPath);
    }
}

function ensure_database_backup_directory(): string
{
    $dir = database_backup_directory();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create backup directory.');
    }

    return $dir;
}

function is_valid_database_backup_filename(string $fileName): bool
{
    $pattern = '/^db-backup-\d{8}-\d{6}-(manual|auto)(?:-\d{2})?\.(sqlite|sql)$/';
    return preg_match($pattern, $fileName) === 1;
}

function resolve_database_backup_path(string $fileName): ?string
{
    $fileName = trim($fileName);
    if ($fileName === '' || basename($fileName) !== $fileName) {
        return null;
    }

    if (!is_valid_database_backup_filename($fileName)) {
        return null;
    }

    return database_backup_directory() . DIRECTORY_SEPARATOR . $fileName;
}

function list_database_backups(): array
{
    $dir = database_backup_directory();
    if (!is_dir($dir)) {
        return [];
    }

    $entries = scandir($dir);
    if ($entries === false) {
        return [];
    }

    $items = [];
    foreach ($entries as $entry) {
        if (!is_string($entry) || !is_valid_database_backup_filename($entry)) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) {
            continue;
        }

        $size = filesize($path);
        $modifiedAt = filemtime($path);
        if ($size === false || $modifiedAt === false) {
            continue;
        }

        $mode = 'manual';
        $driver = 'sqlite';
        if (preg_match('/-(manual|auto)(?:-\d{2})?\.(sqlite|sql)$/', $entry, $matches) === 1) {
            $mode = (string)$matches[1];
            $driver = $matches[2] === 'sql' ? 'mysql' : 'sqlite';
        }

        $items[] = [
            'file_name' => $entry,
            'path' => $path,
            'size' => (int)$size,
            'modified_ts' => (int)$modifiedAt,
            'modified_at' => date(DATE_ATOM, (int)$modifiedAt),
            'mode' => $mode,
            'driver' => $driver,
        ];
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            $timeCompare = ($b['modified_ts'] ?? 0) <=> ($a['modified_ts'] ?? 0);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }

            return strcmp((string)($b['file_name'] ?? ''), (string)($a['file_name'] ?? ''));
        }
    );

    return $items;
}

function build_database_backup_target(string $mode, string $extension): array
{
    $backupDir = ensure_database_backup_directory();
    $stamp = (new DateTimeImmutable('now'))->format('Ymd-His');

    $suffix = 0;
    do {
        $suffixPart = $suffix > 0 ? '-' . str_pad((string)$suffix, 2, '0', STR_PAD_LEFT) : '';
        $fileName = 'db-backup-' . $stamp . '-' . $mode . $suffixPart . '.' . $extension;
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $fileName;
        $suffix++;
    } while (is_file($backupPath) && $suffix <= 99);

    if (is_file($backupPath)) {
        throw new RuntimeException('Unable to create unique backup filename.');
    }

    return [
        'file_name' => $fileName,
        'path' => $backupPath,
    ];
}

function create_database_backup(string $mode = 'manual', ?array $actor = null): array
{
    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['manual', 'auto'], true)) {
        throw new RuntimeException('Invalid backup mode.');
    }

    $driver = database_backup_driver();
    $extension = database_backup_extension_for_driver($driver);
    $target = build_database_backup_target($mode, $extension);
    $fileName = (string)$target['file_name'];
    $backupPath = (string)$target['path'];

    try {
        if ($driver === 'sqlite') {
            create_sqlite_backup_file($backupPath);
        } else {
            create_mysql_backup_file($backupPath);
        }
    } catch (Throwable $exception) {
        if (is_file($backupPath)) {
            @unlink($backupPath);
        }

        throw $exception;
    }

    $size = filesize($backupPath);
    if ($size === false) {
        throw new RuntimeException('Backup created but size could not be determined.');
    }

    $result = [
        'file_name' => $fileName,
        'path' => $backupPath,
        'size' => (int)$size,
        'created_at' => date(DATE_ATOM),
        'mode' => $mode,
        'driver' => $driver,
    ];

    if (function_exists('log_event_action') && is_array($actor) && (int)($actor['id'] ?? 0) > 0) {
        log_event_action($actor, 'create', null, null, [
            'type' => 'database_backup',
            'mode' => $mode,
            'driver' => $driver,
            'file_name' => $fileName,
            'size' => (int)$size,
        ], 'database_backup');
    }

    return $result;
}

function create_sqlite_file_copy_backup(string $sourcePath, string $mode = 'manual', ?array $actor = null): array
{
    $sourcePath = trim($sourcePath);
    if ($sourcePath === '' || !is_file($sourcePath)) {
        throw new RuntimeException('SQLite source file was not found.');
    }

    $target = build_database_backup_target($mode, 'sqlite');
    $fileName = (string)$target['file_name'];
    $backupPath = (string)$target['path'];

    if (!@copy($sourcePath, $backupPath)) {
        throw new RuntimeException('SQLite safety backup failed.');
    }

    $size = filesize($backupPath);
    if ($size === false) {
        throw new RuntimeException('Safety backup created but size could not be determined.');
    }

    if (function_exists('log_event_action') && is_array($actor) && (int)($actor['id'] ?? 0) > 0) {
        log_event_action($actor, 'create', null, null, [
            'type' => 'database_backup',
            'mode' => $mode,
            'driver' => 'sqlite',
            'file_name' => $fileName,
            'size' => (int)$size,
        ], 'database_backup');
    }

    return [
        'file_name' => $fileName,
        'path' => $backupPath,
        'size' => (int)$size,
        'created_at' => date(DATE_ATOM),
        'mode' => $mode,
        'driver' => 'sqlite',
    ];
}

function create_sqlite_backup_file(string $targetPath): void
{
    $config = database_backup_config();
    $sourcePath = (string)($config['db']['sqlite_path'] ?? '');

    if ($sourcePath === '') {
        throw new RuntimeException('SQLite path is not configured.');
    }

    $pdo = get_db();
    $escapedTarget = str_replace("'", "''", $targetPath);

    try {
        $pdo->exec("VACUUM INTO '" . $escapedTarget . "'");
        return;
    } catch (Throwable) {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
    }

    if (!is_file($sourcePath)) {
        throw new RuntimeException('SQLite source file was not found.');
    }

    if (!@copy($sourcePath, $targetPath)) {
        throw new RuntimeException('SQLite backup failed.');
    }
}

function database_backup_mysql_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function create_mysql_backup_file(string $targetPath): void
{
    $pdo = get_db();
    $config = database_backup_config();
    $databaseName = (string)($config['db']['name'] ?? '');

    $handle = fopen($targetPath, 'wb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Unable to create MySQL backup file.');
    }

    try {
        fwrite($handle, "-- Database backup\n");
        if ($databaseName !== '') {
            fwrite($handle, "-- Database: " . $databaseName . "\n");
        }
        fwrite($handle, "-- Generated at: " . date('c') . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tablesStmt = $pdo->query('SHOW TABLES');
        $tableRows = $tablesStmt->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($tableRows as $row) {
            $tableName = (string)($row[0] ?? '');
            if ($tableName !== '') {
                $tables[] = $tableName;
            }
        }

        foreach ($tables as $tableName) {
            $escapedTable = database_backup_mysql_identifier($tableName);

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $escapedTable);
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);

            $createSql = '';
            if (is_array($createRow)) {
                if (isset($createRow['Create Table'])) {
                    $createSql = (string)$createRow['Create Table'];
                } elseif (isset($createRow['Create View'])) {
                    $createSql = (string)$createRow['Create View'];
                } else {
                    $values = array_values($createRow);
                    $createSql = (string)($values[1] ?? $values[0] ?? '');
                }
            }

            if ($createSql === '') {
                throw new RuntimeException('Unable to read schema for table: ' . $tableName);
            }

            fwrite($handle, "DROP TABLE IF EXISTS " . $escapedTable . ";\n");
            fwrite($handle, $createSql . ";\n\n");

            $dataStmt = $pdo->query('SELECT * FROM ' . $escapedTable);
            while (($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns = [];
                $values = [];

                foreach ($row as $column => $value) {
                    $columns[] = database_backup_mysql_identifier((string)$column);
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_bool($value)) {
                        $values[] = $value ? '1' : '0';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string)$value;
                    } else {
                        $quoted = $pdo->quote((string)$value);
                        if ($quoted === false) {
                            throw new RuntimeException('Unable to quote data for table: ' . $tableName);
                        }
                        $values[] = $quoted;
                    }
                }

                fwrite(
                    $handle,
                    'INSERT INTO ' . $escapedTable
                    . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {
        fclose($handle);
    }
}

function prune_database_backups(int $retentionCount): int
{
    if ($retentionCount < 1) {
        $retentionCount = 1;
    }

    $allBackups = list_database_backups();
    $autoBackups = array_values(
        array_filter(
            $allBackups,
            static fn(array $backup): bool => (($backup['mode'] ?? '') === 'auto')
        )
    );

    if (count($autoBackups) <= $retentionCount) {
        return 0;
    }

    $deletedCount = 0;
    for ($i = $retentionCount; $i < count($autoBackups); $i++) {
        $path = (string)($autoBackups[$i]['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            if (@unlink($path)) {
                $deletedCount++;
            }
        }
    }

    return $deletedCount;
}

function delete_database_backup(string $fileName, ?array $actor = null): bool
{
    $path = resolve_database_backup_path($fileName);
    if ($path === null || !is_file($path)) {
        return false;
    }

    $size = filesize($path);
    $deleted = @unlink($path);

    if ($deleted && function_exists('log_event_action') && is_array($actor) && (int)($actor['id'] ?? 0) > 0) {
        log_event_action($actor, 'delete', null, [
            'type' => 'database_backup',
            'file_name' => $fileName,
            'size' => $size === false ? null : (int)$size,
        ], null, 'database_backup');
    }

    return $deleted;
}

function sqlite_quote_identifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function sqlite_master_objects(PDO $pdo, string $schema, array $types): array
{
    $allowed = ['table', 'index', 'trigger', 'view'];
    $selected = [];
    foreach ($types as $type) {
        $normalized = strtolower(trim((string)$type));
        if (in_array($normalized, $allowed, true)) {
            $selected[$normalized] = true;
        }
    }

    if ($selected === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($selected), '?'));
    $schemaName = strtolower($schema) === 'main' ? 'main' : 'restore_source';
    $sql = 'SELECT type, name, sql
            FROM ' . $schemaName . ".sqlite_master
            WHERE type IN (" . $placeholders . ")
              AND name NOT LIKE 'sqlite_%'
            ORDER BY CASE type
                        WHEN 'table' THEN 1
                        WHEN 'view' THEN 2
                        WHEN 'index' THEN 3
                        WHEN 'trigger' THEN 4
                        ELSE 9
                     END, name";

    $stmt = $pdo->prepare($sql);
    $index = 1;
    foreach (array_keys($selected) as $type) {
        $stmt->bindValue($index, $type, PDO::PARAM_STR);
        $index++;
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function restore_database_from_backup(string $fileName, ?array $actor = null, bool $createSafetyBackup = true): array
{
    $backupPath = resolve_database_backup_path($fileName);
    if ($backupPath === null || !is_file($backupPath)) {
        throw new RuntimeException('فایل بکاپ برای بازگردانی پیدا نشد.');
    }

    $currentDriver = database_backup_driver();
    $backupDriver = database_backup_driver_from_filename($fileName);
    if ($backupDriver === null || $backupDriver !== $currentDriver) {
        throw new RuntimeException('فرمت بکاپ با نوع دیتابیس فعلی همخوانی ندارد.');
    }

    if ($currentDriver !== 'sqlite') {
        throw new RuntimeException('بازگردانی مستقیم فقط برای SQLite فعال است.');
    }

    $config = database_backup_config();
    $dbPath = (string)($config['db']['sqlite_path'] ?? '');
    if ($dbPath === '') {
        throw new RuntimeException('مسیر دیتابیس SQLite مشخص نیست.');
    }

    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir) && !mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
        throw new RuntimeException('پوشه دیتابیس در دسترس نیست.');
    }

    $safetyBackup = null;
    if ($createSafetyBackup && is_file($dbPath)) {
        $safetyBackup = create_sqlite_file_copy_backup($dbPath, 'manual', $actor);
    }

    $pdo = get_db();
    $escapedPath = str_replace("'", "''", $backupPath);
    $attached = false;

    try {
        $pdo->exec('PRAGMA busy_timeout = 15000');
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec("ATTACH DATABASE '" . $escapedPath . "' AS restore_source");
        $attached = true;

        $pdo->beginTransaction();

        $mainObjects = sqlite_master_objects($pdo, 'main', ['view', 'trigger', 'index', 'table']);
        foreach ($mainObjects as $object) {
            $type = strtolower((string)($object['type'] ?? ''));
            $name = (string)($object['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $sql = match ($type) {
                'view' => 'DROP VIEW IF EXISTS ' . sqlite_quote_identifier($name),
                'trigger' => 'DROP TRIGGER IF EXISTS ' . sqlite_quote_identifier($name),
                'index' => 'DROP INDEX IF EXISTS ' . sqlite_quote_identifier($name),
                'table' => 'DROP TABLE IF EXISTS ' . sqlite_quote_identifier($name),
                default => null,
            };

            if ($sql !== null) {
                $pdo->exec($sql);
            }
        }

        $sourceTables = sqlite_master_objects($pdo, 'restore_source', ['table']);
        foreach ($sourceTables as $table) {
            $createSql = trim((string)($table['sql'] ?? ''));
            $tableName = (string)($table['name'] ?? '');
            if ($tableName === '' || $createSql === '') {
                continue;
            }

            $pdo->exec($createSql);
            $pdo->exec(
                'INSERT INTO ' . sqlite_quote_identifier($tableName)
                . ' SELECT * FROM restore_source.' . sqlite_quote_identifier($tableName)
            );
        }

        $sourceObjects = sqlite_master_objects($pdo, 'restore_source', ['view', 'index', 'trigger']);
        foreach ($sourceObjects as $object) {
            $createSql = trim((string)($object['sql'] ?? ''));
            if ($createSql !== '') {
                $pdo->exec($createSql);
            }
        }

        $sequenceExists = $pdo->query(
            "SELECT 1 FROM restore_source.sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence' LIMIT 1"
        )->fetchColumn();
        if ($sequenceExists !== false) {
            $pdo->exec('DELETE FROM sqlite_sequence');
            $pdo->exec('INSERT INTO sqlite_sequence(name, seq) SELECT name, seq FROM restore_source.sqlite_sequence');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($attached) {
            try {
                $pdo->exec('DETACH DATABASE restore_source');
            } catch (Throwable) {
                // ignore
            }
        }

        try {
            $pdo->exec('PRAGMA foreign_keys = ON');
        } catch (Throwable) {
            // ignore
        }

        $message = trim($exception->getMessage());
        if ($message === '') {
            throw new RuntimeException('بازگردانی دیتابیس انجام نشد.');
        }

        if (str_contains(strtolower($message), 'locked') || str_contains($message, 'قفل')) {
            throw new RuntimeException('در حال حاضر دیتابیس مشغول است. چند ثانیه بعد دوباره تلاش کنید.');
        }

        throw new RuntimeException('بازگردانی دیتابیس انجام نشد: ' . $message);
    }

    if ($attached) {
        $pdo->exec('DETACH DATABASE restore_source');
    }

    $pdo->exec('PRAGMA foreign_keys = ON');

    if (function_exists('log_event_action') && is_array($actor) && (int)($actor['id'] ?? 0) > 0) {
        log_event_action(
            $actor,
            'update',
            null,
            [
                'type' => 'database_restore',
                'backup_file' => $fileName,
            ],
            [
                'type' => 'database_restore',
                'backup_file' => $fileName,
                'safety_backup' => is_array($safetyBackup) ? (string)($safetyBackup['file_name'] ?? '') : null,
            ],
            'database_backup'
        );
    }

    return [
        'restored_file' => $fileName,
        'restored_at' => date(DATE_ATOM),
        'safety_backup' => is_array($safetyBackup) ? (string)($safetyBackup['file_name'] ?? '') : null,
    ];
}
function run_database_health_check(): array
{
    $driver = database_backup_driver();

    if ($driver === 'sqlite') {
        $config = database_backup_config();
        $dbPath = (string)($config['db']['sqlite_path'] ?? '');

        $pdo = get_db();
        $integrity = '';
        try {
            $integrity = (string)$pdo->query('PRAGMA integrity_check')->fetchColumn();
        } catch (Throwable) {
            $integrity = '';
        }

        $tableCount = 0;
        try {
            $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
        } catch (Throwable) {
            $tableCount = 0;
        }

        $isOk = strtolower(trim($integrity)) === 'ok';
        $dbFileSize = is_file($dbPath) ? (int)(filesize($dbPath) ?: 0) : 0;

        return [
            'ok' => $isOk,
            'driver' => 'sqlite',
            'integrity' => $integrity !== '' ? $integrity : 'unknown',
            'table_count' => $tableCount,
            'db_file_size' => $dbFileSize,
        ];
    }

    $pdo = get_db();
    $pdo->query('SELECT 1');
    $tablesStmt = $pdo->query('SHOW TABLES');
    $rows = $tablesStmt->fetchAll(PDO::FETCH_NUM);

    return [
        'ok' => true,
        'driver' => 'mysql',
        'integrity' => 'connected',
        'table_count' => is_array($rows) ? count($rows) : 0,
        'db_file_size' => 0,
    ];
}

function database_backup_parse_datetime(?string $value): ?DateTimeImmutable
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable) {
        return null;
    }
}

function run_scheduled_database_backup(): ?array
{
    $settings = load_database_backup_settings();
    if (empty($settings['enabled'])) {
        return null;
    }

    $intervalHours = max(1, (int)($settings['interval_hours'] ?? 24));
    $now = new DateTimeImmutable('now');
    $lastSuccess = database_backup_parse_datetime((string)($settings['last_success_at'] ?? ''));
    if ($lastSuccess instanceof DateTimeImmutable) {
        $nextRun = $lastSuccess->modify('+' . $intervalHours . ' hours');
        if ($now < $nextRun) {
            return null;
        }
    }

    $lockPath = database_backup_lock_path();
    $lockDir = dirname($lockPath);
    if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
        return null;
    }

    $lockHandle = @fopen($lockPath, 'c+');
    if (!is_resource($lockHandle)) {
        return null;
    }

    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        return null;
    }

    try {
        $settings = load_database_backup_settings();
        if (empty($settings['enabled'])) {
            return null;
        }

        $intervalHours = max(1, (int)($settings['interval_hours'] ?? 24));
        $now = new DateTimeImmutable('now');
        $lastSuccess = database_backup_parse_datetime((string)($settings['last_success_at'] ?? ''));
        if ($lastSuccess instanceof DateTimeImmutable) {
            $nextRun = $lastSuccess->modify('+' . $intervalHours . ' hours');
            if ($now < $nextRun) {
                return null;
            }
        }

        $settings['last_attempt_at'] = $now->format(DATE_ATOM);
        save_database_backup_settings($settings);

        $result = create_database_backup('auto', null);
        prune_database_backups((int)($settings['retention_count'] ?? 14));

        $settings['last_attempt_at'] = date(DATE_ATOM);
        $settings['last_success_at'] = date(DATE_ATOM);
        $settings['last_error'] = null;
        save_database_backup_settings($settings);

        return $result;
    } catch (Throwable $exception) {
        try {
            $failedSettings = load_database_backup_settings();
            $failedSettings['last_attempt_at'] = date(DATE_ATOM);
            $failedSettings['last_error'] = substr(trim($exception->getMessage()), 0, 500);
            save_database_backup_settings($failedSettings);
        } catch (Throwable) {
            // Keep page rendering stable if backup settings update also fails.
        }

        return null;
    } finally {
        @flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

