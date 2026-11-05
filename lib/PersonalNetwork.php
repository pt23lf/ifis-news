<?php
declare(strict_types=1);

/**
 * Owner-isolated contact operations for the upcoming contributor network.
 * Every public method requires a currently active editorial account.
 * Existing admin-netzwerk.php is intentionally NOT switched over yet.
 */
final class PersonalNetwork
{
    private PDO $network;
    private PDO $users;

    public function __construct(PDO $network, PDO $users)
    {
        $this->network = $network;
        $this->users = $users;
        $this->network->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function ensureActive(int $userId): void
    {
        $stmt = $this->users->prepare("SELECT 1 FROM editorial_users WHERE id=? AND status='active'");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Access denied.');
    }

    public function contacts(int $userId, int $limit = 500): array
    {
        $this->ensureActive($userId);
        $limit = max(1, min(1000, $limit));
        $stmt = $this->network->prepare(
            'SELECT id,name,linkedin_url,place,lat,lon,topic,relation,note,created_at,updated_at
             FROM contacts WHERE owner_user_id=:owner ORDER BY updated_at DESC,id DESC LIMIT :limit'
        );
        $stmt->bindValue(':owner', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contact(int $userId, int $contactId): ?array
    {
        $this->ensureActive($userId);
        $stmt = $this->network->prepare(
            'SELECT id,name,linkedin_url,place,lat,lon,topic,relation,note,created_at,updated_at
             FROM contacts WHERE owner_user_id=? AND id=? LIMIT 1'
        );
        $stmt->execute([$userId, $contactId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $userId, array $data, ?int $contactId = null): int
    {
        $this->ensureActive($userId);
        $name = trim((string)($data['name'] ?? ''));
        $url = trim((string)($data['linkedin_url'] ?? ''));
        $place = trim((string)($data['place'] ?? ''));
        $topic = trim((string)($data['topic'] ?? ''));
        $note = trim((string)($data['note'] ?? ''));
        $relation = (string)($data['relation'] ?? 'interesting');
        if ($name === '' || mb_strlen($name) > 140 || mb_strlen($place) > 160 ||
            mb_strlen($topic) > 220 || mb_strlen($note) > 1200) {
            throw new InvalidArgumentException('Invalid contact fields.');
        }
        if ($url !== '') {
            $p = parse_url($url);
            $host = strtolower((string)($p['host'] ?? ''));
            if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' ||
                isset($p['user']) || isset($p['pass']) ||
                !($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com') || $host === 'lnkd.in')) {
                throw new InvalidArgumentException('Invalid LinkedIn URL.');
            }
        }
        if (!in_array($relation, ['commented','contact','interesting'], true)) {
            throw new InvalidArgumentException('Invalid relation.');
        }
        $lat = $data['lat'] ?? null;
        $lon = $data['lon'] ?? null;
        if (($lat === null) !== ($lon === null) ||
            ($lat !== null && (!is_numeric($lat) || !is_numeric($lon) ||
                !is_finite((float)$lat) || !is_finite((float)$lon) ||
                abs((float)$lat) > 90 || abs((float)$lon) > 180))) {
            throw new InvalidArgumentException('Invalid coordinates.');
        }
        $values = [$name,$url,$place,$lat === null ? null : (float)$lat,
            $lon === null ? null : (float)$lon,$topic,$relation,$note,gmdate('c')];

        if ($contactId !== null) {
            // Enforce ownership at UPDATE time, not just when the form is opened.
            $stmt = $this->network->prepare(
                'UPDATE contacts SET name=?,linkedin_url=?,place=?,lat=?,lon=?,topic=?,relation=?,note=?,updated_at=?
                 WHERE id=? AND owner_user_id=?'
            );
            $stmt->execute([...$values,$contactId,$userId]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Contact not found.');
            return $contactId;
        }

        $stmt = $this->network->prepare(
            'INSERT INTO contacts (name,linkedin_url,place,lat,lon,topic,relation,note,updated_at,created_at,owner_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([...$values,gmdate('c'),$userId]);
        return (int)$this->network->lastInsertId();
    }

    public function setCoordinates(int $userId, int $contactId, string $place, float $lat, float $lon): bool
    {
        $this->ensureActive($userId);
        if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) {
            throw new InvalidArgumentException('Invalid coordinates.');
        }
        $stmt = $this->network->prepare(
            'UPDATE contacts SET lat=?,lon=?,updated_at=? WHERE id=? AND owner_user_id=?
             AND place=? AND lat IS NULL AND lon IS NULL'
        );
        $stmt->execute([$lat,$lon,gmdate('c'),$contactId,$userId,$place]);
        return $stmt->rowCount() === 1;
    }

    public function delete(int $userId, int $contactId): bool
    {
        $this->ensureActive($userId);
        // Legacy capture/comment dependencies must be migrated before
        // exposing delete in the contributor interface.
        throw new LogicException('Deletion disabled pending dependent-record migration.');
    }
}
