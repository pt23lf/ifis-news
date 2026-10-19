<?php
declare(strict_types=1);
// IFIS News: HTTPS-only authenticated entry point for All-inkl URL cron.
$root = dirname(__DIR__);
$tokenFile = $root . '/storage/.cron-http-token';
$given = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$stored = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || strlen($stored) < 32 || !hash_equals($stored, $given)) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if (!function_exists('proc_open')) {
    http_response_code(503);
    echo "PHP proc_open is disabled. Use a provider-side CLI cron instead.\n";
    exit;
}
@set_time_limit(0);
$log = $root . '/storage/logs/cron-http.log';
if (!is_dir(dirname($log))) @mkdir(dirname($log), 0750, true);
$null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
$descriptors = [
    0 => ['file', $null, 'r'],
    1 => ['file', $log, 'a'],
    2 => ['file', $log, 'a'],
];
$process = @proc_open(['/bin/sh', $root . '/bin/cron-import.sh'], $descriptors, $pipes, $root);
if (!is_resource($process)) {
    http_response_code(503);
    echo "Unable to start import. Check PHP process restrictions.\n";
    exit;
}
$exitCode = proc_close($process);
http_response_code($exitCode === 0 ? 200 : 500);
echo $exitCode === 0 ? "IFIS News cron completed\n" : "IFIS News cron failed; inspect storage/logs/import.log\n";
