<?php
declare(strict_types=1);
// CLI-only, explainable first-pass analysis. No article is hidden or rejected.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "pdo_sqlite missing\n"); exit(1); }
$db = new PDO('sqlite:' . $base . '/storage/news.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=5000');
$version = 'rules-0.1';
$limit = 100;
$dry = in_array('--dry-run', $argv, true);
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = min(500, max(1, (int)$m[1]));
}
$rules = [
 'bait' => ['you won\'t believe','what happened next','one weird trick','doctors hate','mind-blowing','das glaubst du nicht','geheimtrick','schockierend'],
 'positive' => ['breakthrough','improved','success','restored','solution','progress','recovery','erfolg','verbessert','lösung','fortschritt'],
 'negative' => ['war','killed','deaths','crisis','disaster','attack','violence','krieg','getötet','krise','katastrophe','angriff','gewalt'],
 'constructive' => ['restoration','reforestation','renewable energy','conservation','cooperation','peace agreement','renaturierung','wiederaufforstung','erneuerbare energie','kooperation','friedensabkommen'],
 'harmful' => ['deforestation','pollution','habitat loss','war crimes','toxic waste','entwaldung','verschmutzung','artensterben','kriegsverbrechen'],
 'relevant' => ['health','education','environment','climate','workplace','society','biodiversity','gesundheit','bildung','umwelt','klima','arbeit','gesellschaft','artenvielfalt']
];
function matches(string $text, array $terms): array {
    $found = [];
    foreach ($terms as $term) {
        // Avoid substring matches inside words (e.g. "war" in "toward").
        if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term,'/').'(?![\p{L}\p{N}])/iu', $text)) $found[] = $term;
    }
    return $found;
}
$q = $db->prepare("SELECT a.id,a.title,a.summary,x.input_hash FROM article_analysis x JOIN articles a ON a.id=x.article_id WHERE x.status='pending' ORDER BY a.published_at DESC LIMIT ?");
$q->bindValue(1, $limit, PDO::PARAM_INT);
$q->execute();
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
$update = $db->prepare("UPDATE article_analysis SET status='review',analyzer_version=:version,quality_label=:quality,tone_label=:tone,direction_label=:direction,relevance_label=:relevance,confidence=NULL,evidence_json=:evidence,analyzed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE article_id=:id AND status='pending' AND input_hash=:hash");
$done = 0;
foreach ($rows as $row) {
    $title = mb_strtolower($row['title'], 'UTF-8');
    $text = mb_strtolower($row['title'].' '.$row['summary'], 'UTF-8');
    $hits = [];
    foreach ($rules as $key => $terms) $hits[$key] = matches($key === 'bait' ? $title : $text, $terms);
    $tone = count($hits['positive']) && count($hits['negative']) ? 'balanced' : (count($hits['positive']) ? 'positive' : (count($hits['negative']) ? 'negative' : 'unknown'));
    $direction = count($hits['constructive']) && count($hits['harmful']) ? 'mixed' : (count($hits['constructive']) ? 'constructive' : (count($hits['harmful']) ? 'harmful' : 'unknown'));
    $evidence = json_encode(['method'=>'keyword-screening','scope'=>'rss-title-and-summary','signals'=>$hits,'limitations'=>'Not a verified journalistic quality or impact assessment'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($dry) { echo $row['id'].": ".(count($hits['bait'])?'clickbait signal':'no clickbait signal').", tone=$tone, direction=$direction\n"; continue; }
    $update->execute([
      ':version'=>$version, ':quality'=>count($hits['bait'])?'clickbait_suspected':'unassessed',
      ':tone'=>$tone, ':direction'=>$direction,
      ':relevance'=>count($hits['relevant'])?'potential':'unassessed',
      ':evidence'=>$evidence, ':id'=>$row['id'], ':hash'=>$row['input_hash']
    ]);
    $done += $update->rowCount();
}
echo $dry ? "Dry run: ".count($rows)." items; no changes\n" : "Screened $done articles; status=review (not verified).\n";
