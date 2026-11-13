<?php
// Run against a native UNA installation; no translations or settings are written.
$root = $argv[1];
$menuFile = $argv[2];
$_SERVER['HTTP_HOST'] = 'myunaworld.com';
$_SERVER['SERVER_NAME'] = 'myunaworld.com';
$_SERVER['REQUEST_URI'] = '/menu.php?o=sys_switch_language_popup';
$_SERVER['QUERY_STRING'] = 'o=sys_switch_language_popup';
require $root . '/inc/header.inc.php';
require_once $menuFile;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$menu = (new ReflectionClass('MyUnaTranslatorMenuSwitchLanguage'))->newInstanceWithoutConstructor();
$popup = new ReflectionMethod($menu, 'getBaseUrlPopup');
$cases = [
    'missing referrer' => [null, true, [BX_DOL_URL_ROOT, []]],
    'null referrer' => [null, false, [BX_DOL_URL_ROOT, []]],
    'empty referrer' => ['', false, [BX_DOL_URL_ROOT, []]],
    'blank referrer' => ['   ', false, [BX_DOL_URL_ROOT, []]],
    'invalid referrer type' => [[], false, [BX_DOL_URL_ROOT, []]],
    'existing page and query retained' => [
        'https://myunaworld.com/page.php?i=home&lang=es&check=1', false,
        ['https://myunaworld.com/page.php', ['i'=>'home','lang'=>'es','check'=>'1']]
    ],
];
$failed = 0;
foreach ($cases as $name => [$referrer, $missing, $want]) {
    if ($missing) unset($_SERVER['HTTP_REFERER']);
    else $_SERVER['HTTP_REFERER'] = $referrer;
    try {
        $got = $popup->invoke($menu);
        if ($got !== $want) throw new RuntimeException('Unexpected URL or query: ' . json_encode($got));
        echo "PASS $name\n";
    } catch (Throwable $e) {
        ++$failed; echo "FAIL $name: " . $e->getMessage() . "\n";
    }
}
unset($_SERVER['HTTP_REFERER']);
try {
    $inline = new ReflectionMethod($menu, 'getBaseUrlInline');
    if ($inline->invoke($menu) !== ['https://myunaworld.com/menu.php',['o'=>'sys_switch_language_popup']])
        throw new RuntimeException('Inline URL changed');
    echo "PASS inline request URL retained\n";
} catch (Throwable $e) { ++$failed; echo "FAIL inline: " . $e->getMessage() . "\n"; }
restore_error_handler();
echo "RESULT: " . (7-$failed) . "/7 passed\n";
exit($failed ? 1 : 0);
