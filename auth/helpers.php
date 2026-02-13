<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';


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
        'role' => $role,
    ];
}

function login_user(array $user): void
{
    start_secure_session();
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'national_code' => $user['national_code'],
        'role' => $user['role'],
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

function log_event_action(?array $actor, string $action, ?int $entityId, ?array $beforeData, ?array $afterData): void
{
    $actorId = (int)($actor['id'] ?? 0);
    if ($actorId <= 0) {
        return;
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
        $stmt->bindValue(':entity', 'calendar_event', PDO::PARAM_STR);

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

function list_users_for_log_filter(): array
{
    $pdo = get_db();
    $stmt = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE role = 'admin' ORDER BY last_name ASC, first_name ASC");
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function build_event_log_filter_sql(array $filters, array &$params): string
{
    $conditions = [];

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


function list_users_for_admin(?string $search = null): array
{
    $pdo = get_db();
    $search = trim((string)$search);

    if ($search === '') {
        $stmt = $pdo->query(
            'SELECT id, first_name, last_name, phone, national_code, role, created_at
             FROM users
             ORDER BY id DESC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    $term = '%' . mb_substr($search, 0, 80) . '%';
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, phone, national_code, role, created_at
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
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $targetUserId]);
    $target = $stmt->fetch();

    if (!$target) {
        throw new RuntimeException('کاربر موردنظر پیدا نشد.');
    }

    $role = (string)$target['role'];
    if ($role === 'admin' && count_users_by_role('admin') <= 1) {
        throw new RuntimeException('حداقل یک مدیر باید در سیستم باقی بماند.');
    }

    try {
        $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $targetUserId]);
    } catch (Throwable) {
        throw new RuntimeException('این کاربر به داده‌های دیگر متصل است و قابل حذف نیست.');
    }
}
