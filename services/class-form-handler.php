<?php
/**
 * Fichier: services/class-form-handler.php
 * Service de gestion des formulaires frontend
 * Gère le rendu, la soumission, la validation et le double opt-in
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Form_Handler {
    
    /**
     * Générer le HTML du formulaire via shortcode
     * 
     * @param array $atts Attributs du shortcode
     * @return string HTML du formulaire
     */
    public static function render_form($atts) {
        // Paramètres par défaut du shortcode
        $atts = shortcode_atts(array(
            'id' => null,                    // ID du formulaire personnalisé
            'title' => 'Inscrivez-vous',     // Titre du formulaire
            'subtitle' => '',                // Sous-titre
            'style' => 'dark',               // Style: dark|light|custom
            'button_text' => 'S\'inscrire',  // Texte du bouton
            'redirect_url' => '',            // URL de redirection après succès
        ), $atts);
        
        // Si un ID est fourni, charger le formulaire depuis la BDD
        if ($atts['id']) {
            $form = self::get_form_by_id($atts['id']);
            if ($form) {
                $atts['title'] = $form->title ?: $atts['title'];
                $atts['subtitle'] = $form->subtitle ?: $atts['subtitle'];
                $atts['style'] = $form->style ?: $atts['style'];
                $atts['redirect_url'] = $form->redirect_url ?: $atts['redirect_url'];
                $custom_fields = json_decode($form->fields, true);
            }
        }
        
        // Champs par défaut si aucun formulaire personnalisé
        if (!isset($custom_fields)) {
            $custom_fields = array(
                array('type' => 'text', 'name' => 'first_name', 'label' => 'Prénom', 'required' => true),
                array('type' => 'text', 'name' => 'last_name', 'label' => 'Nom', 'required' => true),
                array('type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true),
                array('type' => 'tel', 'name' => 'phone', 'label' => 'Téléphone', 'required' => false),
            );
        }
        
        // Générer un ID unique pour ce formulaire
        $form_id_attr = 'lc-form-' . uniqid();
        
        // Classes CSS selon le style
        $style_class = 'lc-form-style-' . esc_attr($atts['style']);
        
        // Démarrer la génération du HTML
        ob_start();
        ?>
        
        <div class="lc-form-container <?php echo $style_class; ?>" data-form-id="<?php echo esc_attr($atts['id']); ?>">
            
            <?php if ($atts['title']): ?>
                <h3 class="lc-form-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <?php if ($atts['subtitle']): ?>
                <p class="lc-form-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
            <?php endif; ?>
            
            <form id="<?php echo esc_attr($form_id_attr); ?>" class="lc-form" method="post" data-redirect="<?php echo esc_url($atts['redirect_url']); ?>">
                
                <?php foreach ($custom_fields as $field): ?>
                    <div class="lc-form-field">
                        <label for="<?php echo esc_attr($field['name']); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <?php if (!empty($field['required'])): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($field['type'] === 'textarea'): ?>
                            <textarea 
                                id="<?php echo esc_attr($field['name']); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                <?php if (!empty($field['required'])): ?>required<?php endif; ?>
                                placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                            ></textarea>
                        <?php elseif ($field['type'] === 'select'): ?>
                            <select 
                                id="<?php echo esc_attr($field['name']); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>"
                                <?php if (!empty($field['required'])): ?>required<?php endif; ?>
                            >
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($field['options'] as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <input 
                                type="checkbox" 
                                id="<?php echo esc_attr($field['name']); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                value="1"
                                <?php if (!empty($field['required'])): ?>required<?php endif; ?>
                            >
                            <span class="checkbox-label"><?php echo esc_html($field['checkbox_label'] ?? ''); ?></span>
                        <?php else: ?>
                            <input 
                                type="<?php echo esc_attr($field['type']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                <?php if (!empty($field['required'])): ?>required<?php endif; ?>
                                placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Champ honeypot anti-spam (caché) -->
                <input type="text" name="lc_website" class="lc-honeypot" tabindex="-1" autocomplete="off">
                
                <!-- Bouton de soumission -->
                <button type="submit" class="lc-submit-btn">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
                
                <!-- Messages de retour -->
                <div class="lc-form-messages"></div>
                
                <!-- Nonce de sécurité -->
                <?php wp_nonce_field('lc_submit_form', 'lc_nonce'); ?>
            </form>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Traiter la soumission AJAX du formulaire
     * Hook: wp_ajax_lc_submit_lead et wp_ajax_nopriv_lc_submit_lead
     */
    public static function ajax_submit_lead() {
        
        // Vérifier le nonce de sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lc_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => 'Erreur de sécurité. Veuillez rafraîchir la page.'
            ));
        }
        
        // Honeypot anti-spam - Si rempli, c'est un bot
        if (!empty($_POST['lc_website'])) {
            wp_send_json_error(array(
                'message' => 'Erreur de validation.'
            ));
        }
        
        // Récupérer les données du formulaire
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        // Validation de l'email
        if (!self::validate_email($email)) {
            wp_send_json_error(array(
                'message' => 'Adresse email invalide.'
            ));
        }
        
        // Anti-spam : Vérifier le rate limit (5 tentatives/heure par IP)
        $ip_address = self::get_client_ip();
        if (!self::check_rate_limit($ip_address)) {
            wp_send_json_error(array(
                'message' => 'Trop de tentatives. Veuillez réessayer dans 1 heure.'
            ));
        }
        
        // Récupérer les champs personnalisés si présents
        $custom_fields = array();
        foreach ($_POST as $key => $value) {
            if (!in_array($key, array('email', 'first_name', 'last_name', 'phone', 'nonce', 'action', 'lc_website', 'lc_nonce'))) {
                $custom_fields[$key] = sanitize_text_field($value);
            }
        }
        
        // Préparer les données du lead
        $lead_data = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'source' => 'form',
            'ip_address' => $ip_address,
            'user_agent' => self::get_user_agent(),
            'custom_fields' => !empty($custom_fields) ? $custom_fields : null,
        );
        
        // Vérifier si le double opt-in est activé
        $double_optin_enabled = get_option('lc_enable_double_optin', false);
        
        if ($double_optin_enabled) {
            // Statut "pending" en attente de confirmation
            $lead_data['status'] = 'pending';
        } else {
            // Statut "active" directement
            $lead_data['status'] = 'active';
        }
        
        // Créer le lead via Lead_Manager
        $result = LC_Lead_Manager::create_lead($lead_data);
        
        if (!$result['success']) {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
        
        $lead_id = $result['lead_id'];
        
        // Si double opt-in activé, envoyer l'email de confirmation
        if ($double_optin_enabled) {
            self::send_double_optin_email($lead_id);
            
            wp_send_json_success(array(
                'message' => 'Un email de confirmation vous a été envoyé. Veuillez vérifier votre boîte de réception.',
                'lead_id' => $lead_id,
                'requires_confirmation' => true
            ));
        }
        
        // Envoyer une notification à l'admin si activé
        if (get_option('lc_enable_notifications', false)) {
            self::send_admin_notification($lead_id, $lead_data);
        }
        
        // Succès
        wp_send_json_success(array(
            'message' => 'Merci ! Votre inscription a été enregistrée avec succès.',
            'lead_id' => $lead_id,
            'requires_confirmation' => false
        ));
    }
    
    /**
     * Valider une adresse email
     * 
     * @param string $email Email à valider
     * @return bool True si valide, False sinon
     */
    public static function validate_email($email) {
        // Vérification de base
        if (empty($email) || !is_email($email)) {
            return false;
        }
        
        // Vérifier que le domaine existe (MX record)
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }
        
        // Liste noire de domaines jetables (exemple)
        $disposable_domains = array(
            'tempmail.com',
            'guerrillamail.com',
            'mailinator.com',
            '10minutemail.com',
            'trashmail.com'
        );
        
        if (in_array(strtolower($domain), $disposable_domains)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Vérifier le rate limit (anti-spam)
     * Limite : 5 tentatives par heure par IP
     * 
     * @param string $ip_address Adresse IP
     * @return bool True si autorisé, False si limite atteinte
     */
    public static function check_rate_limit($ip_address) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_leads';
        
        // Compter les tentatives de cette IP dans la dernière heure
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE ip_address = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip_address
        ));
        
        // Limite de 5 tentatives par heure
        if ($count >= 5) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Envoyer un email de double opt-in
     * 
     * @param int $lead_id ID du lead
     * @return bool Résultat de l'envoi
     */
    public static function send_double_optin_email($lead_id) {
        global $wpdb;
        
        // Récupérer les infos du lead
        $table = $wpdb->prefix . 'lc_leads';
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $lead_id
        ));
        
        if (!$lead) {
            return false;
        }
        
        // Générer un token de confirmation unique
        $token = wp_generate_password(32, false);
        
        // Sauvegarder le token en meta ou dans un champ custom
        update_option('lc_confirmation_token_' . $lead_id, $token);
        
        // URL de confirmation
        $confirmation_url = add_query_arg(array(
            'lc_action' => 'confirm',
            'token' => $token,
            'lead_id' => $lead_id
        ), home_url('/'));
        
        // Préparer le sujet et le corps de l'email
        $subject = get_option('lc_confirmation_subject', 'Confirmez votre inscription');
        
        $body = get_option('lc_confirmation_body', '
            <p>Bonjour {{first_name}},</p>
            <p>Merci de votre inscription ! Pour confirmer votre adresse email, veuillez cliquer sur le lien ci-dessous :</p>
            <p><a href="{{confirmation_url}}" style="background: var(--primary); color: white; padding: 12px 24px; text-decoration: none; border-radius: var(--radius); display: inline-block;">Confirmer mon inscription</a></p>
            <p>Si vous n\'avez pas demandé cette inscription, vous pouvez ignorer cet email.</p>
            <p>Cordialement,<br>L\'équipe {{site_name}}</p>
        ');
        
        // Parser les variables du template
        $variables = array(
            'first_name' => $lead->first_name ?: 'Utilisateur',
            'last_name' => $lead->last_name ?: '',
            'email' => $lead->email,
            'confirmation_url' => $confirmation_url,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/')
        );
        
        $body = LC_Template_Parser::parse($body, $variables);
        
        // Envoyer l'email via Email_Sender
        return LC_Email_Sender::send($lead->email, $subject, $body);
    }
    
    /**
     * Confirmer un lead via le token
     * Hook: init (détection du paramètre lc_action=confirm)
     */
    public static function confirm_lead() {
        // Vérifier si on est sur une page de confirmation
        if (!isset($_GET['lc_action']) || $_GET['lc_action'] !== 'confirm') {
            return;
        }
        
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
        
        if (empty($token) || empty($lead_id)) {
            wp_die('Lien de confirmation invalide.');
        }
        
        // Vérifier le token
        $stored_token = get_option('lc_confirmation_token_' . $lead_id);
        
        if ($stored_token !== $token) {
            wp_die('Token de confirmation invalide ou expiré.');
        }
        
        // Mettre à jour le statut du lead
        global $wpdb;
        $table = $wpdb->prefix . 'lc_leads';
        
        $wpdb->update(
            $table,
            array(
                'status' => 'active',
                'confirmed_at' => current_time('mysql')
            ),
            array('id' => $lead_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Supprimer le token
        delete_option('lc_confirmation_token_' . $lead_id);
        
        // Hook pour actions personnalisées après confirmation
        do_action('lc_lead_confirmed', $lead_id);
        
        // Rediriger vers une page de succès ou afficher un message
        wp_die('
            <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                <h2 style="color: var(--success);">✓ Email confirmé avec succès !</h2>
                <p>Votre inscription est maintenant active. Vous recevrez nos prochaines communications.</p>
                <p><a href="' . home_url('/') . '" style="color: var(--primary);">Retour à l\'accueil</a></p>
            </div>
        ');
    }
    
    /**
     * Envoyer une notification à l'admin lors d'un nouveau lead
     * 
     * @param int $lead_id ID du lead
     * @param array $lead_data Données du lead
     */
    private static function send_admin_notification($lead_id, $lead_data) {
        $admin_email = get_option('admin_email');
        
        $subject = '[' . get_bloginfo('name') . '] Nouveau lead inscrit';
        
        $body = '<h3>Nouveau lead inscrit</h3>';
        $body .= '<p><strong>Nom :</strong> ' . esc_html($lead_data['first_name'] . ' ' . $lead_data['last_name']) . '</p>';
        $body .= '<p><strong>Email :</strong> ' . esc_html($lead_data['email']) . '</p>';
        $body .= '<p><strong>Téléphone :</strong> ' . esc_html($lead_data['phone']) . '</p>';
        $body .= '<p><strong>Date :</strong> ' . current_time('d/m/Y H:i') . '</p>';
        $body .= '<p><a href="' . admin_url('admin.php?page=lead-collector&lead_id=' . $lead_id) . '">Voir le lead dans l\'admin</a></p>';
        
        LC_Email_Sender::send($admin_email, $subject, $body);
    }
    
    /**
     * Récupérer un formulaire par son ID
     * 
     * @param int $form_id ID du formulaire
     * @return object|null Objet formulaire ou null
     */
    private static function get_form_by_id($form_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lc_forms';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_active = 1",
            $form_id
        ));
    }
    
    /**
     * Obtenir l'IP du client
     * 
     * @return string Adresse IP
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
     * 
     * @return string User Agent
     */
    private static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }
}

// Hook pour la confirmation de lead (détection du paramètre lc_action=confirm)
add_action('init', array('LC_Form_Handler', 'confirm_lead'));
