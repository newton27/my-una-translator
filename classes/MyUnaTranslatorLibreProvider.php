<?php defined('BX_DOL') or die('hack attempt');
require_once __DIR__.'/MyUnaTranslatorLibreTerms.php';

/** Plain-text fragment transport: UNA's protected content stays on the site. */
final class MyUnaTranslatorLibreProvider
{
    private $url;
    private $key;
    private $transport;
    private $useTerminology;
    private const TARGETS=['ES'=>'es','FR'=>'fr','DE'=>'de','PT-BR'=>'pt-BR','ZH-HANS'=>'zh-Hans'];

    public static function endpoint($url)
    {
        if (!is_string($url) || strlen($url)>500 || preg_match('/[\s\x00-\x1f\\\\]/',$url))
            throw new RuntimeException('Enter a valid LibreTranslate base URL.');
        $p=parse_url($url);
        if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) ||
            !in_array($p['scheme']??'', ['http','https'],true) ||
            (($p['scheme']??'')==='http' && $p['host']!=='127.0.0.1') ||
            preg_match('~(?:^|/)\.\.(?:/|$)|%~',$p['path']??''))
            throw new RuntimeException('Use HTTPS for LibreTranslate, or http://127.0.0.1 for a service on this server. Do not include credentials or query parameters.');
        if (isset($p['port']) && ($p['port']<1 || $p['port']>65535)) throw new RuntimeException('Invalid LibreTranslate port.');
        return rtrim($url,'/');
    }
    public function __construct($url,$key='', $transport=null,$useTerminology=true)
    {
        $this->url=self::endpoint($url);
        if (!is_string($key) || strlen($key)>512 || preg_match('/[\r\n\x00]/',$key)) throw new RuntimeException('The LibreTranslate API key is invalid.');
        $this->key=trim($key); $this->transport=$transport; $this->useTerminology=(bool)$useTerminology;
    }
    public function translate(array $sources,$target)
    {
        if (!isset(self::TARGETS[$target])) throw new RuntimeException('Unsupported LibreTranslate target language.');
        if (!$sources || count($sources)>20) throw new RuntimeException('Use 1–20 keys per LibreTranslate batch.');
        $packets=[]; $texts=[]; $positions=[]; $result=[]; $segments=[]; $termCounts=[];
        foreach(array_values($sources) as $i=>$source) {
            if (!is_string($source) || strlen($source)>120000) throw new RuntimeException('LibreTranslate source is too large.');
            $packet=MyUnaTranslatorText::encodeFragments($source,true); $packets[$i]=$packet; $termCounts[$i]=0;
            $result[$i]=['text'=>$source,'billed'=>0,'origin'=>'protected','terminology_fragments'=>0];
            foreach($packet['segments'] as $id=>$text) {
                $term=$this->useTerminology?MyUnaTranslatorLibreTerms::lookup($text,$target):null;
                if ($term!==null) { $segments[$i][$id]=$term; ++$termCounts[$i]; }
                else { $texts[]=$text; $positions[]=[$i,$id]; }
            }
        }
        $translated=[];
        foreach(array_chunk($texts,20) as $chunk) {
            $body=['q'=>$chunk,'source'=>'en','target'=>self::TARGETS[$target],'format'=>'text'];
            if ($this->key!=='') $body['api_key']=$this->key;
            $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if (strlen($json)>120000) throw new RuntimeException('LibreTranslate fragment request is too large.');
            $data=$this->request('POST','/translate',$json);
            $rows=$data['translatedText']??null;
            if (!is_array($rows) || !array_is_list($rows) || count($rows)!==count($chunk)) throw new RuntimeException('LibreTranslate returned an incomplete fragment batch.');
            foreach($rows as $text) {
                if (!is_string($text) || trim($text)==='' || strlen($text)>1048576 || !mb_check_encoding($text,'UTF-8'))
                    throw new RuntimeException('LibreTranslate returned an invalid text fragment.');
                $translated[]=$text;
            }
        }
        foreach($positions as $n=>[$i,$id]) $segments[$i][$id]=$translated[$n];
        foreach($segments as $i=>$rows) {
            $xml='<doc>';
            foreach($rows as $id=>$text) $xml.='<seg id="'.$id.'">'.htmlspecialchars($text,ENT_QUOTES|ENT_XML1,'UTF-8').'</seg>';
            $origin=$termCounts[$i]===0?'libre':($termCounts[$i]===count($packets[$i]['segments'])?'terminology':'mixed');
            $result[$i]=['text'=>MyUnaTranslatorText::decodeFragments($xml.'</doc>',$packets[$i]),'billed'=>$packets[$i]['characters'],'origin'=>$origin,'terminology_fragments'=>$termCounts[$i]];
        }
        return $result;
    }
    public function usage()
    {
        $rows=$this->request('GET','/languages'); $targets=[];
        foreach($rows as $row) if (is_array($row) && ($row['code']??'')==='en') $targets=$row['targets']??[];
        if (!is_array($targets) || array_diff(array_values(self::TARGETS),$targets))
            throw new RuntimeException('LibreTranslate needs English routes to es, fr, de, pt-BR and zh-Hans. Load all five target models with LibreTranslate 1.8.4 or newer.');
        return ['used'=>null,'limit'=>null,'remaining'=>null,'provider'=>'libre','available'=>true];
    }
    private function request($method,$path,$body=null)
    {
        $headers=['Content-Type: application/json'];
        $response=$this->transport ? call_user_func($this->transport,$method,$this->url.$path,$headers,$body) : $this->curl($method,$path,$headers,$body);
        $status=(int)($response['status']??0);
        if ($status!==200) {
            $errors=[403=>'LibreTranslate rejected access. Check its API key and access settings.',429=>'LibreTranslate is busy or rate limited. The job is paused; resume later.'];
            throw new RuntimeException($errors[$status]??'LibreTranslate request failed (HTTP '.$status.'). No automatic retry was made.');
        }
        if (!is_string($response['body']??null) || strlen($response['body'])>1048576) throw new RuntimeException('LibreTranslate response exceeded the safe size limit.');
        try { $data=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR); }
        catch(Throwable $e) { throw new RuntimeException('LibreTranslate returned invalid JSON.'); }
        if (!is_array($data)) throw new RuntimeException('LibreTranslate returned an invalid response.');
        return $data;
    }
    private function curl($method,$path,$headers,$body)
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
        $p=parse_url($this->url); $host=$p['host']; $port=$p['port']??($p['scheme']==='https'?443:80); $resolve=[];
        if ($host!=='127.0.0.1') {
            $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:(gethostbynamel($host)?:[]);
            if (!$ips) throw new RuntimeException('The LibreTranslate host could not be resolved to a public IPv4 address.');
            foreach($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))
                throw new RuntimeException('Use a public HTTPS LibreTranslate host, or exact local loopback HTTP.');
            $resolve=[$host.':'.$port.':'.$ips[0]];
        }
        $response=''; $tooLarge=false; $ch=curl_init($this->url.$path);
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>90,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>$resolve,
            CURLOPT_WRITEFUNCTION=>function($curl,$part)use(&$response,&$tooLarge){if(strlen($response)+strlen($part)>1048576){$tooLarge=true;return 0;}$response.=$part;return strlen($part);}]);
        if ($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $ok=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($ok===false) throw new RuntimeException($tooLarge?'LibreTranslate response exceeded the safe size limit.':'LibreTranslate connection failed. Check the service before resuming.');
        return ['status'=>$status,'body'=>$response];
    }
}
