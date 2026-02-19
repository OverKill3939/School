<?php
// attendance_db.php
declare(strict_types=1);

function attendance_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $configPath = __DIR__ . '/auth/config.php';
    if (!is_file($configPath)) {
        $config = [];
        return $config;
    }

    $loaded = require $configPath;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function attendance_db_path(): string
{
    $envPath = trim((string)getenv('ATTENDANCE_DB_SQLITE_PATH'));
    if ($envPath !== '') {
        return $envPath;
    }

    $config = attendance_config();
    $fromConfig = trim((string)($config['attendance_db']['sqlite_path'] ?? ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    return __DIR__ . '/data/attendance.sqlite';
}

function attendance_allowed_grades(): array
{
    return [
        10 => 'دهم',
        11 => 'یازدهم',
        12 => 'دوازدهم',
    ];
}

function attendance_allowed_fields(): array
{
    return [
        'شبکه و نرم افزار',
        'الکترونیک',
        'برق',
    ];
}

function normalize_attendance_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date instanceof DateTimeImmutable) {
        return null;
    }

    return $date->format('Y-m-d') === $value ? $value : null;
}

function normalize_attendance_grade(mixed $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (!preg_match('/^\d+$/', $raw)) {
        return null;
    }

    $grade = (int)$raw;
    $legacyMap = [
        1 => 10,
        2 => 11,
        3 => 12,
    ];

    if (isset($legacyMap[$grade])) {
        $grade = $legacyMap[$grade];
    }

    return array_key_exists($grade, attendance_allowed_grades()) ? $grade : null;
}

function normalize_attendance_field(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $aliases = [
        'کامپیوتر' => 'شبکه و نرم افزار',
        'شبکه و نرم افزار' => 'شبکه و نرم افزار',
        'شبکه' => 'شبکه و نرم افزار',
        'الکترونیک' => 'الکترونیک',
        'برق' => 'برق',
    ];

    if (isset($aliases[$value])) {
        return $aliases[$value];
    }

    return in_array($value, attendance_allowed_fields(), true) ? $value : null;
}

function normalize_attendance_student_name(?string $value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 120, 'UTF-8');
    }

    return substr($text, 0, 120);
}

function normalize_attendance_notes(?string $value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 500, 'UTF-8');
    }

    return substr($text, 0, 500);
}

function sanitize_attendance_hours(array $hours): array
{
    $normalized = [];

    foreach ($hours as $hour) {
        $value = (int)trim((string)$hour);
        if ($value < 1 || $value > 4) {
            continue;
        }

        $normalized[$value] = $value;
    }

    $result = array_values($normalized);
    sort($result);

    return $result;
}

function attendance_redirect_url(string $date, int $grade, string $field): string
{
    $query = http_build_query([
        'date' => $date,
        'grade' => $grade,
        'field' => $field,
    ]);

    return 'attendance.php?' . $query;
}

function attendance_set_flash(string $type, string $message): void
{
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['attendance_flash'] = [
        'type' => $type === 'success' ? 'success' : 'error',
        'text' => $message,
    ];
}

function attendance_take_flash(): ?array
{
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $flash = $_SESSION['attendance_flash'] ?? null;
    unset($_SESSION['attendance_flash']);

    if (!is_array($flash)) {
        return null;
    }

    $text = trim((string)($flash['text'] ?? ''));
    if ($text === '') {
        return null;
    }

    return [
        'type' => ($flash['type'] ?? '') === 'success' ? 'success' : 'error',
        'text' => $text,
    ];
}

function attendance_request_expects_json(): bool
{
    if (function_exists('request_expects_json') && request_expects_json()) {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        return true;
    }

    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $requestedWith === 'xmlhttprequest';
}

function attendance_json_response(int $status, array $payload): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function attendance_handle_error(string $message, string $redirect, int $status = 422): never
{
    if (attendance_request_expects_json()) {
        attendance_json_response($status, [
            'success' => false,
            'error' => $message,
            'redirect' => $redirect,
        ]);
    }

    attendance_set_flash('error', $message);
    header('Location: ' . $redirect);
    exit;
}

function attendance_handle_success(string $message, string $redirect, array $extraPayload = []): never
{
    if (attendance_request_expects_json()) {
        attendance_json_response(200, array_merge([
            'success' => true,
            'message' => $message,
            'redirect' => $redirect,
        ], $extraPayload));
    }

    attendance_set_flash('success', $message);
    header('Location: ' . $redirect);
    exit;
}

function attendance_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            date            TEXT NOT NULL,
            grade           INTEGER NOT NULL CHECK(grade IN (10,11,12)),
            field           TEXT NOT NULL,
            student_name    TEXT NOT NULL COLLATE NOCASE,
            absent_hours    TEXT NOT NULL,
            notes           TEXT DEFAULT '',
            recorded_by     INTEGER NOT NULL,
            created_at      TEXT DEFAULT (datetime('now','localtime')),
            UNIQUE (date, grade, field, student_name COLLATE NOCASE)
        )"
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_lookup ON attendance (date, grade, field, student_name)');
}

function attendance_needs_schema_migration(PDO $pdo): bool
{
    $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'attendance'")->fetchColumn();
    if ($sql === '') {
        return false;
    }

    $normalized = strtolower(preg_replace('/\s+/', '', $sql) ?? '');
    return str_contains($normalized, 'check(gradein(1,2,3))');
}

function attendance_migrate_legacy_schema(PDO $pdo): void
{
    if (!attendance_needs_schema_migration($pdo)) {
        return;
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec('DROP TABLE IF EXISTS attendance_legacy_backup');
        $pdo->exec('ALTER TABLE attendance RENAME TO attendance_legacy_backup');

        attendance_ensure_schema($pdo);

        $pdo->exec(
            "INSERT INTO attendance (date, grade, field, student_name, absent_hours, notes, recorded_by, created_at)
             SELECT
               date,
               CASE grade
                 WHEN 1 THEN 10
                 WHEN 2 THEN 11
                 WHEN 3 THEN 12
                 ELSE grade
               END AS grade,
               CASE TRIM(field)
                 WHEN 'کامپیوتر' THEN 'شبکه و نرم افزار'
                 WHEN 'شبکه' THEN 'شبکه و نرم افزار'
                 ELSE TRIM(field)
               END AS field,
               TRIM(student_name) AS student_name,
               absent_hours,
               COALESCE(notes, ''),
               recorded_by,
               COALESCE(created_at, datetime('now', 'localtime'))
             FROM attendance_legacy_backup
             WHERE TRIM(COALESCE(student_name, '')) <> ''
             ON CONFLICT(date, grade, field, student_name)
             DO UPDATE SET
               absent_hours = excluded.absent_hours,
               notes = excluded.notes,
               recorded_by = excluded.recorded_by,
               created_at = excluded.created_at"
        );

        $pdo->exec('DROP TABLE attendance_legacy_backup');
        attendance_ensure_schema($pdo);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function get_attendance_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = attendance_db_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create attendance data directory.');
    }

    $legacyPath = __DIR__ . '/../data/attendance.sqlite';
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedLegacy = str_replace('\\', '/', $legacyPath);

    if (!is_file($path) && $normalizedLegacy !== $normalizedPath && is_file($legacyPath)) {
        @copy($legacyPath, $path);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    attendance_ensure_schema($pdo);
    attendance_migrate_legacy_schema($pdo);

    return $pdo;
}
