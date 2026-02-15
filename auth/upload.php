<?php
declare(strict_types=1);

function upload_media(array $file, array $allowedExt, array $allowedMime, string $subDir, int $maxSizeMb, string $prefix): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > $maxSizeMb * 1024 * 1024) {
        return null;
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime === false || !in_array($mime, $allowedMime, true)) {
        return null;
    }

    // فایل‌ها در مسیر ریشه پروژه /uploads/<subDir> ذخیره می‌شوند تا مستقیماً سرو شوند.
    $uploadDir = __DIR__ . '/../uploads/' . trim($subDir, '/\\') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return '/uploads/' . trim($subDir, '/\\') . '/' . $filename;
    }

    return null;
}

function upload_news_image(array $file): ?string
{
    return upload_media(
        $file,
        ['jpg', 'jpeg', 'png', 'webp'],
        ['image/jpeg', 'image/png', 'image/webp'],
        'news',
        4,
        'news_img'
    );
}

function upload_news_video(array $file): ?string
{
    return upload_media(
        $file,
        ['mp4', 'webm', 'mov'],
        ['video/mp4', 'video/webm', 'video/quicktime'],
        'news',
        20,
        'news_vid'
    );
}
