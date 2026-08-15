<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Saves and tests return to the tab they were submitted from (see settings_mail.php)
$mail_tabs = ['smtp', 'imap', 'oauth', 'from', 'tests'];
$mail_tab_redirect = 'settings_mail.php';
if (isset($_POST['tab']) && in_array($_POST['tab'], $mail_tabs, true)) {
    $mail_tab_redirect .= '?tab=' . $_POST['tab'];
}

if (!defined('MICROSOFT_OAUTH_BASE_URL')) {
    define('MICROSOFT_OAUTH_BASE_URL', 'https://login.microsoftonline.com/');
}

if (isset($_POST['oauth_connect_microsoft_mail'])) {

    validateCSRFToken();

    // Save the OAuth credential fields from this form so the auth flow uses the latest inputs
    $config_mail_oauth_client_id     = escapeSql($_POST['config_mail_oauth_client_id'] ?? $config_mail_oauth_client_id);
    $oauth_client_secret_input       = trim((string)($_POST['config_mail_oauth_client_secret'] ?? ''));
    $config_mail_oauth_client_secret = escapeSql($oauth_client_secret_input !== '' ? $oauth_client_secret_input : $config_mail_oauth_client_secret);
    $config_mail_oauth_tenant_id     = escapeSql($_POST['config_mail_oauth_tenant_id'] ?? $config_mail_oauth_tenant_id);
    $oauth_refresh_token_input       = trim((string)($_POST['config_mail_oauth_refresh_token'] ?? ''));
    $oauth_access_token_input        = trim((string)($_POST['config_mail_oauth_access_token'] ?? ''));
    $config_mail_oauth_refresh_token = escapeSql($oauth_refresh_token_input !== '' ? $oauth_refresh_token_input : $config_mail_oauth_refresh_token);
    $config_mail_oauth_access_token  = escapeSql($oauth_access_token_input !== '' ? $oauth_access_token_input : $config_mail_oauth_access_token);

    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_client_id     = '$config_mail_oauth_client_id',
        config_mail_oauth_client_secret = '$config_mail_oauth_client_secret',
        config_mail_oauth_tenant_id     = '$config_mail_oauth_tenant_id',
        config_mail_oauth_refresh_token = '$config_mail_oauth_refresh_token',
        config_mail_oauth_access_token  = '$config_mail_oauth_access_token'
        WHERE company_id = 1
    ");

    // Check the SAVED providers (loaded from config at bootstrap), not $_POST —
    // the provider dropdowns live in different forms and are never posted here
    if ($config_imap_provider !== 'microsoft_oauth' && $config_smtp_provider !== 'microsoft_oauth') {
        flashAlert("Please set the SMTP or IMAP Provider to Microsoft 365 (OAuth) and save it before connecting.", 'error');
        redirect($mail_tab_redirect);
    }

    if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_tenant_id)) {
        flashAlert("Missing Microsoft OAuth settings. Please provide Client ID, Client Secret, and Tenant ID first.", 'error');
        redirect($mail_tab_redirect);
    }

    if (defined('BASE_URL') && !empty(BASE_URL)) {
        $base_url = rtrim((string) BASE_URL, '/');
    } else {
        $base_url = 'https://' . rtrim((string) $config_base_url, '/');
    }

    $redirect_uri = $base_url . '/admin/oauth_microsoft_mail_callback.php';

    try {
        $state = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $state = sha1(uniqid((string) mt_rand(), true));
    }

    $_SESSION['mail_oauth_state'] = $state;
    $_SESSION['mail_oauth_state_expires_at'] = time() + 600;

    $scope = 'offline_access openid profile https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send';

    $authorize_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($config_mail_oauth_tenant_id) . '/oauth2/v2.0/authorize?'
        . http_build_query([
            'client_id' => $config_mail_oauth_client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'response_mode' => 'query',
            'scope' => $scope,
            'state' => $state,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

    logAudit("Settings", "Edit", "$session_name started Microsoft OAuth connect flow for mail settings");

    redirect($authorize_url);
}

if (isset($_POST['edit_mail_smtp_settings'])) {

    validateCSRFToken();

    $smtp_provider_input = (string)($_POST['config_smtp_provider'] ?? '');
    $allowed_smtp_providers = ['', 'standard_smtp', 'google_oauth', 'microsoft_oauth', 'microsoft_app_oauth'];
    if (!in_array($smtp_provider_input, $allowed_smtp_providers, true)) {
        flashAlert("SMTP settings update failed: unsupported provider.", 'error');
        redirect($mail_tab_redirect);
    }

    $config_smtp_provider   = escapeSql($smtp_provider_input);
    $config_smtp_host       = escapeSql($_POST['config_smtp_host'] ?? $config_smtp_host);
    $config_smtp_port       = intval($_POST['config_smtp_port'] ?? $config_smtp_port);
    $config_smtp_encryption = escapeSql($_POST['config_smtp_encryption'] ?? $config_smtp_encryption);
    $smtp_username_input    = trim((string)($_POST['config_smtp_username'] ?? $config_smtp_username));
    $config_smtp_username   = escapeSql($smtp_username_input);
    $config_smtp_password   = escapeSql($_POST['config_smtp_password'] ?? $config_smtp_password);

    if ($smtp_provider_input === 'microsoft_app_oauth'
        && (strlen($smtp_username_input) > 200 || !filter_var($smtp_username_input, FILTER_VALIDATE_EMAIL))
    ) {
        flashAlert("SMTP settings update failed: Microsoft Application OAuth requires a valid mailbox email.", 'error');
        redirect($mail_tab_redirect);
    }

    // The host/port/encryption/password inputs are hidden and disabled for OAuth
    // providers, so they never post and the ?? fallbacks above keep whatever the
    // install used before. Clear them instead: the endpoint is fixed by provider,
    // and the stored values would otherwise be both wrong and unreachable from
    // the settings page. The mailbox password is no longer used either.
    if (in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth', 'microsoft_app_oauth'], true)) {
        $config_smtp_host       = '';
        $config_smtp_port       = 0;
        $config_smtp_encryption = '';
        $config_smtp_password   = '';
    }

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_smtp_provider              = '$config_smtp_provider',
            config_smtp_host                  = '$config_smtp_host',
            config_smtp_port                  = $config_smtp_port,
            config_smtp_encryption            = '$config_smtp_encryption',
            config_smtp_username              = '$config_smtp_username',
            config_smtp_password              = '$config_smtp_password'
        WHERE company_id = 1
    ");

    logAudit("Settings", "Edit", "$session_name edited SMTP settings");

    flashAlert("SMTP Mail Settings updated");

    redirect($mail_tab_redirect);

}

if (isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken();

    $imap_provider_input = (string)($_POST['config_imap_provider'] ?? '');
    $allowed_imap_providers = ['', 'standard_imap', 'google_oauth', 'microsoft_oauth', 'microsoft_app_oauth'];
    if (!in_array($imap_provider_input, $allowed_imap_providers, true)) {
        flashAlert("IMAP settings update failed: unsupported provider.", 'error');
        redirect($mail_tab_redirect);
    }

    $config_imap_provider   = escapeSql($imap_provider_input);
    $config_imap_host       = escapeSql($_POST['config_imap_host'] ?? $config_imap_host);
    $config_imap_port       = intval($_POST['config_imap_port'] ?? $config_imap_port);
    $config_imap_encryption = escapeSql($_POST['config_imap_encryption'] ?? $config_imap_encryption);
    $imap_username_input    = trim((string)($_POST['config_imap_username'] ?? $config_imap_username));
    $config_imap_username   = escapeSql($imap_username_input);
    $config_imap_password   = escapeSql($_POST['config_imap_password'] ?? $config_imap_password);

    if ($imap_provider_input === 'microsoft_app_oauth'
        && (strlen($imap_username_input) > 200 || !filter_var($imap_username_input, FILTER_VALIDATE_EMAIL))
    ) {
        flashAlert("IMAP settings update failed: Microsoft Application OAuth requires a valid mailbox email.", 'error');
        redirect($mail_tab_redirect);
    }

    // Same as the SMTP handler above - the connection fields are hidden for OAuth
    // providers and never post, so drop the leftovers rather than carrying them.
    if (in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth', 'microsoft_app_oauth'], true)) {
        $config_imap_host       = '';
        $config_imap_port       = 0;
        $config_imap_encryption = '';
        $config_imap_password   = '';
    }

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_imap_provider              = '$config_imap_provider',
            config_imap_host                  = '$config_imap_host',
            config_imap_port                  = $config_imap_port,
            config_imap_encryption            = '$config_imap_encryption',
            config_imap_username              = '$config_imap_username',
            config_imap_password              = '$config_imap_password'
        WHERE company_id = 1
    ");

    logAudit("Settings", "Edit", "$session_name edited IMAP settings");

    flashAlert("IMAP Mail Settings updated");

    redirect($mail_tab_redirect);

}

if (isset($_POST['edit_mail_oauth_settings'])) {

    validateCSRFToken();

    $oauth_client_secret_input = trim((string)($_POST['config_mail_oauth_client_secret'] ?? ''));
    $oauth_refresh_token_input = trim((string)($_POST['config_mail_oauth_refresh_token'] ?? ''));
    $oauth_access_token_input = trim((string)($_POST['config_mail_oauth_access_token'] ?? ''));

    $config_mail_oauth_client_id     = escapeSql($_POST['config_mail_oauth_client_id'] ?? $config_mail_oauth_client_id);
    $config_mail_oauth_client_secret = escapeSql($oauth_client_secret_input !== '' ? $oauth_client_secret_input : $config_mail_oauth_client_secret);
    $config_mail_oauth_tenant_id     = escapeSql($_POST['config_mail_oauth_tenant_id'] ?? $config_mail_oauth_tenant_id);
    $config_mail_oauth_refresh_token = escapeSql($oauth_refresh_token_input !== '' ? $oauth_refresh_token_input : $config_mail_oauth_refresh_token);
    $config_mail_oauth_access_token  = escapeSql($oauth_access_token_input !== '' ? $oauth_access_token_input : $config_mail_oauth_access_token);

    $app_tenant_id_input = substr(trim((string)($_POST['config_mail_oauth_app_tenant_id'] ?? $config_mail_oauth_app_tenant_id)), 0, 255);
    $app_client_id_input = substr(trim((string)($_POST['config_mail_oauth_app_client_id'] ?? $config_mail_oauth_app_client_id)), 0, 255);
    $app_client_secret_input = substr(trim((string)($_POST['config_mail_oauth_app_client_secret'] ?? '')), 0, 255);
    $app_client_secret = $app_client_secret_input !== '' ? $app_client_secret_input : $config_mail_oauth_app_client_secret;

    $app_credentials_changed = $app_tenant_id_input !== (string)$config_mail_oauth_app_tenant_id
        || $app_client_id_input !== (string)$config_mail_oauth_app_client_id
        || $app_client_secret !== (string)$config_mail_oauth_app_client_secret;

    $config_mail_oauth_app_tenant_id = escapeSql($app_tenant_id_input);
    $config_mail_oauth_app_client_id = escapeSql($app_client_id_input);
    $config_mail_oauth_app_client_secret = escapeSql($app_client_secret);

    $app_token_cache_sql = '';
    if ($app_credentials_changed) {
        $app_token_cache_sql = ",
        config_mail_oauth_app_access_token = NULL,
        config_mail_oauth_app_access_token_expires_at = NULL";
    }

    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_client_id     = '$config_mail_oauth_client_id',
        config_mail_oauth_client_secret = '$config_mail_oauth_client_secret',
        config_mail_oauth_tenant_id     = '$config_mail_oauth_tenant_id',
        config_mail_oauth_refresh_token = '$config_mail_oauth_refresh_token',
        config_mail_oauth_access_token  = '$config_mail_oauth_access_token',
        config_mail_oauth_app_tenant_id = '$config_mail_oauth_app_tenant_id',
        config_mail_oauth_app_client_id = '$config_mail_oauth_app_client_id',
        config_mail_oauth_app_client_secret = '$config_mail_oauth_app_client_secret'
        $app_token_cache_sql
        WHERE company_id = 1
    ");

    logAudit("Settings", "Edit", "$session_name edited mail OAuth settings");
    flashAlert("Mail OAuth Settings updated");
    redirect($mail_tab_redirect);
}

if (isset($_POST['edit_mail_from_settings'])) {

    validateCSRFToken();

    $config_mail_from_email = escapeSql(filter_var($_POST['config_mail_from_email'], FILTER_VALIDATE_EMAIL));
    $config_mail_from_name = escapeSql(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_mail_from_name']));

    $config_invoice_from_email = escapeSql(filter_var($_POST['config_invoice_from_email'], FILTER_VALIDATE_EMAIL));
    $config_invoice_from_name = escapeSql(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_invoice_from_name']));

    $config_quote_from_email = escapeSql(filter_var($_POST['config_quote_from_email'], FILTER_VALIDATE_EMAIL));
    $config_quote_from_name = escapeSql(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_quote_from_name']));

    $config_ticket_from_email = escapeSql(filter_var($_POST['config_ticket_from_email'], FILTER_VALIDATE_EMAIL));
    $config_ticket_from_name = escapeSql(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_ticket_from_name']));

    mysqli_query($mysqli,"UPDATE settings SET config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_invoice_from_email = '$config_invoice_from_email', config_invoice_from_name = '$config_invoice_from_name', config_quote_from_email = '$config_quote_from_email', config_quote_from_name = '$config_quote_from_name', config_ticket_from_email = '$config_ticket_from_email', config_ticket_from_name = '$config_ticket_from_name' WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited mail from settings");

    flashAlert("Mail From Settings updated");

    redirect($mail_tab_redirect);

}

if (isset($_POST['test_email_smtp'])) {

    validateCSRFToken();

    $test_email = intval($_POST['test_email']);

    if($test_email == 1) {
        $email_from = escapeSql($config_mail_from_email);
        $email_from_name = escapeSql($config_mail_from_name);
    } elseif ($test_email == 2) {
        $email_from = escapeSql($config_invoice_from_email);
        $email_from_name = escapeSql($config_invoice_from_name);
    } elseif ($test_email == 3) {
        $email_from = escapeSql($config_quote_from_email);
        $email_from_name = escapeSql($config_quote_from_name);
    } else {
        $email_from = escapeSql($config_ticket_from_email);
        $email_from_name = escapeSql($config_ticket_from_name);
    }

    $email_to = escapeSql($_POST['email_to']);
    $subject = "Test email from ITFlow";
    $body = "This is a test email from ITFlow. If you are reading this, it worked!";

    $data = [
        [
            'from' => $email_from,
            'from_name' => $email_from_name,
            'recipient' => $email_to,
            'recipient_name' => 'Chap',
            'subject' => $subject,
            'body' => $body
        ]
    ];

    $mail = addToMailQueue($data);

    if ($mail === true) {
        flashAlert("Test email queued! <a class='text-bold text-light' href='mail_queue.php'>Check Admin > Mail queue</a>");
    } else {
        flashAlert("Failed to add test mail to queue", 'error');
    }

    redirect($mail_tab_redirect);

}

if (isset($_POST['test_email_imap'])) {

    validateCSRFToken();

    $provider = escapeSql($config_imap_provider ?? '');

    $host       = $config_imap_host;
    $port       = (int) $config_imap_port;
    $encryption = strtolower(trim($config_imap_encryption)); // e.g. "ssl", "tls", "none"
    $username   = $config_imap_username;
    $password   = $config_imap_password;

    // Shared OAuth fields
    $config_mail_oauth_client_id               = $config_mail_oauth_client_id ?? '';
    $config_mail_oauth_client_secret           = $config_mail_oauth_client_secret ?? '';
    $config_mail_oauth_tenant_id               = $config_mail_oauth_tenant_id ?? '';
    $config_mail_oauth_refresh_token           = $config_mail_oauth_refresh_token ?? '';
    $config_mail_oauth_access_token            = $config_mail_oauth_access_token ?? '';
    $config_mail_oauth_access_token_expires_at = $config_mail_oauth_access_token_expires_at ?? '';

    $is_oauth = in_array($provider, ['google_oauth', 'microsoft_oauth', 'microsoft_app_oauth'], true);

    // Override, don't default - a leftover standard-IMAP host from before the
    // switch to OAuth would otherwise make this test fail against the old server
    // while the cron parser (which overrides unconditionally) connects fine.
    if ($provider === 'google_oauth') {
        $host       = 'imap.gmail.com';
        $port       = 993;
        $encryption = 'ssl';
    } elseif ($provider === 'microsoft_oauth' || $provider === 'microsoft_app_oauth') {
        $host       = 'outlook.office365.com';
        $port       = 993;
        $encryption = 'ssl';
    }

    if (empty($host) || empty($port) || empty($username)) {
        flashAlert("<strong>IMAP connection failed:</strong> Missing host, port, or username.", 'error');
        redirect($mail_tab_redirect);
    }

    if ($is_oauth) {
        if ($provider === 'microsoft_app_oauth') {
            $token_result = getMicrosoftMailApplicationAccessToken();
            if (empty($token_result['ok']) || empty($token_result['access_token'])) {
                $token_error = escapeHtml($token_result['error'] ?? 'No usable access token was returned.');
                flashAlert("<strong>IMAP application OAuth failed:</strong> $token_error", 'error');
                redirect($mail_tab_redirect);
            }
            $password = $token_result['access_token'];
        } elseif (!empty($config_mail_oauth_access_token) && !mailOauthTokenExpired($config_mail_oauth_access_token_expires_at)) {
            $password = $config_mail_oauth_access_token;
        } else {
            if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_refresh_token)) {
                flashAlert("<strong>IMAP OAuth failed:</strong> Missing OAuth client credentials or refresh token.", 'error');
                redirect($mail_tab_redirect);
            }

            if ($provider === 'google_oauth') {
                $response = httpFormPost('https://oauth2.googleapis.com/token', [
                    'client_id' => $config_mail_oauth_client_id,
                    'client_secret' => $config_mail_oauth_client_secret,
                    'refresh_token' => $config_mail_oauth_refresh_token,
                    'grant_type' => 'refresh_token',
                ]);
            } else {
                if (empty($config_mail_oauth_tenant_id)) {
                    flashAlert("<strong>IMAP OAuth failed:</strong> Microsoft tenant ID is required.", 'error');
                    redirect($mail_tab_redirect);
                }

                $token_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($config_mail_oauth_tenant_id) . "/oauth2/v2.0/token";
                $response = httpFormPost($token_url, [
                    'client_id' => $config_mail_oauth_client_id,
                    'client_secret' => $config_mail_oauth_client_secret,
                    'refresh_token' => $config_mail_oauth_refresh_token,
                    'grant_type' => 'refresh_token',
                ]);
            }

            if (!$response['ok']) {
                flashAlert("<strong>IMAP OAuth failed:</strong> Could not refresh access token.", 'error');
                redirect($mail_tab_redirect);
            }

            $json = json_decode($response['body'], true);
            if (!is_array($json) || empty($json['access_token'])) {
                flashAlert("<strong>IMAP OAuth failed:</strong> Token response did not include an access token.", 'error');
                redirect($mail_tab_redirect);
            }

            $password = $json['access_token'];
            $expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
            $refresh_token_to_save = $json['refresh_token'] ?? null;

            $token_esc = mysqli_real_escape_string($mysqli, $password);
            $expires_at_esc = mysqli_real_escape_string($mysqli, $expires_at);

            $refresh_sql = '';
            if (!empty($refresh_token_to_save)) {
                $refresh_token_esc = mysqli_real_escape_string($mysqli, $refresh_token_to_save);
                $refresh_sql = ", config_mail_oauth_refresh_token = '{$refresh_token_esc}'";
            }

            mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '{$token_esc}', config_mail_oauth_access_token_expires_at = '{$expires_at_esc}'{$refresh_sql} WHERE company_id = 1");
        }
    }

    // Build remote socket (implicit SSL vs plain TCP)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/vendor/autoload.php'; // ImapEngine (composer)

    // Map the stored encryption value to an ImapEngine transport (matches the cron sync)
    $imap_transport = match ($encryption) {
        'ssl'      => 'ssl',       // implicit TLS (993)
        'tls'      => 'starttls',  // STARTTLS upgrade (143) - Webklex semantics
        'starttls' => 'starttls',
        default    => '',          // 'none' / plain TCP
    };

    try {
        // Same ImapEngine client the cron sync uses, so a passing test predicts a
        // working sync. Typed errors instead of raw banners; host validated at save.
        $mailbox = new \DirectoryTree\ImapEngine\Mailbox([
            'host'           => $host,
            'port'           => $port,
            'encryption'     => $imap_transport,
            'validate_cert'  => true,
            'username'       => $username,
            'password'       => $password,          // access token when OAuth
            'authentication' => $is_oauth ? 'oauth' : 'plain',
        ]);

        $mailbox->connect();
        $mailbox->inbox(); // confirm auth + mailbox access, like the sync does

        flashAlert($is_oauth ? "Connected successfully using OAuth" : "Connected successfully");
    } catch (\Throwable $e) {
        flashAlert("<strong>IMAP connection failed.</strong> Check the host, port, encryption, and credentials.", 'error');
    }

    redirect($mail_tab_redirect);
}

if (isset($_POST['test_microsoft_app_oauth_token'])) {

    validateCSRFToken();

    if ($config_smtp_provider !== 'microsoft_app_oauth' && $config_imap_provider !== 'microsoft_app_oauth') {
        flashAlert("Application OAuth token test failed: the provider is not configured for SMTP or IMAP.", 'error');
        redirect($mail_tab_redirect);
    }

    if (empty($config_mail_oauth_app_tenant_id)
        || empty($config_mail_oauth_app_client_id)
        || empty($config_mail_oauth_app_client_secret)
    ) {
        flashAlert("Application OAuth token test failed: tenant ID, client ID, or client secret is missing.", 'error');
        redirect($mail_tab_redirect);
    }

    $token_result = getMicrosoftMailApplicationAccessToken(true);
    if (empty($token_result['ok']) || empty($token_result['access_token'])) {
        $token_error = escapeHtml($token_result['error'] ?? 'No usable access token was returned.');
        flashAlert("Application OAuth token test failed: $token_error", 'error');
        redirect($mail_tab_redirect);
    }

    $expires_at = escapeHtml($token_result['expires_at']);
    logAudit("Settings", "Edit", "$session_name tested Microsoft application OAuth token acquisition for mail settings");
    flashAlert("Application OAuth token request successful. Access token expires at $expires_at.");
    redirect($mail_tab_redirect);
}


if (isset($_POST['test_oauth_token_refresh'])) {

    validateCSRFToken();

    $provider = escapeSql($_POST['oauth_provider'] ?? '');

    if ($provider !== 'google_oauth' && $provider !== 'microsoft_oauth') {
        flashAlert("OAuth token test failed: unsupported provider.", 'error');
        redirect($mail_tab_redirect);
    }

    $oauth_client_id = escapeSql($config_mail_oauth_client_id ?? '');
    $oauth_client_secret = escapeSql($config_mail_oauth_client_secret ?? '');
    $oauth_tenant_id = escapeSql($config_mail_oauth_tenant_id ?? '');
    $oauth_refresh_token = escapeSql($config_mail_oauth_refresh_token ?? '');

    if (empty($oauth_client_id) || empty($oauth_client_secret) || empty($oauth_refresh_token)) {
        flashAlert("OAuth token test failed: missing client ID, client secret, or refresh token.", 'error');
        redirect($mail_tab_redirect);
    }

    if ($provider === 'microsoft_oauth' && empty($oauth_tenant_id)) {
        flashAlert("OAuth token test failed: Microsoft tenant ID is required.", 'error');
        redirect($mail_tab_redirect);
    }

    $token_url = 'https://oauth2.googleapis.com/token';
    if ($provider === 'microsoft_oauth') {
        $token_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($oauth_tenant_id) . "/oauth2/v2.0/token";
    }

    $post_fields = http_build_query([
        'client_id' => $oauth_client_id,
        'client_secret' => $oauth_client_secret,
        'refresh_token' => $oauth_refresh_token,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $raw_body = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw_body === false || $http_code < 200 || $http_code >= 300) {
        $err_msg = !empty($curl_err) ? $curl_err : "HTTP $http_code";
        flashAlert("OAuth token test failed: $err_msg", 'error');
        redirect($mail_tab_redirect);
    }

    $json = json_decode($raw_body, true);

    if (!is_array($json) || empty($json['access_token'])) {
        flashAlert("OAuth token test failed: access token missing in provider response.", 'error');
        redirect($mail_tab_redirect);
    }

    $new_access_token = escapeSql($json['access_token']);
    $new_expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
    $new_refresh_token = !empty($json['refresh_token']) ? escapeSql($json['refresh_token']) : '';

    $new_access_token_esc = mysqli_real_escape_string($mysqli, $new_access_token);
    $new_expires_at_esc = mysqli_real_escape_string($mysqli, $new_expires_at);

    $refresh_sql = '';
    if (!empty($new_refresh_token)) {
        $new_refresh_token_esc = mysqli_real_escape_string($mysqli, $new_refresh_token);
        $refresh_sql = ", config_mail_oauth_refresh_token = '$new_refresh_token_esc'";
    }

    mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '$new_access_token_esc', config_mail_oauth_access_token_expires_at = '$new_expires_at_esc'$refresh_sql WHERE company_id = 1");

    $provider_label = $provider === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace';
    logAudit("Settings", "Edit", "$session_name tested OAuth token refresh for $provider_label mail settings");

    flashAlert("OAuth token refresh successful for $provider_label. Access token expires at $new_expires_at.");
    redirect($mail_tab_redirect);
}
