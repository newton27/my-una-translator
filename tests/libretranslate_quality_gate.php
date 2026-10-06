<?php

define('BX_DOL', true);
require dirname(__DIR__) . '/classes/MyUnaTranslatorText.php';
require dirname(__DIR__) . '/classes/MyUnaTranslatorLibreTerms.php';
require dirname(__DIR__) . '/classes/MyUnaTranslatorLibreProvider.php';

$cases = [
    'sign in' => [
        'ES' => 'Iniciar sesión', 'FR' => 'Se connecter', 'DE' => 'Anmelden',
        'PT-BR' => 'Entrar', 'ZH-HANS' => '登录',
    ],
    'resume' => [
        'ES' => 'Reanudar', 'FR' => 'Reprendre', 'DE' => 'Fortsetzen',
        'PT-BR' => 'Retomar', 'ZH-HANS' => '继续',
    ],
    'resume generation' => [
        'ES' => 'Reanudar la generación', 'FR' => 'Reprendre la génération',
        'DE' => 'Generierung fortsetzen', 'PT-BR' => 'Retomar a geração',
        'ZH-HANS' => '继续生成',
    ],
    'create a new post' => [
        'ES' => 'Crear una publicación', 'FR' => 'Créer une publication',
        'DE' => 'Neuen Beitrag erstellen', 'PT-BR' => 'Criar uma publicação',
        'ZH-HANS' => '创建新帖子',
    ],
    'roll back changes' => [
        'ES' => 'Revertir cambios', 'FR' => 'Annuler les modifications',
        'DE' => 'Änderungen zurücksetzen', 'PT-BR' => 'Desfazer alterações',
        'ZH-HANS' => '回滚更改',
    ],
    'payment failed. your card was not charged.' => [
        'ES' => 'El pago falló. No se realizó ningún cargo a tu tarjeta.',
        'FR' => 'Le paiement a échoué. Votre carte n’a pas été débitée.',
        'DE' => 'Die Zahlung ist fehlgeschlagen. Ihre Karte wurde nicht belastet.',
        'PT-BR' => 'O pagamento falhou. Seu cartão não foi cobrado.',
        'ZH-HANS' => '付款失败，未向您的银行卡扣款。',
    ],
    'the job is paused because the character limit was reached.' => [
        'ES' => 'El trabajo está en pausa porque se alcanzó el límite de caracteres.',
        'FR' => 'Le travail est en pause, car la limite de caractères a été atteinte.',
        'DE' => 'Der Auftrag wurde pausiert, weil das Zeichenlimit erreicht wurde.',
        'PT-BR' => 'O trabalho foi pausado porque o limite de caracteres foi atingido.',
        'ZH-HANS' => '任务已暂停，因为已达到字符数限制。',
    ],
    'character limit' => [
        'ES' => 'Límite de caracteres', 'FR' => 'Limite de caractères',
        'DE' => 'Zeichenlimit', 'PT-BR' => 'Limite de caracteres',
        'ZH-HANS' => '字符数限制',
    ],
    'translation job' => [
        'ES' => 'Trabajo de traducción', 'FR' => 'Travail de traduction',
        'DE' => 'Übersetzungsauftrag', 'PT-BR' => 'Trabalho de tradução',
        'ZH-HANS' => '翻译任务',
    ],
];

$failed = 0;
foreach ($cases as $source => $translations) {
    foreach ($translations as $target => $expected) {
        $actual = MyUnaTranslatorLibreTerms::lookup($source, $target);
        if ($actual !== $expected) {
            fwrite(STDERR, sprintf("FAIL %s [%s]: expected %s, got %s\n", $source, $target, var_export($expected, true), var_export($actual, true)));
            ++$failed;
        }
    }
}

$requests = 0;
$transport = static function () use (&$requests) { ++$requests; throw new RuntimeException('Reviewed strings should not call the provider.'); };
$provider = new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001', '', $transport, true);
foreach ($cases as $source => $translations) {
    foreach ($translations as $target => $expected) {
        $row = $provider->translate([$source], $target)[0] ?? [];
        if (($row['text'] ?? null) !== $expected || ($row['origin'] ?? null) !== 'terminology') {
            fwrite(STDERR, sprintf("FAIL provider path %s [%s]: %s\n", $source, $target, var_export($row, true)));
            ++$failed;
        }
    }
}
if ($requests !== 0) { fwrite(STDERR, "FAIL: reviewed strings made $requests LibreTranslate requests.\n"); ++$failed; }
if (MyUnaTranslatorLibreTerms::lookup('an unreviewed sentence', 'DE') !== null) {
    fwrite(STDERR, "FAIL: unreviewed sentences must continue through LibreTranslate.\n");
    ++$failed;
}

echo $failed ? "LibreTranslate curated quality gate: FAIL ($failed)\n" : "LibreTranslate curated quality gate: PASS (45 translations verified through provider; unknown text falls through)\n";
exit($failed ? 1 : 0);
