<?php
/**
 * Fichier: admin/class-post-notifications-page.php
 * Page d'administration des Post Notifications
 * Gère les templates d'emails envoyés automatiquement lors de la publication d'articles
 * 
 * FONCTIONNALITÉ PRINCIPALE DU PLUGIN :
 * Quand un article est publié dans une catégorie, tous les leads ayant cette catégorie
 * reçoivent automatiquement un email avec le lien vers l'article.
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Post_Notifications_Page {
    
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
        $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        
        // Router vers la bonne vue
        switch ($action) {
            case 'edit':
                if ($template_id) {
                    self::render_edit_template($template_id);
                } else {
                    self::render_create_template();
                }
                break;
                
            case 'create':
                self::render_create_template();
                break;
                
            case 'test':
                if ($template_id) {
                    self::render_test_template($template_id);
                }
                break;
                
            default:
                self::render_list();
                break;
        }
    }
    
    /**
     * Afficher la liste des templates de notifications
     */
    private static function render_list() {
        
        // Récupérer toutes les catégories WordPress
        $categories = get_categories(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        // Récupérer tous les templates
        $templates = LC_Post_Notification_Handler::get_all_templates();
        
        // Créer un tableau associatif category_id => template
        $templates_by_category = array();
        foreach ($templates as $template) {
            $templates_by_category[$template->category_id] = $template;
        }
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1 class="wp-heading-inline">
                Post Notifications
            </h1>
            
            <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications', array('action' => 'create')); ?>" class="page-title-action">
                Créer un template
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Description de la fonctionnalité -->
            <div class="lc-info-box" style="background: var(--gray-50); padding: var(--spacing); border-radius: var(--radius); margin: 20px 0; border-left: 4px solid var(--primary);">
                <h3 style="margin-top: 0;">📧 Comment ça fonctionne ?</h3>
                <p>
                    <strong>Les Post Notifications automatisent l'envoi d'emails à vos leads lors de la publication d'articles.</strong>
                </p>
                <ol style="margin-left: 20px;">
                    <li>Créez un <strong>template d'email par catégorie WordPress</strong></li>
                    <li>Quand vous <strong>publiez un article</strong> dans cette catégorie, tous les leads concernés reçoivent automatiquement l'email</li>
                    <li>Les leads concernés sont ceux qui ont :
                        <ul style="margin-left: 20px; list-style: circle;">
                            <li>La catégorie assignée <strong>individuellement</strong></li>
                            <li>Ou sont dans un <strong>groupe</strong> ayant cette catégorie</li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <!-- Tableau des templates par catégorie -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40%;">Catégorie WordPress</th>
                        <th style="width: 30%;">Template</th>
                        <th style="width: 15%;">Statut</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php 
                        $template = isset($templates_by_category[$category->term_id]) ? $templates_by_category[$category->term_id] : null;
                        $has_template = $template !== null;
                        $is_active = $has_template && $template->is_active;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($category->name); ?></strong>
                                <?php if ($category->description): ?>
                                    <br><small class="description"><?php echo esc_html($category->description); ?></small>
                                <?php endif; ?>
                                <br><small class="description">ID: <?php echo $category->term_id; ?> • <?php echo $category->count; ?> article(s)</small>
                            </td>
                            <td>
                                <?php if ($has_template): ?>
                                    <strong><?php echo esc_html($template->name); ?></strong>
                                    <br><small class="description"><?php echo esc_html(substr($template->subject, 0, 50)); ?>...</small>
                                <?php else: ?>
                                    <span style="color: var(--gray-400);">Aucun template</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_template): ?>
                                    <?php if ($is_active): ?>
                                        <span class="lc-badge lc-badge-success">✓ Actif</span>
                                    <?php else: ?>
                                        <span class="lc-badge lc-badge-warning">○ Inactif</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="lc-badge lc-badge-default">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_template): ?>
                                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications', array('action' => 'edit', 'template_id' => $template->id)); ?>" class="button button-small">
                                        Éditer
                                    </a>
                                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications', array('action' => 'test', 'template_id' => $template->id)); ?>" class="button button-small">
                                        Tester
                                    </a>
                                    <button class="button button-small button-link-delete lc-delete-template" data-template-id="<?php echo $template->id; ?>">
                                        Supprimer
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications', array('action' => 'create', 'category_id' => $category->term_id)); ?>" class="button button-small button-primary">
                                        Créer
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Variables disponibles -->
            <div class="lc-info-box" style="background: var(--gray-50); padding: var(--spacing); border-radius: var(--radius); margin-top: 30px;">
                <h3>📝 Variables disponibles dans les templates</h3>
                <p>Utilisez ces variables dans le sujet et le corps de vos emails. Elles seront automatiquement remplacées :</p>
                <ul style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; list-style: none; margin-left: 0;">
                    <li><code>{{post_title}}</code> - Titre de l'article</li>
                    <li><code>{{post_excerpt}}</code> - Extrait</li>
                    <li><code>{{post_url}}</code> - Lien vers l'article</li>
                    <li><code>{{post_author}}</code> - Auteur</li>
                    <li><code>{{post_date}}</code> - Date de publication</li>
                    <li><code>{{category_name}}</code> - Nom de la catégorie</li>
                    <li><code>{{first_name}}</code> - Prénom du lead</li>
                    <li><code>{{last_name}}</code> - Nom du lead</li>
                    <li><code>{{email}}</code> - Email du lead</li>
                    <li><code>{{site_name}}</code> - Nom du site</li>
                    <li><code>{{site_url}}</code> - URL du site</li>
                    <li><code>{{unsubscribe_url}}</code> - Lien désinscription</li>
                </ul>
            </div>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Supprimer un template
            $('.lc-delete-template').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Êtes-vous sûr de vouloir supprimer ce template ?')) {
                    return;
                }
                
                var templateId = $(this).data('template-id');
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_delete_notification_template',
                        nonce: lcAdminAjax.nonce,
                        template_id: templateId
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
     * Afficher le formulaire de création de template
     */
    private static function render_create_template() {
        
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        
        // Récupérer toutes les catégories
        $categories = get_categories(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Créer un template de notification</h1>
            
            <form id="lc-notification-form" method="post" style="max-width: 800px;">
                
                <!-- Catégorie -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="category_id">Catégorie WordPress *</label>
                        </th>
                        <td>
                            <select name="category_id" id="category_id" required style="width: 100%; max-width: 400px;">
                                <option value="">-- Sélectionner une catégorie --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php selected($category_id, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> article<?php echo $cat->count > 1 ? 's' : ''; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Les notifications seront envoyées quand un article de cette catégorie sera publié.</p>
                        </td>
                    </tr>
                    
                    <!-- Nom du template -->
                    <tr>
                        <th scope="row">
                            <label for="name">Nom du template *</label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required placeholder="Ex: Notification Actualités">
                            <p class="description">Pour votre référence interne.</p>
                        </td>
                    </tr>
                    
                    <!-- Sujet de l'email -->
                    <tr>
                        <th scope="row">
                            <label for="subject">Sujet de l'email *</label>
                        </th>
                        <td>
                            <input type="text" name="subject" id="subject" class="large-text" required placeholder="Nouvel article : {{post_title}}">
                            <p class="description">Utilisez les variables comme <code>{{post_title}}</code></p>
                        </td>
                    </tr>
                    
                    <!-- Corps de l'email -->
                    <tr>
                        <th scope="row">
                            <label for="body">Corps de l'email (HTML) *</label>
                        </th>
                        <td>
                            <?php
                            $default_body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color: var(--primary);">Bonjour {{first_name}},</h2>
    
    <p>Un nouvel article vient d\'être publié dans la catégorie <strong>{{category_name}}</strong> :</p>
    
    <div style="background: var(--gray-50); padding: 20px; border-radius: var(--radius); margin: 20px 0;">
        <h3 style="margin-top: 0; color: var(--primary);">{{post_title}}</h3>
        <p>{{post_excerpt}}</p>
        <a href="{{post_url}}" style="display: inline-block; background: var(--primary); color: white; padding: 12px 24px; text-decoration: none; border-radius: var(--radius); margin-top: 10px;">
            Lire l\'article
        </a>
    </div>
    
    <p style="color: var(--gray-500); font-size: 12px;">
        Publié le {{post_date}} par {{post_author}}<br>
        <a href="{{unsubscribe_url}}" style="color: var(--gray-500);">Se désinscrire</a>
    </p>
</div>';
                            
                            wp_editor($default_body, 'body', array(
                                'textarea_name' => 'body',
                                'textarea_rows' => 20,
                                'media_buttons' => false,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,code',
                                )
                            ));
                            ?>
                            <p class="description">Utilisez du HTML pour styliser votre email. Les variables seront remplacées automatiquement.</p>
                        </td>
                    </tr>
                    
                    <!-- Actif -->
                    <tr>
                        <th scope="row">
                            <label for="is_active">Statut</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                Activer ce template (les notifications seront envoyées)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Créer le template
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications'); ?>" class="button button-large">
                        Annuler
                    </a>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#lc-notification-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'lc_save_notification_template',
                    nonce: lcAdminAjax.nonce,
                    category_id: $('#category_id').val(),
                    name: $('#name').val(),
                    subject: $('#subject').val(),
                    body: $('#body').val(),
                    is_active: $('#is_active').is(':checked') ? 1 : 0
                };
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Template créé avec succès !');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications'); ?>';
                        } else {
                            alert(response.data.message || 'Erreur lors de la création');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire d'édition de template
     */
    private static function render_edit_template($template_id) {
        
        $template = LC_Post_Notification_Handler::get_template($template_id);
        
        if (!$template) {
            wp_die('Template introuvable.');
        }
        
        // Récupérer toutes les catégories
        $categories = get_categories(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Éditer le template</h1>
            
            <form id="lc-notification-form" method="post" style="max-width: 800px;">
                
                <input type="hidden" name="template_id" value="<?php echo $template->id; ?>">
                
                <!-- Catégorie -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="category_id">Catégorie WordPress *</label>
                        </th>
                        <td>
                            <select name="category_id" id="category_id" required style="width: 100%; max-width: 400px;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php selected($template->category_id, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> article<?php echo $cat->count > 1 ? 's' : ''; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Nom du template -->
                    <tr>
                        <th scope="row">
                            <label for="name">Nom du template *</label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required value="<?php echo esc_attr($template->name); ?>">
                        </td>
                    </tr>
                    
                    <!-- Sujet de l'email -->
                    <tr>
                        <th scope="row">
                            <label for="subject">Sujet de l'email *</label>
                        </th>
                        <td>
                            <input type="text" name="subject" id="subject" class="large-text" required value="<?php echo esc_attr($template->subject); ?>">
                        </td>
                    </tr>
                    
                    <!-- Corps de l'email -->
                    <tr>
                        <th scope="row">
                            <label for="body">Corps de l'email (HTML) *</label>
                        </th>
                        <td>
                            <?php
                            wp_editor($template->body, 'body', array(
                                'textarea_name' => 'body',
                                'textarea_rows' => 20,
                                'media_buttons' => false,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,code',
                                )
                            ));
                            ?>
                        </td>
                    </tr>
                    
                    <!-- Actif -->
                    <tr>
                        <th scope="row">
                            <label for="is_active">Statut</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" value="1" <?php checked($template->is_active, 1); ?>>
                                Activer ce template
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Enregistrer les modifications
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications'); ?>" class="button button-large">
                        Annuler
                    </a>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#lc-notification-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'lc_save_notification_template',
                    nonce: lcAdminAjax.nonce,
                    template_id: $('input[name="template_id"]').val(),
                    category_id: $('#category_id').val(),
                    name: $('#name').val(),
                    subject: $('#subject').val(),
                    body: $('#body').val(),
                    is_active: $('#is_active').is(':checked') ? 1 : 0
                };
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Template mis à jour avec succès !');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications'); ?>';
                        } else {
                            alert(response.data.message || 'Erreur lors de la mise à jour');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire de test d'un template
     */
    private static function render_test_template($template_id) {
        
        $template = LC_Post_Notification_Handler::get_template($template_id);
        
        if (!$template) {
            wp_die('Template introuvable.');
        }
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Tester le template : <?php echo esc_html($template->name); ?></h1>
            
            <div class="lc-info-box" style="background: var(--gray-50); padding: var(--spacing); border-radius: var(--radius); margin: 20px 0;">
                <p><strong>📧 Envoyez un email de test</strong></p>
                <p>L'email sera envoyé avec des données de test pour voir le rendu final.</p>
            </div>
            
            <form id="lc-test-form" style="max-width: 600px;">
                
                <input type="hidden" name="template_id" value="<?php echo $template->id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_email">Email de destination *</label>
                        </th>
                        <td>
                            <input type="email" name="test_email" id="test_email" class="regular-text" required value="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <p class="description">L'email de test sera envoyé à cette adresse.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Envoyer l'email de test
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-post-notifications'); ?>" class="button button-large">
                        Retour
                    </a>
                </p>
                
            </form>
            
            <div id="test-result" style="display: none; margin-top: 20px;"></div>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#lc-test-form').on('submit', function(e) {
                e.preventDefault();
                
                $('#test-result').html('<p>Envoi en cours...</p>').show();
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_test_notification',
                        nonce: lcAdminAjax.nonce,
                        template_id: $('input[name="template_id"]').val(),
                        test_email: $('#test_email').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-result').html('<div style="background: var(--success); color: white; padding: 15px; border-radius: var(--radius);"><strong>✓ Email envoyé avec succès !</strong><br>Vérifiez votre boîte de réception.</div>');
                        } else {
                            $('#test-result').html('<div style="background: var(--danger); color: white; padding: 15px; border-radius: var(--radius);"><strong>✗ Erreur</strong><br>' + (response.data.message || 'Une erreur est survenue') + '</div>');
                        }
                    },
                    error: function() {
                        $('#test-result').html('<div style="background: var(--danger); color: white; padding: 15px; border-radius: var(--radius);"><strong>✗ Erreur de connexion</strong></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
