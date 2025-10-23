<?php
/**
 * Fichier: includes/database.php
 * Gestion de la base de données pour le plugin Lead Collector
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Database {
    
    /**
     * Nom de la table
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'lead_collector';
    }
    
    /**
     * Créer les tables lors de l'activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            ip_address varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Insérer un nouveau lead
     */
    public static function insert_lead($email, $ip_address = '', $user_agent = '') {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Vérifier si l'email existe déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            return array(
                'success' => false,
                'message' => 'Cet email est déjà inscrit.',
                'exists' => true
            );
        }
        
        // Insérer le lead
        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Inscription réussie !',
                'lead_id' => $wpdb->insert_id
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement.'
            );
        }
    }
    
    /**
     * Récupérer tous les leads
     */
    public static function get_all_leads($orderby = 'created_at', $order = 'DESC', $limit = null, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $allowed_orderby = array('id', 'email', 'created_at');
        $allowed_order = array('ASC', 'DESC');
        
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }
        
        if (!in_array($order, $allowed_order)) {
            $order = 'DESC';
        }
        
        $sql = "SELECT * FROM $table_name ORDER BY $orderby $order";
        
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Récupérer un lead par ID
     */
    public static function get_lead_by_id($lead_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $lead_id
        ));
    }
    
    /**
     * Récupérer un lead par email
     */
    public static function get_lead_by_email($email) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Supprimer un lead
     */
    public static function delete_lead($lead_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->delete(
            $table_name,
            array('id' => $lead_id),
            array('%d')
        );
    }
    
    /**
     * Compter le nombre total de leads
     */
    public static function count_leads() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    /**
     * Obtenir les statistiques
     */
    public static function get_stats() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Total de leads
        $total = self::count_leads();
        
        // Leads aujourd'hui
        $today = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()");
        
        // Leads cette semaine
        $week = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE YEARWEEK(created_at) = YEARWEEK(NOW())");
        
        // Leads ce mois
        $month = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())");
        
        // Leads dernières 24h
        $last_24h = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        return array(
            'total' => intval($total),
            'today' => intval($today),
            'week' => intval($week),
            'month' => intval($month),
            'last_24h' => intval($last_24h)
        );
    }
    
    /**
     * Obtenir les leads par date (pour les graphiques)
     */
    public static function get_leads_by_date($days = 30) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $days));
        
        return $results;
    }
    
    /**
     * Nettoyer les vieux leads (optionnel)
     */
    public static function clean_old_leads($days = 365) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->query($wpdb->prepare("
            DELETE FROM $table_name
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }
}
