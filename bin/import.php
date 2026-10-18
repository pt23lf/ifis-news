<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "PDO SQLite missing\n"); exit(1); }
$db = new PDO('sqlite:' . $base . '/storage/news.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, url TEXT NOT NULL UNIQUE, title TEXT NOT NULL, source TEXT NOT NULL, section TEXT NOT NULL, published_at TEXT NOT NULL, summary TEXT NOT NULL DEFAULT "")');
$cols=$db->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC);
if (!in_array('image_url', array_column($cols,'name'),true)) $db->exec("ALTER TABLE articles ADD COLUMN image_url TEXT DEFAULT ''");
$feeds = json_decode(file_get_contents($base . '/config/feeds.json'), true, 512, JSON_THROW_ON_ERROR);
$stmt = $db->prepare('INSERT INTO articles(url,title,source,section,published_at,summary,image_url) VALUES(?,?,?,?,?,?,?) ON CONFLICT(url) DO UPDATE SET image_url=CASE WHEN excluded.image_url != '' THEN excluded.image_url ELSE articles.image_url END');
foreach ($feeds as $feed) {
 $url = $feed['url'] ?? '';
 if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') continue;
 $context = stream_context_create(['http'=>['timeout'=>12,'user_agent'=>'IFIS-News/0.1 (+https://news.ifis.net)','follow_location'=>0]]);
 $body = @file_get_contents($url, false, $context);
 if (!$body) { fwrite(STDERR, "Feed unavailable: $url\n"); continue; }
 $old = libxml_use_internal_errors(true);
 $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
 libxml_clear_errors(); libxml_use_internal_errors($old);
 if (!$xml) continue;
 $entries = isset($xml->channel->item) ? $xml->channel->item : $xml->entry;
 $count = 0;
 foreach ($entries as $entry) {
  if (++$count > 30) break;
  $link = (string)$entry->link;
  if (!$link && isset($entry->link)) $link = (string)$entry->link['href'];
  if (!filter_var($link, FILTER_VALIDATE_URL) || !in_array(parse_url($link, PHP_URL_SCHEME), ['http','https'], true)) continue;
  $title = trim(strip_tags((string)$entry->title));
  if (!$title) continue;
  $summary = trim(strip_tags((string)($entry->description ?? $entry->summary ?? '')));
  $date = (string)($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
  $timestamp = $date ? strtotime($date) : false;
  $image = '';
  $media = $entry->children('http://search.yahoo.com/mrss/');
  if (isset($media->thumbnail)) $image = (string)$media->thumbnail->attributes()->url;
  if (!$image && isset($media->content)) $image = (string)$media->content->attributes()->url;
  if (!$image && isset($entry->enclosure)) $image = (string)$entry->enclosure->attributes()->url;
  if (parse_url($image, PHP_URL_SCHEME) !== 'https') $image = '';
  $stmt->execute([$link,substr($title,0,400),$feed['name'],$feed['section'],gmdate('Y-m-d H:i:s',$timestamp ?: time()),substr($summary,0,450),$image]);
 }
}
echo "Import complete\n";