<?php
/** Integration check against a real stalled loopback HTTP server; requires cURL. */
define('BX_DOL', true);
require dirname(__DIR__).'/classes/MyUnaTranslatorText.php';
require dirname(__DIR__).'/classes/MyUnaTranslatorLibreProvider.php';
if (!function_exists('curl_init')) { fwrite(STDERR,"The cURL extension is required.\n"); exit(1); }
function slowProvider($response) {
    $child= <<<'PHP'
$server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if (!$server) exit(2);
echo stream_socket_get_name($server,false)."\n"; flush();
$client=stream_socket_accept($server,10);
if (!$client) exit(3);
$length=0;
while (($line=fgets($client))!==false && trim($line)!=='') {
    if (stripos($line,'Content-Length:')===0) $length=(int)trim(substr($line,15));
}
while ($length>0) { $part=fread($client,$length); if($part==='')break; $length-=strlen($part); }
sleep(6);
$body=base64_decode($argv[1]);
@fwrite($client,"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
fclose($client); fclose($server);
PHP;
    $process=proc_open([PHP_BINARY,'-r',$child,base64_encode($response)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start test server');
    $address=trim(fgets($pipes[1]));
    if (!preg_match('/^127\.0\.0\.1:\d+$/',$address)) throw new RuntimeException('Test server did not start');
    return [new MyUnaTranslatorLibreProvider('http://'.$address),$process,$pipes];
}
function finishServer($process,$pipes) { foreach($pipes as $pipe)fclose($pipe); return proc_close($process); }
[$provider,$process,$pipes]=slowProvider('[]'); $start=microtime(true); $failed=false;
try { $provider->languages(); } catch(RuntimeException $e) { $failed=true; }
$elapsed=microtime(true)-$start; finishServer($process,$pipes);
if (!$failed || $elapsed<4.5 || $elapsed>6.5) throw new RuntimeException('Discovery timeout failed: '.$elapsed);
echo 'PASS stalled language discovery stops after '.round($elapsed,2)." seconds\n";
[$provider,$process,$pipes]=slowProvider(json_encode(['translatedText'=>['مرحبا']])); $start=microtime(true);
$result=$provider->translate(['Hello'],'AR'); $elapsed=microtime(true)-$start; finishServer($process,$pipes);
if ($result[0]['text']!=='مرحبا' || $elapsed<5.5) throw new RuntimeException('Translation timeout was shortened');
echo "PASS slower translations retain their separate request budget\n";
