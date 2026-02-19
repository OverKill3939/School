<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';


function app_config(): array
{
    static $config = null;

    if (!is_array($config)) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function apply_app_timezone(): void
{
    static $applied = false;

    if ($applied) {
        return;
    }

    $config = app_config();
    $timezone = (string)($config['app']['timezone'] ?? 'Asia/Tehran');
    if ($timezone === '') {
        $timezone = 'Asia/Tehran';
    }

    date_default_timezone_set($timezone);
    $applied = true;
}

function request_expects_json(): bool
{
    $scriptName = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if ($scriptName !== '' && str_contains($scriptName, '/api/')) {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        return true;
    }

    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $requestedWith === 'xmlhttprequest';
}

function json_error_response(int $status, string $message): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function login_url(): string
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($dir === '' || $dir === '.') {
        return '/login.php';
    }

    if ($dir === 'api') {
        return '/login.php';
    }

    if (str_ends_with($dir, '/api')) {
        $dir = substr($dir, 0, -4);
        $dir = trim($dir, '/');
    }

    if ($dir === '') {
        return '/login.php';
    }

    return '/' . $dir . '/login.php';
}

function app_base_url(): string
{
    $config = app_config();
    $configured = trim((string)($config['app']['base_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';

    $rawHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^a-z0-9\.\-:\[\]]/i', '', $rawHost) ?? '';
    if ($host === '') {
        $host = 'localhost';
    }

    return $scheme . '://' . $host;
}

function default_profile_image_path(): string
{
    return 'img/waguri111.jpg';
}

function sanitize_profile_image_path(?string $path): ?string
{
    $value = trim((string)$path);
    if ($value === '') {
        return null;
    }

    if (!str_starts_with($value, '/uploads/')) {
        return null;
    }

    if (str_contains($value, '..') || str_contains($value, "\0")) {
        return null;
    }

    return $value;
}

function user_profile_image_url(?array $user): string
{
    $path = sanitize_profile_image_path((string)($user['profile_image_path'] ?? ''));
    return $path ?? default_profile_image_path();
}

function gregorian_to_jalali_year(int $gy, int $gm = 1, int $gd = 1): int
{
    $gDayMonths = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy)
        + intdiv($gy2 + 3, 4)
        - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400)
        - 80
        + $gd
        + $gDayMonths[$gm - 1];

    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;

    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
    }

    return $jy;
}

function persian_year_now(): int
{
    apply_app_timezone();
    $now = new DateTimeImmutable('now');
    return gregorian_to_jalali_year(
        (int)$now->format('Y'),
        (int)$now->format('n'),
        (int)$now->format('j')
    );
}

function display_persian_year(int $year): int
{
    if ($year >= 1700) {
        return gregorian_to_jalali_year($year, 1, 1);
    }

    return $year;
}

function grade_label(int $grade): string
{
    return match ($grade) {
        10 => 'دهم',
        11 => 'یازدهم',
        12 => 'دوازدهم',
        default => (string)$grade,
    };
}

function start_secure_session(): void
{
    apply_app_timezone();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = app_config();

    session_name((string)$config['app']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function mark_notification_permission_prompt(): void
{
    start_secure_session();
    $_SESSION['notification_permission_prompt'] = 1;
}

function consume_notification_permission_prompt(): bool
{
    start_secure_session();
    $pending = !empty($_SESSION['notification_permission_prompt']);
    unset($_SESSION['notification_permission_prompt']);
    return $pending;
}

function is_logged_in(): bool
{
    start_secure_session();
    return !empty($_SESSION['user']);
}

function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    if (request_expects_json()) {
        json_error_response(401, 'Authentication required. Please login first.');
    }

    header('Location: ' . login_url());
    exit;
}

function require_admin(): void
{
    require_login();

    $user = current_user();
    if (($user['role'] ?? '') === 'admin') {
        return;
    }

    if (request_expects_json()) {
        json_error_response(403, 'Admin access is required.');
    }

    http_response_code(403);
    exit('Access denied.');
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null) {
        return null;
    }

    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }

    if (preg_match('/^989\d{9}$/', $digits)) {
        return '0' . substr($digits, 2);
    }

    return null;
}

