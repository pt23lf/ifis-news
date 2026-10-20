<?php
declare(strict_types=1);

/** Private, read-only IFIS numbers dashboard helpers. */
function ifisNumbersRequireAuth(): void {
    $secretFile = dirname(__DIR__) . '/storage/.numbers-admin-password';
    $expected = @file_get_contents($secretFile);
    if (!is_string($expected) || trim($expected) === '') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Dashboard ist noch nicht eingerichtet. Bitte das Admin-Passwort auf dem Server setzen.";
        exit;
    }
    $user = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $password = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    if ($user !== 'admin' || !hash_equals(trim($expected), $password)) {
        header('WWW-Authenticate: Basic realm="IFIS Zahlen", charset="UTF-8"');
        header('Cache-Control: no-store');
        http_response_code(401);
        echo 'Authentifizierung erforderlich.';
        exit;
    }
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; img-src \'self\'; base-uri \'none\'; form-action \'self\'; frame-ancestors \'none\'');
}

function ifisNumbersEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ifisNumbersMonth(string $value): string {
    if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $value)) return gmdate('Y-m');
    return $value;
}

function ifisNumbersReadDays(string $month): array {
    $file = dirname(__DIR__) . '/storage/metrics/' . ifisNumbersMonth($month) . '.json';
    $raw = @file_get_contents($file);
    $days = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($days)) return [];
    $valid = [];
    foreach ($days as $date => $events) {
        if (!is_string($date) || strpos($date, ifisNumbersMonth($month) . '-') !== 0 || !is_array($events)) continue;
        $valid[$date] = [];
        foreach ($events as $event => $count) {
            if (!is_string($event) || !preg_match('/^[a-z_]{2,40}$/', $event) || !is_int($count) || $count < 0) continue;
            $valid[$date][$event] = $count;
        }
    }
    ksort($valid);
    return $valid;
}

function ifisNumbersSum(array $days): array {
    $totals = [];
    foreach ($days as $events) {
        foreach ($events as $event => $count) $totals[$event] = ($totals[$event] ?? 0) + $count;
    }
    arsort($totals);
    return $totals;
}

function ifisNumbersLabels(): array {
    return [
        'map_open' => 'Karte geöffnet', 'map_projection' => 'Globus / Karte gewechselt',
        'map_settings' => 'Karteneinstellungen', 'time_range' => 'Zeitraum bedient',
        'filter' => 'Filter bedient', 'saved_view' => 'Gemerkt geöffnet',
        'save_article' => 'Artikel gemerkt', 'hide_article' => 'Artikel ausgeblendet',
        'share_collection' => 'Sammlung teilen', 'share_location' => 'Orts-Sammlung teilen',
        'marker_open' => 'Ort geöffnet', 'marker_hover' => 'Ort mit Maus erkundet',
        'source_links' => 'Verbindungslinien umgestellt'
    ];
}

function ifisNumbersStyle(): string {
    return '<style>
    :root{font-family:system-ui,-apple-system,sans-serif;color-scheme:light dark}
    body{margin:0;background:light-dark(#f6f7f9,#111823);color:light-dark(#18212d,#e9eff5);line-height:1.45}
    main{max-width:1100px;margin:auto;padding:30px 18px 70px}
    nav{display:flex;flex-wrap:wrap;gap:18px;margin:0 0 32px}
    a{color:inherit;text-underline-offset:3px}a[aria-current=page]{font-weight:750}
    h1{font-size:clamp(24px,4vw,36px);margin:0 0 10px}h2{font-size:20px;margin:30px 0 12px}
    p{max-width:76ch}small,.muted{color:light-dark(#526071,#a7b5c7)}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:12px;margin:20px 0}
    .card,.panel{border:1px solid light-dark(#d9e0e8,#344153);background:light-dark(#fff,#1b2634);border-radius:14px;padding:18px}
    .value{font-size:clamp(25px,4vw,35px);font-weight:700;font-variant-numeric:tabular-nums}
    table{border-collapse:collapse;width:100%;font-variant-numeric:tabular-nums}
    th,td{text-align:left;border-bottom:1px solid light-dark(#e5e9ee,#364354);padding:10px 7px}
    td:last-child,th:last-child{text-align:right}
    .bar{height:8px;background:light-dark(#e7edf2,#344153);border-radius:5px;overflow:hidden}
    .bar>span{display:block;background:#d89236;height:100%}
    .scroll{overflow-x:auto}
    .sources{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
    .source-title{font-weight:700}.source-state{font-size:13px;color:light-dark(#637184,#b5c0cf)}
    select,button{font:inherit;padding:8px 11px;border-radius:8px;border:1px solid light-dark(#bbc8d5,#536274);background:transparent;color:inherit}
    </style>';
}

function ifisNumbersHeader(string $title, string $active): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . ifisNumbersEscape($title) . '</title>' . ifisNumbersStyle() . '</head><body><main><nav><a href="numbers.php"' . ($active === 'numbers' ? ' aria-current="page"' : '') . '>IFIS Zahlen</a><a href="metrics.php"' . ($active === 'metrics' ? ' aria-current="page"' : '') . '>IFIS News · Funktionen</a><a href="./">Zurück zu IFIS News</a></nav>';
}

function ifisNumbersFooter(): void {
    echo '<p class="muted"><small>Private IFIS-Statistik · Nur aggregierte Bedienhandlungen. Keine Personen-, Sitzungs- oder Besucherzählung. Zeitbasis: UTC.</small></p></main></body></html>';
}
