<?php
declare(strict_types=1);
// Run locally: php tests/editorial-auth-smoke.php
require dirname(__DIR__) . '/lib/EditorialAuth.php';

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "pdo_sqlite required\n");
    exit(2);
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/2026-11-05-editorial-foundation.sql'));
$auth = new EditorialAuth($db);
$adminPass = 'a-local-test-password-42';
$adminId = $auth->createInitialAdmin('admin@example.test', 'Test Admin', $adminPass);
assertTrue($adminId > 0, 'Admin created');
assertTrue($auth->authenticate('admin@example.test', $adminPass)['role'] === 'admin', 'Admin login');
assertTrue($auth->authenticate('admin@example.test', 'wrong password') === null, 'Invalid login rejected');
$token = $auth->invite($adminId, 'contributor@example.test', 'contributor');
assertTrue(strlen($token) === 64, 'Token length');
$contributorId = $auth->accept($token, 'Contributor', 'a-secure-test-password-42');
assertTrue($auth->authenticate('contributor@example.test', 'a-secure-test-password-42')['role'] === 'contributor', 'Invited account login');
expectFailure(fn() => $auth->accept($token, 'Replay', 'a-secure-test-password-42'), 'Invitation replay rejected');
expectFailure(fn() => $auth->invite($contributorId, 'other@example.test'), 'Contributor cannot invite');
expectFailure(fn() => $auth->createInitialAdmin('extra@example.test', 'Extra', $adminPass), 'Second bootstrap rejected');
echo "Editorial auth smoke tests passed.\n";

function assertTrue(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
}
function expectFailure(callable $fn, string $message): void {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('FAIL: ' . $message);
}
