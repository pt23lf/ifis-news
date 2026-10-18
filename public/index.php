<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$view = ($_GET['view'] ?? 'world') === 'ifis' ? 'ifis' : 'world';
$items = [];
$dbPath = dirname(__DIR__) . '/storage/news.sqlite';
if (is_file($dbPath) && extension_loaded('pdo_sqlite')) {
 try {
  $db = new PDO('sqlite:' . $dbPath);
  $query = $db->prepare('SELECT title,url,source,published_at,summary FROM articles WHERE section=:section ORDER BY published_at DESC LIMIT 80');
  $query->execute(['section'=>$view]);
  $items = $query->fetchAll(PDO::FETCH_ASSOC);
 } catch (Throwable $e) { error_log($e->getMessage()); }
}
function esc(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IFIS News</title><style>
:root{color-scheme:light dark;font-family:system-ui,-apple-system,sans-serif}body{max-width:960px;margin:auto;padding:2rem 1.2rem;line-height:1.5}header{border-bottom:1px solid #8885}nav{display:flex;gap:1rem;margin:1.6rem 0}nav a{padding:.6rem 1rem;border:1px solid #8888;border-radius:1rem;color:inherit;text-decoration:none}nav .active{border-color:currentColor;font-weight:700}article{padding:1rem 0;border-bottom:1px solid #8885}article h2{font-size:1.2rem;margin:.3rem 0}article a{color:inherit}small,footer{opacity:.7}footer{margin-top:3rem}
</style></head><body><header><h1>IFIS News</h1><p>Weltgeschehen verstehen. Strukturelle Zusammenhänge erkennen.</p></header><nav><a class="<?= $view==='world'?'active':'' ?>" href="?view=world">Weltlage</a><a class="<?= $view==='ifis'?'active':'' ?>" href="?view=ifis">IFIS-Radar</a></nav><main><?php if (!$items): ?><p>Noch keine Nachrichten importiert.</p><?php endif; ?><?php foreach ($items as $item): ?><article><small><?=esc($item['source'])?> · <?=esc($item['published_at'])?></small><h2><a href="<?=esc($item['url'])?>" target="_blank" rel="noopener noreferrer"><?=esc($item['title'])?></a></h2><p><?=esc($item['summary'])?></p></article><?php endforeach; ?></main><footer>Nachrichten stammen aus externen Quellen. <a href="https://ifis.net">IFIS</a></footer></body></html>