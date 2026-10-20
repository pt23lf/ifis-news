<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/numbers-dashboard.php';
ifisNumbersRequireAuth();
$month=ifisNumbersMonth((string)($_GET['month']??gmdate('Y-m')));
$days=ifisNumbersReadDays($month);
$totals=ifisNumbersSum($days);
$labels=ifisNumbersLabels();
$events=array_sum($totals);
$activeDays=count(array_filter($days,static function($row){return array_sum($row)>0;}));
ifisNumbersHeader('IFIS News · Funktionsnutzung','metrics');
echo '<h1>IFIS News · Funktionsnutzung</h1><p class="muted">Welche Werkzeuge werden genutzt? Anonyme Zähler, keine Besucherzahlen.</p>';
echo '<form method="get"><label>Monat <input type="month" name="month" value="' . ifisNumbersEscape($month) . '" min="2026-10" max="' . gmdate('Y-m') . '"></label> <button type="submit">Anzeigen</button></form>';
echo '<div class="cards"><div class="card"><div class="muted">Erfasste Aktionen</div><div class="value">' . number_format($events,0,',','.') . '</div></div>';
echo '<div class="card"><div class="muted">Tage mit Aktionen</div><div class="value">' . $activeDays . '</div></div>';
echo '<div class="card"><div class="muted">Funktionsarten genutzt</div><div class="value">' . count($totals) . '</div></div></div>';
if (!$totals) {
    echo '<div class="panel">Noch keine Funktionsaktionen für diesen Monat erfasst. Bitte zuerst die aktualisierte Website aufrufen und Funktionen verwenden.</div>';
} else {
    echo '<section class="panel"><h2>Funktionen nach Häufigkeit</h2><div class="scroll"><table><thead><tr><th>Funktion</th><th style="width:28%">Anteil</th><th>Aktionen</th></tr></thead><tbody>';
    $max=max(1,max($totals));
    foreach($totals as $event=>$count){
        $label=$labels[$event]??$event;
        $width=round($count/$max*100,1);
        echo '<tr><td>'.ifisNumbersEscape($label).'</td><td><div class="bar"><span style="width:'.$width.'%"></span></div></td><td>'.number_format($count,0,',','.').'</td></tr>';
    }
    echo '</tbody></table></div></section>';
    echo '<section class="panel"><h2>Tägliche Aktivität</h2><div class="scroll"><table><thead><tr><th>Tag (UTC)</th><th>Aktionen</th></tr></thead><tbody>';
    foreach(array_reverse($days,true) as $day=>$values){
       echo '<tr><td>'.ifisNumbersEscape($day).'</td><td>'.number_format(array_sum($values),0,',','.').'</td></tr>';
    }
    echo '</tbody></table></div></section>';
}
echo '<p class="muted"><small>Die Werte zählen Klicks und Bedienhandlungen. Wiederholte Nutzung, Browserblockaden und Bots können die Zahlen beeinflussen. Sie sind weder eindeutige Besucher noch belastbare Nutzungsquoten.</small></p>';
ifisNumbersFooter();