function valid_national_code(string $value): bool
{
    $code = preg_replace('/\D+/', '', $value);
    if ($code === null || !preg_match('/^\d{10}$/', $code)) {
        return false;
    }

    if (preg_match('/^(\d)\1{9}$/', $code)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((int)$code[$i]) * (10 - $i);
    }

    $remainder = $sum % 11;
    $check = (int)$code[9];

    if ($remainder < 2) {
        return $check === $remainder;
    }

    return $check === 11 - $remainder;
}

function valid_password(string $password): bool
{
    if (strlen($password) < 6) {
        return false;
    }

    return (bool)preg_match('/[A-Z]/', $password)
        && (bool)preg_match('/[a-z]/', $password)
        && (bool)preg_match('/\d/', $password);
}

function find_user_by_national_code(string $nationalCode): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE national_code = :national_code LIMIT 1');
    $stmt->execute(['national_code' => $nationalCode]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function find_user_by_id(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function create_user(array $data): array
{
    $pdo = get_db();

    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $role = $adminCount === 0 ? 'admin' : 'user';

    $stmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, phone, national_code, password_hash, role)
         VALUES (:first_name, :last_name, :phone, :national_code, :password_hash, :role)'
    );

    $stmt->execute([
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'phone' => $data['phone'],
        'national_code' => $data['national_code'],
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'phone' => $data['phone'],
        'national_code' => $data['national_code'],
        'profile_image_path' => null,
        'role' => $role,
    ];
}

function create_user_by_admin(array $data): array
{
    $role = (string)($data['role'] ?? 'user');
    if (!in_array($role, ['admin', 'user'], true)) {
        throw new RuntimeException('نقش انتخاب شده معتبر نیست.');
    }

    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, phone, national_code, password_hash, role)
         VALUES (:first_name, :last_name, :phone, :national_code, :password_hash, :role)'
    );

    $stmt->execute([
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'phone' => $data['phone'],
        'national_code' => $data['national_code'],
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'phone' => $data['phone'],
        'national_code' => $data['national_code'],
        'profile_image_path' => null,
        'role' => $role,
    ];
}

function set_authenticated_user_session(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'national_code' => $user['national_code'],
        'role' => $user['role'],
        'profile_image_path' => sanitize_profile_image_path((string)($user['profile_image_path'] ?? '')),
    ];
}

function login_user(array $user): void
{
    start_secure_session();
    session_regenerate_id(true);
    set_authenticated_user_session($user);
}

