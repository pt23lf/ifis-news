<?php
declare(strict_types=1);
/**
 * CLI-only, explicit, one-time contact ownership migration.
 * Usage:
 *   php bin/migrate-network-owners.php /absolute/path/to/network.sqlite /absolute/path/to/news.sqlite ADMIN_USER_ID
 *
 * Make verified backups of both databases first. Never run against production
 * until the old admin network UI has been made owner-aware or disabled.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 4 || !ctype_digit($argv[3]) || (int)$argv[3] < 1) {
    fwrite(STDERR, "Usage: php bin/migrate-network-owners.php NETWORK_DB NEWS_DB ADMIN_USER_ID\n");
    exit(2);
}
[$script,$networkPath,$newsPath,$id] = $argv;
foreach ([$networkPath,$newsPath] as $path) {
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "Both existing database files are required.\n"); exit(2);
    }
}
$users = new PDO('sqlite:' . $newsPath,null,null,[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $users->prepare("SELECT 1 FROM editorial_users WHERE id=? AND status='active' AND role='admin'");
$stmt->execute([(int)$id]);
if (!$stmt->fetchColumn()) { fwrite(STDERR, "Admin account not found.\n"); exit(2); }
$db = new PDO('sqlite:' . $networkPath,null,null,[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=15000');
$db->exec('BEGIN IMMEDIATE');
try {
    $cols = array_column($db->query('PRAGMA table_info(contacts)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if (!in_array('id',$cols,true)) throw new RuntimeException('Contacts table missing.');
    if (!in_array('owner_user_id',$cols,true)) {
        $db->exec('ALTER TABLE contacts ADD COLUMN owner_user_id INTEGER');
    }
    $stmt = $db->prepare('UPDATE contacts SET owner_user_id=? WHERE owner_user_id IS NULL');
    $stmt->execute([(int)$id]);
    $db->exec('CREATE INDEX IF NOT EXISTS contacts_owner_updated ON contacts(owner_user_id, updated_at DESC)');
    // The old globally unique LinkedIn constraint would prevent different
    // owners from tracking the same LinkedIn profile. Replace it only now.
    $db->exec('DROP INDEX IF EXISTS contacts_linkedin_unique');
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS contacts_owner_linkedin_unique
               ON contacts(owner_user_id,linkedin_url) WHERE linkedin_url <> ''");
    $unowned = (int)$db->query('SELECT COUNT(*) FROM contacts WHERE owner_user_id IS NULL')->fetchColumn();
    if ($unowned !== 0) throw new RuntimeException('Unowned contacts remain.');
    $db->exec('COMMIT');
    echo "Contact owner migration complete. Legacy admin UI must not be used until updated.\n";
} catch (Throwable $e) {
    $db->exec('ROLLBACK');
    fwrite(STDERR, "Migration rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
