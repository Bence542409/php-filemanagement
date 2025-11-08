<?php
// download.php
// Usage: /php/download.php?ekke/bevinf -> serves file ../ekke/bevinf

// Base directory: one level up from this script
$baseDir = realpath(__DIR__ . '/..');
if ($baseDir === false) {
    http_response_code(500);
    echo "Server configuration error.";
    exit;
}

// Csak ezekből a mappákból engedélyezzük a letöltést, relatív a baseDir-hez
$ALLOWED_DIRS = [
    'ekke',
    'public'
];

function is_in_allowed_dirs($target, $baseDir, $allowedDirs) {
    foreach ($allowedDirs as $dir) {
        $dirPath = realpath($baseDir . DIRECTORY_SEPARATOR . $dir);
        if ($dirPath && strpos($target, $dirPath) === 0) {
            return true;
        }
    }
    return false;
}

// Get raw query string (everything after ?)
$raw = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
if ($raw === '') {
    http_response_code(400);
    echo "No file specified.<br /><br />Usage: download.php?test/test.png";
    exit;
}

// URL-decode the path (allow / separators now)
$path = urldecode($raw);

// Basic sanitation: disallow null bytes and control chars
if (strpos($path, "\0") !== false || preg_match('/[\x00-\x1F]/', $path)) {
    http_response_code(400);
    echo "Invalid path.";
    exit;
}

// Prevent traversal
if (strpos($path, '..') !== false) {
    http_response_code(400);
    echo "Path traversal not allowed.";
    exit;
}

// Ensure allowed characters (letters, numbers, underscore, dot, slash)
if (!preg_match('#^[A-Za-z0-9_\-./]+$#', $path)) {
    http_response_code(400);
    echo "Invalid characters in path.";
    exit;
}

// Compute absolute target path and ensure it's inside baseDir
$target = realpath($baseDir . DIRECTORY_SEPARATOR . $path);
if ($target === false) {
    http_response_code(404);
    echo "File or directory not found.";
    exit;
}
if (strpos($target, $baseDir) !== 0) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Biztonsági ellenőrzés: csak az engedélyezett mappákból lehet
if (!is_in_allowed_dirs($target, $baseDir, $ALLOWED_DIRS)) {
    http_response_code(403);
    echo "Access to this directory is not allowed.";
    exit;
}

// If target is a directory -> create zip and send
if (is_dir($target)) {
    $zipName = basename($target) ?: 'archive';
    $tmpZip = tempnam(sys_get_temp_dir(), 'dlzip_');

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo "Failed to create zip file.";
        exit;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($target) + 1);
        $zip->addFile($filePath, $relativePath);
    }

    $zip->close();

    $downloadFilename = $zipName . '.zip';
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($downloadFilename) . '"');
    header('Content-Length: ' . filesize($tmpZip));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    if (ob_get_level()) ob_end_clean();
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}

// If it's a file -> send it
if (is_file($target) && is_readable($target)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $target) : 'application/octet-stream';
    if ($finfo) finfo_close($finfo);

    $downloadName = basename($target);

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($target));

    if (ob_get_level()) ob_end_clean();

    $chunkSize = 8 * 1024 * 1024;
    $handle = fopen($target, 'rb');
    if ($handle === false) {
        http_response_code(500);
        echo "Unable to open file.";
        exit;
    }
    while (!feof($handle)) {
        echo fread($handle, $chunkSize);
        flush();
    }
    fclose($handle);
    exit;
}

http_response_code(404);
echo "Not found.";
exit;
