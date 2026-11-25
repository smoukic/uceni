<?php
declare(strict_types=1);

final class RecoveryMail
{
    public static function send(string $email, string $purpose, string $token): bool
    {
        $path = getenv('UCENI_MAIL_CONFIG') ?: '/home/rulcj/.rulc-contact/contact.ini';
        if (!is_readable($path) || !function_exists('curl_init')) {
            return false;
        }
        $config = @parse_ini_file($path, false, INI_SCANNER_RAW);
        $from = $config['from'] ?? '';
        $url = $config['smtp_url'] ?? '';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !preg_match('~^smtps?://[^/@\s]+/?$~D', $url)
            || empty($config['smtp_user']) || empty($config['smtp_password'])) {
            return false;
        }
        $verify = $purpose === 'verify';
        $subject = $verify ? 'Učení – ověření e-mailu' : 'Učení – obnova hesla';
        $link = 'https://uceni.rulc.eu/' . ($verify ? 'overit-email' : 'nove-heslo') . '#' . $token;
        $body = ($verify ? 'Potvrď e-mail pro svůj účet v Učení.' : 'Na tomto odkazu můžeš do 30 minut nastavit nové heslo.')
            . "\r\n\r\n$link\r\n\r\nPokud jsi o tuto zprávu nežádal/a, můžeš ji ignorovat.";
        $message = "From: <$from>\r\nTo: <$email>\r\nSubject: =?UTF-8?B?" . base64_encode($subject)
            . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body), 76, "\r\n");
        $payload = fopen('php://temp', 'w+b');
        fwrite($payload, $message);
        rewind($payload);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_USERNAME => $config['smtp_user'], CURLOPT_PASSWORD => $config['smtp_password'],
            CURLOPT_USE_SSL => CURLUSESSL_ALL, CURLOPT_MAIL_FROM => "<$from>", CURLOPT_MAIL_RCPT => ["<$email>"],
            CURLOPT_UPLOAD => true, CURLOPT_INFILE => $payload, CURLOPT_INFILESIZE => strlen($message),
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_RETURNTRANSFER => true,
        ]);
        try {
            return curl_exec($curl) !== false;
        } finally {
            fclose($payload);
        }
    }
}
