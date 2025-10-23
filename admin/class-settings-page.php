<?php
/**
 * Fichier: admin/class-settings-page.php
 * Page des paramètres généraux du plugin
 * Configuration des emails, double opt-in, catégories par défaut, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Settings_Page {
    
    /**
     * Afficher la page d'administration
     */
    public static function render() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }
        
        // Enregistrer les paramètres si formulaire soumis
        if (isset($_POST['lc_save_settings']) && check_admin_referer('lc_settings_nonce')) {
            self::save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>Paramètres enregistrés avec succès !</p></div>';
        }
        
        // Récupérer les catégories WordPress
        $categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Paramètres - Lead Collector Pro</h1>
            
            <form method="post" action="" style="max-width: 900px;">
                
                <?php wp_nonce_field('lc_settings_nonce'); ?>
                <input type="hidden" name="lc_save_settings" value="1">
                
                <!-- Section 1 : Paramètres d'envoi -->
                <h2>📧 Paramètres d'envoi</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_from_name">Nom de l'expéditeur</label>
                        </th>
                        <td>
                            <input type="text" name="lc_from_name" id="lc_from_name" class="regular-text" value="<?php echo esc_attr(get_option('lc_from_name', get_bloginfo('name'))); ?>">
                            <p class="description">Nom affiché dans les emails envoyés.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_from_email">Email de l'expéditeur</label>
                        </th>
                        <td>
                            <input type="email" name="lc_from_email" id="lc_from_email" class="regular-text" value="<?php echo esc_attr(get_option('lc_from_email', get_option('admin_email'))); ?>">
                            <p class="description">Adresse email utilisée pour l'envoi.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_enable_notifications">Notifications admin</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="lc_enable_notifications" id="lc_enable_notifications" value="1" <?php checked(get_option('lc_enable_notifications', true), true); ?>>
                                Recevoir une notification par email à chaque nouveau lead
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <hr style="margin: 40px 0;">
                
                <!-- Section 2 : Double opt-in -->
                <h2>✉️ Double opt-in</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_enable_double_optin">Activer le double opt-in</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="lc_enable_double_optin" id="lc_enable_double_optin" value="1" <?php checked(get_option('lc_enable_double_optin', false), true); ?>>
                                Les nouveaux leads doivent confirmer leur email avant de recevoir des communications
                            </label>
                            <p class="description">Recommandé pour respecter le RGPD.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_confirmation_subject">Sujet de l'email de confirmation</label>
                        </th>
                        <td>
                            <input type="text" name="lc_confirmation_subject" id="lc_confirmation_subject" class="large-text" value="<?php echo esc_attr(get_option('lc_confirmation_subject', 'Confirmez votre inscription')); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_confirmation_body">Corps de l'email de confirmation</label>
                        </th>
                        <td>
                            <?php
                            $default_body = '<p>Bonjour {{first_name}},</p>