function refresh_current_user_session(): void
{
    start_secure_session();
    $sessionUser = $_SESSION['user'] ?? null;
    $userId = (int)($sessionUser['id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $freshUser = find_user_by_id($userId);
    if ($freshUser === null) {
        return;
    }

    set_authenticated_user_session($freshUser);
}

function delete_profile_image_file(string $path): void
{
    $safePath = sanitize_profile_image_path($path);
    if ($safePath === null) {
        return;
    }

    $absolutePath = __DIR__ . '/..' . $safePath;
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function save_user_profile_image(int $userId, array $file): array
{
    if ($userId <= 0) {
        return [
            'ok' => false,
            'message' => 'کاربر نامعتبر است.',
        ];
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'ok' => false,
            'message' => 'لطفا یک تصویر انتخاب کنید.',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'message' => 'خطا در آپلود فایل. دوباره تلاش کنید.',
        ];
    }

    $newPath = upload_media(
        $file,
        ['jpg', 'jpeg', 'png', 'webp'],
        ['image/jpeg', 'image/png', 'image/webp'],
        'profiles',
        3,
        'profile'
    );

    if ($newPath === null) {
        return [
            'ok' => false,
            'message' => 'فرمت تصویر نامعتبر است یا حجم فایل بیشتر از 3 مگابایت است.',
        ];
    }

    $currentUser = find_user_by_id($userId);
    if ($currentUser === null) {
        delete_profile_image_file($newPath);
        return [
            'ok' => false,
            'message' => 'کاربر پیدا نشد.',
        ];
    }

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE users SET profile_image_path = :profile_image_path WHERE id = :id');
        $stmt->execute([
            'profile_image_path' => $newPath,
            'id' => $userId,
        ]);
    } catch (Throwable) {
        delete_profile_image_file($newPath);
        return [
            'ok' => false,
            'message' => 'ذخیره تصویر در دیتابیس انجام نشد.',
        ];
    }

    $oldPath = sanitize_profile_image_path((string)($currentUser['profile_image_path'] ?? ''));
    if ($oldPath !== null && $oldPath !== $newPath) {
        delete_profile_image_file($oldPath);
    }

    refresh_current_user_session();

    return [
        'ok' => true,
        'path' => $newPath,
    ];
}

function user_can_manage_events(?array $user): bool
{
    return (($user['role'] ?? '') === 'admin');
}

function list_calendar_events(int $year, int $month): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, year, month, day, title, type, notes, created_by, created_at
         FROM calendar_events
         WHERE year = :year AND month = :month
         ORDER BY day ASC, id ASC'
    );
    $stmt->execute([
        'year' => $year,
        'month' => $month,
    ]);

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function get_calendar_event_by_id(int $eventId): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, year, month, day, title, type, notes, created_by, created_at
         FROM calendar_events
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $eventId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function create_calendar_event(array $data, int $createdBy): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO calendar_events (year, month, day, title, type, notes, created_by)
         VALUES (:year, :month, :day, :title, :type, :notes, :created_by)'
    );

    $stmt->execute([
        'year' => (int)$data['year'],
        'month' => (int)$data['month'],
        'day' => (int)$data['day'],
        'title' => (string)$data['title'],
        'type' => (string)$data['type'],
        'notes' => (string)($data['notes'] ?? ''),
        'created_by' => $createdBy,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'year' => (int)$data['year'],
        'month' => (int)$data['month'],
        'day' => (int)$data['day'],
        'title' => (string)$data['title'],
        'type' => (string)$data['type'],
        'notes' => (string)($data['notes'] ?? ''),
        'created_by' => $createdBy,
    ];
}

function update_calendar_event(int $eventId, array $data): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'UPDATE calendar_events
         SET year = :year,
             month = :month,
             day = :day,
             title = :title,
             type = :type,
             notes = :notes
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $eventId,
        'year' => (int)$data['year'],
        'month' => (int)$data['month'],
        'day' => (int)$data['day'],
        'title' => (string)$data['title'],
        'type' => (string)$data['type'],
        'notes' => (string)($data['notes'] ?? ''),
    ]);

    return get_calendar_event_by_id($eventId);
}

function delete_calendar_event(int $eventId): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE id = :id');
    $stmt->execute(['id' => $eventId]);
    return $stmt->rowCount() > 0;
}

