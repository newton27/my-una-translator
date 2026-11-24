<?php
namespace MyUnaSupportV1;
if(!class_exists(ReportContract::class,false)) require_once __DIR__.'/ReportContract.php';
/** Fixed destination, no credentials, redirects, background retries or remote access. */
final class ReportClient
{
    public const ENDPOINT='https://myunaworld.com/modules/newton/my_una_tickets/report.php';
    public const ACCESS_URL='https://myunaworld.com/modules/newton/my_una_tickets/request.php?page=access';
    public const SUPPORT_URL='https://myunaworld.com/modules/newton/my_una_tickets/request.php?page=submit';
    private $transport;
    public function __construct(?callable $transport=null) { $this->transport=$transport; }
    public function send(array $report): array
    {
        $body=ReportContract::encode($report);
        try {
            $r=$this->transport?($this->transport)(self::ENDPOINT,$body):$this->curl($body);
            if(!is_array($r) || !is_string($r['body']??null) || strlen($r['body'])>8192) throw new \RuntimeException('Invalid support response');
            $status=(int)($r['status']??0);
            if($status!==200) {
                $known=in_array($status,[400,405,413,415,422,429],true);
                return ['accepted'=>false,'ambiguous'=>!$known,'message'=>$status===429?'Support report limit reached. Retry in ten minutes or use the support page.':($known?'The report was not accepted. Check the details or use the support page.':'Delivery not confirmed. Retry the same report later.')];
            }
            $d=json_decode($r['body'],true,16,JSON_THROW_ON_ERROR);
            if(!is_array($d) || ($d['accepted']??null)!==true || !is_int($d['ticket_id']??null) || $d['ticket_id']<1 || !in_array($d['receipt']??null,['sent','delayed'],true)) throw new \RuntimeException('Invalid acknowledgment');
            return ['accepted'=>true,'ticket_id'=>$d['ticket_id'],'receipt'=>$d['receipt'],'access_url'=>self::ACCESS_URL];
        } catch(\Throwable $e) { return ['accepted'=>false,'ambiguous'=>true,'message'=>'Delivery not confirmed. Retry the same report later.']; }
    }
    private function curl(string $body): array
    {
        if(!function_exists('curl_init')) throw new \RuntimeException('cURL unavailable');
        $response='';$tooLarge=false;$ch=curl_init(self::ENDPOINT);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RETURNTRANSFER=>false,CURLOPT_WRITEFUNCTION=>static function($c,$part)use(&$response,&$tooLarge){if(strlen($response)+strlen($part)>8192){$tooLarge=true;return 0;}$response.=$part;return strlen($part);}]);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($ok===false || $tooLarge) throw new \RuntimeException('Support delivery not confirmed');
        return ['status'=>$status,'body'=>$response];
    }
}
