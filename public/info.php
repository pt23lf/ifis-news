<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$page=(string)($_GET['page']??'about');
$pages=['about'=>'Über IFIS News','method'=>'Quellen & Methodik','imprint'=>'Impressum','privacy'=>'Datenschutz'];
if(!isset($pages[$page])){http_response_code(404);$page='about';}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($pages[$page])?> · IFIS News</title>
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#27313e;background:#fff}
*{box-sizing:border-box}body{margin:0;line-height:1.65}header,footer{border-bottom:1px solid #e7eaf0;padding:20px max(22px,calc((100vw - 900px)/2));display:flex;gap:24px;align-items:center;flex-wrap:wrap}header a:first-child{font-size:21px;letter-spacing:.03em}a{color:inherit;text-decoration:none}a:hover{text-decoration:underline}main{max-width:780px;margin:55px auto 80px;padding:0 22px}h1{font-size:clamp(30px,4vw,43px);font-weight:600;letter-spacing:-.03em;line-height:1.2}h2{font-size:20px;margin-top:32px}p,li{color:#526071}a.link{color:#2468d5;text-decoration:underline}footer{border-top:1px solid #e7eaf0;border-bottom:0;font-size:13px;color:#667284}
</style></head><body><header><a href="/">IFIS</a><a href="/?view=world">World</a><a href="/?view=ifis">Radar</a></header><main>
<h1><?=h($pages[$page])?></h1>
<?php if($page==='about'): ?>
<p><strong>IFIS News zeigt ausschließlich Nachrichten externer Quellen.</strong> Die Meldungen werden nach Kriterien des Instituts für funktionale innere Strukturen (IFIS) ausgewählt, gefiltert und übersichtlich präsentiert. IFIS News betreibt keine eigene Nachrichtenredaktion.</p>
<p>Im Mittelpunkt steht nicht die Unterscheidung zwischen guten und schlechten Nachrichten, sondern die Frage, welche Entwicklungen, Belastungen und Gestaltungsmöglichkeiten für das Leben auf der Erde erkennbar werden.</p>
<p>Alle Artikel führen zur jeweiligen Originalquelle. Überschriften, Kurztexte und Bilder stammen, soweit verfügbar, aus den RSS-Angeboten der Quellen.</p>
<p>Die automatische Einordnung ist vorläufig und kann Fehler enthalten. Sie ist weder eine inhaltliche Prüfung noch eine Empfehlung oder Bewertung der beteiligten Personen und Organisationen.</p>
<p><a class="link" href="info.php?page=method">Mehr zu Quellen und Methodik</a> · <a class="link" href="https://ifis.net/">Über IFIS</a></p>
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
</main><footer><a href="info.php?page=about">Über IFIS News</a><a href="info.php?page=method">Quellen & Methodik</a><a href="info.php?page=imprint">Impressum</a><a href="info.php?page=privacy">Datenschutz</a><a href="https://ifis.net/">IFIS.net</a></footer></body></html>