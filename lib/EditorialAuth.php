<?php
declare(strict_types=1);

/**
 * IFIS News editorial authentication primitives.
 * Not wired to a public endpoint until reviewed and tested.
 */
final class EditorialAuth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function connect(string $dbPath): self
    {
        if (!is_file($dbPath)) {
            throw new RuntimeException('News database does not exist; refusing implicit creation.');
        }
        $db = new PDO('sqlite:' . $dbPath);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 15000');
        return new self($db);
    }

    public function createInitialAdmin(string $email, string $name, string $password): int
    {
        $this->checkEmail($email);
        $this->checkPassword($password);
        $count = (int) $this->db->query('SELECT COUNT(*) FROM editorial_users')->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException('Initial account can be created only on an empty editorial user table.');
        }
        return $this->addAccount($email, $name, $password, 'admin');
    }

    private function addAccount(string $email, string $name, string $password, string $role): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Invalid display name.');
        }
        $stmt = $this->db->prepare("INSERT INTO editorial_users (email,display_name,password_hash,role,status) VALUES (?,?,?,?, 'active')");
        $stmt->execute([strtolower(trim($email)), $name, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int) $this->db->lastInsertId();
    }

    public function invite(int $actorId, string $email, string $role = 'contributor'): string
    {
        $this->requireRole($actorId, ['admin']);
        $this->checkEmail($email);
        if (!in_array($role, ['admin', 'editor', 'contributor', 'reader'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        $email = strtolower(trim($email));
        $exists = $this->db->prepare('SELECT 1 FROM editorial_users WHERE email=? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new RuntimeException('Account already exists.');
        }
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("INSERT INTO editorial_invitations (email,role,token_hash,invited_by,expires_at)
            VALUES (?,?,?,?,datetime('now','+48 hours'))");
        $stmt->execute([$email, $role, hash('sha256', $token), $actorId]);
        return $token; // Return once over secure admin channel; never log or store raw token.
    }

    public function accept(string $token, string $name, string $password): int
    {
        $this->checkPassword($password);
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new InvalidArgumentException('Invalid invitation token.');
        }
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->db->prepare("SELECT id,email,role FROM editorial_invitations
               WHERE token_hash=? AND accepted_at IS NULL AND revoked_at IS NULL
               AND expires_at > datetime('now') LIMIT 1");
            $stmt->execute([hash('sha256', $token)]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invite) {
                throw new RuntimeException('Invalid or expired invitation.');
            }
            $id = $this->addAccount($invite['email'], $name, $password, $invite['role']);
            $stmt = $this->db->prepare("UPDATE editorial_invitations SET accepted_at=datetime('now') WHERE id=? AND accepted_at IS NULL");
            $stmt->execute([$invite['id']]);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function authenticate(string $email, string $password): ?array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $stmt = $this->db->prepare("SELECT id,email,display_name,role,password_hash FROM editorial_users
            WHERE email=? AND status='active' LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->db->prepare('UPDATE editorial_users SET password_hash=? WHERE id=?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $update = $this->db->prepare("UPDATE editorial_users SET last_login_at=datetime('now') WHERE id=?");
        $update->execute([$user['id']]);
        unset($user['password_hash']);
        return $user;
    }

    public function requireRole(int $userId, array $allowed): array
    {
        $stmt = $this->db->prepare("SELECT id,email,display_name,role FROM editorial_users WHERE id=? AND status='active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !in_array($user['role'], $allowed, true)) {
            throw new RuntimeException('Access denied.');
        }
        return $user;
    }

    private function checkEmail(string $email): void
    {
        if (strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email.');
        }
    }

    private function checkPassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new InvalidArgumentException('Password must have 12–1024 bytes.');
        }
    }
}
