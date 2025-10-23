<?php
/**
 * Fichier: services/class-database.php
 * Service de gestion de la base de données
 * Gère la création et les opérations CRUD sur toutes les tables du plugin
 * 
 * Tables gérées :
 * - wp_lc_leads : Tous les leads collectés
 * - wp_lc_groups : Groupes de leads
 * - wp_lc_lead_groups : Relation leads <-> groupes (many-to-many)
 * - wp_lc_lead_categories : Relation leads <-> catégories WordPress (many-to-many)
 * - wp_lc_notification_templates : Templates d'emails par catégorie
 * - wp_lc_campaigns : Campagnes d'emailing
 * - wp_lc_campaign_logs : Logs d'envoi de campagnes
 * - wp_lc_forms : Formulaires d'inscription
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Database {
    
    /**
     * Créer toutes les tables lors de l'activation du plugin
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table 1 : LEADS (leads collectés)
        $table_leads = $wpdb->prefix . 'lc_leads';
        $sql_leads = "CREATE TABLE IF NOT EXISTS $table_leads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            status varchar(20) DEFAULT 'active' COMMENT 'active, pending, unsubscribed, bounced',
            source varchar(100) DEFAULT NULL COMMENT 'form_id, import, manual',
            ip_address varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            tags text DEFAULT NULL COMMENT 'JSON array de tags',
            custom_fields longtext DEFAULT NULL COMMENT 'JSON object pour champs personnalisés',
            confirmed_at datetime DEFAULT NULL,
            unsubscribed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY created_at (created_at),
            KEY source (source)
        ) $charset_collate;";
        dbDelta($sql_leads);
        
        // Table 2 : GROUPS (groupes de leads)
        $table_groups = $wpdb->prefix . 'lc_groups';
        $sql_groups = "CREATE TABLE IF NOT EXISTS $table_groups (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        dbDelta($sql_groups);
        
        // Table 3 : LEAD_GROUPS (relation many-to-many : leads <-> groups)
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        $sql_lead_groups = "CREATE TABLE IF NOT EXISTS $table_lead_groups (
            lead_id bigint(20) UNSIGNED NOT NULL,
            group_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lead_id, group_id),
            KEY lead_id (lead_id),
            KEY group_id (group_id)
        ) $charset_collate;";
        dbDelta($sql_lead_groups);
        
        // Table 4 : LEAD_CATEGORIES (relation many-to-many : leads <-> catégories WordPress)
        $table_lead_categories = $wpdb->prefix . 'lc_lead_categories';
        $sql_lead_categories = "CREATE TABLE IF NOT EXISTS $table_lead_categories (
            lead_id bigint(20) UNSIGNED NOT NULL,
            category_id bigint(20) UNSIGNED NOT NULL COMMENT 'ID de la catégorie WordPress',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lead_id, category_id),
            KEY lead_id (lead_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        dbDelta($sql_lead_categories);
        
        // Table 5 : NOTIFICATION_TEMPLATES (templates d'emails par catégorie)
        $table_notification_templates = $wpdb->prefix . 'lc_notification_templates';
        $sql_notification_templates = "CREATE TABLE IF NOT EXISTS $table_notification_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id bigint(20) UNSIGNED NOT NULL COMMENT 'ID de la catégorie WordPress',
            name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL COMMENT 'HTML du template avec variables {{var}}',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_notification_templates);
        
        // Table 6 : CAMPAIGNS (campagnes d'emailing)
        $table_campaigns = $wpdb->prefix . 'lc_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            status varchar(20) DEFAULT 'draft' COMMENT 'draft, scheduled, sending, sent, failed',
            target_type varchar(50) DEFAULT 'all' COMMENT 'all, groups, categories, custom',
            target_ids text DEFAULT NULL COMMENT 'JSON array des IDs cibles',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            total_recipients int DEFAULT 0,
            total_sent int DEFAULT 0,
            total_failed int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        dbDelta($sql_campaigns);
        
        // Table 7 : CAMPAIGN_LOGS (logs d'envoi de campagnes)
        $table_campaign_logs = $wpdb->prefix . 'lc_campaign_logs';
        $sql_campaign_logs = "CREATE TABLE IF NOT EXISTS $table_campaign_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            lead_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) DEFAULT 'pending' COMMENT 'pending, sent, failed, opened, clicked',
            error_message text DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY lead_id (lead_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_campaign_logs);
        
        // Table 8 : FORMS (formulaires d'inscription)
        $table_forms = $wpdb->prefix . 'lc_forms';
        $sql_forms = "CREATE TABLE IF NOT EXISTS $table_forms (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            title varchar(500) DEFAULT NULL,
            subtitle text DEFAULT NULL,
            fields longtext DEFAULT NULL COMMENT 'JSON array des champs du formulaire',
            style varchar(50) DEFAULT 'dark' COMMENT 'dark, light, custom',
            double_optin tinyint(1) DEFAULT 0,
            redirect_url varchar(500) DEFAULT NULL,
            success_message text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_forms);
        
        // Créer un formulaire par défaut
        self::create_default_form();
    }
    
    /**
     * Créer un formulaire par défaut lors de l'installation
     */
    private static function create_default_form() {
        global $wpdb;
        
        $table_forms = $wpdb->prefix . 'lc_forms';
        
        // Vérifier si le formulaire par défaut existe déjà
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_forms");
        
        if ($exists == 0) {
            $default_fields = json_encode(array(
                array('name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => 'votre.email@exemple.com'),
                array('name' => 'terms', 'type' => 'checkbox', 'label' => 'J\'accepte les conditions', 'required' => true),
            ));
            
            $wpdb->insert(
                $table_forms,
                array(
                    'name' => 'Formulaire par défaut',
                    'title' => 'Ne Manquez Aucun Épisode',
                    'subtitle' => 'Recevez les alertes directement dans votre boîte mail.',
                    'fields' => $default_fields,
                    'style' => 'dark',
                    'double_optin' => 0,
                    'success_message' => 'Merci ! Votre inscription a été enregistrée avec succès.',
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
            );
        }
    }
    
    /**
     * Obtenir les noms de toutes les tables du plugin
     */
    public static function get_table_names() {
        global $wpdb;
        
        return array(
            'leads' => $wpdb->prefix . 'lc_leads',
            'groups' => $wpdb->prefix . 'lc_groups',
            'lead_groups' => $wpdb->prefix . 'lc_lead_groups',
            'lead_categories' => $wpdb->prefix . 'lc_lead_categories',
            'notification_templates' => $wpdb->prefix . 'lc_notification_templates',
            'campaigns' => $wpdb->prefix . 'lc_campaigns',
            'campaign_logs' => $wpdb->prefix . 'lc_campaign_logs',
            'forms' => $wpdb->prefix . 'lc_forms',
        );
    }
    
    /**
     * Supprimer toutes les tables du plugin (utilisé lors de la désinstallation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
