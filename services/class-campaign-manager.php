<?php
/**
 * Fichier: services/class-campaign-manager.php
 * Service de gestion des campagnes d'emailing
 * Gère la création, l'envoi et le suivi des campagnes email
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Campaign_Manager {
    
    /**
     * Créer une nouvelle campagne
     * 
     * @param array $data Données de la campagne
     * @return array Résultat avec success et campaign_id
     */
    public static function create_campaign($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        // Valider les données obligatoires
        if (empty($data['name']) || empty($data['subject']) || empty($data['body'])) {
            return array(
                'success' => false,
                'message' => 'Le nom, le sujet et le contenu sont requis.'
            );
        }
        
        // Préparer les données d'insertion
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => wp_kses_post($data['body']),
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'draft',
            'target_type' => isset($data['target_type']) ? sanitize_text_field($data['target_type']) : 'all',
            'target_ids' => isset($data['target_ids']) ? json_encode($data['target_ids']) : null,
            'scheduled_at' => isset($data['scheduled_at']) ? sanitize_text_field($data['scheduled_at']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result) {
            $campaign_id = $wpdb->insert_id;
            
            // Calculer le nombre de destinataires
            $recipients_count = self::count_campaign_recipients($campaign_id);
            $wpdb->update(
                $table,
                array('total_recipients' => $recipients_count),
                array('id' => $campaign_id),
                array('%d'),
                array('%d')
            );
            
            do_action('lc_campaign_created', $campaign_id, $data);
            
            return array(
                'success' => true,
                'message' => 'Campagne créée avec succès.',
                'campaign_id' => $campaign_id,
                'recipients_count' => $recipients_count
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la création de la campagne.'
        );
    }
    
    /**
     * Mettre à jour une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @param array $data Données à mettre à jour
     * @return array Résultat
     */
    public static function update_campaign($campaign_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        // Vérifier que la campagne existe et n'est pas déjà envoyée
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign) {
            return array(
                'success' => false,
                'message' => 'Campagne introuvable.'
            );
        }
        
        if (in_array($campaign->status, array('sent', 'sending'))) {
            return array(
                'success' => false,
                'message' => 'Impossible de modifier une campagne en cours d\'envoi ou déjà envoyée.'
            );
        }
        
        $update_data = array();
        
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['subject'])) $update_data['subject'] = sanitize_text_field($data['subject']);
        if (isset($data['body'])) $update_data['body'] = wp_kses_post($data['body']);
        if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);
        if (isset($data['target_type'])) $update_data['target_type'] = sanitize_text_field($data['target_type']);
        if (isset($data['target_ids'])) $update_data['target_ids'] = json_encode($data['target_ids']);
        if (isset($data['scheduled_at'])) $update_data['scheduled_at'] = sanitize_text_field($data['scheduled_at']);
        
        if (empty($update_data)) {
            return array(
                'success' => false,
                'message' => 'Aucune donnée à mettre à jour.'
            );
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $campaign_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            // Recalculer le nombre de destinataires si la cible a changé
            if (isset($data['target_type']) || isset($data['target_ids'])) {
                $recipients_count = self::count_campaign_recipients($campaign_id);
                $wpdb->update(
                    $table,
                    array('total_recipients' => $recipients_count),
                    array('id' => $campaign_id),
                    array('%d'),
                    array('%d')
                );
            }
            
            do_action('lc_campaign_updated', $campaign_id, $data);
            
            return array(
                'success' => true,
                'message' => 'Campagne mise à jour avec succès.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de la campagne.'
        );
    }
    
    /**
     * Supprimer une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @return array Résultat
     */
    public static function delete_campaign($campaign_id) {
        global $wpdb;
        
        $table_campaigns = $wpdb->prefix . 'lc_campaigns';
        $table_logs = $wpdb->prefix . 'lc_campaign_logs';
        
        // Supprimer tous les logs associés
        $wpdb->delete($table_logs, array('campaign_id' => $campaign_id), array('%d'));
        
        // Supprimer la campagne
        $result = $wpdb->delete($table_campaigns, array('id' => $campaign_id), array('%d'));
        
        if ($result) {
            do_action('lc_campaign_deleted', $campaign_id);
            
            return array(
                'success' => true,
                'message' => 'Campagne supprimée avec succès.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la suppression de la campagne.'
        );
    }
    
    /**
     * Récupérer une campagne par son ID
     * 
     * @param int $campaign_id ID de la campagne
     * @return object|null Objet campagne ou null
     */
    public static function get_campaign($campaign_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $campaign_id
        ));
        
        if ($campaign && $campaign->target_ids) {
            $campaign->target_ids = json_decode($campaign->target_ids, true);
        }
        
        return $campaign;
    }
    
    /**
     * Récupérer toutes les campagnes avec filtres
     * 
     * @param array $filters Filtres optionnels
     * @return array Liste des campagnes
     */
    public static function get_all_campaigns($filters = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        $where = array('1=1');
        
        // Filtrer par statut
        if (!empty($filters['status'])) {
            $where[] = $wpdb->prepare("status = %s", $filters['status']);
        }
        
        // Recherche par nom
        if (!empty($filters['search'])) {
            $where[] = $wpdb->prepare("name LIKE %s", '%' . $wpdb->esc_like($filters['search']) . '%');
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC";
        
        $campaigns = $wpdb->get_results($sql);
        
        foreach ($campaigns as $campaign) {
            if ($campaign->target_ids) {
                $campaign->target_ids = json_decode($campaign->target_ids, true);
            }
        }
        
        return $campaigns;
    }
    
    /**
     * Envoyer une campagne immédiatement
     * 
     * @param int $campaign_id ID de la campagne
     * @return array Résultat
     */
    public static function send_campaign($campaign_id) {
        global $wpdb;
        
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign) {
            return array(
                'success' => false,
                'message' => 'Campagne introuvable.'
            );
        }
        
        // Vérifier le statut
        if (!in_array($campaign->status, array('draft', 'scheduled'))) {
            return array(
                'success' => false,
                'message' => 'Cette campagne ne peut pas être envoyée.'
            );
        }
        
        // Mettre à jour le statut en "sending"
        $wpdb->update(
            $wpdb->prefix . 'lc_campaigns',
            array('status' => 'sending'),
            array('id' => $campaign_id),
            array('%s'),
            array('%d')
        );
        
        // Récupérer les destinataires
        $recipients = self::get_campaign_recipients($campaign_id);
        
        if (empty($recipients)) {
            $wpdb->update(
                $wpdb->prefix . 'lc_campaigns',
                array('status' => 'failed'),
                array('id' => $campaign_id),
                array('%s'),
                array('%d')
            );
            
            return array(
                'success' => false,
                'message' => 'Aucun destinataire trouvé pour cette campagne.'
            );
        }
        
        $total_sent = 0;
        $total_failed = 0;
        
        // Envoyer l'email à chaque destinataire
        foreach ($recipients as $lead) {
            
            // Créer une entrée dans les logs
            $log_id = self::create_campaign_log($campaign_id, $lead->id);
            
            // Parser le template avec les données du lead
            $variables = array(
                'first_name' => $lead->first_name ?: 'Utilisateur',
                'last_name' => $lead->last_name ?: '',
                'email' => $lead->email,
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url('/'),
                'unsubscribe_url' => self::get_unsubscribe_url($lead->id)
            );
            
            $body = LC_Template_Parser::parse($campaign->body, $variables);
            $subject = LC_Template_Parser::parse($campaign->subject, $variables);
            
            // Envoyer l'email
            $sent = LC_Email_Sender::send($lead->email, $subject, $body);
            
            if ($sent) {
                $total_sent++;
                self::update_campaign_log($log_id, 'sent');
            } else {
                $total_failed++;
                self::update_campaign_log($log_id, 'failed', 'Erreur lors de l\'envoi');
            }
            
            // Pause pour éviter la surcharge du serveur
            usleep(100000); // 0.1 seconde
        }
        
        // Mettre à jour la campagne
        $wpdb->update(
            $wpdb->prefix . 'lc_campaigns',
            array(
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'total_sent' => $total_sent,
                'total_failed' => $total_failed
            ),
            array('id' => $campaign_id),
            array('%s', '%s', '%d', '%d'),
            array('%d')
        );
        
        do_action('lc_campaign_sent', $campaign_id, $total_sent, $total_failed);
        
        return array(
            'success' => true,
            'message' => 'Campagne envoyée avec succès.',
            'total_sent' => $total_sent,
            'total_failed' => $total_failed
        );
    }
    
    /**
     * Programmer l'envoi d'une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @param string $datetime Date et heure d'envoi (format MySQL)
     * @return array Résultat
     */
    public static function schedule_campaign($campaign_id, $datetime) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'scheduled',
                'scheduled_at' => $datetime
            ),
            array('id' => $campaign_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('lc_campaign_scheduled', $campaign_id, $datetime);
            
            return array(
                'success' => true,
                'message' => 'Campagne programmée avec succès.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erreur lors de la programmation de la campagne.'
        );
    }
    
    /**
     * Traiter les campagnes programmées
     * Appelé par le cron job WordPress
     * Hook: lc_send_scheduled_campaigns
     */
    public static function process_scheduled_campaigns() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaigns';
        
        // Récupérer toutes les campagnes programmées dont la date d'envoi est passée
        $campaigns = $wpdb->get_results(
            "SELECT id FROM $table 
             WHERE status = 'scheduled' 
             AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC"
        );
        
        foreach ($campaigns as $campaign) {
            self::send_campaign($campaign->id);
        }
    }
    
    /**
     * Récupérer les statistiques d'une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @return array Statistiques
     */
    public static function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'lc_campaign_logs';
        
        $total_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ));
        
        $total_failed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE campaign_id = %d AND status = 'failed'",
            $campaign_id
        ));
        
        $total_opened = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE campaign_id = %d AND opened_at IS NOT NULL",
            $campaign_id
        ));
        
        $total_clicked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE campaign_id = %d AND clicked_at IS NOT NULL",
            $campaign_id
        ));
        
        // Calculer les taux
        $open_rate = $total_sent > 0 ? round(($total_opened / $total_sent) * 100, 2) : 0;
        $click_rate = $total_sent > 0 ? round(($total_clicked / $total_sent) * 100, 2) : 0;
        
        return array(
            'total_sent' => intval($total_sent),
            'total_failed' => intval($total_failed),
            'total_opened' => intval($total_opened),
            'total_clicked' => intval($total_clicked),
            'open_rate' => $open_rate,
            'click_rate' => $click_rate
        );
    }
    
    /**
     * Récupérer les destinataires d'une campagne selon le type de cible
     * 
     * @param int $campaign_id ID de la campagne
     * @return array Liste des leads
     */
    private static function get_campaign_recipients($campaign_id) {
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign) {
            return array();
        }
        
        $recipients = array();
        
        switch ($campaign->target_type) {
            case 'all':
                // Tous les leads actifs
                $recipients = LC_Lead_Manager::get_leads(array('status' => 'active'));
                break;
                
            case 'groups':
                // Leads dans des groupes spécifiques
                if (!empty($campaign->target_ids)) {
                    foreach ($campaign->target_ids as $group_id) {
                        $leads = LC_Group_Manager::get_leads_in_group($group_id, array('status' => 'active'));
                        $recipients = array_merge($recipients, $leads);
                    }
                }
                break;
                
            case 'categories':
                // Leads ayant des catégories spécifiques
                if (!empty($campaign->target_ids)) {
                    foreach ($campaign->target_ids as $category_id) {
                        $leads = LC_Group_Manager::get_leads_for_category($category_id, array('status' => 'active'));
                        $recipients = array_merge($recipients, $leads);
                    }
                }
                break;
                
            case 'custom':
                // Liste personnalisée de lead IDs
                if (!empty($campaign->target_ids)) {
                    foreach ($campaign->target_ids as $lead_id) {
                        $lead = LC_Lead_Manager::get_lead($lead_id);
                        if ($lead && $lead->status === 'active') {
                            $recipients[] = $lead;
                        }
                    }
                }
                break;
        }
        
        // Supprimer les doublons (même email)
        $unique_recipients = array();
        $seen_emails = array();
        
        foreach ($recipients as $lead) {
            if (!in_array($lead->email, $seen_emails)) {
                $unique_recipients[] = $lead;
                $seen_emails[] = $lead->email;
            }
        }
        
        return $unique_recipients;
    }
    
    /**
     * Compter les destinataires d'une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @return int Nombre de destinataires
     */
    private static function count_campaign_recipients($campaign_id) {
        $recipients = self::get_campaign_recipients($campaign_id);
        return count($recipients);
    }
    
    /**
     * Créer une entrée de log pour l'envoi d'une campagne
     * 
     * @param int $campaign_id ID de la campagne
     * @param int $lead_id ID du lead
     * @return int ID du log créé
     */
    private static function create_campaign_log($campaign_id, $lead_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaign_logs';
        
        $wpdb->insert(
            $table,
            array(
                'campaign_id' => $campaign_id,
                'lead_id' => $lead_id,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Mettre à jour un log de campagne
     * 
     * @param int $log_id ID du log
     * @param string $status Nouveau statut (sent, failed, opened, clicked)
     * @param string $error_message Message d'erreur optionnel
     */
    private static function update_campaign_log($log_id, $status, $error_message = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_campaign_logs';
        
        $update_data = array('status' => $status);
        
        if ($status === 'sent') {
            $update_data['sent_at'] = current_time('mysql');
        }
        
        if ($status === 'opened') {
            $update_data['opened_at'] = current_time('mysql');
        }
        
        if ($status === 'clicked') {
            $update_data['clicked_at'] = current_time('mysql');
        }
        
        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }
        
        $wpdb->update(
            $table,
            $update_data,
            array('id' => $log_id),
            null,
            array('%d')
        );
    }
    
    /**
     * Générer une URL de désinscription pour un lead
     * 
     * @param int $lead_id ID du lead
     * @return string URL de désinscription
     */
    private static function get_unsubscribe_url($lead_id) {
        return add_query_arg(array(
            'lc_action' => 'unsubscribe',
            'lead_id' => $lead_id,
            'token' => wp_hash($lead_id . 'unsubscribe')
        ), home_url('/'));
    }
    
    /**
     * AJAX : Envoyer une campagne immédiatement
     * Hook: wp_ajax_lc_send_campaign
     */
    public static function ajax_send_campaign() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission refusée.'));
        }
        
        // Vérifier le nonce
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        
        if (empty($campaign_id)) {
            wp_send_json_error(array('message' => 'ID de campagne manquant.'));
        }
        
        $result = self::send_campaign($campaign_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX : Programmer une campagne
     * Hook: wp_ajax_lc_schedule_campaign
     */
    public static function ajax_schedule_campaign() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission refusée.'));
        }
        
        // Vérifier le nonce
        check_ajax_referer('lc_admin_nonce', 'nonce');
        
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $datetime = isset($_POST['scheduled_at']) ? sanitize_text_field($_POST['scheduled_at']) : '';
        
        if (empty($campaign_id) || empty($datetime)) {
            wp_send_json_error(array('message' => 'Paramètres manquants.'));
        }
        
        $result = self::schedule_campaign($campaign_id, $datetime);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
