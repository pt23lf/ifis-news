<?php
declare(strict_types=1);
// php tests/personal-network-smoke.php
require dirname(__DIR__) . '/lib/PersonalNetwork.php';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR,"PDO SQLite required\n");exit(2); }
$users = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$users->exec("CREATE TABLE editorial_users(id INTEGER PRIMARY KEY,role TEXT,status TEXT)");
$users->exec("INSERT INTO editorial_users VALUES(1,'admin','active'),(2,'contributor','active'),(3,'contributor','suspended')");
$network = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$network->exec("CREATE TABLE contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,linkedin_url TEXT NOT NULL DEFAULT '',place TEXT NOT NULL DEFAULT '',lat REAL,lon REAL,topic TEXT NOT NULL DEFAULT '',relation TEXT NOT NULL DEFAULT 'commented',note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,updated_at TEXT NOT NULL,owner_user_id INTEGER)");
$network->exec("CREATE UNIQUE INDEX contacts_owner_linkedin_unique ON contacts(owner_user_id,linkedin_url) WHERE linkedin_url <> ''");
$n = new PersonalNetwork($network,$users);
$one = $n->save(1,['name'=>'A','linkedin_url'=>'https://linkedin.com/in/example','note'=>'private admin note']);
$two = $n->save(2,['name'=>'B','linkedin_url'=>'https://linkedin.com/in/example','note'=>'private contributor note']);
check($one !== $two,'separate contact ids');
check(count($n->contacts(1))===1 && count($n->contacts(2))===1,'owner lists');
check($n->contact(2,$one)===null,'cannot read another owner');
fails(fn()=>$n->save(2,['name'=>'Hacked'], $one),'cannot edit another owner');
check($n->contact(1,$one)['name']==='A','unauthorized edit did not change record');
check(!$n->setCoordinates(2,$one,'',50,10),'cannot geocode another owner');
fails(fn()=>$n->contacts(3),'suspended account cannot read');
fails(fn()=>$n->contacts(99),'unknown account cannot read');
fails(fn()=>$n->save(2,['name'=>'Bad','linkedin_url'=>'http://localhost/']),'non-HTTPS URL rejected');
fails(fn()=>$n->delete(1,$one),'delete disabled until legacy dependencies are migrated');
echo "Personal network isolation smoke tests passed.\n";
function check(bool $v,string $label):void{if(!$v)throw new RuntimeException('FAIL '.$label);}
function fails(callable $cb,string $label):void{
 try{$cb();}catch(RuntimeException|InvalidArgumentException|LogicException $e){return;}
 throw new RuntimeException('FAIL '.$label);
}
