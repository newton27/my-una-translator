<?php
if (!defined('BX_DOL')) die('hack attempt');

class MyUnaTranslatorKeyClient
{
    private const SOCKET = '/run/my-una-translator/key-manager.sock';

    public static function request(array $payload): array
    {
        $errno = 0;
        $error = '';
        $stream = @stream_socket_client('unix://' . self::SOCKET, $errno, $error, 3, STREAM_CLIENT_CONNECT);
        if (!$stream) throw new RuntimeException('LibreTranslate key management is not installed or available.');
        stream_set_timeout($stream, 5);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || strlen($json) > 16384) { fclose($stream); throw new RuntimeException('Key manager request is invalid.'); }
        $written = fwrite($stream, $json . "
");
        if ($written !== strlen($json) + 1) { fclose($stream); throw new RuntimeException('Key manager request could not be sent.'); }
        $line = fgets($stream, 65537);
        $meta = stream_get_meta_data($stream);
        fclose($stream);
        if (!is_string($line) || !empty($meta['timed_out']) || strlen($line) > 65536) throw new RuntimeException('Key manager did not return a valid response.');
        $response = json_decode($line, true);
        if (!is_array($response) || !array_key_exists('ok', $response)) throw new RuntimeException('Key manager did not return a valid response.');
        if (!$response['ok']) throw new RuntimeException(is_string($response['message'] ?? null) ? $response['message'] : 'Key manager request failed.');
        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }
}
