<?php
/**
 * Fichier: admin/class-forms-page.php
 * Page de gestion des formulaires d'inscription
 * Créer, éditer, supprimer des formulaires personnalisés
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Forms_Page {
    
    /**
     * Afficher la page d'administration
     */
    public static function render() {
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }
        
        // Déterminer l'action à effectuer
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        
        // Router vers la bonne vue
        switch ($action) {
            case 'edit':
                if ($form_id) {
                    self::render_edit_form($form_id);
                } else {
                    self::render_create_form();
                }
                break;
                
            case 'create':
                self::render_create_form();
                break;
                
            default:
                self::render_list();
                break;
        }
    }
    
    /**
     * Afficher la liste des formulaires
     */
    private static function render_list() {
        
        global $wpdb;
        $table = $wpdb->prefix . 'lc_forms';
        
        $forms = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1 class="wp-heading-inline">
                Formulaires d'inscription
            </h1>
            
            <a href="<?php echo LC_Admin_Menu::get_page_url('lc-forms', array('action' => 'create')); ?>" class="page-title-action">
                Créer un formulaire
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Info -->
            <div class="lc-info-box" style="background: var(--gray-50); padding: var(--spacing); border-radius: var(--radius); margin: 20px 0;">
                <h3 style="margin-top: 0;">📝 Formulaires personnalisés</h3>
                <p>Créez des formulaires d'inscription sur mesure et intégrez-les sur votre site avec un shortcode.</p>
                <p><strong>Utilisation :</strong> <code>[lc_form id="1"]</code> ou <code>[lead_collector id="1"]</code></p>
            </div>
            
            <!-- Tableau des formulaires -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">Nom du formulaire</th>
                        <th style="width: 25%;">Shortcode</th>
                        <th style="width: 15%;">Champs</th>
                        <th style="width: 10%;">Statut</th>
                        <th style="width: 10%;">Inscriptions</th>
                        <th style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($forms)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--gray-400);">
                                Aucun formulaire créé
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($forms as $form): ?>
                            <?php
                            $fields = json_decode($form->fields, true);
                            $field_count = is_array($fields) ? count($fields) : 0;
                            
                            // Compter les inscriptions via ce formulaire
                            $signups = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}lc_leads WHERE source LIKE %s",
                                '%form_' . $form->id . '%'
                            ));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($form->name); ?></strong>
                                    <?php if ($form->title): ?>
                                        <br><small class="description"><?php echo esc_html($form->title); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="background: var(--gray-100); padding: 4px 8px; border-radius: 3px; font-size: 12px;">[lc_form id="<?php echo $form->id; ?>"]</code>
                                    <button class="button button-small lc-copy-shortcode" data-shortcode="[lc_form id=&quot;<?php echo $form->id; ?>&quot;]" style="margin-left: 5px;">
                                        Copier
                                    </button>
                                </td>
                                <td>
                                    <?php echo $field_count; ?> champ<?php echo $field_count > 1 ? 's' : ''; ?>
                                    <?php if ($form->double_optin): ?>
                                        <br><small class="description">Double opt-in activé</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($form->is_active): ?>
                                        <span class="lc-badge lc-badge-success">✓ Actif</span>
                                    <?php else: ?>
                                        <span class="lc-badge lc-badge-default">○ Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $signups; ?></strong> lead<?php echo $signups > 1 ? 's' : ''; ?>
                                </td>
                                <td>
                                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-forms', array('action' => 'edit', 'form_id' => $form->id)); ?>" class="button button-small">
                                        Éditer
                                    </a>
                                    <br>
                                    <button class="button button-small button-link-delete lc-delete-form" data-form-id="<?php echo $form->id; ?>" style="margin-top: 5px;">
                                        Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Copier le shortcode
            $('.lc-copy-shortcode').on('click', function(e) {
                e.preventDefault();
                
                var shortcode = $(this).data('shortcode');
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(shortcode).select();
                document.execCommand('copy');
                $temp.remove();
                
                $(this).text('✓ Copié').prop('disabled', true);
                setTimeout(() => {
                    $(this).text('Copier').prop('disabled', false);
                }, 2000);
            });
            
            // Supprimer un formulaire
            $('.lc-delete-form').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Êtes-vous sûr de vouloir supprimer ce formulaire ?')) {
                    return;
                }
                
                var formId = $(this).data('form-id');
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_delete_form',
                        nonce: lcAdminAjax.nonce,
                        form_id: formId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Erreur lors de la suppression');
                        }
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire de création
     */
    private static function render_create_form() {
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Créer un formulaire</h1>
            
            <form id="lc-form-builder" style="max-width: 800px;">
                
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="name">Nom du formulaire *</label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required placeholder="Ex: Formulaire Newsletter">
                            <p class="description">Pour votre référence interne.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="title">Titre affiché</label>
                        </th>
                        <td>
                            <input type="text" name="title" id="title" class="regular-text" placeholder="Ex: Inscrivez-vous">
                            <p class="description">Titre visible sur le formulaire.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="subtitle">Sous-titre</label>
                        </th>
                        <td>
                            <input type="text" name="subtitle" id="subtitle" class="large-text" placeholder="Recevez nos actualités directement par email">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Champs du formulaire *</label>
                        </th>
                        <td>
                            <div id="form-fields">
                                <!-- Les champs seront ajoutés ici dynamiquement -->
                            </div>
                            <button type="button" class="button" id="add-field">+ Ajouter un champ</button>
                            <p class="description">Glissez-déposez pour réorganiser les champs.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="style">Style</label>
                        </th>
                        <td>
                            <select name="style" id="style">
                                <option value="dark">Dark (Fond sombre)</option>
                                <option value="light">Light (Fond clair)</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="double_optin">Double opt-in</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="double_optin" id="double_optin" value="1">
                                Activer la confirmation par email
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="success_message">Message de succès</label>
                        </th>
                        <td>
                            <textarea name="success_message" id="success_message" rows="3" class="large-text">Merci ! Votre inscription a été enregistrée avec succès.</textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="redirect_url">URL de redirection</label>
                        </th>
                        <td>
                            <input type="url" name="redirect_url" id="redirect_url" class="regular-text" placeholder="https://exemple.com/merci">
                            <p class="description">Optionnel : redirige l'utilisateur après l'inscription.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="is_active">Statut</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                Formulaire actif
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Créer le formulaire
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-forms'); ?>" class="button button-large">
                        Annuler
                    </a>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            var fieldCount = 0;
            
            // Ajouter un champ
            $('#add-field').on('click', function() {
                fieldCount++;
                
                var fieldHtml = `
                    <div class="form-field" style="background: white; padding: 15px; margin-bottom: 10px; border: 1px solid var(--gray-200); border-radius: var(--radius);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 100px 60px; gap: 10px; align-items: start;">
                            <div>
                                <label>Label</label>
                                <input type="text" class="field-label" placeholder="Ex: Email" required>
                            </div>
                            <div>
                                <label>Type</label>
                                <select class="field-type">
                                    <option value="text">Texte</option>
                                    <option value="email">Email</option>
                                    <option value="tel">Téléphone</option>
                                    <option value="textarea">Zone de texte</option>
                                    <option value="checkbox">Case à cocher</option>
                                    <option value="select">Liste déroulante</option>
                                </select>
                            </div>
                            <div>
                                <label>Requis</label>
                                <input type="checkbox" class="field-required" value="1">
                            </div>
                            <div>
                                <button type="button" class="button button-small remove-field" style="color: var(--danger); margin-top: 20px;">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#form-fields').append(fieldHtml);
            });
            
            // Supprimer un champ
            $(document).on('click', '.remove-field', function() {
                $(this).closest('.form-field').remove();
            });
            
            // Soumettre le formulaire
            $('#lc-form-builder').on('submit', function(e) {
                e.preventDefault();
                
                // Collecter les champs
                var fields = [];
                $('.form-field').each(function() {
                    var $field = $(this);
                    fields.push({
                        label: $field.find('.field-label').val(),
                        type: $field.find('.field-type').val(),
                        name: $field.find('.field-label').val().toLowerCase().replace(/[^a-z0-9]/g, '_'),
                        required: $field.find('.field-required').is(':checked')
                    });
                });
                
                var formData = {
                    action: 'lc_save_form',
                    nonce: lcAdminAjax.nonce,
                    name: $('#name').val(),
                    title: $('#title').val(),
                    subtitle: $('#subtitle').val(),
                    fields: JSON.stringify(fields),
                    style: $('#style').val(),
                    double_optin: $('#double_optin').is(':checked') ? 1 : 0,
                    success_message: $('#success_message').val(),
                    redirect_url: $('#redirect_url').val(),
                    is_active: $('#is_active').is(':checked') ? 1 : 0
                };
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Formulaire créé avec succès !');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lc-forms'); ?>';
                        } else {
                            alert(response.data.message || 'Erreur lors de la création');
                        }
                    }
                });
            });
            
            // Ajouter les champs par défaut
            $('#add-field').click(); // Email
            $('#form-fields .field-label').last().val('Email');
            $('#form-fields .field-type').last().val('email');
            $('#form-fields .field-required').last().prop('checked', true);
            
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire d'édition
     */
    private static function render_edit_form($form_id) {
        // TODO: Implémenter l'édition
        echo '<div class="wrap"><h1>Édition de formulaire (à venir)</h1></div>';
    }
}
