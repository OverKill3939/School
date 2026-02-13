<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function parse_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int)$value;
    }
    return null;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $year = parse_int($_GET['year'] ?? null);
    $month = parse_int($_GET['month'] ?? null);

    if ($year === null || $month === null || $month < 1 || $month > 12) {
        json_response(['ok' => false, 'message' => 'Invalid parameters.'], 422);
    }

    $events = list_calendar_events($year, $month);
    json_response(['ok' => true, 'events' => $events]);
}

if ($method === 'POST') {
    $user = current_user();
    if (!user_can_manage_events($user)) {
        json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
    }

    $data = read_json_body();

    $year = parse_int($data['year'] ?? null);
    $month = parse_int($data['month'] ?? null);
    $day = parse_int($data['day'] ?? null);
    $title = trim((string)($data['title'] ?? ''));
    $type = (string)($data['type'] ?? '');
    $notes = trim((string)($data['notes'] ?? ''));

    if ($year === null || $year < 1300 || $year > 1600) {
        json_response(['ok' => false, 'message' => 'Invalid year.'], 422);
    }
    if ($month === null || $month < 1 || $month > 12) {
        json_response(['ok' => false, 'message' => 'Invalid month.'], 422);
    }
    if ($day === null || $day < 1 || $day > 31) {
        json_response(['ok' => false, 'message' => 'Invalid day.'], 422);
    }
    if ($title === '') {
        json_response(['ok' => false, 'message' => 'Title is required.'], 422);
    }

    $allowedTypes = ['exam', 'event', 'extra-holiday'];
    if (!in_array($type, $allowedTypes, true)) {
        json_response(['ok' => false, 'message' => 'Invalid event type.'], 422);
    }

    $created = create_calendar_event([
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'title' => $title,
        'type' => $type,
        'notes' => $notes,
    ], (int)($user['id'] ?? 0));

    log_event_action($user, 'create', (int)$created['id'], null, $created);

    json_response(['ok' => true, 'event' => $created], 201);
}

if ($method === 'PUT') {
    $user = current_user();
    if (!user_can_manage_events($user)) {
        json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
    }

    $data = read_json_body();

    $id = parse_int($data['id'] ?? null);
    $year = parse_int($data['year'] ?? null);
    $month = parse_int($data['month'] ?? null);
    $day = parse_int($data['day'] ?? null);
    $title = trim((string)($data['title'] ?? ''));
    $type = (string)($data['type'] ?? '');
    $notes = trim((string)($data['notes'] ?? ''));

    if ($id === null || $id < 1) {
        json_response(['ok' => false, 'message' => 'Invalid event id.'], 422);
    }

    $existing = get_calendar_event_by_id($id);
    if ($existing === null) {
        json_response(['ok' => false, 'message' => 'Event not found.'], 404);
    }

    if ($year === null || $year < 1300 || $year > 1600) {
        json_response(['ok' => false, 'message' => 'Invalid year.'], 422);
    }
    if ($month === null || $month < 1 || $month > 12) {
        json_response(['ok' => false, 'message' => 'Invalid month.'], 422);
    }
    if ($day === null || $day < 1 || $day > 31) {
        json_response(['ok' => false, 'message' => 'Invalid day.'], 422);
    }
    if ($title === '') {
        json_response(['ok' => false, 'message' => 'Title is required.'], 422);
    }

    $allowedTypes = ['exam', 'event', 'extra-holiday'];
    if (!in_array($type, $allowedTypes, true)) {
        json_response(['ok' => false, 'message' => 'Invalid event type.'], 422);
    }

    $updated = update_calendar_event($id, [
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'title' => $title,
        'type' => $type,
        'notes' => $notes,
    ]);

    if ($updated === null) {
        json_response(['ok' => false, 'message' => 'Update failed.'], 409);
    }

    log_event_action($user, 'update', $id, $existing, $updated);

    json_response(['ok' => true, 'event' => $updated]);
}

if ($method === 'DELETE') {
    $user = current_user();
    if (!user_can_manage_events($user)) {
        json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
    }

    $id = parse_int($_GET['id'] ?? null);
    if ($id === null) {
        $body = read_json_body();
        $id = parse_int($body['id'] ?? null);
    }

    if ($id === null || $id < 1) {
        json_response(['ok' => false, 'message' => 'Invalid event id.'], 422);
    }

    $existing = get_calendar_event_by_id($id);
    if ($existing === null) {
        json_response(['ok' => false, 'message' => 'Event not found.'], 404);
    }

    $deleted = delete_calendar_event($id);
    if (!$deleted) {
        json_response(['ok' => false, 'message' => 'Delete failed.'], 409);
    }

    log_event_action($user, 'delete', $id, $existing, null);

    json_response(['ok' => true]);
}

json_response(['ok' => false, 'message' => 'Method Not Allowed'], 405);
