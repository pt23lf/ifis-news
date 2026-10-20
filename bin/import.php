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
if (!in_array('first_seen_at', array_column($cols,'name'),true)) $db->exec("ALTER TABLE articles ADD COLUMN first_seen_at TEXT DEFAULT NULL");
// Separate, versioned analysis queue. Existing articles remain published unchanged.
// No automatic rejection and no unsupported claims of AI classification.
$db->exec("CREATE TABLE IF NOT EXISTS article_analysis (
 article_id INTEGER PRIMARY KEY REFERENCES articles(id) ON DELETE CASCADE,
 input_hash TEXT NOT NULL,
 analyzer_version TEXT NOT NULL DEFAULT 'pending',
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','processing','review','complete','error')),
 quality_label TEXT NOT NULL DEFAULT 'unassessed',
 tone_label TEXT NOT NULL DEFAULT 'unassessed',
 direction_label TEXT NOT NULL DEFAULT 'unassessed',
 relevance_label TEXT NOT NULL DEFAULT 'unassessed',
 confidence REAL,
 evidence_json TEXT NOT NULL DEFAULT '{}',
 analyzed_at TEXT,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_article_analysis_status ON article_analysis(status,updated_at)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_articles_published_at ON articles(published_at)");
$queue=$db->prepare("INSERT INTO article_analysis(article_id,input_hash,status,updated_at)
 VALUES(:id,:hash,'pending',CURRENT_TIMESTAMP)
 ON CONFLICT(article_id) DO UPDATE SET
 input_hash=excluded.input_hash,
 status=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'pending' ELSE article_analysis.status END,
 analyzer_version=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'pending' ELSE article_analysis.analyzer_version END,
 quality_label=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'unassessed' ELSE article_analysis.quality_label END,
 tone_label=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'unassessed' ELSE article_analysis.tone_label END,
 direction_label=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'unassessed' ELSE article_analysis.direction_label END,
 relevance_label=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN 'unassessed' ELSE article_analysis.relevance_label END,
 confidence=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN NULL ELSE article_analysis.confidence END,
 evidence_json=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN '{}' ELSE article_analysis.evidence_json END,
 analyzed_at=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN NULL ELSE article_analysis.analyzed_at END,
 updated_at=CASE WHEN article_analysis.input_hash!=excluded.input_hash THEN CURRENT_TIMESTAMP ELSE article_analysis.updated_at END");
$articleId=$db->prepare("SELECT id,title,summary FROM articles WHERE url=?");
$feeds = json_decode(file_get_contents($base . '/config/feeds.json'), true, 512, JSON_THROW_ON_ERROR);
$articlesBeforeImport=(int)$db->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$stmt = $db->prepare("INSERT INTO articles(url,title,source,section,published_at,summary,image_url,first_seen_at) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(url) DO UPDATE SET image_url=CASE WHEN excluded.image_url != '' THEN excluded.image_url ELSE articles.image_url END");
$rights=json_decode((string)file_get_contents($base.'/config/rss-rights.json'),true,512,JSON_THROW_ON_ERROR);
$allowed=[];
foreach(($rights['feeds']??[]) as $entry){
 if(!is_array($entry)||!in_array($entry['mode']??'', ['own','links_activity'],true))continue;
 $allowed[(string)($entry['name']??'').'|'.(string)($entry['url']??'')]=$entry['mode'];
}
foreach ($feeds as $feed) {
 $mode=$allowed[(string)($feed['name']??'').'|'.(string)($feed['url']??'')]??'blocked';
 if($mode==='blocked')continue;
 // Only NASA/NOAA and exact URLs documented in the rights register are permitted.
 if($mode==='links_activity' && !in_array(($feed['name']??'').'|'.($feed['url']??''),[
 'NASA · News|https://www.nasa.gov/feed/',
 'NOAA · Environment|https://www.noaa.gov/rss.xml'
 ],true))continue;
 $url = $feed['url'] ?? '';
 if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') continue;
 $context = stream_context_create(['http'=>['timeout'=>12,'user_agent'=>'IFIS-News/0.1 (+https://news.ifis.net)','follow_location'=>1,'max_redirects'=>3]]);
 $body = @file_get_contents($url, false, $context);
 if (!$body) { fwrite(STDERR, "Feed unavailable: $url\n"); continue; }
 $old = libxml_use_internal_errors(true);
 $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
 libxml_clear_errors(); libxml_use_internal_errors($old);
 if (!$xml) continue;
 $entries = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->item) ? $xml->item : $xml->entry);
 $count = 0;
 $imported = 0;
 $feedLimit = $feed['name'] === 'IFIS · Artikel' ? 250 : 30;
 foreach ($entries as $entry) {
  if (++$count > $feedLimit) break;
  $link = (string)$entry->link;
  if (!$link && isset($entry->link)) $link = (string)$entry->link['href'];
  if (!$link && isset($entry->link)) { foreach ($entry->link as $atomLink) { if ((string)$atomLink['rel'] === 'alternate' || !(string)$atomLink['rel']) { $link = (string)$atomLink['href']; break; } } }
  if (!filter_var($link, FILTER_VALIDATE_URL) || !in_array(parse_url($link, PHP_URL_SCHEME), ['http','https'], true)) continue;
  $title = trim(strip_tags((string)$entry->title));
  if (!$title) continue;
  $summary = trim(strip_tags((string)($entry->description ?? $entry->summary ?? '')));
  $date = (string)($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
  $timestamp = $date ? strtotime($date) : false;
  $image = '';
  $media = $entry->children('http://search.yahoo.com/mrss/');
  // Prefer the largest declared media image rather than a tiny RSS thumbnail.
  $candidates = [];
  foreach (['content','thumbnail'] as $tag) {
    foreach ($media->$tag as $node) {
      $attrs = $node->attributes();
      $candidate = (string)$attrs->url;
      $width = (int)($attrs->width ?? 0);
      $height = (int)($attrs->height ?? 0);
      if ($candidate && filter_var($candidate,FILTER_VALIDATE_URL) && parse_url($candidate,PHP_URL_SCHEME)==='https') {
        $candidates[] = ['url'=>$candidate,'score'=>($width > 0 && $height > 0) ? $width*$height : ($tag==='content' ? 100000 : 1)];
      }
    }
  }
  usort($candidates,static fn($a,$b)=>$b['score']<=>$a['score']);
  if ($candidates) $image = $candidates[0]['url'];
  if (!$image && isset($entry->enclosure)) $image = (string)$entry->enclosure->attributes()->url;
  if (!$image) {
    $html = (string)($entry->description ?? $entry->summary ?? $entry->children('http://www.w3.org/2005/Atom')->content ?? '');
    if (preg_match('~<img\\b[^>]*\\bsrc\\s*=\\s*(["\\x27])([^"\\x27]+)\\1~i', $html, $matches)) {
      $image = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
  }
  if ($image && (!filter_var($image, FILTER_VALIDATE_URL) || parse_url($image, PHP_URL_SCHEME) !== 'https')) $image = '';
  if($mode==='links_activity'){
   // Do not retain source headline, description, photo, enclosure, or article text.
   $title=$feed['name'].' · Originalmeldung';
   $summary='';
   $image='';
  }
  $stmt->execute([$link,substr($title,0,400),$feed['name'],$feed['section'],gmdate('Y-m-d H:i:s',$timestamp ?: time()),substr($summary,0,450),$image,gmdate('Y-m-d H:i:s')]);
  $imported++;
  $articleId->execute([$link]);
  $row=$articleId->fetch(PDO::FETCH_ASSOC);
  if ($row) $queue->execute([':id'=>(int)$row['id'], ':hash'=>hash('sha256', $row['title']."\\n".$row['summary'])]);
 }
 if ($feed['name'] === 'IFIS · Artikel') echo "IFIS RSS: $imported Artikel verarbeitet\\n";
}
$articlesAfterImport=(int)$db->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$importStats=['at'=>gmdate('c'),'new'=>max(0,$articlesAfterImport-$articlesBeforeImport),'total'=>$articlesAfterImport];
file_put_contents(dirname(__DIR__).'/storage/news-last-import-stats.json',json_encode($importStats,JSON_UNESCAPED_UNICODE),LOCK_EX);
file_put_contents(dirname(__DIR__).'/storage/news-last-import.txt',gmdate('c'),LOCK_EX);
echo "Import complete\\n";