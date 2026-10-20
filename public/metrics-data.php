<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/numbers-dashboard.php';
ifisNumbersRequireAuth();
header('Content-Type: application/json; charset=utf-8');
$month=ifisNumbersMonth((string)($_GET['month']??gmdate('Y-m')));
$days=ifisNumbersReadDays($month);
echo json_encode(['month'=>$month,'days'=>$days,'totals'=>ifisNumbersSum($days)],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
