<?php
declare(strict_types=1);
// Run via CLI only: php bin/metrics-summary.php [YYYY-MM]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$month=$argv[1]??gmdate('Y-m');
if (!preg_match('/^20\\d{2}-(0[1-9]|1[0-2])$/',$month)) { fwrite(STDERR,"Invalid month. Use YYYY-MM.\n"); exit(1); }
$file=dirname(__DIR__).'/storage/metrics/'.$month.'.json';
if (!is_file($file)) { echo "No feature events stored for $month yet.\n"; exit; }
$days=json_decode((string)file_get_contents($file),true);
if (!is_array($days)) { fwrite(STDERR,"Metrics format invalid.\n"); exit(1); }
$sum=[];
ksort($days);
foreach($days as $day=>$counts){
 if(!is_array($counts))continue;
 foreach($counts as $event=>$value){ if(!is_numeric($value))continue;$sum[$event]=($sum[$event]??0)+(int)$value; }
}
arsort($sum);
echo "IFIS News feature interactions – $month (UTC)\n";
echo str_repeat('-',48)."\n";
foreach($sum as $name=>$count)printf("%-25s %9d\n",$name,$count);
echo str_repeat('-',48)."\n";
echo "Days with recorded actions: ".count($days)."\n";
echo "Total actions: ".array_sum($sum)."\n";
echo "No unique-user, session or visit counts are inferred.\n";
