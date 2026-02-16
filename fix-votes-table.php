<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';

if (PHP_SAPI !== 'cli') {
    require_admin();
}

$pdo = get_db();
$driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

$messages = [];

try {
    if ($driver === 'sqlite') {
        $columns = [];
        $stmt = $pdo->query('PRAGMA table_info(votes)');
        foreach ($stmt->fetchAll() as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        if (!isset($columns['grade'])) {
            $pdo->exec('ALTER TABLE votes ADD COLUMN grade INTEGER NOT NULL DEFAULT 0');
            $messages[] = 'ستون grade اضافه شد.';
        }

        if (!isset($columns['field'])) {
            $pdo->exec("ALTER TABLE votes ADD COLUMN field TEXT NOT NULL DEFAULT ''");
            $messages[] = 'ستون field اضافه شد.';
        }

        if (!isset($columns['voter_key'])) {
            $pdo->exec("ALTER TABLE votes ADD COLUMN voter_key TEXT NOT NULL DEFAULT ''");
            $messages[] = 'ستون voter_key اضافه شد.';
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_votes_voter ON votes(election_id, grade, field, voter_key)');
        $messages[] = 'ایندکس idx_votes_voter بررسی/ایجاد شد.';
    } else {
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM votes');
        foreach ($stmt->fetchAll() as $row) {
            $name = (string)($row['Field'] ?? '');
            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        if (!isset($columns['grade'])) {
            $pdo->exec('ALTER TABLE votes ADD COLUMN grade TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER candidate_id');
            $messages[] = 'ستون grade اضافه شد.';
        }

        if (!isset($columns['field'])) {
            $pdo->exec("ALTER TABLE votes ADD COLUMN field VARCHAR(80) NOT NULL DEFAULT '' AFTER grade");
            $messages[] = 'ستون field اضافه شد.';
        }

        if (!isset($columns['voter_key'])) {
            $pdo->exec("ALTER TABLE votes ADD COLUMN voter_key VARCHAR(64) NOT NULL DEFAULT '' AFTER field");
            $messages[] = 'ستون voter_key اضافه شد.';
        }

        $indexStmt = $pdo->prepare("SHOW INDEX FROM votes WHERE Key_name = :name");
        $indexStmt->execute([':name' => 'idx_votes_voter']);
        if (!$indexStmt->fetch()) {
            $pdo->exec('CREATE INDEX idx_votes_voter ON votes(election_id, grade, field, voter_key)');
            $messages[] = 'ایندکس idx_votes_voter ایجاد شد.';
        } else {
            $messages[] = 'ایندکس idx_votes_voter از قبل موجود بود.';
        }
    }

    if ($messages === []) {
        $messages[] = 'هیچ تغییری لازم نبود.';
    }

    echo '<pre dir="rtl">' . htmlspecialchars(implode(PHP_EOL, $messages), ENT_QUOTES, 'UTF-8') . '</pre>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre dir="rtl">خطا: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
