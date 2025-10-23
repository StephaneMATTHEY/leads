<?php
/**
 * Fichier: services/class-lead-manager.php
 * Service de gestion des leads
 * Gère toutes les opérations CRUD sur les leads
 * Gère les statuts, catégories, groupes, tags, import/export
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Lead_Manager {
    
    /**
     * Insérer un nouveau lead
     */
    public static function create_lead($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        // Vérifier si l'email existe déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s",
            $data['email']
        ));
        
        if ($existing) {
            return array(
                'success' => false,
                'message' => 'Cet email est déjà inscrit.',
                'lead_id' => $existing
            );
        }
        
        // Préparer les données
        $insert_data = array(
            'email' => sanitize_email($data['email']),
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : null,
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : null,
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'source' => isset($data['source']) ? sanitize_text_field($data['source']) : 'form',
            'ip_address' => isset($data['ip_address']) ? sanitize_text_field($data['ip_address']) : self::get_client_ip(),
            'user_agent' => isset($data['user_agent']) ? sanitize_text_field($data['user_agent']) : self::get_user_agent(),
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'custom_fields' => isset($data['custom_fields']) ? json_encode($data['custom_fields']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result) {
            $lead_id = $wpdb->insert_id;
            
            // Assigner les catégories par défaut
            self::assign_default_categories($lead_id);
            
            // Hook pour permettre des actions personnalisées
            do_action('lc_lead_created', $lead_id, $insert_data);
            
            return array(
                'success' => true,
                'message' => 'Lead créé avec succès.',
                'lead_id' => $lead_id
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la création du lead.'
        );
    }
    
    /**
     * Mettre à jour un lead
     */
    public static function update_lead($lead_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        $update_data = array();
        
        if (isset($data['email'])) $update_data['email'] = sanitize_email($data['email']);
        if (isset($data['first_name'])) $update_data['first_name'] = sanitize_text_field($data['first_name']);
        if (isset($data['last_name'])) $update_data['last_name'] = sanitize_text_field($data['last_name']);
        if (isset($data['phone'])) $update_data['phone'] = sanitize_text_field($data['phone']);
        if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);
        if (isset($data['tags'])) $update_data['tags'] = json_encode($data['tags']);
        if (isset($data['custom_fields'])) $update_data['custom_fields'] = json_encode($data['custom_fields']);
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $lead_id),
            null,
            array('%d')
        );
        
        do_action('lc_lead_updated', $lead_id, $update_data);
        
        return $result !== false;
    }
    
    /**
     * Supprimer un lead
     */
    public static function delete_lead($lead_id) {
        global $wpdb;
        
        $tables = LC_Database::get_table_names();
        
        // Supprimer le lead
        $wpdb->delete($tables['leads'], array('id' => $lead_id), array('%d'));
        
        // Supprimer les relations
        $wpdb->delete($tables['lead_groups'], array('lead_id' => $lead_id), array('%d'));
        $wpdb->delete($tables['lead_categories'], array('lead_id' => $lead_id), array('%d'));
        
        do_action('lc_lead_deleted', $lead_id);
        
        return true;
    }
    
    /**
     * Récupérer un lead par ID
     */
    public static function get_lead($lead_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $lead_id
        ));
        
        if ($lead) {
            // Décoder les champs JSON
            $lead->tags = !empty($lead->tags) ? json_decode($lead->tags, true) : array();
            $lead->custom_fields = !empty($lead->custom_fields) ? json_decode($lead->custom_fields, true) : array();
            
            // Récupérer les catégories et groupes
            $lead->categories = self::get_lead_categories($lead_id);
            $lead->groups = self::get_lead_groups($lead_id);
        }
        
        return $lead;
    }
    
    /**
     * Récupérer tous les leads avec filtres
     */
    public static function get_leads($args = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        $defaults = array(
            'status' => null,
            'group_id' => null,
            'category_id' => null,
            'search' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if ($args['status']) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare("(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)", $search, $search, $search);
        }
        
        if ($args['group_id']) {
            $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
            $where[] = $wpdb->prepare("id IN (SELECT lead_id FROM $table_lead_groups WHERE group_id = %d)", $args['group_id']);
        }
        
        if ($args['category_id']) {
            $table_lead_categories = $wpdb->prefix . 'lc_lead_categories';
            $where[] = $wpdb->prepare("id IN (SELECT lead_id FROM $table_lead_categories WHERE category_id = %d)", $args['category_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = $args['orderby'] . ' ' . $args['order'];
        
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_clause";
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Compter les leads avec filtres
     */
    public static function count_leads($args = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        $where = array('1=1');
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
    }
    
    /**
     * Obtenir les statistiques des leads
     */
    public static function get_stats() {
        $total = self::count_leads();
        $active = self::count_leads(array('status' => 'active'));
        $pending = self::count_leads(array('status' => 'pending'));
        $unsubscribed = self::count_leads(array('status' => 'unsubscribed'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'lc_leads';
        
        $today = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()");
        $week = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE YEARWEEK(created_at) = YEARWEEK(NOW())");
        $month = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())");
        
        return array(
            'total' => intval($total),
            'active' => intval($active),
            'pending' => intval($pending),
            'unsubscribed' => intval($unsubscribed),
            'today' => intval($today),
            'week' => intval($week),
            'month' => intval($month)
        );
    }
    
    /**
     * Assigner les catégories par défaut à un nouveau lead
     */
    public static function assign_default_categories($lead_id) {
        $default_categories = get_option('lc_default_categories', array());
        
        if (!empty($default_categories)) {
            foreach ($default_categories as $category_id) {
                self::add_lead_category($lead_id, $category_id);
            }
        }
    }
    
    /**
     * Ajouter une catégorie à un lead
     */
    public static function add_lead_category($lead_id, $category_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_categories';
        
        $wpdb->insert(
            $table,
            array('lead_id' => $lead_id, 'category_id' => $category_id),
            array('%d', '%d')
        );
    }
    
    /**
     * Supprimer une catégorie d'un lead
     */
    public static function remove_lead_category($lead_id, $category_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_categories';
        
        $wpdb->delete(
            $table,
            array('lead_id' => $lead_id, 'category_id' => $category_id),
            array('%d', '%d')
        );
    }
    
    /**
     * Obtenir les catégories d'un lead
     */
    public static function get_lead_categories($lead_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_categories';
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT category_id FROM $table WHERE lead_id = %d",
            $lead_id
        ));
    }
    
    /**
     * Mettre à jour les catégories d'un lead (remplace toutes)
     */
    public static function update_lead_categories($lead_id, $category_ids) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_categories';
        
        // Supprimer toutes les catégories actuelles
        $wpdb->delete($table, array('lead_id' => $lead_id), array('%d'));
        
        // Ajouter les nouvelles catégories
        if (!empty($category_ids)) {
            foreach ($category_ids as $category_id) {
                self::add_lead_category($lead_id, $category_id);
            }
        }
        
        return true;
    }
    
    /**
     * Obtenir les groupes d'un lead
     */
    public static function get_lead_groups($lead_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_groups';
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT group_id FROM $table WHERE lead_id = %d",
            $lead_id
        ));
    }
    
    /**
     * Ajouter un lead à un groupe
     */
    public static function add_lead_to_group($lead_id, $group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_groups';
        
        $wpdb->insert(
            $table,
            array('lead_id' => $lead_id, 'group_id' => $group_id),
            array('%d', '%d')
        );
    }
    
    /**
     * Retirer un lead d'un groupe
     */
    public static function remove_lead_from_group($lead_id, $group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_lead_groups';
        
        $wpdb->delete(
            $table,
            array('lead_id' => $lead_id, 'group_id' => $group_id),
            array('%d', '%d')
        );
    }
    
    /**
     * Exporter les leads en CSV
     */
    public static function export_to_csv($args = array()) {
        $leads = self::get_leads($args);
        
        if (empty($leads)) {
            return false;
        }
        
        $filename = 'leads-' . date('Y-m-d-His') . '.csv';
        $filepath = LC_PLUGIN_DIR . 'exports/' . $filename;
        
        // Créer le dossier exports s'il n'existe pas
        if (!file_exists(LC_PLUGIN_DIR . 'exports')) {
            mkdir(LC_PLUGIN_DIR . 'exports', 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // En-têtes CSV
        fputcsv($file, array('ID', 'Email', 'Prénom', 'Nom', 'Téléphone', 'Statut', 'Source', 'Date d\'inscription'));
        
        // Données
        foreach ($leads as $lead) {
            fputcsv($file, array(
                $lead->id,
                $lead->email,
                $lead->first_name,
                $lead->last_name,
                $lead->phone,
                $lead->status,
                $lead->source,
                $lead->created_at
            ));
        }
        
        fclose($file);
        
        return array(
            'success' => true,
            'url' => LC_PLUGIN_URL . 'exports/' . $filename,
            'filename' => $filename
        );
    }
    
    /**
     * AJAX - Supprimer un lead
     */
    public static function ajax_delete_lead() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        
        if ($lead_id <= 0) {
            wp_send_json_error(array('message' => 'ID invalide.'));
        }
        
        $result = self::delete_lead($lead_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Lead supprimé avec succès.'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression.'));
        }
    }
    
    /**
     * AJAX - Exporter les leads
     */
    public static function ajax_export_leads() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $result = self::export_to_csv();
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => 'Aucun lead à exporter.'));
        }
    }
    
    /**
     * AJAX - Mettre à jour le statut d'un lead
     */
    public static function ajax_update_lead_status() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $result = self::update_lead($lead_id, array('status' => $status));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Statut mis à jour.'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la mise à jour.'));
        }
    }
    
    /**
     * AJAX - Mettre à jour les catégories d'un lead
     */
    public static function ajax_update_lead_categories() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : array();
        
        $result = self::update_lead_categories($lead_id, $category_ids);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Catégories mises à jour.'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la mise à jour.'));
        }
    }
    
    /**
     * AJAX - Mettre à jour les groupes d'un lead
     */
    public static function ajax_update_lead_groups() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $group_ids = isset($_POST['group_ids']) ? array_map('intval', $_POST['group_ids']) : array();
        
        global $wpdb;
        $table = $wpdb->prefix . 'lc_lead_groups';
        
        // Supprimer tous les groupes actuels
        $wpdb->delete($table, array('lead_id' => $lead_id), array('%d'));
        
        // Ajouter les nouveaux groupes
        foreach ($group_ids as $group_id) {
            self::add_lead_to_group($lead_id, $group_id);
        }
        
        wp_send_json_success(array('message' => 'Groupes mis à jour.'));
    }
    
    /**
     * Obtenir l'IP du client
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Obtenir le User Agent
     */
    private static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }
}
