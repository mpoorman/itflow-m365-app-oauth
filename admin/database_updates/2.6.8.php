<?php

/*
 * ITFlow - Database update to version 2.6.8 (from 2.6.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN IF NOT EXISTS `config_mail_oauth_app_tenant_id` varchar(255) DEFAULT NULL AFTER `config_mail_oauth_access_token_expires_at`,
        ADD COLUMN IF NOT EXISTS `config_mail_oauth_app_client_id` varchar(255) DEFAULT NULL AFTER `config_mail_oauth_app_tenant_id`,
        ADD COLUMN IF NOT EXISTS `config_mail_oauth_app_client_secret` varchar(255) DEFAULT NULL AFTER `config_mail_oauth_app_client_id`,
        ADD COLUMN IF NOT EXISTS `config_mail_oauth_app_access_token` text DEFAULT NULL AFTER `config_mail_oauth_app_client_secret`,
        ADD COLUMN IF NOT EXISTS `config_mail_oauth_app_access_token_expires_at` datetime DEFAULT NULL AFTER `config_mail_oauth_app_access_token`
    ");
