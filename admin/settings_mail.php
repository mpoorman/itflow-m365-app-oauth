<?php
require_once "includes/inc_all_admin.php";

// ---- Tiny status dot for tab labels ----------------------------------------
function renderMailStatusDot($on) {
    return $on
        ? '<i class="fas fa-circle text-success ml-2" style="font-size:.5rem;vertical-align:middle;" title="Configured"></i>'
        : '<i class="far fa-circle text-muted ml-2" style="font-size:.5rem;vertical-align:middle;" title="Not configured"></i>';
}

$smtp_on = !empty($config_smtp_provider);
$imap_on = !empty($config_imap_provider);
$delegated_oauth_providers = ['google_oauth', 'microsoft_oauth'];
$delegated_oauth_needed = in_array($config_smtp_provider, $delegated_oauth_providers, true)
    || in_array($config_imap_provider, $delegated_oauth_providers, true);
$app_oauth_needed = $config_smtp_provider === 'microsoft_app_oauth'
    || $config_imap_provider === 'microsoft_app_oauth';
$oauth_needed = $delegated_oauth_needed || $app_oauth_needed;

// ---- Active tab -------------------------------------------------------------
// The tab lives in the URL (?tab=imap) so it can be linked, bookmarked, survives a
//  reload, and lets the POST handlers send you back to the tab you saved from
$mail_tabs = ['smtp', 'imap', 'oauth', 'from', 'tests'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $mail_tabs, true) ? $_GET['tab'] : 'smtp';

// A direct link to the OAuth tab reveals it even when no OAuth provider is selected yet
if ($active_tab === 'oauth') {
    $oauth_needed = true;
}

// ---- OAuth callback URI (for Entra App Registration) ------------------------
if (defined('BASE_URL') && !empty(BASE_URL)) {
    $mail_oauth_callback_uri = rtrim((string) BASE_URL, '/') . '/admin/oauth_microsoft_mail_callback.php';
} else {
    $mail_oauth_callback_uri = 'https://' . rtrim((string) $config_base_url, '/') . '/admin/oauth_microsoft_mail_callback.php';
}

// ---- Readiness checks (drive the Tests tab) --------------------------------
$smtp_standard_ready = $config_smtp_provider === 'standard_smtp'
    && !empty($config_smtp_host) && !empty($config_smtp_port)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name);

$smtp_delegated_oauth_ready = in_array($config_smtp_provider, $delegated_oauth_providers, true)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_smtp_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$smtp_app_oauth_ready = $config_smtp_provider === 'microsoft_app_oauth'
    && filter_var($config_smtp_username, FILTER_VALIDATE_EMAIL)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name)
    && !empty($config_mail_oauth_app_tenant_id) && !empty($config_mail_oauth_app_client_id)
    && !empty($config_mail_oauth_app_client_secret);

$imap_standard_ready = $config_imap_provider === 'standard_imap'
    && !empty($config_imap_username) && !empty($config_imap_password)
    && !empty($config_imap_host) && !empty($config_imap_port);

$imap_delegated_oauth_ready = in_array($config_imap_provider, $delegated_oauth_providers, true)
    && !empty($config_imap_username)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_imap_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$imap_app_oauth_ready = $config_imap_provider === 'microsoft_app_oauth'
    && filter_var($config_imap_username, FILTER_VALIDATE_EMAIL)
    && !empty($config_mail_oauth_app_tenant_id) && !empty($config_mail_oauth_app_client_id)
    && !empty($config_mail_oauth_app_client_secret);

$oauth_provider_for_test = '';
if (in_array($config_imap_provider, $delegated_oauth_providers, true)) {
    $oauth_provider_for_test = $config_imap_provider;
} elseif (in_array($config_smtp_provider, $delegated_oauth_providers, true)) {
    $oauth_provider_for_test = $config_smtp_provider;
}

