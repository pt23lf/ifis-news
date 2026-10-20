<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$page=(string)($_GET['page']??'about');
$overlay=(($_GET['overlay']??'')==='1');
$pages=['about'=>'Über IFIS News','method'=>'Quellen & Methodik','imprint'=>'Impressum','privacy'=>'Datenschutz'];
if(!isset($pages[$page])){http_response_code(404);$page='about';}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($pages[$page])?> · IFIS News</title>
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;color-scheme:light;--bg:#fff;--text:#17212e;--muted:#697587;--line:#e7eaf0;--soft:#f5f7fa;--accent:#2468d5;--hover:#eef4ff}
@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#15191f;--text:#e9edf3;--muted:#a0acba;--line:#313945;--soft:#202731;--accent:#8ab4ff;--hover:#26354a}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-size:15px;line-height:1.6}
a{color:inherit;text-decoration:none}a:hover{color:var(--accent)}
.topbar{height:65px;border-bottom:1px solid var(--line);display:flex;align-items:center;position:sticky;top:0;background:var(--bg);z-index:5}
.topbar-inner{width:100%;max-width:1100px;margin:0 auto;padding:0 24px;display:flex;align-items:center;gap:32px;height:100%}
.brand{font-size:25px;letter-spacing:-1px;white-space:nowrap}.brand strong{font-weight:780}
.topnav{height:100%;display:flex;align-items:center;gap:18px;margin:0}
.topnav a{display:flex;align-items:center;height:100%;padding:19px 12px 16px;border-bottom:3px solid transparent;white-space:nowrap;font-size:15px;line-height:1.2}
.topnav a:hover,.topnav a.active{border-bottom-color:var(--accent);color:var(--text)}
main{max-width:828px;margin:0 auto;padding:40px 24px 65px;min-height:65vh}
h1{font-size:clamp(28px,4vw,40px);font-weight:650;letter-spacing:-.03em;line-height:1.2;margin:10px 0 30px}
h2{font-size:20px;margin-top:32px;line-height:1.35}
p,li{color:var(--muted)}strong{color:var(--text)}
a.link{color:var(--accent);text-decoration:underline;text-underline-offset:3px}
.site-footer{display:flex;flex-wrap:wrap;gap:10px 24px;max-width:1100px;margin:0 auto;padding:24px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}
.site-footer a.active{color:var(--accent);font-weight:650}
@media(max-width:720px){.topbar{height:107px;align-items:flex-start}.topbar-inner{height:60px;padding:0 15px;justify-content:space-between;gap:8px}.brand{font-size:23px}.topnav{position:absolute;top:60px;left:0;right:0;height:47px;justify-content:center;gap:5px;border-bottom:1px solid var(--line);background:var(--bg)}.topnav a{font-size:12px;height:47px;padding:12px 12px 8px}main{padding:28px 16px 55px}.site-footer{padding:20px 16px}}
<?php if($overlay): ?>
.topbar,.site-footer{display:none!important}
main{padding-top:24px;min-height:0}
<?php endif; ?>
</style></head><body><header class="topbar"><div class="topbar-inner"><a class="brand" href="/?view=ifis"><strong>IFIS</strong></a><nav class="topnav" aria-label="Bereiche"><a href="/?view=ifis&amp;map=0">Potenzial</a><a href="/?view=ifis#globeView">Karte</a><a href="/?view=world&amp;map=0">Weltlage</a></nav></div></header><main>
<h1><?=h($pages[$page])?></h1>
<?php if($page==='about'): ?>
<p><strong>IFIS News zeigt ausschließlich Nachrichten externer Quellen.</strong> Die Meldungen werden nach Kriterien des Instituts für funktionale innere Strukturen (IFIS) ausgewählt, gefiltert und übersichtlich präsentiert. IFIS News betreibt keine eigene Nachrichtenredaktion.</p>
<p>Im Mittelpunkt steht nicht die Unterscheidung zwischen guten und schlechten Nachrichten, sondern die Frage, welche Entwicklungen, Belastungen und Gestaltungsmöglichkeiten für das Leben auf der Erde erkennbar werden.</p>
<p>Alle Artikel führen zur jeweiligen Originalquelle. Überschriften, Kurztexte und Bilder stammen, soweit verfügbar, aus den RSS-Angeboten der Quellen.</p>
<p>Die automatische Einordnung ist vorläufig und kann Fehler enthalten. Sie ist weder eine inhaltliche Prüfung noch eine Empfehlung oder Bewertung der beteiligten Personen und Organisationen.</p>
<p><a class="link" href="info.php?page=method" class="<?= $page==='method'?'active':'' ?>">Mehr zu Quellen und Methodik</a> · <a class="link" href="https://ifis.net/">Über IFIS</a></p>
<?php elseif($page==='method'): ?>
<p>IFIS News sammelt öffentlich angebotene RSS-Meldungen externer Nachrichten-, Wissenschafts- und Fachquellen. Die Beiträge werden nicht von IFIS verfasst. Ein Klick öffnet den Originalartikel bei der jeweiligen Quelle.</p>
<h2>Einordnung nach IFIS-Perspektiven</h2>
<p>Die Ansicht unterscheidet zwischen Themen, Tonlage und einer vorläufigen Entwicklungsrichtung. Entscheidend ist dabei nicht aggressiver Optimismus, sondern die Aufmerksamkeit für Zusammenhänge, Belastungen, Regeneration und mögliche konstruktive Schritte.</p>
<p>Aktuell beruhen zahlreiche Zuordnungen auf einfachen Stichwortregeln in Überschriften und Kurztexten. Sie verstehen den vollständigen Artikel nicht und können deshalb falsch oder unvollständig sein. „Hoffnungsvoll“ oder „belastend“ beschreibt eine geschätzte Tonlage, nicht den tatsächlichen Wert einer Meldung.</p>
<h2>Grenzen und Transparenz</h2>
<p>Die Auswahl der RSS-Quellen ist kuratiert, nicht vollständig oder repräsentativ für die weltweite Nachrichtenlage. Sichtbarkeit hängt auch davon ab, welche Daten eine Quelle im Feed bereitstellt. Automatische Hinweise zu Clickbait, Themen oder Entwicklungsrichtung sind keine verlässlichen journalistischen Qualitätsurteile.</p>
<p>Quellen behalten die Verantwortung für ihre Inhalte. Bei Fragen zu einzelnen Meldungen ist die jeweilige Originalquelle maßgeblich.</p>
<?php elseif($page==='imprint'): ?>
<p>IFIS News ist ein Angebot im Rahmen von <strong>IFIS – Institut für funktionale innere Strukturen</strong>.</p>
<p>Die Angaben zum Anbieter, zur ladungsfähigen Anschrift und zur Kontaktaufnahme stehen im <a class="link" href="https://ifis.net/impressum">Impressum von IFIS.net</a>. Dieses ist auch für IFIS News maßgeblich.</p>
<p>IFIS News veröffentlicht keine eigenen redaktionellen Nachrichtenartikel, sondern verweist auf Beiträge externer Quellen. Die jeweiligen Anbieter sind für ihre Originalinhalte verantwortlich.</p>
<?php elseif($page==='privacy'): ?>
<p>IFIS News ist ein Teilangebot von IFIS. Die allgemeinen Angaben zum Verantwortlichen und zu Betroffenenrechten stehen in der <a class="link" href="https://ifis.net/datenschutz">Datenschutzerklärung von IFIS.net</a>.</p>
<h2>Verarbeitung auf IFIS News</h2>
<p>Beim Seitenaufruf können technisch erforderliche Verbindungsdaten in Serverprotokollen verarbeitet werden. Für persönliche Einstellungen wie gemerkte oder ausgeblendete Meldungen, Filter und Spaltenansicht verwendet die Seite den lokalen Speicher Ihres Browsers (localStorage). Diese Einstellungen werden dort gespeichert und können über die Browserdaten gelöscht werden.</p>
<h2>Externe Inhalte</h2>
<p>Artikelbilder können direkt von den Servern der jeweiligen Nachrichtenquellen geladen werden. Die Kartenansicht verwendet MapLibre sowie externe Kartenkacheln. Die MapLibre-Bibliothek wird über ein externes Content Delivery Network geladen. Dabei können die jeweiligen Anbieter technische Verbindungsdaten wie Ihre IP-Adresse erhalten.</p>
<p>Wenn Sie auf einen Artikel klicken, verlassen Sie IFIS News; dann gelten die Datenschutzbestimmungen der jeweiligen Quelle.</p>
<p><strong>Hinweis:</strong> Diese Angaben beschreiben die derzeit erkennbaren technischen Funktionen. Die Datenschutzhinweise müssen vor einer abschließenden rechtlichen Freigabe mit dem tatsächlichen Hosting, den externen Diensten und den Serverlogs abgeglichen werden.</p>
<?php endif; ?>
</main><footer class="site-footer"><a href="info.php?page=about" class="<?= $page==='about'?'active':'' ?>"Über IFIS News</a><a href="info.php?page=method" class="<?= $page==='method'?'active':'' ?>">Quellen & Methodik</a><a href="info.php?page=imprint" class="<?= $page==='imprint'?'active':'' ?>">Impressum</a><a href="info.php?page=privacy" class="<?= $page==='privacy'?'active':'' ?>">Datenschutz</a><a href="https://ifis.net/">IFIS.net</a></footer><?php if($overlay): ?><script>document.querySelectorAll('a[href^="info.php?page="]').forEach(a=>{const u=new URL(a.href);u.searchParams.set('overlay','1');a.href=u.toString()});</script><?php endif; ?></body></html>