<?php
declare(strict_types=1);
// Anonymous, aggregate-only feature counters. No visitor IDs, IPs or user agents.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 512) { http_response_code(413); echo '{"ok":false}'; exit; }
$allowed = ['map_open','map_projection','map_settings','time_range','filter','saved_view','save_article','hide_article','share_collection','share_location','marker_open','marker_hover','source_links'];
$raw = file_get_contents('php://input', false, null, 0, 512);
$data = json_decode((string)$raw, true);
$event = is_array($data) ? ($data['event'] ?? null) : null;
if (!is_string($event) || !in_array($event, $allowed, true)) { http_response_code(400); echo '{"ok":false}'; exit; }
$dir = dirname(__DIR__) . '/storage/metrics';
if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) { http_response_code(503); echo '{"ok":false}'; exit; }
$file = $dir . '/' . gmdate('Y-m') . '.json';
$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(503); echo '{"ok":false}'; exit; }
try {
 if (!flock($fp, LOCK_EX)) throw new RuntimeException('lock');
 $content = stream_get_contents($fp);
 $counts = json_decode(is_string($content) ? $content : '', true);
 if (!is_array($counts)) $counts = [];
 $day = gmdate('Y-m-d');
 if (!isset($counts[$day]) || !is_array($counts[$day])) $counts[$day] = [];
 $counts[$day][$event] = min(2147483647, (int)($counts[$day][$event] ?? 0) + 1);
 rewind($fp); ftruncate($fp, 0);
 fwrite($fp, json_encode($counts, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
 fflush($fp); flock($fp, LOCK_UN);
} catch (Throwable $e) { http_response_code(503); echo '{"ok":false}'; exit; }
finally { fclose($fp); }
echo '{"ok":true}';