<p>Merci de votre inscription ! Pour confirmer votre adresse email, veuillez cliquer sur le lien ci-dessous :</p>
<p><a href="{{confirmation_url}}" style="background: var(--primary); color: white; padding: 12px 24px; text-decoration: none; border-radius: var(--radius); display: inline-block;">Confirmer mon inscription</a></p>
<p>Si vous n\'avez pas demandé cette inscription, vous pouvez ignorer cet email.</p>
<p>Cordialement,<br>L\'équipe {{site_name}}</p>';
                            
                            wp_editor(
                                get_option('lc_confirmation_body', $default_body),
                                'lc_confirmation_body',
                                array(
                                    'textarea_name' => 'lc_confirmation_body',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false,
                                    'teeny' => true
                                )
                            );
                            ?>
                            <p class="description">Variables disponibles : {{first_name}}, {{last_name}}, {{email}}, {{confirmation_url}}, {{site_name}}, {{site_url}}</p>
                        </td>
                    </tr>
                    
                </table>
                
                <hr style="margin: 40px 0;">
                
                <!-- Section 3 : Catégories par défaut -->
                <h2>🏷️ Catégories par défaut pour nouveaux leads</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_default_categories">Catégories automatiques</label>
                        </th>
                        <td>
                            <?php
                            $default_categories = get_option('lc_default_categories', array());
                            if (!is_array($default_categories)) {
                                $default_categories = array();
                            }
                            ?>
                            <select name="lc_default_categories[]" id="lc_default_categories" multiple size="10" style="width: 100%; max-width: 500px;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php echo in_array($cat->term_id, $default_categories) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Quand un nouveau lead s'inscrit, il sera automatiquement associé à ces catégories et recevra les notifications d'articles correspondantes.
                                <br>Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs catégories.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <hr style="margin: 40px 0;">
                
                <!-- Section 4 : Protection et sécurité -->
                <h2>🔒 Protection et sécurité</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_rate_limit">Limite de soumissions</label>
                        </th>
                        <td>
                            <input type="number" name="lc_rate_limit" id="lc_rate_limit" min="1" max="50" value="<?php echo esc_attr(get_option('lc_rate_limit', 5)); ?>" style="width: 80px;">
                            soumissions par heure depuis une même IP
                            <p class="description">Protection anti-spam. Par défaut : 5 tentatives/heure.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lc_enable_honeypot">Honeypot anti-spam</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="lc_enable_honeypot" id="lc_enable_honeypot" value="1" <?php checked(get_option('lc_enable_honeypot', true), true); ?>>
                                Activer le champ caché anti-bot (recommandé)
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <hr style="margin: 40px 0;">
                
                <!-- Section 5 : Maintenance -->
                <h2>🛠️ Maintenance</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            Nettoyer les anciennes données
                        </th>
                        <td>
                            <button type="button" class="button" id="clean-old-leads">
                                Supprimer les leads de plus d'1 an
                            </button>
                            <p class="description">Nettoie les leads inactifs pour optimiser la base de données.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            Exporter toutes les données
                        </th>
                        <td>
                            <button type="button" class="button" id="export-all-data">
                                Télécharger un export complet (CSV)
                            </button>
                            <p class="description">Exporte tous les leads avec leurs informations.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            Réinitialisation
                        </th>
                        <td>
                            <button type="button" class="button button-link-delete" id="reset-plugin" style="color: var(--danger);">
                                ⚠️ Réinitialiser le plugin
                            </button>
                            <p class="description" style="color: var(--danger);">
                                <strong>Attention :</strong> Supprime toutes les données (leads, campagnes, templates). Action irréversible !
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <hr style="margin: 40px 0;">
                
                <!-- Section 6 : Informations -->
                <h2>ℹ️ Informations</h2>
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">Version du plugin</th>
                        <td>
                            <strong>Lead Collector Pro v<?php echo LC_VERSION; ?></strong>
                            <p class="description">
                                Plugin WordPress de collecte de leads avec notifications automatiques d'articles.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Base de données</th>
                        <td>
                            <?php
                            global $wpdb;
                            $tables = LC_Database::get_table_names();
                            
                            echo '<ul style="margin: 0;">';
                            foreach ($tables as $name => $table) {
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                                echo '<li><code>' . $name . '</code> : ' . $count . ' entrée(s)</li>';
                            }
                            echo '</ul>';
                            ?>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Bouton Enregistrer -->
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Enregistrer tous les paramètres
                    </button>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Nettoyer les anciens leads
            $('#clean-old-leads').on('click', function() {
                if (!confirm('Supprimer tous les leads de plus d\'1 an ?')) {
                    return;
                }
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_clean_old_leads',
                        nonce: lcAdminAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Nettoyage effectué avec succès');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Erreur');
                        }
                    }
                });
            });
            
            // Exporter toutes les données
            $('#export-all-data').on('click', function() {
                window.location.href = lcAdminAjax.ajaxurl + '?action=lc_export_leads&nonce=' + lcAdminAjax.nonce;
            });
            
            // Réinitialiser le plugin
            $('#reset-plugin').on('click', function() {
                var confirmation = prompt('⚠️ ATTENTION : Cette action est IRRÉVERSIBLE !\n\nToutes vos données seront supprimées définitivement (leads, campagnes, templates, formulaires).\n\nPour confirmer, tapez : SUPPRIMER');
                
                if (confirmation !== 'SUPPRIMER') {
                    alert('Réinitialisation annulée.');
                    return;
                }
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_reset_plugin',
                        nonce: lcAdminAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Plugin réinitialisé avec succès. Redirection...');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Erreur');
                        }
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    /**
     * Enregistrer les paramètres
     */
    private static function save_settings() {
        
        // Paramètres d'envoi
        update_option('lc_from_name', sanitize_text_field($_POST['lc_from_name']));
        update_option('lc_from_email', sanitize_email($_POST['lc_from_email']));
        update_option('lc_enable_notifications', isset($_POST['lc_enable_notifications']) ? 1 : 0);
        
        // Double opt-in
        update_option('lc_enable_double_optin', isset($_POST['lc_enable_double_optin']) ? 1 : 0);
        update_option('lc_confirmation_subject', sanitize_text_field($_POST['lc_confirmation_subject']));
        update_option('lc_confirmation_body', wp_kses_post($_POST['lc_confirmation_body']));
        
        // Catégories par défaut
        $default_categories = isset($_POST['lc_default_categories']) ? array_map('intval', $_POST['lc_default_categories']) : array();
        update_option('lc_default_categories', $default_categories);
        
        // Protection
        update_option('lc_rate_limit', intval($_POST['lc_rate_limit']));
        update_option('lc_enable_honeypot', isset($_POST['lc_enable_honeypot']) ? 1 : 0);
    }
}
