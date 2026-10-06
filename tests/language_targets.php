<?php
define('BX_DOL', true);
require dirname(__DIR__) . '/classes/MyUnaTranslatorText.php';
require dirname(__DIR__) . '/classes/MyUnaTranslatorEngine.php';
require dirname(__DIR__) . '/classes/MyUnaTranslatorLibreProvider.php';
require dirname(__DIR__) . '/classes/MyUnaTranslatorProvider.php';

function expectSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$languages = MyUnaTranslatorEngine::presets();
expectSame(true, isset($languages['ar']), 'Arabic is available as a job target');
expectSame('Arabic', $languages['ar']['name'] ?? null, 'Arabic has an English label');
expectSame('العربية', $languages['ar']['native'] ?? null, 'Arabic has a native label');
expectSame('AR', $languages['ar']['target'] ?? null, 'Arabic uses the provider target code');

$payload = null;
$transport = static function ($method, $url, $headers, $body) use (&$payload) {
    $payload = json_decode($body, true);
    return ['status'=>200, 'body'=>json_encode(['translatedText'=>array_fill(0, count($payload['q']), 'مرحبا')])];
};
$libre = new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001', '', $transport, true);
$result = $libre->translate(['Hello'], 'AR');
expectSame('ar', $payload['target'] ?? null, 'LibreTranslate receives Arabic target code ar');
expectSame('مرحبا', $result[0]['text'] ?? null, 'Arabic provider response is returned to the job');

$deepL = new MyUnaTranslatorProvider('test-key', 'free');
try {
    $deepL->translate([], 'AR');
    fwrite(STDERR, 'FAIL: DeepL should reject the empty test batch after accepting Arabic' . PHP_EOL);
    exit(1);
} catch (RuntimeException $error) {
    expectSame('A provider batch must contain 1 to 50 keys.', $error->getMessage(), 'DeepL accepts Arabic as a target');
}
echo "PASS: Arabic target is selectable and routed to LibreTranslate\n";