$oauth_has_required_fields = !empty($oauth_provider_for_test)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($oauth_provider_for_test !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$app_oauth_has_required_fields = $app_oauth_needed
    && !empty($config_mail_oauth_app_tenant_id) && !empty($config_mail_oauth_app_client_id)
    && !empty($config_mail_oauth_app_client_secret);

$send_ready = $smtp_standard_ready || $smtp_delegated_oauth_ready || $smtp_app_oauth_ready;
$imap_ready = $imap_standard_ready || $imap_delegated_oauth_ready || $imap_app_oauth_ready;
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>Mail Configuration</h3>
    </div>
    <div class="card-body">

        <ul class="nav nav-tabs" id="mailTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'smtp') { echo 'active'; } ?>" href="?tab=smtp" data-target="#tab-smtp">
                    <i class="fas fa-fw fa-paper-plane mr-1"></i>Sending<?= renderMailStatusDot($smtp_on) ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'imap') { echo 'active'; } ?>" href="?tab=imap" data-target="#tab-imap">
                    <i class="fas fa-fw fa-inbox mr-1"></i>Receiving<?= renderMailStatusDot($imap_on) ?>
                </a>
            </li>
            <li class="nav-item" id="tabitem-oauth" style="<?= $oauth_needed ? '' : 'display:none;' ?>">
                <a class="nav-link <?php if ($active_tab === 'oauth') { echo 'active'; } ?>" href="?tab=oauth" data-target="#tab-oauth">
                    <i class="fas fa-fw fa-key mr-1"></i>OAuth
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'from') { echo 'active'; } ?>" href="?tab=from" data-target="#tab-from">
                    <i class="fas fa-fw fa-at mr-1"></i>From Addresses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'tests') { echo 'active'; } ?>" href="?tab=tests" data-target="#tab-tests">
                    <i class="fas fa-fw fa-vial mr-1"></i>Tests
                </a>
            </li>
        </ul>

        <div class="tab-content pt-4">

            <!-- ============================ SENDING / SMTP ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'smtp') { echo 'show active'; } ?>" id="tab-smtp" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="smtp">

                    <div class="form-group">
                        <label>SMTP Provider <small class="text-muted">— outbound</small></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span></div>
                            <select class="form-control" name="config_smtp_provider" id="config_smtp_provider">
                                <option value="" <?php if (empty($config_smtp_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                                <option value="standard_smtp" <?php if ($config_smtp_provider === 'standard_smtp') { echo 'selected'; } ?>>Standard SMTP (Username/Password)</option>
                                <option value="google_oauth" <?php if ($config_smtp_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                                <option value="microsoft_oauth" <?php if ($config_smtp_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (Delegated OAuth)</option>
                                <option value="microsoft_app_oauth" <?php if ($config_smtp_provider === 'microsoft_app_oauth') { echo 'selected'; } ?>>Microsoft 365 (Application OAuth)</option>
                            </select>
                        </div>
                        <small class="form-text text-muted" id="smtp_provider_hint">Choose your outbound mail provider.</small>
                    </div>

                    <div id="smtp_conn_fields">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>SMTP Host</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-server"></i></span></div>
                                    <input type="text" class="form-control" name="config_smtp_host" placeholder="smtp.yourcompany.com" maxlength="200" value="<?= escapeHtml($config_smtp_host) ?>" required>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Port</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span></div>
                                    <input type="text" class="form-control numeric-only" inputmode="numeric" pattern="[0-9]*" maxlength="5" name="config_smtp_port" placeholder="587 / 465 / 25" value="<?= !empty($config_smtp_port) ? intval($config_smtp_port) : '' ?>" required>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Encryption</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span></div>
                                    <select class="form-control" name="config_smtp_encryption">
                                        <option value="">None</option>
                                        <option <?php if ($config_smtp_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                        <option <?php if ($config_smtp_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6" id="smtp_user_group">
                            <label id="smtp_user_label">SMTP Username</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-user"></i></span></div>
                                <input type="text" class="form-control" name="config_smtp_username" id="config_smtp_username" placeholder="usually your full email address" maxlength="200" value="<?= escapeHtml($config_smtp_username) ?>">
                            </div>
                            <small class="form-text text-muted" id="smtp_user_hint">Leave blank if no authentication is required.</small>
                        </div>
                        <div class="form-group col-md-6" id="smtp_pass_group">
                            <label>SMTP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="mailbox or app password" maxlength="200" value="<?= escapeHtml($config_smtp_password) ?>" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="smtp_oauth_pointer" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-fw fa-info-circle mr-2"></i>This provider uses OAuth — the password is ignored. Enter app credentials in the OAuth tab.</span>
                            <button type="button" class="btn btn-sm btn-outline-primary goto-oauth ml-3 text-nowrap"><i class="fas fa-fw fa-key mr-1"></i>Open OAuth</button>
                        </div>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_smtp_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Sending Settings</button>
                </form>
            </div>

            <!-- ============================ RECEIVING / IMAP ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'imap') { echo 'show active'; } ?>" id="tab-imap" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="imap">

                    <div class="form-group">
                        <label>IMAP Provider <small class="text-muted">— inbound ticket inbox</small></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span></div>
                            <select class="form-control" name="config_imap_provider" id="config_imap_provider">
                                <option value="" <?php if (empty($config_imap_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                                <option value="standard_imap" <?php if ($config_imap_provider === 'standard_imap') { echo 'selected'; } ?>>Standard IMAP (Username/Password)</option>
                                <option value="google_oauth" <?php if ($config_imap_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                                <option value="microsoft_oauth" <?php if ($config_imap_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (Delegated OAuth)</option>
                                <option value="microsoft_app_oauth" <?php if ($config_imap_provider === 'microsoft_app_oauth') { echo 'selected'; } ?>>Microsoft 365 (Application OAuth)</option>
                            </select>
                        </div>
                        <small class="form-text text-muted" id="imap_provider_hint">Select your mailbox provider.</small>
                    </div>

                    <div id="imap_conn_fields">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>IMAP Host</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-server"></i></span></div>
                                    <input type="text" class="form-control" name="config_imap_host" placeholder="imap.yourcompany.com" maxlength="200" value="<?= escapeHtml($config_imap_host) ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Port</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span></div>
                                    <input type="text" class="form-control numeric-only" inputmode="numeric" pattern="[0-9]*" maxlength="5" name="config_imap_port" placeholder="993 / 143" value="<?= !empty($config_imap_port) ? intval($config_imap_port) : '' ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Encryption</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span></div>
                                    <select class="form-control" name="config_imap_encryption">
                                        <option value="">None</option>
                                        <option <?php if ($config_imap_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                        <option <?php if ($config_imap_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6" id="imap_user_group">
                            <label id="imap_user_label">IMAP Username</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-user"></i></span></div>
                                <input type="text" class="form-control" name="config_imap_username" placeholder="tickets@yourcompany.com" maxlength="200" value="<?= escapeHtml($config_imap_username) ?>" required>
                            </div>
                            <small class="form-text text-muted" id="imap_user_hint">The mailbox address to monitor for incoming tickets.</small>
                        </div>
                        <div class="form-group col-md-6" id="imap_pass_group">
                            <label>IMAP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_imap_password" placeholder="mailbox or app password" maxlength="200" value="<?= escapeHtml($config_imap_password) ?>" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="imap_oauth_pointer" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-fw fa-info-circle mr-2"></i>This provider uses OAuth — the password is ignored. Enter app credentials in the OAuth tab.</span>
                            <button type="button" class="btn btn-sm btn-outline-primary goto-oauth ml-3 text-nowrap"><i class="fas fa-fw fa-key mr-1"></i>Open OAuth</button>
                        </div>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_imap_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Receiving Settings</button>
                </form>
            </div>

            <!-- ============================ OAUTH ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'oauth') { echo 'show active'; } ?>" id="tab-oauth" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="oauth">

                    <div id="delegated_oauth_section">
                        <h5 class="text-bold">Delegated / User OAuth</h5>
                        <div class="alert alert-secondary" id="delegated_oauth_hint">
                            <i class="fas fa-fw fa-info-circle mr-2"></i>These credentials use an interactive user consent flow and a refresh token.
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>OAuth Client ID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span></div>
                                    <input type="text" class="form-control" name="config_mail_oauth_client_id" id="config_mail_oauth_client_id" placeholder="Application (client) ID" maxlength="255" value="<?= escapeHtml($config_mail_oauth_client_id ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>OAuth Client Secret</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                    <input type="password" class="form-control" data-toggle="password" name="config_mail_oauth_client_secret" id="config_mail_oauth_client_secret" placeholder="<?= !empty($config_mail_oauth_client_secret) ? 'Stored — leave blank to keep' : 'Client secret value' ?>" maxlength="255" value="" autocomplete="new-password">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="tenant_row" style="display:none;">
                            <label>Tenant ID <small class="text-muted">— delegated Microsoft 365 only</small></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-building"></i></span></div>
                                <input type="text" class="form-control" name="config_mail_oauth_tenant_id" placeholder="Directory (tenant) ID" maxlength="255" value="<?= escapeHtml($config_mail_oauth_tenant_id ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Refresh Token</label>
                                <textarea class="form-control" name="config_mail_oauth_refresh_token" rows="2" placeholder="<?= !empty($config_mail_oauth_refresh_token) ? 'Stored — leave blank to keep' : 'Paste a refresh token, or use Connect below' ?>"></textarea>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Access Token <small class="text-muted">— optional</small></label>
                                <textarea class="form-control" name="config_mail_oauth_access_token" rows="2" placeholder="<?= !empty($config_mail_oauth_access_token) ? 'Stored — leave blank to keep' : 'Leave blank — auto-refreshed from the refresh token' ?>"></textarea>
                                <small class="form-text text-muted">Expires at: <?= !empty($config_mail_oauth_access_token_expires_at) ? escapeHtml($config_mail_oauth_access_token_expires_at) : 'n/a' ?></small>
                            </div>
                        </div>

                        <div class="form-group" id="ms_connect_group" style="display:none;">
                            <label>Microsoft OAuth Connect (Web)</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-link"></i></span></div>
                                <input type="text" class="form-control" readonly value="<?= escapeHtml($mail_oauth_callback_uri) ?>">
                                <div class="input-group-append">
                                    <button type="submit" name="oauth_connect_microsoft_mail" class="btn btn-outline-primary">
                                        <i class="fab fa-fw fa-microsoft mr-2"></i>Connect Microsoft 365
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Add this callback URI in your Entra App Registration, save credentials, then click Connect to store the refresh token automatically.</small>
                        </div>
                    </div>

                    <div id="application_oauth_section">
                        <hr>
                        <h5 class="text-bold">Microsoft Application OAuth</h5>
                        <div class="alert alert-secondary">
                            <i class="fas fa-fw fa-info-circle mr-2"></i>Uses the client-credentials grant without an interactive user, callback URI, or refresh token.
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Tenant ID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-building"></i></span></div>
                                    <input type="text" class="form-control" name="config_mail_oauth_app_tenant_id" maxlength="255" placeholder="Directory (tenant) ID" value="<?= escapeHtml($config_mail_oauth_app_tenant_id ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Client / Application ID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span></div>
                                    <input type="text" class="form-control" name="config_mail_oauth_app_client_id" maxlength="255" placeholder="Application (client) ID" value="<?= escapeHtml($config_mail_oauth_app_client_id ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Client Secret</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_mail_oauth_app_client_secret" maxlength="255" placeholder="<?= !empty($config_mail_oauth_app_client_secret) ? 'Stored — leave blank to keep' : 'Client secret value' ?>" value="" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                            <small class="form-text text-muted">Cached access token expires at: <?= !empty($config_mail_oauth_app_access_token_expires_at) ? escapeHtml($config_mail_oauth_app_access_token_expires_at) : 'n/a' ?></small>
                        </div>

                        <p class="text-muted">
                            IMAP requires the Office 365 Exchange Online <code>IMAP.AccessAsApp</code> application permission, admin consent, and Exchange service-principal mailbox authorization. For SMTP, use either the current scoped Exchange Application RBAC role <code>Application SMTP.SendAsApp</code>, or the Entra <code>SMTP.SendAsApp</code> application permission with the required Exchange mailbox / Send As authorization. Microsoft advises not adding the Entra permission when using the RBAC onboarding model.
                            See the <a href="https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth" target="_blank" rel="noopener">protocol setup guidance</a> and
                            <a href="https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/smtp-app-rbac-onboarding" target="_blank" rel="noopener">SMTP Application RBAC guidance</a>.
                        </p>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_oauth_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save OAuth Credentials</button>
                </form>
            </div>

            <!-- ============================ FROM ADDRESSES ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'from') { echo 'show active'; } ?>" id="tab-from" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="from">

                    <p class="text-muted">Each From address must be allowed to send on behalf of the SMTP user.</p>

                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width:26%">Purpose</th>
                                <th>From Email</th>
                                <th>From Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="align-middle">System Default<br><small class="text-muted">share links &amp; system tasks</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_mail_from_email" placeholder="noreply@yourcompany.com" maxlength="200" value="<?= escapeHtml($config_mail_from_email) ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_mail_from_name" placeholder="YourCompany" maxlength="200" value="<?= escapeHtml($config_mail_from_name) ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Invoices<br><small class="text-muted">sent when emailing invoices</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_invoice_from_email" placeholder="billing@yourcompany.com" maxlength="200" value="<?= escapeHtml($config_invoice_from_email) ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_invoice_from_name" placeholder="YourCompany Billing" maxlength="200" value="<?= escapeHtml($config_invoice_from_name) ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Quotes<br><small class="text-muted">sent when emailing quotes</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_quote_from_email" placeholder="sales@yourcompany.com" maxlength="200" value="<?= escapeHtml($config_quote_from_email) ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_quote_from_name" placeholder="YourCompany Sales" maxlength="200" value="<?= escapeHtml($config_quote_from_name) ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Tickets<br><small class="text-muted">ticket creation &amp; client replies</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_ticket_from_email" placeholder="support@yourcompany.com" maxlength="200" value="<?= escapeHtml($config_ticket_from_email) ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_ticket_from_name" placeholder="YourCompany Support" maxlength="200" value="<?= escapeHtml($config_ticket_from_name) ?>"></td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="submit" name="edit_mail_from_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save From Addresses</button>
                </form>
            </div>

            <!-- ============================ TESTS ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'tests') { echo 'show active'; } ?>" id="tab-tests" role="tabpanel">

                <?php if (!$send_ready && !$imap_ready && !$oauth_has_required_fields && !$app_oauth_needed) { ?>
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-fw fa-info-circle mr-2"></i>Finish configuring Sending, Receiving, or OAuth (plus at least one From address) to unlock the tests.
                    </div>
                <?php } ?>

                <?php if ($app_oauth_needed && !$app_oauth_has_required_fields) { ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Microsoft Application OAuth requires a tenant ID, client / application ID, and client secret before token or connection testing.
                    </div>
                <?php } ?>

                <?php if ($send_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send a Test Email</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <div class="input-group">
                            <select class="form-control select2" name="test_email" required>
                                <option value="">- Select a From address -</option>
                                <?php if ($config_mail_from_email) { ?><option value="1"><?= escapeHtml($config_mail_from_name) ?> (<?= escapeHtml($config_mail_from_email) ?>)</option><?php } ?>
                                <?php if ($config_invoice_from_email) { ?><option value="2"><?= escapeHtml($config_invoice_from_name) ?> (<?= escapeHtml($config_invoice_from_email) ?>)</option><?php } ?>
                                <?php if ($config_quote_from_email) { ?><option value="3"><?= escapeHtml($config_quote_from_name) ?> (<?= escapeHtml($config_quote_from_email) ?>)</option><?php } ?>
                                <?php if ($config_ticket_from_email) { ?><option value="4"><?= escapeHtml($config_ticket_from_name) ?> (<?= escapeHtml($config_ticket_from_email) ?>)</option><?php } ?>
                            </select>
                            <input type="email" class="form-control" name="email_to" placeholder="recipient@example.com">
                            <div class="input-group-append">
                                <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php } ?>

                <?php if ($imap_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-plug mr-2"></i>Test IMAP Connection</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox mr-2"></i>Test IMAP</button>
                    </form>
                </div>
                <?php } ?>

                <?php if ($oauth_has_required_fields) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-sync-alt mr-2"></i>Test OAuth Token Refresh</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <input type="hidden" name="oauth_provider" value="<?= htmlspecialchars($oauth_provider_for_test) ?>">
                        <p class="text-muted mb-2">Validates the refresh token and stores a new access token for <?= $oauth_provider_for_test === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace' ?>.</p>
                        <button type="submit" name="test_oauth_token_refresh" class="btn btn-success"><i class="fas fa-fw fa-sync-alt mr-2"></i>Test OAuth Token Refresh</button>
                    </form>
                </div>
                <?php } ?>

                <?php if ($app_oauth_has_required_fields) { ?>
                <div>
                    <h6 class="text-bold"><i class="fas fa-fw fa-key mr-2"></i>Test Application OAuth Token</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <p class="text-muted mb-2">Performs a Microsoft client-credentials request and caches the returned access token without displaying it.</p>
                        <button type="submit" name="test_microsoft_app_oauth_token" class="btn btn-success"><i class="fas fa-fw fa-key mr-2"></i>Test Application OAuth Token</button>
                    </form>
                </div>
                <?php } ?>

            </div>

        </div>
    </div>
</div>

<script>
(function () {
    function setDisabled(c, d) { if (c) c.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !!d); }
    function show(el, v) { if (el) el.style.display = v ? '' : 'none'; }
    function toggle(el, v) { show(el, v); setDisabled(el, !v); }
    function val(s) { return (s && s.value) || ''; }
    function isStd(v) { return v === 'standard_imap' || v === 'standard_smtp'; }
    function isDelegatedOauth(v) { return v === 'google_oauth' || v === 'microsoft_oauth'; }
    function isApplicationOauth(v) { return v === 'microsoft_app_oauth'; }
    function isOauth(v) { return isDelegatedOauth(v) || isApplicationOauth(v); }

    // ---- Numeric-only inputs (ports): strip anything that isn't a digit ----
    document.querySelectorAll('.numeric-only').forEach(function (el) {
        el.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
    });

    // ---- Self-contained tab controller (no dependency on the BS tab plugin) ----
    // Set when the page was opened directly on the OAuth tab - stops the provider pass hiding it
    const forcedOauthTab = <?= $active_tab === 'oauth' ? 'true' : 'false' ?>;
    const navLinks = Array.from(document.querySelectorAll('#mailTabs .nav-link'));
    const panes = ['tab-smtp', 'tab-imap', 'tab-oauth', 'tab-from', 'tab-tests']
        .map(id => document.getElementById(id)).filter(Boolean);

    // Server rendered the initial tab; keep the URL honest as the user clicks around
    function syncTabUrl(target) {
        const tab = target.replace('#tab-', '');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url);
    }

    function activateTab(target) {
        syncTabUrl(target);
        navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('data-target') === target));
        panes.forEach(p => {
            const on = ('#' + p.id) === target;
            p.classList.toggle('active', on);
            p.classList.toggle('show', on);
        });
    }
    navLinks.forEach(l => l.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(l.getAttribute('data-target'));
    }));

    // ---- Provider-driven field visibility ----
    const smtpSel = document.getElementById('config_smtp_provider');
    const imapSel = document.getElementById('config_imap_provider');

    const smtpConn = document.getElementById('smtp_conn_fields');
    const smtpUser = document.getElementById('smtp_user_group');
    const smtpPass = document.getElementById('smtp_pass_group');
    const smtpPtr  = document.getElementById('smtp_oauth_pointer');
    const smtpHint = document.getElementById('smtp_provider_hint');
    const smtpUserLb = document.getElementById('smtp_user_label');
    const smtpUserHt = document.getElementById('smtp_user_hint');
    const smtpUserIn = document.getElementById('config_smtp_username');

    const imapConn = document.getElementById('imap_conn_fields');
    const imapUser = document.getElementById('imap_user_group');
    const imapPass = document.getElementById('imap_pass_group');
    const imapPtr  = document.getElementById('imap_oauth_pointer');
    const imapHint = document.getElementById('imap_provider_hint');
    const imapUserLb = document.getElementById('imap_user_label');
    const imapUserHt = document.getElementById('imap_user_hint');

    const oauthTabItem = document.getElementById('tabitem-oauth');
    const tenantRow = document.getElementById('tenant_row');
    const msConnect = document.getElementById('ms_connect_group');
    const delegatedSection = document.getElementById('delegated_oauth_section');
    const applicationSection = document.getElementById('application_oauth_section');
    const delegatedHint = document.getElementById('delegated_oauth_hint');
    const oauthClientId = document.getElementById('config_mail_oauth_client_id');
    const oauthClientSecret = document.getElementById('config_mail_oauth_client_secret');

    function render() {
        const sv = val(smtpSel), iv = val(imapSel);

        toggle(smtpConn, isStd(sv));
        toggle(smtpUser, isStd(sv) || isOauth(sv));
        toggle(smtpPass, isStd(sv));
        show(smtpPtr, isOauth(sv));
        if (smtpUserLb) smtpUserLb.textContent = isApplicationOauth(sv) ? 'Mailbox Email'
            : isDelegatedOauth(sv) ? 'Authenticated User Email (licensed user)' : 'SMTP Username';
        if (smtpUserIn) smtpUserIn.placeholder = isApplicationOauth(sv) ? 'mailbox@example.com'
            : isDelegatedOauth(sv) ? 'licensed.user@example.com' : 'usually your full email address';
        if (smtpUserHt) smtpUserHt.innerHTML = isApplicationOauth(sv)
            ? 'The Microsoft 365 mailbox used as the <code>user=</code> identity in XOAUTH2. The Entra / Exchange service principal must be authorized to send as required.'
            : isDelegatedOauth(sv)
                ? 'The licensed user that completed the OAuth flow &mdash; <strong>not</strong> the From / shared-mailbox address. Becomes the <code>user=</code> identity in the XOAUTH2 string.'
                : 'Leave blank if no authentication is required.';
        if (smtpHint) smtpHint.textContent = isApplicationOauth(sv) ? 'Application OAuth: set the mailbox email here; client credentials live in the OAuth tab.'
            : isDelegatedOauth(sv) ? 'Delegated OAuth: set the authenticated user email here; app credentials live in the OAuth tab.'
            : isStd(sv) ? 'Standard: host, port, encryption, username & password.' : 'Disabled.';

        toggle(imapConn, isStd(iv));
        toggle(imapUser, isStd(iv) || isOauth(iv));
        toggle(imapPass, isStd(iv));
        show(imapPtr, isOauth(iv));
        if (imapUserLb) imapUserLb.textContent = isOauth(iv) ? 'Mailbox Email (monitored inbox)' : 'IMAP Username';
        if (imapUserHt) imapUserHt.innerHTML = isApplicationOauth(iv)
            ? 'The monitored Microsoft 365 mailbox and the <code>user=</code> identity in XOAUTH2.'
            : isDelegatedOauth(iv)
                ? 'The mailbox you monitor for tickets (the account the refresh token was issued for).'
            : 'The mailbox address to monitor for incoming tickets.';
        if (imapHint) imapHint.textContent = isOauth(iv) ? 'OAuth: set the mailbox here; app credentials live in the OAuth tab.'
            : isStd(iv) ? 'Standard: host, port, encryption, username & password.' : 'Disabled.';

        const anyOauth = isOauth(sv) || isOauth(iv);
        const anyDelegated = isDelegatedOauth(sv) || isDelegatedOauth(iv);
        const anyDelegatedMs = sv === 'microsoft_oauth' || iv === 'microsoft_oauth';
        const anyApplication = isApplicationOauth(sv) || isApplicationOauth(iv);
        const showUnselectedSections = forcedOauthTab && !anyOauth;

        show(oauthTabItem, anyOauth || forcedOauthTab);
        toggle(delegatedSection, anyDelegated || showUnselectedSections);
        toggle(applicationSection, anyApplication || showUnselectedSections);
        toggle(tenantRow, anyDelegatedMs);
        toggle(msConnect, anyDelegatedMs);
        if (oauthClientId) oauthClientId.placeholder = anyDelegatedMs
            ? 'Application (client) ID, e.g. 00000000-0000-0000-0000-000000000000'
            : 'xxxxxxxxxxxx.apps.googleusercontent.com';
        if (oauthClientSecret && !oauthClientSecret.placeholder.startsWith('Stored')) {
            oauthClientSecret.placeholder = anyDelegatedMs ? 'Entra client secret value' : 'Google client secret';
        }
        if (delegatedHint) delegatedHint.innerHTML = anyDelegatedMs
            ? '<i class="fas fa-fw fa-info-circle mr-2"></i>Delegated Microsoft 365: Client ID / Secret / Tenant from Entra ID; refresh token via the interactive Connect button below.'
            : '<i class="fas fa-fw fa-info-circle mr-2"></i>Google Workspace: Client ID / Secret from Google Cloud; refresh token obtained through user consent.';

        if (!anyOauth && !forcedOauthTab) {
            const oauthLink = document.querySelector('#mailTabs .nav-link[data-target="#tab-oauth"]');
            if (oauthLink && oauthLink.classList.contains('active')) activateTab('#tab-smtp');
        }
    }

    if (smtpSel) smtpSel.addEventListener('change', render);
    if (imapSel) imapSel.addEventListener('change', render);
    document.querySelectorAll('.goto-oauth').forEach(b => b.addEventListener('click', () => activateTab('#tab-oauth')));
    render();
})();
</script>

<?php require_once "../includes/footer.php"; ?>
