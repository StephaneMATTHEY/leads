<?php
/**
 * Fichier: services/class-group-manager.php
 * Service de gestion des groupes de leads
 * Gère les groupes, leurs catégories et les relations avec les leads
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Group_Manager {
    
    /**
     * Créer un nouveau groupe
     * 
     * @param string $name Nom du groupe
     * @param string $description Description du groupe
     * @return array Résultat avec success et group_id
     */
    public static function create_group($name, $description = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_groups';
        
        // Vérifier si le nom existe déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE name = %s",
            $name
        ));
        
        if ($existing) {
            return array(
                'success' => false,
                'message' => 'Un groupe avec ce nom existe déjà.'
            );
        }
        
        // Insérer le nouveau groupe
        $result = $wpdb->insert(
            $table,
            array(
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result) {
            $group_id = $wpdb->insert_id;
            
            do_action('lc_group_created', $group_id, $name);
            
            return array(
                'success' => true,
                'message' => 'Groupe créé avec succès.',
                'group_id' => $group_id
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la création du groupe.'
        );
    }
    
    /**
     * Mettre à jour un groupe
     * 
     * @param int $group_id ID du groupe
     * @param array $data Données à mettre à jour
     * @return array Résultat
     */
    public static function update_group($group_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_groups';
        
        $update_data = array();
        
        if (isset($data['name'])) {
            // Vérifier que le nom n'est pas déjà utilisé par un autre groupe
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s AND id != %d",
                $data['name'],
                $group_id
            ));
            
            if ($existing) {
                return array(
                    'success' => false,
                    'message' => 'Un autre groupe utilise déjà ce nom.'
                );
            }
            
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (empty($update_data)) {
            return array(
                'success' => false,
                'message' => 'Aucune donnée à mettre à jour.'
            );
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $group_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            do_action('lc_group_updated', $group_id, $data);
            
            return array(
                'success' => true,
                'message' => 'Groupe mis à jour avec succès.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du groupe.'
        );
    }
    
    /**
     * Supprimer un groupe
     * 
     * @param int $group_id ID du groupe
     * @return array Résultat
     */
    public static function delete_group($group_id) {
        global $wpdb;
        
        $table_groups = $wpdb->prefix . 'lc_groups';
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        $table_group_categories = $wpdb->prefix . 'lc_group_categories';
        
        // Supprimer toutes les relations leads <-> groupe
        $wpdb->delete($table_lead_groups, array('group_id' => $group_id), array('%d'));
        
        // Supprimer toutes les relations groupe <-> catégories
        $wpdb->delete($table_group_categories, array('group_id' => $group_id), array('%d'));
        
        // Supprimer le groupe
        $result = $wpdb->delete($table_groups, array('id' => $group_id), array('%d'));
        
        if ($result) {
            do_action('lc_group_deleted', $group_id);
            
            return array(
                'success' => true,
                'message' => 'Groupe supprimé avec succès.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la suppression du groupe.'
        );
    }
    
    /**
     * Récupérer un groupe par son ID
     * 
     * @param int $group_id ID du groupe
     * @return object|null Objet groupe ou null
     */
    public static function get_group($group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_groups';
        
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $group_id
        ));
        
        if ($group) {
            // Ajouter le nombre de leads dans ce groupe
            $group->lead_count = self::count_leads_in_group($group_id);
            
            // Ajouter les catégories assignées
            $group->categories = self::get_group_categories($group_id);
        }
        
        return $group;
    }
    
    /**
     * Récupérer tous les groupes
     * 
     * @param array $args Arguments optionnels
     * @return array Liste des groupes
     */
    public static function get_all_groups($args = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_groups';
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        
        // Recherche par nom
        if (!empty($args['search'])) {
            $sql .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($args['search']) . '%');
        }
        
        // Tri
        $sql .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        
        $groups = $wpdb->get_results($sql);
        
        // Enrichir chaque groupe avec le nombre de leads et les catégories
        foreach ($groups as $group) {
            $group->lead_count = self::count_leads_in_group($group->id);
            $group->categories = self::get_group_categories($group->id);
        }
        
        return $groups;
    }
    
    /**
     * Assigner une catégorie WordPress à un groupe
     * 
     * @param int $group_id ID du groupe
     * @param int $category_id ID de la catégorie WordPress
     * @return bool Succès ou échec
     */
    public static function assign_category_to_group($group_id, $category_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_group_categories';
        
        // Vérifier si la relation existe déjà
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE group_id = %d AND category_id = %d",
            $group_id,
            $category_id
        ));
        
        if ($exists) {
            return true; // Déjà assignée
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'group_id' => $group_id,
                'category_id' => $category_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
        
        if ($result) {
            do_action('lc_category_assigned_to_group', $group_id, $category_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Retirer une catégorie d'un groupe
     * 
     * @param int $group_id ID du groupe
     * @param int $category_id ID de la catégorie WordPress
     * @return bool Succès ou échec
     */
    public static function remove_category_from_group($group_id, $category_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_group_categories';
        
        $result = $wpdb->delete(
            $table,
            array(
                'group_id' => $group_id,
                'category_id' => $category_id
            ),
            array('%d', '%d')
        );
        
        if ($result) {
            do_action('lc_category_removed_from_group', $group_id, $category_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Obtenir toutes les catégories d'un groupe
     * 
     * @param int $group_id ID du groupe
     * @return array Liste des IDs de catégories
     */
    public static function get_group_categories($group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_group_categories';
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT category_id FROM $table WHERE group_id = %d ORDER BY category_id ASC",
            $group_id
        ));
        
        return array_map('intval', $results);
    }
    
    /**
     * Mettre à jour toutes les catégories d'un groupe
     * Remplace toutes les catégories existantes par la nouvelle liste
     * 
     * @param int $group_id ID du groupe
     * @param array $category_ids Liste des IDs de catégories
     * @return array Résultat
     */
    public static function update_group_categories($group_id, $category_ids) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_group_categories';
        
        // Supprimer toutes les catégories actuelles
        $wpdb->delete($table, array('group_id' => $group_id), array('%d'));
        
        // Ajouter les nouvelles catégories
        if (!empty($category_ids)) {
            foreach ($category_ids as $category_id) {
                self::assign_category_to_group($group_id, intval($category_id));
            }
        }
        
        do_action('lc_group_categories_updated', $group_id, $category_ids);
        
        return array(
            'success' => true,
            'message' => 'Catégories du groupe mises à jour avec succès.',
            'category_count' => count($category_ids)
        );
    }
    
    /**
     * Obtenir tous les leads d'un groupe
     * 
     * @param int $group_id ID du groupe
     * @param array $args Arguments optionnels
     * @return array Liste des leads
     */
    public static function get_leads_in_group($group_id, $args = array()) {
        global $wpdb;
        
        $table_leads = $wpdb->prefix . 'lc_leads';
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        
        $defaults = array(
            'status' => 'active',
            'limit' => null,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT l.* FROM $table_leads l 
                INNER JOIN $table_lead_groups lg ON l.id = lg.lead_id 
                WHERE lg.group_id = %d";
        
        $sql_params = array($group_id);
        
        // Filtrer par statut si spécifié
        if (!empty($args['status'])) {
            $sql .= " AND l.status = %s";
            $sql_params[] = $args['status'];
        }
        
        // Tri
        $sql .= " ORDER BY l.created_at DESC";
        
        // Limite et pagination
        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $sql_params[] = $args['limit'];
            $sql_params[] = $args['offset'];
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $sql_params));
    }
    
    /**
     * Compter le nombre de leads dans un groupe
     * 
     * @param int $group_id ID du groupe
     * @param string $status Statut des leads (optionnel)
     * @return int Nombre de leads
     */
    public static function count_leads_in_group($group_id, $status = '') {
        global $wpdb;
        
        $table_leads = $wpdb->prefix . 'lc_leads';
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        
        $sql = "SELECT COUNT(*) FROM $table_leads l 
                INNER JOIN $table_lead_groups lg ON l.id = lg.lead_id 
                WHERE lg.group_id = %d";
        
        $sql_params = array($group_id);
        
        if (!empty($status)) {
            $sql .= " AND l.status = %s";
            $sql_params[] = $status;
        }
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $sql_params)));
    }
    
    /**
     * Obtenir tous les leads ayant une catégorie spécifique
     * (directement ou via un groupe)
     * 
     * @param int $category_id ID de la catégorie WordPress
     * @param array $args Arguments optionnels
     * @return array Liste des leads
     */
    public static function get_leads_for_category($category_id, $args = array()) {
        global $wpdb;
        
        $table_leads = $wpdb->prefix . 'lc_leads';
        $table_lead_categories = $wpdb->prefix . 'lc_lead_categories';
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        $table_group_categories = $wpdb->prefix . 'lc_group_categories';
        
        $defaults = array(
            'status' => 'active'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Leads avec la catégorie assignée directement OU via un groupe
        $sql = "SELECT DISTINCT l.* FROM $table_leads l 
                WHERE l.status = %s
                AND (
                    -- Catégorie assignée directement au lead
                    l.id IN (
                        SELECT lead_id FROM $table_lead_categories 
                        WHERE category_id = %d
                    )
                    OR
                    -- Lead dans un groupe ayant cette catégorie
                    l.id IN (
                        SELECT lg.lead_id FROM $table_lead_groups lg
                        INNER JOIN $table_group_categories gc ON lg.group_id = gc.group_id
                        WHERE gc.category_id = %d
                    )
                )
                ORDER BY l.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['status'], $category_id, $category_id));
    }
    
    /**
     * Obtenir les statistiques d'un groupe
     * 
     * @param int $group_id ID du groupe
     * @return array Statistiques
     */
    public static function get_group_stats($group_id) {
        return array(
            'total_leads' => self::count_leads_in_group($group_id),
            'active_leads' => self::count_leads_in_group($group_id, 'active'),
            'pending_leads' => self::count_leads_in_group($group_id, 'pending'),
            'unsubscribed_leads' => self::count_leads_in_group($group_id, 'unsubscribed'),
            'categories_count' => count(self::get_group_categories($group_id))
        );
    }
    
    /**
     * AJAX : Créer un nouveau groupe
     * Hook: wp_ajax_lc_create_group
     */
    public static function ajax_create_group() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission refusée.'));
        }
        
        // Vérifier le nonce
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Le nom du groupe est requis.'));
        }
        
        $result = self::create_group($name, $description);
        
        if ($result['success']) {
            // Assigner les catégories si fournies
            if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
                $category_ids = array_map('intval', $_POST['category_ids']);
                self::update_group_categories($result['group_id'], $category_ids);
            }
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX : Supprimer un groupe
     * Hook: wp_ajax_lc_delete_group
     */
    public static function ajax_delete_group() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission refusée.'));
        }
        
        // Vérifier le nonce
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (empty($group_id)) {
            wp_send_json_error(array('message' => 'ID du groupe manquant.'));
        }
        
        $result = self::delete_group($group_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX : Mettre à jour les catégories d'un groupe
     * Hook: wp_ajax_lc_update_group_categories
     */
    public static function ajax_update_group_categories() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission refusée.'));
        }
        
        // Vérifier le nonce
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : array();
        
        if (empty($group_id)) {
            wp_send_json_error(array('message' => 'ID du groupe manquant.'));
        }
        
        $result = self::update_group_categories($group_id, $category_ids);
        
        wp_send_json_success($result);
    }
}
