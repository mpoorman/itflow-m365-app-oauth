<?php

// Shared mail authentication helpers


if (!defined('MICROSOFT_MAIL_APP_OAUTH_SCOPE')) {
    define('MICROSOFT_MAIL_APP_OAUTH_SCOPE', 'https://outlook.office365.com/.default');
}

function mailOauthTokenExpired(?string $expires_at, int $leeway_seconds = 60): bool {
    if (empty($expires_at)) {
        return true;
    }

    $expires_timestamp = strtotime($expires_at);
    if ($expires_timestamp === false) {
        return true;
    }

    return ($expires_timestamp - $leeway_seconds) <= time();
}

function microsoftMailApplicationOauthFailure(array $response): string {
    $details = [];
    $http_code = intval($response['code'] ?? 0);

    if ($http_code > 0) {
        $details[] = "HTTP $http_code";
    }

    $response_body = json_decode((string)($response['body'] ?? ''), true);
    if (is_array($response_body) && !empty($response_body['error'])) {
        $provider_error = preg_replace('/[^A-Za-z0-9._-]/', '', substr((string)$response_body['error'], 0, 100));
        if (!empty($provider_error)) {
            $details[] = $provider_error;
        }
    }

    if (empty($details) && !empty($response['err'])) {
        $curl_error = preg_replace('/[^A-Za-z0-9 .,:_\/-]/', '', substr((string)$response['err'], 0, 150));
        if (!empty($curl_error)) {
            $details[] = $curl_error;
        }
    }

    return 'Microsoft application OAuth token request failed'
        . (!empty($details) ? ' (' . implode(', ', $details) . ')' : '') . '.';
}

/**
 * Return a cached Microsoft application token or acquire one with client credentials.
 *
 * The returned error is deliberately limited to HTTP/provider error identifiers. The
 * client secret, access token, Authorization data, and raw response are never logged.
 */
function getMicrosoftMailApplicationAccessToken(bool $force_refresh = false): array {
    global $mysqli,
           $config_mail_oauth_app_tenant_id,
           $config_mail_oauth_app_client_id,
           $config_mail_oauth_app_client_secret,
           $config_mail_oauth_app_access_token,
           $config_mail_oauth_app_access_token_expires_at;

    if (!$force_refresh
        && !empty($config_mail_oauth_app_access_token)
        && !mailOauthTokenExpired($config_mail_oauth_app_access_token_expires_at)
    ) {
        return [
            'ok' => true,
            'access_token' => $config_mail_oauth_app_access_token,
            'expires_at' => $config_mail_oauth_app_access_token_expires_at,
            'cached' => true,
            'error' => '',
        ];
    }

    if (empty($config_mail_oauth_app_tenant_id)
        || empty($config_mail_oauth_app_client_id)
        || empty($config_mail_oauth_app_client_secret)
    ) {
        $error = 'Microsoft application OAuth token request failed: tenant ID, client ID, or client secret is missing.';
        logApp('Mail-OAuth', 'error', $error);

        return ['ok' => false, 'access_token' => '', 'expires_at' => '', 'cached' => false, 'error' => $error];
    }

    $token_url = 'https://login.microsoftonline.com/'
        . rawurlencode($config_mail_oauth_app_tenant_id)
        . '/oauth2/v2.0/token';

    $response = httpFormPost($token_url, [
        'client_id' => $config_mail_oauth_app_client_id,
        'client_secret' => $config_mail_oauth_app_client_secret,
        'grant_type' => 'client_credentials',
        'scope' => MICROSOFT_MAIL_APP_OAUTH_SCOPE,
    ]);

    if (empty($response['ok'])) {
        $error = microsoftMailApplicationOauthFailure($response);
        logApp('Mail-OAuth', 'error', $error);

        return ['ok' => false, 'access_token' => '', 'expires_at' => '', 'cached' => false, 'error' => $error];
    }

    $response_body = json_decode((string)$response['body'], true);
    if (!is_array($response_body) || empty($response_body['access_token'])) {
        $error = 'Microsoft application OAuth token request failed: access token missing in provider response.';
        logApp('Mail-OAuth', 'error', $error);

        return ['ok' => false, 'access_token' => '', 'expires_at' => '', 'cached' => false, 'error' => $error];
    }

    $access_token = (string)$response_body['access_token'];
    $expires_in = intval($response_body['expires_in'] ?? 3600);
    if ($expires_in <= 0) {
        $expires_in = 3600;
    }
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in);

    $access_token_sql = escapeSql($access_token);
    $expires_at_sql = escapeSql($expires_at);
    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_app_access_token = '$access_token_sql',
        config_mail_oauth_app_access_token_expires_at = '$expires_at_sql'
        WHERE company_id = 1
    ");

    $config_mail_oauth_app_access_token = $access_token;
    $config_mail_oauth_app_access_token_expires_at = $expires_at;

    return [
        'ok' => true,
        'access_token' => $access_token,
        'expires_at' => $expires_at,
        'cached' => false,
        'error' => '',
    ];
}
