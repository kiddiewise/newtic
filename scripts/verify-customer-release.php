<?php

declare(strict_types=1);

/**
 * Dependency-free installer integrity verifier.
 * Checks immutable files before any database migration or new APP_KEY.
 * This detects corruption; authentic origin is established by downloading the
 * artifact from the official repository and comparing the published ZIP SHA256.
 */
$root = realpath($argv[1] ?? dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "ERROR: Release root does not exist.\n");
    exit(1);
}
$manifest = $root.'/NEWTIC-RELEASE-SHA256SUMS.txt';
$revisionFile = $root.'/.newtic-release-revision';
$buildRevision = $root.'/public/build/newtic-revision.txt';
if (!is_file($manifest) || !is_file($revisionFile) || !is_file($buildRevision)) {
    fwrite(STDERR, "ERROR: Incomplete NEWTIC release metadata.\n");
    exit(1);
}
$revision = trim((string) file_get_contents($revisionFile));
if (!preg_match('/^[a-f0-9]{40}$/D', $revision)
    || trim((string) file_get_contents($buildRevision)) !== $revision) {
    fwrite(STDERR, "ERROR: Application and frontend revision mismatch.\n");
    exit(1);
}
$lines = file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false || count($lines) < 100) {
    fwrite(STDERR, "ERROR: Missing or incomplete release checksums.\n");
    exit(1);
}
$checked = [];
foreach ($lines as $line) {
    if (!preg_match('/^([a-f0-9]{64})  \.\/(.+)$/D', $line, $m)) {
        fwrite(STDERR, "ERROR: Malformed release checksum record.\n");
        exit(1);
    }
    $relative = $m[2];
    $parts = explode('/', $relative);
    if (isset($checked[$relative]) || in_array('..', $parts, true)
        || in_array('', $parts, true)
        || strpos($relative, chr(92)) !== false
        || preg_match('/[\x00-\x1F]/', $relative)
        || str_starts_with($relative, '/') || $relative === 'NEWTIC-RELEASE-SHA256SUMS.txt') {
        fwrite(STDERR, "ERROR: Unsafe or duplicate checksum record.\n");
        exit(1);
    }
    $file = $root.'/'.$relative;
    $resolved = realpath($file);
    if (!$resolved || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)
        || !is_file($file) || is_link($file)
        || !hash_equals($m[1], (string) hash_file('sha256', $file))) {
        fwrite(STDERR, "ERROR: A NEWTIC release file is missing, changed or unsafe.\n");
        exit(1);
    }
    $checked[$relative] = true;
}
foreach (['artisan', 'composer.lock', 'vendor/autoload.php', 'public/build/manifest.json',
    'public/.htaccess', 'scripts/install-production.sh'] as $mandatory) {
    if (!isset($checked[$mandatory])) {
        fwrite(STDERR, "ERROR: A mandatory component is missing from this release.\n");
        exit(1);
    }
}
echo "NEWTIC_RELEASE_INTEGRITY_OK: ".substr($revision, 0, 12)." (".count($checked)." files)\n";
