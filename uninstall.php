<?php
/**
 * Fichier: uninstall.php
 * Script de désinstallation du plugin Lead Collector Pro
 * Supprime toutes les tables et options du plugin
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Supprimer toutes les tables
$tables = array(
    $wpdb->prefix . 'lc_leads',
    $wpdb->prefix . 'lc_groups',
    $wpdb->prefix . 'lc_lead_groups',
    $wpdb->prefix . 'lc_lead_categories',
    $wpdb->prefix . 'lc_group_categories',
    $wpdb->prefix . 'lc_notification_templates',
    $wpdb->prefix . 'lc_campaigns',
    $wpdb->prefix . 'lc_campaign_logs',
    $wpdb->prefix . 'lc_forms',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Supprimer toutes les options
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lc_%'");

// Supprimer les transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lc_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_lc_%'");

// Supprimer les fichiers d'export
$export_dir = plugin_dir_path(__FILE__) . 'exports/';
if (is_dir($export_dir)) {
    $files = glob($export_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Log de désinstallation
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Lead Collector Pro: Plugin désinstallé avec succès');
}
