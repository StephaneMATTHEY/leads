<?php
/**
 * Fichier: services/class-post-notification-handler.php
 * Service de gestion des notifications automatiques d'articles par catégorie
 * 
 * FONCTIONNALITÉ PRINCIPALE :
 * Quand un article WordPress est publié dans une catégorie,
 * tous les leads associés à cette catégorie reçoivent automatiquement un email
 * avec le template configuré pour cette catégorie.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Post_Notification_Handler {
    
    /**
     * Hook WordPress - Détecte la publication d'un article
     * Appelé automatiquement quand le statut d'un post change
     */
    public static function handle_post_publish($new_status, $old_status, $post) {
        
        // Vérifier que c'est une publication (pas un brouillon, pas une mise à jour)
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Vérifier que c'est un article (pas une page, pas un custom post type)
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Récupérer les catégories de l'article
        $categories = wp_get_post_categories($post->ID);
        
        if (empty($categories)) {
            return;
        }
        
        // Pour chaque catégorie, envoyer les notifications
        foreach ($categories as $category_id) {
            self::send_notifications_for_category($post, $category_id);
        }
    }
    
    /**
     * Envoyer les notifications pour une catégorie spécifique
     */
    private static function send_notifications_for_category($post, $category_id) {
        
        // Récupérer le template pour cette catégorie
        $template = self::get_template_for_category($category_id);
        
        if (!$template || !$template->is_active) {
            return; // Pas de template actif pour cette catégorie
        }
        
        // Récupérer tous les leads associés à cette catégorie
        $leads = self::get_leads_for_category($category_id);
        
        if (empty($leads)) {
            return; // Pas de leads pour cette catégorie
        }
        
        // Préparer les données de l'article
        $post_data = array(
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post->ID),
            'post_excerpt' => get_the_excerpt($post),
            'post_content' => $post->post_content,
            'post_date' => get_the_date('', $post),
            'post_author' => get_the_author_meta('display_name', $post->post_author),
            'category_name' => get_cat_name($category_id),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        );
        
        // Envoyer l'email à chaque lead
        foreach ($leads as $lead) {
            self::send_notification_to_lead($lead, $template, $post_data);
        }
    }
    
    /**
     * Envoyer la notification à un lead
     */
    private static function send_notification_to_lead($lead, $template, $post_data) {
        
        // Ajouter les données du lead aux variables disponibles
        $variables = array_merge($post_data, array(
            'lead_email' => $lead->email,
            'lead_first_name' => $lead->first_name,
            'lead_last_name' => $lead->last_name,
        ));
        
        // Parser le sujet avec les variables
        $subject = LC_Template_Parser::parse($template->subject, $variables);
        
        // Parser le corps avec les variables
        $body = LC_Template_Parser::parse($template->body, $variables);
        
        // Envoyer l'email
        $result = LC_Email_Sender::send(
            $lead->email,
            $subject,
            $body,
            array('type' => 'post_notification', 'post_id' => $post_data['post_url'])
        );
        
        // Logger l'envoi (optionnel)
        do_action('lc_post_notification_sent', $lead->id, $template->id, $result);
        
        return $result;
    }
    
    /**
     * Récupérer le template pour une catégorie
     */
    public static function get_template_for_category($category_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_notification_templates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE category_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
            $category_id
        ));
    }
    
    /**
     * Récupérer tous les leads associés à une catégorie
     * Inclut :
     * - Les leads qui ont la catégorie assignée individuellement
     * - Les leads des groupes qui ont la catégorie assignée
     */
    private static function get_leads_for_category($category_id) {
        global $wpdb;
        
        $table_leads = $wpdb->prefix . 'lc_leads';
        $table_lead_categories = $wpdb->prefix . 'lc_lead_categories';
        $table_lead_groups = $wpdb->prefix . 'lc_lead_groups';
        $table_groups = $wpdb->prefix . 'lc_groups';
        
        // Requête SQL complexe pour récupérer tous les leads concernés
        $sql = "
            SELECT DISTINCT l.*
            FROM $table_leads l
            WHERE l.status = 'active'
            AND (
                -- Leads avec catégorie assignée individuellement
                l.id IN (SELECT lead_id FROM $table_lead_categories WHERE category_id = %d)
                OR
                -- Leads dans des groupes avec cette catégorie
                l.id IN (
                    SELECT lg.lead_id 
                    FROM $table_lead_groups lg
                    INNER JOIN $table_groups g ON lg.group_id = g.id
                    WHERE g.id IN (
                        SELECT DISTINCT group_id
                        FROM {$wpdb->prefix}lc_group_categories
                        WHERE category_id = %d
                    )
                )
            )
        ";
        
        return $wpdb->get_results($wpdb->prepare($sql, $category_id, $category_id));
    }
    
    /**
     * Créer ou mettre à jour un template
     */
    public static function save_template($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_notification_templates';
        
        $template_data = array(
            'category_id' => intval($data['category_id']),
            'name' => sanitize_text_field($data['name']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => wp_kses_post($data['body']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            // Mise à jour
            $wpdb->update(
                $table,
                $template_data,
                array('id' => intval($data['id']))
            );
            $template_id = intval($data['id']);
        } else {
            // Création
            $wpdb->insert($table, $template_data);
            $template_id = $wpdb->insert_id;
        }
        
        return $template_id;
    }
    
    /**
     * Récupérer tous les templates
     */
    public static function get_all_templates() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_notification_templates';
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY category_id, created_at DESC");
    }
    
    /**
     * Récupérer un template par ID
     */
    public static function get_template($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_notification_templates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $template_id
        ));
    }
    
    /**
     * Supprimer un template
     */
    public static function delete_template($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_notification_templates';
        
        return $wpdb->delete($table, array('id' => $template_id), array('%d'));
    }
    
    /**
     * AJAX - Sauvegarder un template
     */
    public static function ajax_save_template() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $data = array(
            'id' => isset($_POST['template_id']) ? intval($_POST['template_id']) : 0,
            'category_id' => isset($_POST['category_id']) ? intval($_POST['category_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'subject' => isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '',
            'body' => isset($_POST['body']) ? wp_kses_post($_POST['body']) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if (empty($data['category_id']) || empty($data['name']) || empty($data['subject']) || empty($data['body'])) {
            wp_send_json_error(array('message' => 'Tous les champs sont requis.'));
        }
        
        $template_id = self::save_template($data);
        
        if ($template_id) {
            wp_send_json_success(array(
                'message' => 'Template enregistré avec succès.',
                'template_id' => $template_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de l\'enregistrement.'));
        }
    }
    
    /**
     * AJAX - Supprimer un template
     */
    public static function ajax_delete_template() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => 'ID invalide.'));
        }
        
        $result = self::delete_template($template_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Template supprimé avec succès.'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression.'));
        }
    }
    
    /**
     * AJAX - Tester l'envoi d'une notification
     */
    public static function ajax_test_notification() {
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        
        if (!$test_email || !is_email($test_email)) {
            wp_send_json_error(array('message' => 'Email invalide.'));
        }
        
        $template = self::get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(array('message' => 'Template introuvable.'));
        }
        
        // Variables de test
        $variables = array(
            'post_title' => 'Titre de l\'article de test',
            'post_url' => home_url('/article-test'),
            'post_excerpt' => 'Ceci est un extrait de test pour démontrer le template.',
            'post_date' => date('d/m/Y'),
            'post_author' => 'Auteur Test',
            'category_name' => get_cat_name($template->category_id),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'lead_email' => $test_email,
            'lead_first_name' => 'Prénom',
            'lead_last_name' => 'Nom',
        );
        
        $subject = LC_Template_Parser::parse($template->subject, $variables);
        $body = LC_Template_Parser::parse($template->body, $variables);
        
        $result = LC_Email_Sender::send($test_email, $subject, $body);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Email de test envoyé avec succès à ' . $test_email));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de l\'envoi de l\'email de test.'));
        }
    }
}
