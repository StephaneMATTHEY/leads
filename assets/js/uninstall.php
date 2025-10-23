<?php
/**
 * Fichier: uninstall.php
 * Script de désinstallation du plugin Lead Collector Pro
 * 
 * Ce fichier est appelé automatiquement par WordPress lors de la suppression du plugin.
 * Il nettoie TOUTES les données créées par le plugin :
 * - Tables de la base de données
 * - Options WordPress
 * - Fichiers d'export
 * - Tâches cron
 * 
 * ATTENTION : Ce processus est IRRÉVERSIBLE !
 */

// Sécurité : Vérifier que ce fichier est appelé par WordPress lors de la désinstallation
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Supprimer toutes les tables de la base de données
 */
function lc_uninstall_delete_tables() {
    global $wpdb;
    
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
}

/**
 * Supprimer toutes les options WordPress du plugin
 */
function lc_uninstall_delete_options() {
    
    // Liste de toutes les options créées par le plugin
    $options = array(
        // Paramètres généraux
        'lc_enable_notifications',
        'lc_enable_double_optin',
        'lc_from_name',
        'lc_from_email',
        'lc_default_categories',
        
        // Double opt-in
        'lc_confirmation_subject',
        'lc_confirmation_body',
        
        // Protection
        'lc_rate_limit',
        'lc_enable_honeypot',
        
        // Autres paramètres potentiels
        'lc_version',
        'lc_installed_at',
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Supprimer les tokens de confirmation (options dynamiques)
    $wpdb = $GLOBALS['wpdb'];
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lc_confirmation_token_%'");
}

/**
 * Nettoyer les fichiers d'export CSV
 */
function lc_uninstall_delete_exports() {
    
    $upload_dir = wp_upload_dir();
    $exports_dir = $upload_dir['basedir'] . '/lead-collector-exports';
    
    if (is_dir($exports_dir)) {
        lc_uninstall_delete_directory($exports_dir);
    }
}

/**
 * Supprimer un répertoire et tout son contenu (récursif)
 */
function lc_uninstall_delete_directory($dir) {
    
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            lc_uninstall_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}

/**
 * Supprimer les tâches cron programmées
 */
function lc_uninstall_delete_cron() {
    
    // Supprimer le cron pour l'envoi des campagnes programmées
    $timestamp = wp_next_scheduled('lc_send_scheduled_campaigns');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'lc_send_scheduled_campaigns');
    }
    
    // Supprimer toutes les occurrences du cron
    wp_clear_scheduled_hook('lc_send_scheduled_campaigns');
}

/**
 * Supprimer les métadonnées utilisateur (si le plugin en a créé)
 */
function lc_uninstall_delete_user_meta() {
    global $wpdb;
    
    // Supprimer toutes les métadonnées utilisateur du plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lc_%'");
}

/**
 * Supprimer les capacités personnalisées (si le plugin en a créé)
 */
function lc_uninstall_delete_capabilities() {
    
    // Si des rôles ou capacités personnalisés ont été créés
    // Les supprimer ici
    
    // Exemple :
    // remove_role('lc_manager');
}

/**
 * Log de désinstallation (optionnel, pour debug)
 */
function lc_uninstall_log() {
    
    // Créer un fichier de log de désinstallation
    $log_file = WP_CONTENT_DIR . '/lead-collector-uninstall.log';
    
    $log_content = sprintf(
        "[%s] Lead Collector Pro a été désinstallé\n",
        date('Y-m-d H:i:s')
    );
    
    file_put_contents($log_file, $log_content, FILE_APPEND);
}

/**
 * Nettoyer les transients (cache temporaire)
 */
function lc_uninstall_delete_transients() {
    global $wpdb;
    
    // Supprimer tous les transients du plugin
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lc_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_lc_%'");
}

/**
 * Vérifier si l'utilisateur a confirmé la suppression des données
 * (Cette fonction peut être appelée depuis une page de désinstallation personnalisée)
 */
function lc_uninstall_confirmed() {
    
    // Par défaut, on considère que l'utilisateur a confirmé en supprimant le plugin
    // Mais on pourrait ajouter une option pour conserver les données
    
    $keep_data = get_option('lc_keep_data_on_uninstall', false);
    
    return !$keep_data;
}

// ============================================
// EXÉCUTION DE LA DÉSINSTALLATION
// ============================================

// Vérifier si l'utilisateur veut vraiment supprimer les données
if (lc_uninstall_confirmed()) {
    
    // 1. Supprimer les tables de la base de données
    lc_uninstall_delete_tables();
    
    // 2. Supprimer les options WordPress
    lc_uninstall_delete_options();
    
    // 3. Supprimer les fichiers d'export
    lc_uninstall_delete_exports();
    
    // 4. Supprimer les tâches cron
    lc_uninstall_delete_cron();
    
    // 5. Supprimer les métadonnées utilisateur
    lc_uninstall_delete_user_meta();
    
    // 6. Supprimer les capacités personnalisées
    lc_uninstall_delete_capabilities();
    
    // 7. Supprimer les transients
    lc_uninstall_delete_transients();
    
    // 8. Logger la désinstallation (optionnel)
    // lc_uninstall_log();
    
    // Nettoyer le cache
    wp_cache_flush();
    
} else {
    
    // L'utilisateur a choisi de conserver les données
    // On ne supprime que les options de configuration légères
    delete_option('lc_version');
}

/**
 * Note de sécurité :
 * Ce fichier ne sera exécuté QUE si l'utilisateur supprime le plugin depuis l'interface WordPress.
 * Il ne sera PAS exécuté si l'utilisateur désactive simplement le plugin.
 * 
 * Pour désactiver temporairement le plugin sans perdre les données,
 * utilisez le bouton "Désactiver" au lieu de "Supprimer".
 */

/**
 * Note pour les développeurs :
 * Si vous souhaitez ajouter une option pour conserver les données lors de la désinstallation,
 * ajoutez cette option dans la page des paramètres :
 * 
 * <label>
 *     <input type="checkbox" name="lc_keep_data_on_uninstall" value="1">
 *     Conserver les données lors de la désinstallation du plugin
 * </label>
 * 
 * Cette option permettra à l'utilisateur de réinstaller le plugin plus tard
 * sans perdre ses leads et campagnes.
 */
