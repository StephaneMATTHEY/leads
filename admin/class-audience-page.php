<?php
/**
 * Fichier: admin/class-audience-page.php
 * Page de gestion de l'audience (leads)
 * Liste, filtres, statistiques, édition, suppression, export CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Audience_Page {
    
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
        $lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
        
        // Router vers la bonne vue
        switch ($action) {
            case 'edit':
                if ($lead_id) {
                    self::render_edit_lead($lead_id);
                } else {
                    self::render_create_lead();
                }
                break;
                
            case 'create':
                self::render_create_lead();
                break;
                
            default:
                self::render_list();
                break;
        }
    }
    
    /**
     * Afficher la liste des leads avec filtres et statistiques
     */
    private static function render_list() {
        
        // Récupérer les paramètres de filtrage
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $group_filter = isset($_GET['group']) ? intval($_GET['group']) : 0;
        $category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 50;
        
        // Construire les arguments pour la requête
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        if ($status_filter) {
            $args['status'] = $status_filter;
        }
        
        if ($search) {
            $args['search'] = $search;
        }
        
        // Récupérer les leads
        $leads = LC_Lead_Manager::get_leads($args);
        $total_leads = LC_Lead_Manager::count_leads($args);
        $total_pages = ceil($total_leads / $per_page);
        
        // Récupérer les statistiques globales
        $stats = LC_Lead_Manager::get_stats();
        
        // Récupérer les groupes et catégories pour les filtres
        $groups = LC_Group_Manager::get_all_groups();
        $categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1 class="wp-heading-inline">
                Audience
            </h1>
            
            <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector', array('action' => 'create')); ?>" class="page-title-action">
                Ajouter un lead
            </a>
            
            <button class="page-title-action lc-export-leads">
                Exporter en CSV
            </button>
            
            <hr class="wp-header-end">
            
            <!-- Statistiques globales -->
            <div class="lc-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--spacing); margin: 20px 0;">
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Total de leads</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--primary);"><?php echo number_format($stats['total'], 0, ',', ' '); ?></div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        +<?php echo $stats['today']; ?> aujourd'hui
                    </div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Leads actifs</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--success);"><?php echo number_format($stats['active'], 0, ',', ' '); ?></div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        <?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100) : 0; ?>% du total
                    </div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">En attente</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--warning);"><?php echo number_format($stats['pending'], 0, ',', ' '); ?></div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        Double opt-in requis
                    </div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Désinscrits</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--danger);"><?php echo number_format($stats['unsubscribed'], 0, ',', ' '); ?></div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        <?php echo $stats['total'] > 0 ? round(($stats['unsubscribed'] / $stats['total']) * 100, 1) : 0; ?>% du total
                    </div>
                </div>
                
            </div>
            
            <!-- Filtres -->
            <div class="lc-filters" style="background: white; padding: var(--spacing); border-radius: var(--radius); margin-bottom: 20px;">
                
                <form method="get" action="" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    
                    <input type="hidden" name="page" value="lead-collector">
                    
                    <!-- Filtres rapides par statut -->
                    <div class="lc-filter-tabs" style="display: flex; gap: 5px; margin-right: auto;">
                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector'); ?>" class="button <?php echo empty($status_filter) ? 'button-primary' : ''; ?>">
                            Tous (<?php echo $stats['total']; ?>)
                        </a>
                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector', array('status' => 'active')); ?>" class="button <?php echo $status_filter === 'active' ? 'button-primary' : ''; ?>">
                            Actifs (<?php echo $stats['active']; ?>)
                        </a>
                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector', array('status' => 'pending')); ?>" class="button <?php echo $status_filter === 'pending' ? 'button-primary' : ''; ?>">
                            En attente (<?php echo $stats['pending']; ?>)
                        </a>
                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector', array('status' => 'unsubscribed')); ?>" class="button <?php echo $status_filter === 'unsubscribed' ? 'button-primary' : ''; ?>">
                            Désinscrits (<?php echo $stats['unsubscribed']; ?>)
                        </a>
                    </div>
                    
                    <!-- Recherche -->
                    <div>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher par email, nom..." style="width: 250px;">
                    </div>
                    
                    <!-- Filtre par groupe -->
                    <div>
                        <select name="group" style="width: 180px;">
                            <option value="">Tous les groupes</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group->id; ?>" <?php selected($group_filter, $group->id); ?>>
                                    <?php echo esc_html($group->name); ?> (<?php echo $group->lead_count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filtre par catégorie -->
                    <div>
                        <select name="category" style="width: 180px;">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->term_id; ?>" <?php selected($category_filter, $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="button">Filtrer</button>
                    
                    <?php if ($status_filter || $group_filter || $category_filter || $search): ?>
                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector'); ?>" class="button">
                            Réinitialiser
                        </a>
                    <?php endif; ?>
                    
                </form>
                
            </div>
            
            <!-- Tableau des leads -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;"><input type="checkbox" id="select-all"></th>
                        <th style="width: 30%;">Lead</th>
                        <th style="width: 15%;">Téléphone</th>
                        <th style="width: 20%;">Groupes / Catégories</th>
                        <th style="width: 12%;">Statut</th>
                        <th style="width: 13%;">Date d'inscription</th>
                        <th style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--gray-400);">
                                Aucun lead trouvé
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                            <?php
                            $lead_groups = LC_Lead_Manager::get_lead_groups($lead->id);
                            $lead_categories = LC_Lead_Manager::get_lead_categories($lead->id);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="lead-checkbox" value="<?php echo $lead->id; ?>">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($lead->first_name . ' ' . $lead->last_name); ?></strong>
                                    <br>
                                    <a href="mailto:<?php echo esc_attr($lead->email); ?>" style="color: var(--primary);">
                                        <?php echo esc_html($lead->email); ?>
                                    </a>
                                    <?php if ($lead->source): ?>
                                        <br><small class="description">Source: <?php echo esc_html($lead->source); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $lead->phone ? esc_html($lead->phone) : '—'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($lead_groups)): ?>
                                        <div style="margin-bottom: 5px;">
                                            <?php foreach ($lead_groups as $group_id): ?>
                                                <?php $group = LC_Group_Manager::get_group($group_id); ?>
                                                <?php if ($group): ?>
                                                    <span class="lc-badge" style="background: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 3px;">
                                                        <?php echo esc_html($group->name); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lead_categories)): ?>
                                        <div>
                                            <?php foreach ($lead_categories as $cat_id): ?>
                                                <?php $cat = get_category($cat_id); ?>
                                                <?php if ($cat): ?>
                                                    <span class="lc-badge" style="background: var(--secondary-light); color: var(--secondary); padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 3px;">
                                                        <?php echo esc_html($cat->name); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($lead_groups) && empty($lead_categories)): ?>
                                        <span style="color: var(--gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = array(
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'unsubscribed' => 'danger',
                                        'bounced' => 'default'
                                    );
                                    $status_labels = array(
                                        'active' => 'Actif',
                                        'pending' => 'En attente',
                                        'unsubscribed' => 'Désinscrit',
                                        'bounced' => 'Bounce'
                                    );
                                    $color = $status_colors[$lead->status] ?? 'default';
                                    $label = $status_labels[$lead->status] ?? $lead->status;
                                    ?>
                                    <span class="lc-badge lc-badge-<?php echo $color; ?>">
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date_i18n('d/m/Y', strtotime($lead->created_at)); ?>
                                    <br>
                                    <small class="description"><?php echo human_time_diff(strtotime($lead->created_at), current_time('timestamp')); ?></small>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector', array('action' => 'edit', 'lead_id' => $lead->id)); ?>">
                                            Éditer
                                        </a>
                                        |
                                        <a href="#" class="lc-delete-lead" data-lead-id="<?php echo $lead->id; ?>" style="color: var(--danger);">
                                            Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Précédent',
                            'next_text' => 'Suivant &raquo;',
                            'total' => $total_pages,
                            'current' => $page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Sélectionner tous les leads
            $('#select-all').on('change', function() {
                $('.lead-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Supprimer un lead
            $('.lc-delete-lead').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Êtes-vous sûr de vouloir supprimer ce lead ?')) {
                    return;
                }
                
                var leadId = $(this).data('lead-id');
                var $row = $(this).closest('tr');
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_delete_lead',
                        nonce: lcAdminAjax.nonce,
                        lead_id: leadId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert(response.data.message || 'Erreur lors de la suppression');
                        }
                    }
                });
            });
            
            // Exporter en CSV
            $('.lc-export-leads').on('click', function(e) {
                e.preventDefault();
                
                var filters = <?php echo json_encode(array(
                    'status' => $status_filter,
                    'group' => $group_filter,
                    'category' => $category_filter,
                    'search' => $search
                )); ?>;
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_export_leads',
                        nonce: lcAdminAjax.nonce,
                        filters: filters
                    },
                    success: function(response) {
                        if (response.success && response.data.download_url) {
                            window.location.href = response.data.download_url;
                        } else {
                            alert('Erreur lors de l\'export');
                        }
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire d'édition d'un lead
     */
    private static function render_edit_lead($lead_id) {
        
        $lead = LC_Lead_Manager::get_lead($lead_id);
        
        if (!$lead) {
            wp_die('Lead introuvable.');
        }
        
        // Récupérer les groupes et catégories du lead
        $lead_groups = LC_Lead_Manager::get_lead_groups($lead_id);
        $lead_categories = LC_Lead_Manager::get_lead_categories($lead_id);
        
        // Récupérer tous les groupes et catégories disponibles
        $all_groups = LC_Group_Manager::get_all_groups();
        $all_categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Éditer le lead</h1>
            
            <form id="lc-lead-form" style="max-width: 800px;">
                
                <input type="hidden" name="lead_id" value="<?php echo $lead->id; ?>">
                
                <table class="form-table">
                    
                    <!-- Email -->
                    <tr>
                        <th scope="row">
                            <label for="email">Email *</label>
                        </th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required value="<?php echo esc_attr($lead->email); ?>">
                        </td>
                    </tr>
                    
                    <!-- Prénom -->
                    <tr>
                        <th scope="row">
                            <label for="first_name">Prénom</label>
                        </th>
                        <td>
                            <input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr($lead->first_name); ?>">
                        </td>
                    </tr>
                    
                    <!-- Nom -->
                    <tr>
                        <th scope="row">
                            <label for="last_name">Nom</label>
                        </th>
                        <td>
                            <input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr($lead->last_name); ?>">
                        </td>
                    </tr>
                    
                    <!-- Téléphone -->
                    <tr>
                        <th scope="row">
                            <label for="phone">Téléphone</label>
                        </th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($lead->phone); ?>">
                        </td>
                    </tr>
                    
                    <!-- Statut -->
                    <tr>
                        <th scope="row">
                            <label for="status">Statut</label>
                        </th>
                        <td>
                            <select name="status" id="status" required>
                                <option value="active" <?php selected($lead->status, 'active'); ?>>Actif</option>
                                <option value="pending" <?php selected($lead->status, 'pending'); ?>>En attente</option>
                                <option value="unsubscribed" <?php selected($lead->status, 'unsubscribed'); ?>>Désinscrit</option>
                                <option value="bounced" <?php selected($lead->status, 'bounced'); ?>>Bounce</option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Groupes -->
                    <tr>
                        <th scope="row">
                            <label for="groups">Groupes</label>
                        </th>
                        <td>
                            <select name="groups[]" id="groups" multiple size="5" style="width: 100%; max-width: 400px;">
                                <?php foreach ($all_groups as $group): ?>
                                    <option value="<?php echo $group->id; ?>" <?php echo in_array($group->id, $lead_groups) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($group->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs groupes.</p>
                        </td>
                    </tr>
                    
                    <!-- Catégories -->
                    <tr>
                        <th scope="row">
                            <label for="categories">Catégories</label>
                        </th>
                        <td>
                            <select name="categories[]" id="categories" multiple size="8" style="width: 100%; max-width: 400px;">
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php echo in_array($cat->term_id, $lead_categories) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Le lead recevra les notifications des articles de ces catégories.</p>
                        </td>
                    </tr>
                    
                    <!-- Informations supplémentaires -->
                    <tr>
                        <th scope="row">Informations</th>
                        <td>
                            <p><strong>Source :</strong> <?php echo esc_html($lead->source ?: '—'); ?></p>
                            <p><strong>IP :</strong> <?php echo esc_html($lead->ip_address ?: '—'); ?></p>
                            <p><strong>Inscription :</strong> <?php echo date_i18n('d/m/Y à H:i', strtotime($lead->created_at)); ?></p>
                            <?php if ($lead->confirmed_at): ?>
                                <p><strong>Confirmé le :</strong> <?php echo date_i18n('d/m/Y à H:i', strtotime($lead->confirmed_at)); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        Enregistrer les modifications
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lead-collector'); ?>" class="button button-large">
                        Annuler
                    </a>
                    <button type="button" class="button button-large button-link-delete lc-delete-lead-btn" style="float: right; color: var(--danger);">
                        Supprimer ce lead
                    </button>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Enregistrer les modifications
            $('#lc-lead-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'lc_update_lead',
                    nonce: lcAdminAjax.nonce,
                    lead_id: $('input[name="lead_id"]').val(),
                    email: $('#email').val(),
                    first_name: $('#first_name').val(),
                    last_name: $('#last_name').val(),
                    phone: $('#phone').val(),
                    status: $('#status').val()
                };
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Mettre à jour les groupes
                            var groupIds = $('#groups').val() || [];
                            $.post(lcAdminAjax.ajaxurl, {
                                action: 'lc_update_lead_groups',
                                nonce: lcAdminAjax.nonce,
                                lead_id: formData.lead_id,
                                group_ids: groupIds
                            });
                            
                            // Mettre à jour les catégories
                            var categoryIds = $('#categories').val() || [];
                            $.post(lcAdminAjax.ajaxurl, {
                                action: 'lc_update_lead_categories',
                                nonce: lcAdminAjax.nonce,
                                lead_id: formData.lead_id,
                                category_ids: categoryIds
                            });
                            
                            alert('Lead mis à jour avec succès !');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lead-collector'); ?>';
                        } else {
                            alert(response.data.message || 'Erreur lors de la mise à jour');
                        }
                    }
                });
            });
            
            // Supprimer le lead
            $('.lc-delete-lead-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement ce lead ?')) {
                    return;
                }
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_delete_lead',
                        nonce: lcAdminAjax.nonce,
                        lead_id: $('input[name="lead_id"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Lead supprimé avec succès');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lead-collector'); ?>';
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
     * Afficher le formulaire de création d'un lead
     */
    private static function render_create_lead() {
        // TODO: Implémenter le formulaire de création
        // Similaire à render_edit_lead mais sans charger un lead existant
        echo '<div class="wrap"><h1>Fonctionnalité à venir : Créer un lead manuellement</h1></div>';
    }
}
