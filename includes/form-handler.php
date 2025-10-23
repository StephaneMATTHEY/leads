<?php
/**
 * Fichier: includes/form-handler.php
 * Traitement des formulaires de collecte de leads
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Form_Handler {
    
    /**
     * Sauvegarder un lead
     */
    public static function save_lead($email) {
        // Valider l'email
        if (!is_email($email)) {
            return array(
                'success' => false,
                'message' => 'Adresse email invalide.'
            );
        }
        
        // Récupérer l'IP et le User Agent
        $ip_address = self::get_client_ip();
        $user_agent = self::get_user_agent();
        
        // Vérifier les limitations anti-spam
        if (!self::check_rate_limit($ip_address)) {
            return array(
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer plus tard.'
            );
        }
        
        // Insérer dans la base de données
        $result = LC_Database::insert_lead($email, $ip_address, $user_agent);
        
        // Si succès, envoyer une notification (optionnel)
        if ($result['success']) {
            self::send_notification($email);
            
            // Hook pour permettre aux développeurs d'ajouter des actions
            do_action('lead_collector_new_lead', $email, $result['lead_id']);
        }
        
        return $result;
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
    
    /**
     * Vérifier la limitation de taux (anti-spam)
     */
    private static function check_rate_limit($ip_address) {
        $transient_key = 'lc_rate_limit_' . md5($ip_address);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // Première tentative
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($attempts >= 5) {
            // Trop de tentatives
            return false;
        }
        
        // Incrémenter les tentatives
        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Envoyer une notification email à l'admin
     */
    private static function send_notification($email) {
        // Récupérer l'email de l'admin
        $admin_email = get_option('admin_email');
        
        // Option pour activer/désactiver les notifications
        $notifications_enabled = get_option('lc_enable_notifications', true);
        
        if (!$notifications_enabled) {
            return;
        }
        
        $subject = '[' . get_bloginfo('name') . '] Nouveau lead collecté';
        
        $message = "Un nouveau lead a été collecté sur votre site.\n\n";
        $message .= "Email : " . $email . "\n";
        $message .= "Date : " . date('d/m/Y à H:i:s') . "\n";
        $message .= "IP : " . self::get_client_ip() . "\n\n";
        $message .= "Voir tous les leads : " . admin_url('admin.php?page=lead-collector') . "\n";
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Valider le honeypot (anti-bot)
     */
    public static function validate_honeypot($honeypot_value) {
        // Si le champ honeypot est rempli, c'est un bot
        return empty($honeypot_value);
    }
    
    /**
     * Sanitize et valider les données
     */
    public static function sanitize_data($data) {
        $sanitized = array();
        
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }
        
        if (isset($data['terms'])) {
            $sanitized['terms'] = (bool) $data['terms'];
        }
        
        return $sanitized;
    }
    
    /**
     * Vérifier si un email existe déjà
     */
    public static function email_exists($email) {
        $lead = LC_Database::get_lead_by_email($email);
        return !empty($lead);
    }
}
