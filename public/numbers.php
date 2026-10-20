<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/numbers-dashboard.php';
ifisNumbersRequireAuth();
$month=ifisNumbersMonth((string)($_GET['month']??gmdate('Y-m')));
$days=ifisNumbersReadDays($month);
$totals=ifisNumbersSum($days);
ifisNumbersHeader('IFIS · Zahlendashboard','numbers');
echo '<h1>IFIS · Zahlen</h1><p>Gemeinsame Faktenebene der IFIS-Projekte. Getrennt vom persönlichen Denk- und Priorisierungsdashboard.</p>';
echo '<form method="get"><label>Monat <input type="month" name="month" value="'.ifisNumbersEscape($month).'" min="2026-10" max="'.gmdate('Y-m').'"></label> <button>Aktualisieren</button></form>';
echo '<h2>Datenquellen</h2><div class="sources">';
$sources=[
 ['IFIS News','Aktive Quelle','Funktionsnutzung und Werkzeuginteraktionen','metrics.php?month='.rawurlencode($month)],
 ['IFIS.net','Vorbereitet','Artikel, Lesepfade, Textwerkzeuge und Reichweite',null],
 ['fitpro.de','Vorbereitet','Artikel, Selbstcheck und Rückmeldungen',null],
 ['larsfocke.com','Vorbereitet','Archiv und Projektverweise',null],
 ['Planet / Global Compass','Vorbereitet','Beiträge, Orte und Entwicklung',null]
];
foreach($sources as $source){
 echo '<div class="card"><div class="source-title">'.ifisNumbersEscape($source[0]).'</div><div class="source-state">'.ifisNumbersEscape($source[1]).'</div><p>'.ifisNumbersEscape($source[2]).'</p>';
 if($source[3]!==null)echo '<a href="'.ifisNumbersEscape($source[3]).'">Details ansehen →</a>';
 else echo '<span class="muted">Noch keine angebundene Datenquelle</span>';
 echo '</div>';
}
echo '</div>';
echo '<h2>Aktueller Datenstand</h2><div class="cards">';
echo '<div class="card"><div class="muted">IFIS News · Aktionen</div><div class="value">'.number_format(array_sum($totals),0,',','.').'</div><a href="metrics.php?month='.rawurlencode($month).'">Aufschlüsseln</a></div>';
echo '<div class="card"><div class="muted">Verbundene Projekte</div><div class="value">1 / 5</div><small>Aktuell nur IFIS News</small></div>';
echo '<div class="card"><div class="muted">Datenzeitraum</div><div class="value">'.ifisNumbersEscape($month).'</div><small>UTC, aggregierte Tageszähler</small></div></div>';
echo '<section class="panel"><h2>Nächste Messgrößen</h2><p>Für weitere Websites sind dieselben Dimensionen vorgesehen: Reichweite, Nutzungstiefe, freiwillige Interaktionen, Rückkehr und Unterstützungsbeiträge. Sie werden erst angezeigt, wenn eine echte und rechtlich geprüfte Datenquelle angeschlossen ist.</p><p class="muted">Keine Projekt-Gesamtzahlen aus nicht vergleichbaren Zählmethoden. Insbesondere sind Funktionsaktionen nicht mit Seitenaufrufen oder Personen gleichzusetzen.</p></section>';
ifisNumbersFooter();
