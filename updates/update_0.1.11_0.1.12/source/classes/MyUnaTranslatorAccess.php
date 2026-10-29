<?php defined('BX_DOL') or die('hack attempt');
final class MyUnaTranslatorAccess
{
    public static function check($method, $admin, $token, $expected)
    {
        if (!$admin) throw new RuntimeException('Administrator access is required.');
        if ($method !== 'POST') throw new RuntimeException('Use a POST request for Translator actions.');
        if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token))
            throw new RuntimeException('Your session token changed. Refresh Studio and try again.');
    }
}

