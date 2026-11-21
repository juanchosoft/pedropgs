<?php
/** Strict, ES256-only WebAuthn verifier, no external Composer dependency. PHP 7.4+ recommended. */
final class PGSWebAuthn
{
    public static function b64e(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
    public static function b64d(string $v): string {
        if (!preg_match('~^[A-Za-z0-9_-]+={0,2}$~D', $v)) throw new RuntimeException('Invalid base64url');
        $bin = base64_decode(strtr($v, '-_', '+/'), true);
        if ($bin === false) throw new RuntimeException('Bad base64url');
        return $bin;
    }
    public static function rpId(): string {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if (!preg_match('/^[a-z0-9.-]+$/', $host) || strlen($host) > 253) throw new RuntimeException('Invalid host');
        return defined('PGS_PASSKEY_RP_ID') ? PGS_PASSKEY_RP_ID : $host;
    }
    public static function origin(): string {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $rp = self::rpId();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (!$secure && $rp !== 'localhost' && $rp !== '127.0.0.1') throw new RuntimeException('Passkeys require HTTPS');
        return defined('PGS_PASSKEY_ORIGIN') ? PGS_PASSKEY_ORIGIN : ($secure ? 'https://' : 'http://') . $host;
    }
    public static function checkClient(string $raw, string $expectedType, string $challenge): void {
        if (strlen($raw) > 8192) throw new RuntimeException('Invalid client data');
        $json = json_decode($raw, true);
        if (!is_array($json) || ($json['type'] ?? '') !== $expectedType || !isset($json['challenge']) || !hash_equals($challenge, (string)$json['challenge']) || ($json['origin'] ?? '') !== self::origin() || !empty($json['crossOrigin'])) throw new RuntimeException('WebAuthn challenge or origin mismatch');
    }
    public static function checkAuth(string $auth, bool $registration): int {
        if (strlen($auth) < 37) throw new RuntimeException('Invalid authenticator data');
        if (!hash_equals(hash('sha256', self::rpId(), true), substr($auth, 0, 32))) throw new RuntimeException('RP ID mismatch');
        $flags = ord($auth[32]);
        if (!(($flags & 1) && ($flags & 4))) throw new RuntimeException('Device user verification is required');
        if ($registration && !($flags & 0x40)) throw new RuntimeException('Credential missing');
        if (!$registration && ($flags & 0x40)) throw new RuntimeException('Unexpected attested data');
        return unpack('N', substr($auth, 33, 4))[1];
    }
    private static function cbor(string $data, int &$off, int $depth=0) {
        if ($depth > 12 || $off >= strlen($data)) throw new RuntimeException('Invalid CBOR');
        $b=ord($data[$off++]);$major=$b>>5;$extra=$b&31;
        if ($extra<24) $n=$extra;
        elseif ($extra===24){self::need($data,$off,1);$n=ord($data[$off++]);}
        elseif ($extra===25){self::need($data,$off,2);$n=unpack('n',substr($data,$off,2))[1];$off+=2;}
        elseif ($extra===26){self::need($data,$off,4);$n=unpack('N',substr($data,$off,4))[1];$off+=4;}
        elseif ($extra===27){self::need($data,$off,8);$v=unpack('Nhi/Nlo',substr($data,$off,8));$n=$v['hi']*4294967296+$v['lo'];$off+=8;}
        else throw new RuntimeException('Unsupported CBOR length');
        if ($major===0) return $n;
        if ($major===1) return -1-$n;
        if ($major===2||$major===3){if($n>1048576)throw new RuntimeException('CBOR too large');self::need($data,$off,(int)$n);$v=substr($data,$off,(int)$n);$off+=(int)$n;return $v;}
        if ($major===4){if($n>1000)throw new RuntimeException('CBOR too large');$a=[];for($i=0;$i<$n;$i++)$a[]=self::cbor($data,$off,$depth+1);return $a;}
        if ($major===5){if($n>1000)throw new RuntimeException('CBOR too large');$a=[];for($i=0;$i<$n;$i++){ $k=self::cbor($data,$off,$depth+1);if(!is_int($k)&&!is_string($k))throw new RuntimeException('Invalid CBOR key');$a[$k]=self::cbor($data,$off,$depth+1);}return $a;}
        if ($major===7 && $extra===20)return false;
        if ($major===7 && $extra===21)return true;
        if ($major===7 && $extra===22)return null;
        throw new RuntimeException('Unsupported CBOR type');
    }
    private static function need(string $bin,int $off,int $len): void {if ($len<0||strlen($bin)-$off<$len)throw new RuntimeException('Truncated data');}
    private static function der(int $tag,string $body): string {
        $l=strlen($body);$len=$l<128?chr($l):($l<256?"\x81".chr($l):"\x82".pack('n',$l));return chr($tag).$len.$body;
    }
    private static function publicPem(array $key): string {
        if (($key[1]??null)!==2 || ($key[3]??null)!==-7 || ($key[-1]??null)!==1 || !is_string($key[-2]??null) || !is_string($key[-3]??null) || strlen($key[-2])!==32 || strlen($key[-3])!==32)throw new RuntimeException('Only ES256 P-256 passkeys are supported');
        $point="\x04".$key[-2].$key[-3];
        $oidEc="\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01";
        $oidP256="\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";
        $spki=self::der(0x30,self::der(0x30,$oidEc.$oidP256).self::der(0x03,"\x00".$point));
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki),64,"\n")."-----END PUBLIC KEY-----\n";
    }
    public static function registration(string $attObject,string $clientData,string $challenge,string $credentialId): array {
        self::checkClient($clientData,'webauthn.create',$challenge);
        $off=0;$obj=self::cbor($attObject,$off);
        if(!is_array($obj)||($obj['fmt']??'')!=='none'||!empty($obj['attStmt'])||!is_string($obj['authData']??null))throw new RuntimeException('Only none-attested passkeys supported');
        $auth=$obj['authData'];$count=self::checkAuth($auth,true);
        $off=37;self::need($auth,$off,18);$off+=16;$len=unpack('n',substr($auth,$off,2))[1];$off+=2;
        if($len<16||$len>255)throw new RuntimeException('Invalid credential ID');
        self::need($auth,$off,$len);$internalId=substr($auth,$off,$len);$off+=$len;
        if(!hash_equals($internalId,$credentialId))throw new RuntimeException('Credential ID mismatch');
        $coseOffset=$off;$key=self::cbor($auth,$off);if(!is_array($key))throw new RuntimeException('Invalid credential key');
        self::publicPem($key); // validate curve and supported algorithm
        return ['credential_id'=>$internalId,'cose'=>substr($auth,$coseOffset,$off-$coseOffset),'counter'=>$count];
    }
    public static function assertion(string $auth,string $clientData,string $signature,string $challenge,string $cose,int $oldCount): int {
        self::checkClient($clientData,'webauthn.get',$challenge);
        $count=self::checkAuth($auth,false);
        $off=0;$key=self::cbor($cose,$off);if(!is_array($key))throw new RuntimeException('Stored public key invalid');
        $pem=self::publicPem($key);
        if(strlen($signature)<8 || strlen($signature)>512 || openssl_verify($auth.hash('sha256',$clientData,true),$signature,$pem,OPENSSL_ALGO_SHA256)!==1)throw new RuntimeException('Invalid passkey signature');
        if($oldCount>0 && $count>0 && $count<=$oldCount)throw new RuntimeException('Passkey signature counter invalid');
        return max($oldCount,$count);
    }
}
