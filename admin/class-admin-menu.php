<?php
/**
 * Fichier: admin/class-admin-menu.php
 * Gestion du menu d'administration WordPress
 * Crée le menu principal et tous les sous-menus du plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Admin_Menu {
    
    /**
     * Enregistrer le menu dans l'admin WordPress
     * Hook: admin_menu
     */
    public static function register_menu() {
        
        // Menu principal : Lead Collector
        add_menu_page(
            'Lead Collector Pro',              // Page title
            'Leads',                            // Menu title
            'manage_options',                   // Capability
            'lead-collector',                   // Menu slug
            array('LC_Audience_Page', 'render'), // Callback (page par défaut)
            'dashicons-email-alt',              // Icon
            30                                  // Position
        );
        
        // Sous-menu 1 : Audience (page par défaut)
        add_submenu_page(
            'lead-collector',                   // Parent slug
            'Audience - Lead Collector',        // Page title
            'Audience',                         // Menu title
            'manage_options',                   // Capability
            'lead-collector',                   // Menu slug (même que parent pour être la page par défaut)
            array('LC_Audience_Page', 'render') // Callback
        );
        
        // Sous-menu 2 : Formulaires
        add_submenu_page(
            'lead-collector',
            'Formulaires - Lead Collector',
            'Formulaires',
            'manage_options',
            'lc-forms',
            array('LC_Forms_Page', 'render')
        );
        
        // Sous-menu 3 : Campagnes
        add_submenu_page(
            'lead-collector',
            'Campagnes - Lead Collector',
            'Campagnes',
            'manage_options',
            'lc-campaigns',
            array('LC_Campaigns_Page', 'render')
        );
        
        // Sous-menu 4 : Post Notifications (NOUVELLE FONCTIONNALITÉ)
        add_submenu_page(
            'lead-collector',
            'Post Notifications - Lead Collector',
            'Post Notifications',
            'manage_options',
            'lc-post-notifications',
            array('LC_Post_Notifications_Page', 'render')
        );
        
        // Sous-menu 5 : Paramètres
        add_submenu_page(
            'lead-collector',
            'Paramètres - Lead Collector',
            'Paramètres',
            'manage_options',
            'lc-settings',
            array('LC_Settings_Page', 'render')
        );
    }
    
    /**
     * Obtenir le menu slug de la page courante
     * 
     * @return string|null Slug de la page ou null
     */
    public static function get_current_page() {
        if (!isset($_GET['page'])) {
            return null;
        }
        
        $valid_pages = array(
            'lead-collector',
            'lc-forms',
            'lc-campaigns',
            'lc-post-notifications',
            'lc-settings'
        );
        
        $page = sanitize_text_field($_GET['page']);
        
        return in_array($page, $valid_pages) ? $page : null;
    }
    
    /**
     * Vérifier si on est sur une page du plugin
     * 
     * @return bool True si on est sur une page du plugin
     */
    public static function is_plugin_page() {
        return self::get_current_page() !== null;
    }
    
    /**
     * Obtenir l'URL d'une page admin du plugin
     * 
     * @param string $page Slug de la page
     * @param array $args Arguments supplémentaires (query string)
     * @return string URL de la page
     */
    public static function get_page_url($page, $args = array()) {
        $url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
}
