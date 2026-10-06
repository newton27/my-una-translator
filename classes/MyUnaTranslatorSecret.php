<?php defined('BX_DOL') or die('hack attempt');

final class MyUnaTranslatorSecret
{
    private static function key($secret)
    {
        if (!is_string($secret) || $secret === '')
            throw new RuntimeException('The UNA site secret is unavailable.');
        return hash('sha256', 'My UNA Translator credential v1|' . $secret, true);
    }

    public static function seal($value, $secret)
    {
        $iv = random_bytes(12); $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', self::key($secret), OPENSSL_RAW_DATA, $iv, $tag, 'my_una_translator', 16);
        if ($encrypted === false) throw new RuntimeException('Unable to encrypt the provider credential.');
        return 'v1:' . base64_encode($iv . $tag . $encrypted);
    }

    public static function open($sealed, $secret)
    {
        $raw = strpos($sealed, 'v1:') === 0 ? base64_decode(substr($sealed, 3), true) : false;
        if ($raw === false || strlen($raw) < 29)
            throw new RuntimeException('Saved provider credential is invalid.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key($secret), OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), 'my_una_translator');
        if ($plain === false) throw new RuntimeException('Saved provider credential could not be decrypted for this site.');
        return $plain;
    }
}
