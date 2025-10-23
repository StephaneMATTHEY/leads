<?php
/**
 * Fichier: lead-collector.php
 * Plugin Name: Lead Collector Pro
 * Plugin URI: https://example.com
 * Description: Plugin professionnel de collecte de leads avec notifications d'articles automatiques par catégorie
 * Version: 2.0.0
 * Author: Votre Nom
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: lead-collector
 * Domain Path: /languages
 */

// Sécurité : Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('LC_VERSION', '2.0.0');
define('LC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale du plugin Lead Collector Pro
 * Architecture flat : 1 seul niveau de dossiers (admin, services, views)
 * Chaque fonctionnalité = 1 fichier unique et indépendant
 */
class Lead_Collector_Pro {
    
    private static $instance = null;
    
    /**
     * Singleton - Instance unique du plugin
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur - Initialise le plugin
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Charger toutes les dépendances du plugin
     * Architecture flat : tous les fichiers au même niveau dans leurs dossiers respectifs
     */
    private function load_dependencies() {
        
        // === SERVICES (Logique métier) ===
        require_once LC_PLUGIN_DIR . 'services/class-database.php';
        require_once LC_PLUGIN_DIR . 'services/class-lead-manager.php';
        require_once LC_PLUGIN_DIR . 'services/class-form-handler.php';
        require_once LC_PLUGIN_DIR . 'services/class-email-sender.php';
        require_once LC_PLUGIN_DIR . 'services/class-template-parser.php';
        require_once LC_PLUGIN_DIR . 'services/class-post-notification-handler.php';
        require_once LC_PLUGIN_DIR . 'services/class-campaign-manager.php';
        require_once LC_PLUGIN_DIR . 'services/class-group-manager.php';
        
        // === ADMIN (Pages d'administration) ===
        require_once LC_PLUGIN_DIR . 'admin/class-admin-menu.php';
        require_once LC_PLUGIN_DIR . 'admin/class-audience-page.php';
        require_once LC_PLUGIN_DIR . 'admin/class-forms-page.php';
        require_once LC_PLUGIN_DIR . 'admin/class-campaigns-page.php';
        require_once LC_PLUGIN_DIR . 'admin/class-post-notifications-page.php';
        require_once LC_PLUGIN_DIR . 'admin/class-settings-page.php';
    }
    
    /**
     * Initialiser tous les hooks WordPress
     */
    private function init_hooks() {
        
        // Activation et désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Charger les assets frontend et admin
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Enregistrer les shortcodes
        add_shortcode('lead_collector', array($this, 'render_form_shortcode'));
        add_shortcode('lc_form', array($this, 'render_form_shortcode')); // Alias
        
        // AJAX Frontend - Soumission de formulaire
        add_action('wp_ajax_lc_submit_lead', array('LC_Form_Handler', 'ajax_submit_lead'));
        add_action('wp_ajax_nopriv_lc_submit_lead', array('LC_Form_Handler', 'ajax_submit_lead'));
        
        // AJAX Admin - Gestion des leads
        add_action('wp_ajax_lc_delete_lead', array('LC_Lead_Manager', 'ajax_delete_lead'));
        add_action('wp_ajax_lc_export_leads', array('LC_Lead_Manager', 'ajax_export_leads'));
        add_action('wp_ajax_lc_update_lead_status', array('LC_Lead_Manager', 'ajax_update_lead_status'));
        add_action('wp_ajax_lc_update_lead_categories', array('LC_Lead_Manager', 'ajax_update_lead_categories'));
        add_action('wp_ajax_lc_update_lead_groups', array('LC_Lead_Manager', 'ajax_update_lead_groups'));
        
        // AJAX Admin - Groupes
        add_action('wp_ajax_lc_create_group', array('LC_Group_Manager', 'ajax_create_group'));
        add_action('wp_ajax_lc_delete_group', array('LC_Group_Manager', 'ajax_delete_group'));
        add_action('wp_ajax_lc_update_group_categories', array('LC_Group_Manager', 'ajax_update_group_categories'));
        
        // AJAX Admin - Post Notifications
        add_action('wp_ajax_lc_save_notification_template', array('LC_Post_Notification_Handler', 'ajax_save_template'));
        add_action('wp_ajax_lc_delete_notification_template', array('LC_Post_Notification_Handler', 'ajax_delete_template'));
        add_action('wp_ajax_lc_test_notification', array('LC_Post_Notification_Handler', 'ajax_test_notification'));
        
        // AJAX Admin - Campagnes
        add_action('wp_ajax_lc_send_campaign', array('LC_Campaign_Manager', 'ajax_send_campaign'));
        add_action('wp_ajax_lc_schedule_campaign', array('LC_Campaign_Manager', 'ajax_schedule_campaign'));
        
        // Hook WordPress - Notification automatique lors de la publication d'un article
        add_action('transition_post_status', array('LC_Post_Notification_Handler', 'handle_post_publish'), 10, 3);
        
        // Cron - Envoi des campagnes programmées
        add_action('lc_send_scheduled_campaigns', array('LC_Campaign_Manager', 'process_scheduled_campaigns'));
        
        // Menu d'administration
        add_action('admin_menu', array('LC_Admin_Menu', 'register_menu'));
    }
    
    /**
     * Activation du plugin - Créer les tables et configurer le cron
     */
    public function activate() {
        // Créer toutes les tables nécessaires
        LC_Database::create_tables();
        
        // Configurer le cron pour les campagnes programmées
        if (!wp_next_scheduled('lc_send_scheduled_campaigns')) {
            wp_schedule_event(time(), 'hourly', 'lc_send_scheduled_campaigns');
        }
        
        // Créer les options par défaut
        $default_options = array(
            'lc_enable_notifications' => true,
            'lc_enable_double_optin' => false,
            'lc_from_name' => get_bloginfo('name'),
            'lc_from_email' => get_option('admin_email'),
            'lc_default_categories' => array(), // Catégories par défaut pour nouveaux leads
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Désactivation du plugin - Nettoyer le cron
     */
    public function deactivate() {
        // Supprimer le cron
        $timestamp = wp_next_scheduled('lc_send_scheduled_campaigns');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'lc_send_scheduled_campaigns');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Charger les assets frontend
     */
    public function enqueue_frontend_assets() {
        
        // CSS Frontend
        wp_enqueue_style(
            'lead-collector-style',
            LC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            LC_VERSION
        );
        
        // JavaScript Frontend
        wp_enqueue_script(
            'lead-collector-script',
            LC_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            LC_VERSION,
            true
        );
        
        // Localiser les variables JavaScript
        wp_localize_script('lead-collector-script', 'lcAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lc_frontend_nonce'),
            'messages' => array(
                'success' => 'Merci ! Votre inscription a été enregistrée avec succès.',
                'error' => 'Une erreur est survenue. Veuillez réessayer.',
                'invalid_email' => 'Veuillez saisir une adresse email valide.',
                'required' => 'Ce champ est requis.',
                'terms_required' => 'Vous devez accepter les conditions.',
            )
        ));
    }
    
    /**
     * Charger les assets admin
     */
    public function enqueue_admin_assets($hook) {
        
        // Charger uniquement sur les pages du plugin
        if (strpos($hook, 'lead-collector') === false) {
            return;
        }
        
        // CSS Admin
        wp_enqueue_style(
            'lead-collector-admin-style',
            LC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LC_VERSION
        );
        
        // JavaScript Admin
        wp_enqueue_script(
            'lead-collector-admin-script',
            LC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            LC_VERSION,
            true
        );
        
        // Localiser les variables JavaScript admin
        wp_localize_script('lead-collector-admin-script', 'lcAdminAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lc_admin_nonce'),
            'messages' => array(
                'confirm_delete' => 'Êtes-vous sûr de vouloir supprimer cet élément ?',
                'delete_success' => 'Élément supprimé avec succès.',
                'delete_error' => 'Erreur lors de la suppression.',
                'save_success' => 'Enregistré avec succès.',
                'save_error' => 'Erreur lors de l\'enregistrement.',
            )
        ));
        
        // Éditeur de code pour les templates d'emails
        wp_enqueue_code_editor(array('type' => 'text/html'));
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
    }
    
    /**
     * Rendu du shortcode du formulaire
     * Utilise le service Form_Handler pour générer le HTML
     */
    public function render_form_shortcode($atts) {
        return LC_Form_Handler::render_form($atts);
    }
}

/**
 * Fonction d'initialisation du plugin
 * Point d'entrée unique du plugin
 */
function lead_collector_pro_init() {
    return Lead_Collector_Pro::get_instance();
}

// Lancer le plugin
lead_collector_pro_init();
