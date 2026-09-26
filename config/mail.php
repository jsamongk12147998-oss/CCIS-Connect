<?php

declare(strict_types=1);

function isMailConfigured(): bool
{
    return trim((string) (getenv('MAIL_HOST') ?: '')) !== ''
        && trim((string) (getenv('MAIL_USERNAME') ?: '')) !== ''
        && (string) (getenv('MAIL_PASSWORD') ?: '') !== ''
        && filter_var(getenv('MAIL_FROM_ADDRESS') ?: '', FILTER_VALIDATE_EMAIL) !== false;
}

function smtpReadResponse($socket): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('The SMTP server closed the connection unexpectedly.');
        }

        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    return $response;
}

function smtpWrite($socket, string $data): void
{
    $offset = 0;
    $length = strlen($data);
    while ($offset < $length) {
        $written = fwrite($socket, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Could not write to the SMTP server.');
        }
        $offset += $written;
    }
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    smtpWrite($socket, $command . "\r\n");
    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed with response: ' . trim($response));
    }

    return $response;
}

function sendMail(string $to, string $subject, string $message, string $fromName = 'CCIS Connect'): bool
{
    $host = trim((string) (getenv('MAIL_HOST') ?: ''));
    $username = (string) (getenv('MAIL_USERNAME') ?: '');
    $password = (string) (getenv('MAIL_PASSWORD') ?: '');
    $fromAddress = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: ''));
    $configuredFromName = (string) (getenv('MAIL_FROM_NAME') ?: $fromName);
    $port = (int) (getenv('MAIL_PORT') ?: 587);

    if (!isMailConfigured()) {
        throw new RuntimeException('SMTP is not fully configured. Set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, and MAIL_FROM_ADDRESS.');
    }

    foreach ([$host, $username, $fromAddress, $to] as $value) {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('SMTP configuration or recipient contains invalid line breaks.');
        }
    }

    if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
        throw new RuntimeException('MAIL_HOST must be a hostname or IPv4 address.');
    }

    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid sender and recipient email address are required.');
    }

    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('MAIL_PORT must be a valid TCP port.');
    }

    $transport = $port === 465 ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        10,
        STREAM_CLIENT_CONNECT
    );

    if ($socket === false) {
        throw new RuntimeException("Could not connect to the configured SMTP server: {$errorMessage} ({$errorNumber}).");
    }

    stream_set_timeout($socket, 10);

    try {
        $banner = smtpReadResponse($socket);
        if ((int) substr($banner, 0, 3) !== 220) {
            throw new RuntimeException('SMTP server did not send a ready response.');
        }

        $clientName = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'localhost') ?: 'localhost';
        smtpCommand($socket, 'EHLO ' . $clientName, [250]);

        if ($port !== 465) {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not establish TLS encryption with the SMTP server.');
            }
            smtpCommand($socket, 'EHLO ' . $clientName, [250]);
        }

        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode($username), [334]);
        smtpCommand($socket, base64_encode($password), [235]);
        smtpCommand($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $subject)) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $configuredFromName)) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $message)), 76, "\r\n");
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $payload = preg_replace('/(?m)^\./', '..', $payload) ?? $payload;

        smtpWrite($socket, $payload . "\r\n.\r\n");
        $deliveryResponse = smtpReadResponse($socket);
        if ((int) substr($deliveryResponse, 0, 3) !== 250) {
            throw new RuntimeException('SMTP server rejected the email: ' . trim($deliveryResponse));
        }

        smtpWrite($socket, "QUIT\r\n");

        return true;
    } finally {
        fclose($socket);
    }
}
