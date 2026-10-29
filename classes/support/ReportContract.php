<?php
namespace MyUnaSupportV1;

/** Versioned, credential-free diagnostic contract; shared unchanged by client modules. */
final class ReportContract
{
    public const MAX_BYTES=32768;
    public const MESSAGES=[
        'invalid_fragment'=>'The translation service returned an invalid text fragment.',
        'protected_content'=>'The translation changed protected placeholders, links, or markup.',
        'service_connection'=>'The translation service could not be reached.',
        'rate_limited'=>'The translation service rate limited the request.',
        'operation_failed'=>'The module could not complete the operation.',
    ];
    public static function uuid(): string
    {
        $b=random_bytes(16); $b[6]=chr((ord($b[6])&15)|64); $b[8]=chr((ord($b[8])&63)|128);
        $s=bin2hex($b); return substr($s,0,8).'-'.substr($s,8,4).'-'.substr($s,12,4).'-'.substr($s,16,4).'-'.substr($s,20);
    }
    public static function code(string $message): string
    {
        if ($message==='LibreTranslate returned an invalid text fragment.') return 'invalid_fragment';
        if (in_array($message,['Translation introduced or omitted a placeholder, link, or markup.','Translation reordered protected markup or format arguments.','Translation moved text across protected markup.'],true)) return 'protected_content';
        if ($message==='LibreTranslate connection failed. Check the service before resuming.') return 'service_connection';
        if (str_starts_with($message,'LibreTranslate rate limited this batch.') || $message==='LibreTranslate is busy or rate limited. The job is paused; resume later.') return 'rate_limited';
        return 'operation_failed';
    }
    private static function keys(array $data,array $required,array $optional=[]): void
    {
        if (array_diff($required,array_keys($data)) || array_diff(array_keys($data),array_merge($required,$optional))) throw new \InvalidArgumentException('Unexpected or missing report fields.');
    }
    private static function text($value,int $max,bool $empty=false): string
    {
        if (!is_string($value) || !preg_match('//u',$value) || strlen($value)>$max || (!$empty && trim($value)==='') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)) throw new \InvalidArgumentException('Invalid report text.');
        return $value;
    }
    public static function diagnostics(array $d): array
    {
        self::keys($d,['module','module_version','una_version','php_version','site_host','incident_at','operation','error_code','error_message'],['module_title','job_id','target','state','total','ready','applied','conflicts','provider','language_key']);
        foreach($d as $k=>$v) {
            if (in_array($k,['job_id','total','ready','applied','conflicts'],true)) {
                if (!is_int($v) || $v<0 || $v>2147483647) throw new \InvalidArgumentException('Invalid diagnostic count.');
            } else self::text($v,in_array($k,['error_message','module_title'],true)?255:255);
        }
        if (!preg_match('/\A[a-z][a-z0-9_]{1,63}\z/D',$d['module'])) throw new \InvalidArgumentException('Invalid module.');
        foreach(['module_version','una_version','php_version'] as $k) if(!preg_match('/\A[A-Za-z0-9.+_-]{1,64}\z/D',$d[$k])) throw new \InvalidArgumentException('Invalid version.');
        if (!preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D',$d['site_host'])) throw new \InvalidArgumentException('Use a hostname only.');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$d['incident_at'],new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s\Z')!==$d['incident_at']) throw new \InvalidArgumentException('Invalid incident time.');
        if (!in_array($d['operation'],['generation','apply','rollback','provider','support'],true)) throw new \InvalidArgumentException('Invalid operation.');
        if (!isset(self::MESSAGES[$d['error_code']]) || self::MESSAGES[$d['error_code']]!==$d['error_message']) throw new \InvalidArgumentException('Use a safe diagnostic message.');
        if(isset($d['target']) && !preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})?\z/D',$d['target'])) throw new \InvalidArgumentException('Invalid language.');
        if(isset($d['state']) && !preg_match('/\A[a-z_]{1,32}\z/D',$d['state'])) throw new \InvalidArgumentException('Invalid job state.');
        if(isset($d['provider']) && !in_array($d['provider'],['libre','deepl','unknown'],true)) throw new \InvalidArgumentException('Invalid provider.');
        if(isset($d['language_key']) && !preg_match('/\A[_A-Za-z0-9 .:-]{1,255}\z/D',$d['language_key'])) throw new \InvalidArgumentException('Invalid language key.');
        ksort($d); return $d;
    }
    public static function validate(array $r): array
    {
        self::keys($r,['schema','submission_id','email','summary','description','diagnostics']);
        if($r['schema']!==1 || !is_string($r['submission_id']) || !preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$r['submission_id'])) throw new \InvalidArgumentException('Invalid report identity.');
        $r['email']=strtolower(trim(self::text($r['email'],254)));
        if(!filter_var($r['email'],FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid contact email is required.');
        $r['summary']=trim(self::text($r['summary'],255)); $r['description']=trim(self::text($r['description'],16384));
        if(!is_array($r['diagnostics'])) throw new \InvalidArgumentException('Invalid diagnostics.');
        $r['diagnostics']=self::diagnostics($r['diagnostics']); ksort($r);
        if(strlen(json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))>self::MAX_BYTES) throw new \InvalidArgumentException('Report exceeds 32 KiB.');
        return $r;
    }
    public static function encode(array $r): string { return json_encode(self::validate($r),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
}
