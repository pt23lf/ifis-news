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
</style></head><body><header class="topbar"><div class="topbar-inner"><a class="brand" href="/?view=ifis"><strong>IFIS</strong></a><nav class="topnav" aria-label="Bereiche"><a href="/?view=world&amp;map=0#stories">Links</a><a href="/">Karte</a></nav></div></header><main>
<h1><?=h($pages[$page])?></h1>
<?php if($page==='about'): ?>
<p><strong>IFIS News ist ein experimenteller Nachrichten- und Aktivitätskompass.</strong> Er macht geografisch verortete Entwicklungen, Quellenbezüge und mögliche konstruktive Aktivitäten sichtbar. Ein Schwerpunkt liegt auf positiven Entwicklungen, die im klassischen Nachrichtenstrom leicht untergehen.</p>
<p>Die Karte zeigt aktuell insbesondere eigene IFIS-Beiträge und aggregierte Ereignisbeobachtungen aus GDELT. Nutzer können weitere öffentliche Webseiten, Feeds und Netzwerkbeiträge zur Prüfung vorschlagen. Eingereichte Vorschläge erscheinen nicht automatisch öffentlich.</p>
<p>Die Ansicht ist weder eine vollständige Nachrichtenübersicht noch eine journalistische Tatsachenprüfung. Ein Ereignis, eine verknüpfte Quelle oder eine automatische Klassifikation bedeutet nicht, dass IFIS deren Aussage bestätigt.</p>
<p><a class="link" href="info.php?page=method">Quellen und Methodik</a> · <a class="link" href="https://ifis.net/">IFIS – Hintergründe</a></p>
<?php elseif($page==='method'): ?>
<p>IFIS News kombiniert unterschiedliche Datenarten, die getrennt betrachtet werden müssen. Die Karten- und Quellenfunktionen befinden sich in Entwicklung.</p>
<h2>Aktuelle Datenquellen</h2>
<p><strong>IFIS:</strong> Eigene Artikel werden über den IFIS-RSS-Feed erschlossen und mit der Originalseite verlinkt.</p>
<p><strong>GDELT:</strong> Importiert werden strukturierte Ereignismetadaten, Zeitpunkte, ungefähre geografische Koordinaten, Ereignisklassen und Referenzlinks aus dem klassischen GDELT-Ereignisdatenstrom. Es werden dabei keine vollständigen fremden Nachrichtentexte übernommen. Eine GDELT-Erwähnung ist keine unabhängige Bestätigung eines Ereignisses.</p>
<p><strong>Weitere Quellen:</strong> RSS-/Atom-Feeds, Webseiten, LinkedIn und andere Netzwerke können vorgeschlagen werden. Sie werden zunächst ausschließlich zur manuellen Prüfung gespeichert. Eine automatische Freigabe oder Übernahme fremder Inhalte findet dadurch nicht statt.</p>
<h2>Karte, Verbindungen und Zeitraum</h2>
<p>GDELT-Beobachtungen werden näherungsweise in geografischen Rasterzellen zusammengefasst. Quellenverbindungen beruhen auf erfassten Referenzlinks und vorläufig hinterlegten Verlagsstandorten; sie belegen weder den tatsächlichen Entstehungsort einer Meldung noch einen ursächlichen Zusammenhang.</p>
<p>Der Zeitraum kann bis zum ältesten gespeicherten Datensatz ausgeweitet werden. Die GDELT-Importe liefen zunächst nur mit 48 Stunden Aufbewahrung; früher gelöschte Daten stehen daher nicht automatisch zur Verfügung. Bei umfangreichen Datenbeständen sind die Kartenabfragen derzeit auf eine begrenzte Anzahl von Datensätzen und Ortsgruppen beschränkt. Die Karte zeigt dann keinen vollständigen historischen Bestand.</p>
<h2>Einordnung und Rechte</h2>
<p>GDELT-Ereignisklassen beruhen auf maschinell erfassten Datensätzen. Kategorien wie Kooperation oder Konflikt sind nur Anhaltspunkte und keine Bewertung der tatsächlichen Wirkung. Die Quellenlage kann unvollständig und zeitlich verzerrt sein.</p>
<p>Für fremde Nachrichtenangebote gilt eine restriktive Rechteprüfung. Ein öffentlich zugänglicher Feed oder Link ist keine pauschale Lizenz, Texte, Bilder oder ganze Archive erneut zu veröffentlichen. Die Rechte an verlinkten Originalinhalten verbleiben bei den jeweiligen Berechtigten.</p>
<?php elseif($page==='imprint'): ?>
<p><strong>IFIS News</strong> ist ein Angebot im Rahmen von IFIS – Institut für funktionale innere Strukturen.</p>
<p>Verantwortliche Angaben, ladungsfähige Anschrift und Kontaktmöglichkeiten finden Sie im <a class="link" href="https://ifis.net/impressum">Impressum von IFIS.net</a>.</p>
<p>Die Plattform verknüpft eigene IFIS-Inhalte, strukturierte Ereignismetadaten und Verweise auf externe Quellen. Verlinkte externe Inhalte stammen von den jeweiligen Anbietern; deren Urheberschaft bleibt unberührt. Nutzer können Quellen zur Prüfung einreichen, ohne dass diese automatisch veröffentlicht werden.</p>
<?php elseif($page==='privacy'): ?>
<p>IFIS News gehört zum Angebot von IFIS. Angaben zum Verantwortlichen und zu Ihren Datenschutzrechten finden Sie in der <a class="link" href="https://ifis.net/datenschutz">Datenschutzerklärung von IFIS.net</a>.</p>
<h2>Besuch und Einstellungen</h2>
<p>Beim Seitenabruf verarbeitet der Hostinganbieter technisch erforderliche Verbindungsdaten; Serverprotokolle können anfallen. Persönliche Einstellungen wie Sprache, gespeicherte und verborgene Beiträge sowie Karten- und Filterauswahl werden teilweise im lokalen Speicher Ihres Browsers gespeichert. Diese Daten können über die Browsereinstellungen gelöscht werden.</p>
<h2>Quellenvorschläge</h2>
<p>Wenn Sie eine Quelle einreichen, werden die von Ihnen übermittelten Angaben (Quellenart, URL, gegebenenfalls Feedadresse, Titel, Ort, Beschreibung und Einverständniserklärungen) zur manuellen Prüfung in einer serverseitigen Datenbank gespeichert. Die Einreichung wird nicht automatisch veröffentlicht. Bitte geben Sie keine personenbezogenen Daten Dritter ohne entsprechende Berechtigung ein.</p>
<p>Für Auskunft, Berichtigung oder die Rücknahme eines Vorschlags verwenden Sie bitte die Kontaktmöglichkeiten im <a class="link" href="https://ifis.net/impressum">Impressum</a>. Aufbewahrungs- und Löschfristen sowie die konkrete Rechtsgrundlage der Verarbeitung müssen vor dem produktiven Ausbau des Freigabesystems noch verbindlich festgelegt und dokumentiert werden.</p>
<h2>Karte und externe Dienste</h2>
<p>Für die interaktive Kartenansicht werden MapLibre und externe Kartendaten beziehungsweise technische Ressourcen eingesetzt. Beim Laden externer Ressourcen können Anbieter Verbindungsdaten einschließlich Ihrer IP-Adresse erhalten. Das Öffnen verlinkter Originalquellen führt zu deren Websites, für die eigene Datenschutzbestimmungen gelten.</p>
<p><strong>Stand dieser Beschreibung:</strong> Oktober 2026. Vor einer abschließenden rechtlichen Freigabe sind die Angaben mit den tatsächlich eingebundenen Ressourcen, Serverlogs und geltenden Aufbewahrungsregeln abzugleichen.</p>
<?php endif; ?>
</main><footer class="site-footer"><a href="info.php?page=about" class="<?= $page==='about'?'active':'' ?>">Über IFIS News</a><a href="info.php?page=method" class="<?= $page==='method'?'active':'' ?>">Quellen & Methodik</a><a href="info.php?page=imprint" class="<?= $page==='imprint'?'active':'' ?>">Impressum</a><a href="info.php?page=privacy" class="<?= $page==='privacy'?'active':'' ?>">Datenschutz</a><a href="https://ifis.net/">IFIS.net</a></footer><?php if($overlay): ?><script>document.querySelectorAll('a[href^="info.php?page="]').forEach(a=>{const u=new URL(a.href);u.searchParams.set('overlay','1');a.href=u.toString()});</script><?php endif; ?></body></html>