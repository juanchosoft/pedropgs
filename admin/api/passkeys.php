<?php
// PGS Centrum passkey registration/login endpoint. Session + origin + CSRF checked for every request.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/../classes/DbConection.php';
require_once __DIR__.'/../classes/Util.php';
require_once __DIR__.'/../classes/Usuario.php';
require_once __DIR__.'/../classes/PGSWebAuthn.php';
function pg_error(string $message,int $status=400): void {http_response_code($status);echo json_encode(['ok'=>false,'error'=>$message]);exit;}
function pg_ok(array $data=[]): void {echo json_encode(array_merge(['ok'=>true],$data),JSON_UNESCAPED_SLASHES);exit;}
function pg_active(): int {return (int)($_SESSION['session_user']['id']??0);}
function pg_nonce(): string {return PGSWebAuthn::b64e(random_bytes(32));}
function pg_db(): array {$db=new DbConection();return [$db,$db->openConect()];}
function pg_assert_preconditions(): void {
 if ($_SERVER['REQUEST_METHOD']!=='POST')pg_error('POST required',405);
 if (!isset($_SERVER['CONTENT_TYPE'])||stripos($_SERVER['CONTENT_TYPE'],'application/json')===false)pg_error('JSON required',415);
 $origin=$_SERVER['HTTP_ORIGIN']??'';if($origin!==''&&!hash_equals(PGSWebAuthn::origin(),$origin))pg_error('Invalid origin',403);
}
try{
 pg_assert_preconditions();
 $raw=file_get_contents('php://input');if(strlen($raw)>1048576)pg_error('Request too large',413);
 $input=json_decode($raw,true);if(!is_array($input))pg_error('Malformed JSON');
 $csrf=(string)($input['csrf']??'');if(empty($_SESSION['login_csrf'])||!hash_equals($_SESSION['login_csrf'],$csrf))pg_error('Session expired. Reload the page.',403);
 $op=(string)($input['action']??'');
 if(!in_array($op,['register_begin','register_finish','login_begin','login_finish','status'],true))pg_error('Unknown action');
 [$db,$pdo]=pg_db();$table=$db->getTable('tec_usuario_passkeys');$users=$db->getTable('tec_usuarios');
 if($op==='status'){
  $uid=pg_active();if(!$uid)pg_error('Sign in first',401);
  $q=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE tec_usuario_id=:id AND revoked_at IS NULL");$q->execute([':id'=>$uid]);pg_ok(['registered'=>(int)$q->fetchColumn()>0]);
 }
 if($op==='register_begin'){
  $uid=pg_active();if(!$uid)pg_error('Sign in with password first',401);
  $u=$pdo->prepare("SELECT nickname,nombre FROM $users WHERE id=:id AND habilitado='yes' LIMIT 1");$u->execute([':id'=>$uid]);$user=$u->fetch(PDO::FETCH_ASSOC);if(!$user)pg_error('Account disabled',403);
  $q=$pdo->prepare("SELECT credential_id,user_handle FROM $table WHERE tec_usuario_id=:id AND revoked_at IS NULL");$q->execute([':id'=>$uid]);$existing=$q->fetchAll(PDO::FETCH_ASSOC);
  $handle=$existing ? $existing[0]['user_handle'] : random_bytes(32);$challenge=pg_nonce();
  $_SESSION['pgs_webauthn']=['type'=>'register','challenge'=>$challenge,'expires'=>time()+300,'uid'=>$uid,'handle'=>PGSWebAuthn::b64e($handle)];
  pg_ok(['publicKey'=>['challenge'=>$challenge,'rp'=>['id'=>PGSWebAuthn::rpId(),'name'=>'PGS Centrum'],'user'=>['id'=>PGSWebAuthn::b64e($handle),'name'=>$user['nickname'],'displayName'=>trim($user['nombre'])?:$user['nickname']],'pubKeyCredParams'=>[['type'=>'public-key','alg'=>-7]],'timeout'=>60000,'attestation'=>'none','authenticatorSelection'=>['residentKey'=>'preferred','userVerification'=>'required'],'excludeCredentials'=>array_map(function($r){return ['type'=>'public-key','id'=>PGSWebAuthn::b64e($r['credential_id'])];},$existing)]]);
 }
 if($op==='register_finish'){
  $pending=$_SESSION['pgs_webauthn']??null;unset($_SESSION['pgs_webauthn']);
  if(!is_array($pending)||$pending['type']!=='register'||time()>$pending['expires']||pg_active()!==(int)$pending['uid'])pg_error('Registration expired. Start again.',403);
  $r=$input['credential']['response']??null;$id=(string)($input['credential']['id']??'');if(!is_array($r)||!is_string($r['attestationObject']??null)||!is_string($r['clientDataJSON']??null))pg_error('Invalid credential');
  $rawId=PGSWebAuthn::b64d($id);
  $record=PGSWebAuthn::registration(PGSWebAuthn::b64d($r['attestationObject']),PGSWebAuthn::b64d($r['clientDataJSON']),$pending['challenge'],$rawId);
  $q=$pdo->prepare("INSERT INTO $table (tec_usuario_id,credential_id,public_key_cose,sign_count,user_handle,device_name) VALUES(:uid,:cred,:key,:cnt,:handle,:device)");
  $q->execute([':uid'=>$pending['uid'],':cred'=>$record['credential_id'],':key'=>$record['cose'],':cnt'=>$record['counter'],':handle'=>PGSWebAuthn::b64d($pending['handle']),':device'=>'Passkey']);
  pg_ok(['message'=>'Passkey enabled successfully.']);
 }
 if($op==='login_begin'){
  $nickname=trim((string)($input['nickname']??''));if(strlen($nickname)<3||strlen($nickname)>100)pg_error('Enter your username first');
  $q=$pdo->prepare("SELECT p.credential_id,u.id FROM $table p INNER JOIN $users u ON u.id=p.tec_usuario_id WHERE u.nickname=:nick AND u.habilitado='yes' AND p.revoked_at IS NULL LIMIT 20");$q->execute([':nick'=>$nickname]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
  if(!$rows)pg_error('No passkey registered for this account. Use your password first.');
  $challenge=pg_nonce();$_SESSION['pgs_webauthn']=['type'=>'login','challenge'=>$challenge,'expires'=>time()+300,'uid'=>(int)$rows[0]['id']];
  pg_ok(['publicKey'=>['challenge'=>$challenge,'rpId'=>PGSWebAuthn::rpId(),'userVerification'=>'required','timeout'=>60000,'allowCredentials'=>array_map(function($r){return ['type'=>'public-key','id'=>PGSWebAuthn::b64e($r['credential_id'])];},$rows)]]);
 }
 if($op==='login_finish'){
  $pending=$_SESSION['pgs_webauthn']??null;unset($_SESSION['pgs_webauthn']);
  if(!is_array($pending)||$pending['type']!=='login'||time()>$pending['expires'])pg_error('Sign-in expired. Try again.',403);
  $cred=$input['credential']??[];$r=$cred['response']??[];
  if(!is_array($cred)||!is_array($r)||!is_string($cred['id']??null)||!is_string($r['clientDataJSON']??null)||!is_string($r['authenticatorData']??null)||!is_string($r['signature']??null))pg_error('Invalid passkey response');
  $id=PGSWebAuthn::b64d($cred['id']);
  $q=$pdo->prepare("SELECT p.*,u.habilitado FROM $table p JOIN $users u ON u.id=p.tec_usuario_id WHERE p.credential_id=:cred AND p.tec_usuario_id=:uid AND p.revoked_at IS NULL LIMIT 1");
  $q->execute([':cred'=>$id,':uid'=>$pending['uid']]);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row||$row['habilitado']!=='yes')pg_error('Passkey is not valid',403);
  $newCount=PGSWebAuthn::assertion(PGSWebAuthn::b64d($r['authenticatorData']),PGSWebAuthn::b64d($r['clientDataJSON']),PGSWebAuthn::b64d($r['signature']),$pending['challenge'],$row['public_key_cose'],(int)$row['sign_count']);
  $res=Usuario::loginVerifiedPasskey((int)$pending['uid']);if(empty($res['output']['valid']))pg_error('Unable to load user permissions',403);
  $q=$pdo->prepare("UPDATE $table SET sign_count=:cnt,last_used_at=NOW() WHERE id=:id");$q->execute([':cnt'=>$newCount,':id'=>$row['id']]);
  session_regenerate_id(true);
  $u=$res['output']['response'][0];$u['permisos']=$res['output']['permisos'];$u['config']=$res['output']['config'];$u['telefono_emergencia']=$res['output']['telefono_emergencia'];$u['unidad']=$res['output']['unidad'];$u['unidades']=$res['output']['unidades'];
  $_SESSION['session_user']=$u;
  unset($_SESSION['pgs_webauthn']);
  pg_ok(['redirect'=>'main.php']);
 }
}catch(Throwable $e){error_log('PGS passkey: '.$e->getMessage());pg_error('Passkey request could not be completed. Check the device or server configuration.',400);}
