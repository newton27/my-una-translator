<?php defined('BX_DOL') or die('hack attempt');

final class MyUnaTranslatorProvider
{
    private $key;
    private $host;
    private $transport;

    public function __construct($key, $plan, $transport = null)
    {
        if (!is_string($key) || trim($key) === '' || preg_match('/[\r\n]/', $key))
            throw new RuntimeException('Configure a valid DeepL API key first.');
        if (!in_array($plan, ['free', 'pro'], true)) throw new RuntimeException('Select a DeepL API Free or Pro account.');
        $this->key = trim($key);
        $this->host = $plan === 'free' ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
        $this->transport = $transport;
    }

    public function translate(array $sources, $target)
    {
        if (!in_array($target, ['ES', 'FR', 'DE', 'PT-BR', 'ZH-HANS', 'AR'], true))
            throw new RuntimeException('Unsupported target language.');
        if (!$sources || count($sources) > 50) throw new RuntimeException('A provider batch must contain 1 to 50 keys.');
        $packets = array_map(['MyUnaTranslatorText', 'encodeFragments'], array_values($sources));
        $texts=[]; $indices=[]; $result=[];
        foreach($packets as $i=>$packet) {
            $result[$i]=['text'=>$packet['source'],'billed'=>0];
            if (!$packet['segments']) continue;
            $texts[]=$packet['xml']; $indices[]=$i;
        }
        if (!$texts) return $result;
        $body = json_encode(['text' => $texts, 'source_lang' => 'EN', 'target_lang' => $target,
            'context' => 'UNA is social community software. People sign in, edit profiles, publish posts and send messages. Administrators manage application settings and interface translations. Translation jobs generate previews in batches; paused jobs resume generation and ready translations are applied to stored interface strings. Character ceilings limit the number of text characters translated by the API. Software settings and languages can be enabled or disabled.',
            'tag_handling' => 'xml', 'tag_handling_version' => 'v2', 'outline_detection'=>false, 'splitting_tags'=>['seg'],
            'preserve_formatting' => true, 'split_sentences' => 'nonewlines', 'show_billed_characters' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($body) > 120000) throw new RuntimeException('Translation batch is too large.');
        $data = $this->request('POST', '/v2/translate', $body);
        if (!isset($data['translations']) || !is_array($data['translations']) || count($data['translations']) !== count($texts))
            throw new RuntimeException('Provider returned an incomplete translation batch.');
        foreach ($data['translations'] as $i => $row) {
            if (!is_array($row) || !isset($row['text']) || !is_string($row['text']))
                throw new RuntimeException('Provider returned an invalid translation row.');
            $index=$indices[$i];
            $result[$index] = ['text' => MyUnaTranslatorText::decodeFragments($row['text'], $packets[$index]),
                'billed' => isset($row['billed_characters']) ? max(0, (int)$row['billed_characters']) : $packets[$index]['characters']];
        }
        return $result;
    }

    public function usage()
    {
        $data = $this->request('GET', '/v2/usage');
        if (!isset($data['character_count'], $data['character_limit']) || !is_numeric($data['character_count']) || !is_numeric($data['character_limit']))
            throw new RuntimeException('Provider returned an invalid usage response.');
        return ['used' => (int)$data['character_count'], 'limit' => (int)$data['character_limit'],
            'remaining' => max(0, (int)$data['character_limit'] - (int)$data['character_count'])];
    }

    private function request($method, $path, $body = null)
    {
        $headers = ['Authorization: DeepL-Auth-Key ' . $this->key, 'Content-Type: application/json'];
        if ($this->transport) $response = call_user_func($this->transport, $method, $this->host . $path, $headers, $body);
        else $response = $this->curl($method, $this->host . $path, $headers, $body);
        $status = (int)($response['status'] ?? 0);
        $errors = [403 => 'DeepL rejected the API key or its permissions.', 429 => 'DeepL rate limit reached. Pause and try again later.',
            456 => 'DeepL character quota reached. The job has been paused.'];
        if ($status !== 200) throw new RuntimeException($errors[$status] ?? 'DeepL request failed (HTTP ' . $status . '). No automatic retry was made.');
        try { $data = json_decode($response['body'] ?? '', true, 32, JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('Provider returned invalid JSON.'); }
        if (!is_array($data)) throw new RuntimeException('Provider returned invalid data.');
        return $data;
    }

    private function curl($method, $url, array $headers, $body)
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
        $curl = curl_init($url); $payload = ''; $tooLarge = false;
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$payload, &$tooLarge) {
                if (strlen($payload) + strlen($chunk) > 1048576) { $tooLarge = true; return 0; }
                $payload .= $chunk; return strlen($chunk);
            }]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $ok = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        if ($ok === false) throw new RuntimeException($tooLarge ? 'Provider response exceeded the safe size limit.' : 'Provider connection failed. The request may have been billed; retry only after checking provider usage.');
        return ['status' => $status, 'body' => $payload];
    }
}