function log_event_action(?array $actor, string $action, ?int $entityId, ?array $beforeData, ?array $afterData, string $entity = 'calendar_event'): void
{
    $actorId = (int)($actor['id'] ?? 0);
    if ($actorId <= 0) {
        return;
    }

    $entity = trim($entity);
    if ($entity === '' || preg_match('/^[a-z0-9_]{1,40}$/', $entity) !== 1) {
        $entity = 'calendar_event';
    }

    $beforeJson = $beforeData !== null
        ? json_encode($beforeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;
    $afterJson = $afterData !== null
        ? json_encode($afterData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare(
            'INSERT INTO event_logs (actor_user_id, action, entity, entity_id, before_data, after_data, ip_address, user_agent)
             VALUES (:actor_user_id, :action, :entity, :entity_id, :before_data, :after_data, :ip_address, :user_agent)'
        );

        $stmt->bindValue(':actor_user_id', $actorId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':entity', $entity, PDO::PARAM_STR);

        if ($entityId === null) {
            $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        }

        if ($beforeJson === null) {
            $stmt->bindValue(':before_data', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':before_data', $beforeJson, PDO::PARAM_STR);
        }

        if ($afterJson === null) {
            $stmt->bindValue(':after_data', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':after_data', $afterJson, PDO::PARAM_STR);
        }

        $stmt->bindValue(':ip_address', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable) {
        // Keep app flow stable even if logging fails.
    }
}

function log_auth_event(string $event, bool $success, ?int $userId, string $nationalCode): void
{
    if (!in_array($event, ['login', 'register'], true)) {
        return;
    }

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $nationalCode = substr(preg_replace('/\D+/', '', $nationalCode) ?? '', 0, 10);

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare(
            'INSERT INTO auth_logs (event, success, user_id, national_code, ip_address, user_agent)
             VALUES (:event, :success, :user_id, :national_code, :ip_address, :user_agent)'
        );

        $stmt->bindValue(':event', $event, PDO::PARAM_STR);
        $stmt->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);

        if ($userId === null) {
            $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':national_code', $nationalCode, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable) {
        // Do not break auth flow if logging fails.
    }
}

function log_user_management_action(?array $actor, string $action, array $target): void
{
    if (!in_array($action, ['create', 'delete'], true)) {
        return;
    }

    $actorId = (int)($actor['id'] ?? 0);
    if ($actorId <= 0) {
        return;
    }

    $targetUserId = (int)($target['id'] ?? 0);
    if ($targetUserId <= 0) {
        $targetUserId = null;
    }
    if ($action === 'delete') {
        $targetUserId = null;
    }

    $targetRole = (string)($target['role'] ?? 'user');
    if (!in_array($targetRole, ['admin', 'user'], true)) {
        $targetRole = 'user';
    }

    $targetFirstName = mb_substr(trim((string)($target['first_name'] ?? '')), 0, 100);
    $targetLastName = mb_substr(trim((string)($target['last_name'] ?? '')), 0, 100);
    $targetPhone = mb_substr(trim((string)($target['phone'] ?? '')), 0, 20);
    $targetNationalCode = substr(preg_replace('/\D+/', '', (string)($target['national_code'] ?? '')) ?? '', 0, 10);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare(
            'INSERT INTO user_management_logs (
                actor_user_id,
                action,
                target_user_id,
                target_first_name,
                target_last_name,
                target_phone,
                target_national_code,
                target_role,
                ip_address,
                user_agent
             ) VALUES (
                :actor_user_id,
                :action,
                :target_user_id,
                :target_first_name,
                :target_last_name,
                :target_phone,
                :target_national_code,
                :target_role,
                :ip_address,
                :user_agent
             )'
        );

        $stmt->bindValue(':actor_user_id', $actorId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        if ($targetUserId === null) {
            $stmt->bindValue(':target_user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':target_user_id', $targetUserId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':target_first_name', $targetFirstName, PDO::PARAM_STR);
        $stmt->bindValue(':target_last_name', $targetLastName, PDO::PARAM_STR);
        $stmt->bindValue(':target_phone', $targetPhone, PDO::PARAM_STR);
        $stmt->bindValue(':target_national_code', $targetNationalCode, PDO::PARAM_STR);
        $stmt->bindValue(':target_role', $targetRole, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable) {
        // Do not break user management flow if logging fails.
    }
}

function list_users_for_log_filter(): array
{
    $pdo = get_db();
    $stmt = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE role = 'admin' ORDER BY last_name ASC, first_name ASC");
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function build_event_log_filter_sql(array $filters, array &$params): string
{
    $conditions = ['el.entity = :entity'];
    $params[':entity'] = 'calendar_event';

    if (!empty($filters['action'])) {
        $conditions[] = 'el.action = :action';
        $params[':action'] = (string)$filters['action'];
    }

    if (!empty($filters['actor_id'])) {
        $conditions[] = 'el.actor_user_id = :actor_id';
        $params[':actor_id'] = (int)$filters['actor_id'];
    }

    if (!empty($filters['from_date'])) {
        $conditions[] = 'el.created_at >= :from_date';
        $params[':from_date'] = (string)$filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $conditions[] = 'el.created_at < :to_date';
        $params[':to_date'] = (string)$filters['to_date'];
    }

    if ($conditions === []) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $conditions);
}

function list_event_logs(array $filters, int $limit = 20, int $offset = 0): array
{
    $pdo = get_db();

    $params = [];
    $where = build_event_log_filter_sql($filters, $params);

    $sql =
        'SELECT el.id, el.actor_user_id, el.action, el.entity, el.entity_id, el.before_data, el.after_data, el.ip_address, el.user_agent, el.created_at,
                u.first_name, u.last_name, u.role
         FROM event_logs el
         INNER JOIN users u ON u.id = el.actor_user_id' .
        $where .
        ' ORDER BY el.id DESC
          LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function count_event_logs(array $filters): int
{
    $pdo = get_db();

    $params = [];
    $where = build_event_log_filter_sql($filters, $params);

    $sql = 'SELECT COUNT(*) FROM event_logs el' . $where;
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function build_auth_log_filter_sql(array $filters, array &$params): string
{
    $conditions = [];

    if (!empty($filters['event'])) {
        $conditions[] = 'al.event = :event';
        $params[':event'] = (string)$filters['event'];
    }

    if ($filters['success'] !== null) {
        $conditions[] = 'al.success = :success';
        $params[':success'] = $filters['success'] ? 1 : 0;
    }

    if (!empty($filters['user_id'])) {
        $conditions[] = 'al.user_id = :user_id';
        $params[':user_id'] = (int)$filters['user_id'];
    }

    if (!empty($filters['from_date'])) {
        $conditions[] = 'al.created_at >= :from_date';
        $params[':from_date'] = (string)$filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $conditions[] = 'al.created_at < :to_date';
        $params[':to_date'] = (string)$filters['to_date'];
    }

    if ($conditions === []) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $conditions);
}

function list_auth_logs(array $filters, int $limit = 20, int $offset = 0): array
{
    $pdo = get_db();

    $params = [];
    $where = build_auth_log_filter_sql($filters, $params);

    $sql =
        'SELECT al.id, al.event, al.success, al.user_id, al.national_code, al.ip_address, al.user_agent, al.created_at,
                u.first_name, u.last_name, u.role
         FROM auth_logs al
         LEFT JOIN users u ON u.id = al.user_id' .
        $where .
        ' ORDER BY al.id DESC
          LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function count_auth_logs(array $filters): int
{
    $pdo = get_db();

    $params = [];
    $where = build_auth_log_filter_sql($filters, $params);

    $sql = 'SELECT COUNT(*) FROM auth_logs al' . $where;
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function build_user_management_log_filter_sql(array $filters, array &$params): string
{
    $conditions = [];

    if (!empty($filters['action'])) {
        $conditions[] = 'uml.action = :action';
        $params[':action'] = (string)$filters['action'];
    }

    if (!empty($filters['actor_id'])) {
        $conditions[] = 'uml.actor_user_id = :actor_id';
        $params[':actor_id'] = (int)$filters['actor_id'];
    }

    if (!empty($filters['from_date'])) {
        $conditions[] = 'uml.created_at >= :from_date';
        $params[':from_date'] = (string)$filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $conditions[] = 'uml.created_at < :to_date';
        $params[':to_date'] = (string)$filters['to_date'];
    }

    if ($conditions === []) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $conditions);
}

function list_user_management_logs(array $filters, int $limit = 20, int $offset = 0): array
{
    $pdo = get_db();

    $params = [];
    $where = build_user_management_log_filter_sql($filters, $params);

    $sql =
        'SELECT uml.id, uml.actor_user_id, uml.action, uml.target_user_id, uml.target_first_name, uml.target_last_name,
                uml.target_phone, uml.target_national_code, uml.target_role, uml.ip_address, uml.user_agent, uml.created_at,
                u.first_name AS actor_first_name, u.last_name AS actor_last_name, u.role AS actor_role
         FROM user_management_logs uml
         INNER JOIN users u ON u.id = uml.actor_user_id' .
        $where .
        ' ORDER BY uml.id DESC
          LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function count_user_management_logs(array $filters): int
{
    $pdo = get_db();

    $params = [];
    $where = build_user_management_log_filter_sql($filters, $params);

    $sql = 'SELECT COUNT(*) FROM user_management_logs uml' . $where;
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int)$stmt->fetchColumn();
}


function list_users_for_admin(?string $search = null): array
{
    $pdo = get_db();
    $search = trim((string)$search);

    if ($search === '') {
        $stmt = $pdo->query(
            'SELECT id, first_name, last_name, phone, national_code, profile_image_path, role, created_at
             FROM users
             ORDER BY id DESC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    $term = '%' . mb_substr($search, 0, 80) . '%';
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, phone, national_code, profile_image_path, role, created_at
         FROM users
         WHERE first_name LIKE :term
            OR last_name LIKE :term
            OR national_code LIKE :term
            OR phone LIKE :term
         ORDER BY id DESC'
    );
    $stmt->execute(['term' => $term]);
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

function count_users_by_role(string $role): int
{
    if (!in_array($role, ['admin', 'user'], true)) {
        return 0;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = :role');
    $stmt->execute(['role' => $role]);
    return (int)$stmt->fetchColumn();
}

function update_user_role_by_admin(int $targetUserId, string $newRole, int $actorUserId): void
{
    if (!in_array($newRole, ['admin', 'user'], true)) {
        throw new RuntimeException('نقش انتخاب شده معتبر نیست.');
    }

    if ($targetUserId === $actorUserId) {
        throw new RuntimeException('تغییر نقش حساب فعلی مجاز نیست.');
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $targetUserId]);
    $target = $stmt->fetch();

    if (!$target) {
        throw new RuntimeException('کاربر موردنظر پیدا نشد.');
    }

    $currentRole = (string)$target['role'];
    if ($currentRole === $newRole) {
        return;
    }

    if ($currentRole === 'admin' && $newRole === 'user' && count_users_by_role('admin') <= 1) {
        throw new RuntimeException('حداقل یک مدیر باید در سیستم باقی بماند.');
    }

    $update = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
    $update->execute([
        'role' => $newRole,
        'id' => $targetUserId,
    ]);
}

function delete_user_by_admin(int $targetUserId, int $actorUserId): void
{
    if ($targetUserId === $actorUserId) {
        throw new RuntimeException('حذف حساب فعلی مجاز نیست.');
    }

    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, phone, national_code, role, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $targetUserId]);
    $target = $stmt->fetch();

    if (!$target) {
        throw new RuntimeException('کاربر موردنظر پیدا نشد.');
    }

    $role = (string)$target['role'];
    if ($role === 'admin') {
        throw new RuntimeException('حذف مدیر توسط مدیر دیگر مجاز نیست.');
    }

    try {
        $pdo->beginTransaction();

        $dependenciesToReassign = [
            'UPDATE calendar_events SET created_by = :actor_id WHERE created_by = :target_id',
            'UPDATE event_logs SET actor_user_id = :actor_id WHERE actor_user_id = :target_id',
            'UPDATE news SET author_id = :actor_id WHERE author_id = :target_id',
            'UPDATE schedule_history SET admin_id = :actor_id WHERE admin_id = :target_id',
        ];

        foreach ($dependenciesToReassign as $sql) {
            $reassign = $pdo->prepare($sql);
            $reassign->execute([
                'actor_id' => $actorUserId,
                'target_id' => $targetUserId,
            ]);
        }

        $clearAuthLogs = $pdo->prepare('UPDATE auth_logs SET user_id = NULL WHERE user_id = :target_id');
        $clearAuthLogs->execute(['target_id' => $targetUserId]);

        $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $targetUserId]);
        if ($delete->rowCount() < 1) {
            throw new RuntimeException('کاربر موردنظر پیدا نشد.');
        }

        log_user_management_action(
            ['id' => $actorUserId],
            'delete',
            [
                'id' => (int)$target['id'],
                'first_name' => (string)$target['first_name'],
                'last_name' => (string)$target['last_name'],
                'phone' => (string)$target['phone'],
                'national_code' => (string)$target['national_code'],
                'role' => (string)$target['role'],
            ]
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof RuntimeException) {
            throw $exception;
        }

        throw new RuntimeException('این کاربر به داده‌های دیگر متصل است و قابل حذف نیست.');
    }
}
// helpers.php
function format_number(mixed $num, int $decimals = 0): string
{
    return number_format((float)$num, $decimals, '.', ',');
}
