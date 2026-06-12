<?php
// Google OAuth2 config

if (!function_exists('google_oauth_env')) {
    function google_oauth_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        $envPath = __DIR__ . '/../.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return $default;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$envKey, $envValue] = array_map('trim', explode('=', $line, 2));
            if ($envKey !== $key) {
                continue;
            }

            return trim($envValue, "\"'");
        }

        return $default;
    }
}

return [
    'client_id' => google_oauth_env('GOOGLE_CLIENT_ID'),
    'client_secret' => google_oauth_env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => google_oauth_env('GOOGLE_REDIRECT_URI'),
    'scope' => 'email profile',
];
