<?php

function google_oauth_redirect_uri(array $config): string
{
    $configuredUri = trim((string)($config['redirect_uri'] ?? ''));
    if ($configuredUri !== '' && strpos($configuredUri, 'YOUR_') !== 0) {
        return $configuredUri;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . BASE_URL . '/google_callback.php';
}
