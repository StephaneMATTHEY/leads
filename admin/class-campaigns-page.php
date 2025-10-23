<?php
/**
 * Fichier: admin/class-campaigns-page.php
 * Page de gestion des campagnes d'emailing
 * Créer, éditer, envoyer et programmer des campagnes
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Campaigns_Page {
    
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
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        // Router vers la bonne vue
        switch ($action) {
            case 'edit':
                if ($campaign_id) {
                    self::render_edit_campaign($campaign_id);
                } else {
                    self::render_create_campaign();
                }
                break;
                
            case 'create':
                self::render_create_campaign();
                break;
                
            case 'stats':
                if ($campaign_id) {
                    self::render_campaign_stats($campaign_id);
                }
                break;
                
            default:
                self::render_list();
                break;
        }
    }
    
    /**
     * Afficher la liste des campagnes
     */
    private static function render_list() {
        
        $campaigns = LC_Campaign_Manager::get_all_campaigns();
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1 class="wp-heading-inline">
                Campagnes d'emailing
            </h1>
            
            <a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns', array('action' => 'create')); ?>" class="page-title-action">
                Créer une campagne
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Info -->
            <div class="lc-info-box" style="background: var(--gray-50); padding: var(--spacing); border-radius: var(--radius); margin: 20px 0;">
                <h3 style="margin-top: 0;">📬 Campagnes d'emailing</h3>
                <p>Créez et envoyez des emails groupés à vos leads. Vous pouvez cibler tous les leads, des groupes spécifiques, des catégories, ou une liste personnalisée.</p>
            </div>
            
            <!-- Tableau des campagnes -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Nom de la campagne</th>
                        <th style="width: 20%;">Cible</th>
                        <th style="width: 15%;">Statut</th>
                        <th style="width: 15%;">Date d'envoi</th>
                        <th style="width: 15%;">Résultats</th>
                        <th style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--gray-400);">
                                Aucune campagne créée pour le moment
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($campaign->name); ?></strong>
                                    <br><small class="description"><?php echo esc_html(substr($campaign->subject, 0, 50)); ?>...</small>
                                </td>
                                <td>
                                    <?php
                                    $target_labels = array(
                                        'all' => 'Tous les leads',
                                        'groups' => 'Groupes spécifiques',
                                        'categories' => 'Catégories spécifiques',
                                        'custom' => 'Liste personnalisée'
                                    );
                                    echo esc_html($target_labels[$campaign->target_type] ?? $campaign->target_type);
                                    ?>
                                    <br><small class="description"><?php echo $campaign->total_recipients; ?> destinataire(s)</small>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = array(
                                        'draft' => '<span class="lc-badge lc-badge-default">Brouillon</span>',
                                        'scheduled' => '<span class="lc-badge lc-badge-warning">Programmé</span>',
                                        'sending' => '<span class="lc-badge lc-badge-info">En cours...</span>',
                                        'sent' => '<span class="lc-badge lc-badge-success">Envoyé</span>',
                                        'failed' => '<span class="lc-badge lc-badge-danger">Échec</span>'
                                    );
                                    echo $status_badges[$campaign->status] ?? $campaign->status;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($campaign->scheduled_at && $campaign->status === 'scheduled'): ?>
                                        <strong>Programmé pour :</strong><br>
                                        <?php echo date_i18n('d/m/Y à H:i', strtotime($campaign->scheduled_at)); ?>
                                    <?php elseif ($campaign->sent_at): ?>
                                        <?php echo date_i18n('d/m/Y à H:i', strtotime($campaign->sent_at)); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($campaign->status === 'sent'): ?>
                                        <strong><?php echo $campaign->total_sent; ?></strong> envoyés
                                        <?php if ($campaign->total_failed > 0): ?>
                                            <br><span style="color: var(--danger);"><?php echo $campaign->total_failed; ?> échecs</span>
                                        <?php endif; ?>
                                        <br><a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns', array('action' => 'stats', 'campaign_id' => $campaign->id)); ?>">Voir les stats</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($campaign->status === 'draft'): ?>
                                        <a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns', array('action' => 'edit', 'campaign_id' => $campaign->id)); ?>" class="button button-small">
                                            Éditer
                                        </a>
                                        <br>
                                        <button class="button button-small button-primary lc-send-campaign" data-campaign-id="<?php echo $campaign->id; ?>" style="margin-top: 5px;">
                                            Envoyer
                                        </button>
                                    <?php elseif ($campaign->status === 'scheduled'): ?>
                                        <button class="button button-small button-link-delete lc-cancel-schedule" data-campaign-id="<?php echo $campaign->id; ?>">
                                            Annuler
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns', array('action' => 'edit', 'campaign_id' => $campaign->id)); ?>">
                                            Voir
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Envoyer une campagne
            $('.lc-send-campaign').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Confirmer l\'envoi immédiat de cette campagne ?')) {
                    return;
                }
                
                var $btn = $(this);
                var campaignId = $btn.data('campaign-id');
                
                $btn.prop('disabled', true).text('Envoi...');
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lc_send_campaign',
                        nonce: lcAdminAjax.nonce,
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Campagne envoyée avec succès ! ' + response.data.total_sent + ' emails envoyés.');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Erreur lors de l\'envoi');
                            $btn.prop('disabled', false).text('Envoyer');
                        }
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire de création de campagne
     */
    private static function render_create_campaign() {
        
        $groups = LC_Group_Manager::get_all_groups();
        $categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Créer une campagne</h1>
            
            <form id="lc-campaign-form" style="max-width: 800px;">
                
                <table class="form-table">
                    
                    <tr>
                        <th scope="row">
                            <label for="name">Nom de la campagne *</label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required placeholder="Ex: Newsletter Janvier 2025">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="subject">Sujet de l'email *</label>
                        </th>
                        <td>
                            <input type="text" name="subject" id="subject" class="large-text" required placeholder="Votre sujet d'email">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="body">Contenu de l'email (HTML) *</label>
                        </th>
                        <td>
                            <?php
                            wp_editor('', 'body', array(
                                'textarea_name' => 'body',
                                'textarea_rows' => 15,
                                'media_buttons' => true
                            ));
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="target_type">Cible *</label>
                        </th>
                        <td>
                            <select name="target_type" id="target_type" required>
                                <option value="all">Tous les leads actifs</option>
                                <option value="groups">Groupes spécifiques</option>
                                <option value="categories">Catégories spécifiques</option>
                                <option value="custom">Liste personnalisée (IDs)</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="target-groups" style="display: none;">
                        <th scope="row">
                            <label for="groups">Groupes</label>
                        </th>
                        <td>
                            <select name="groups[]" multiple size="5" style="width: 100%; max-width: 400px;">
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group->id; ?>">
                                        <?php echo esc_html($group->name); ?> (<?php echo $group->lead_count; ?> leads)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="target-categories" style="display: none;">
                        <th scope="row">
                            <label for="categories">Catégories</label>
                        </th>
                        <td>
                            <select name="categories[]" multiple size="8" style="width: 100%; max-width: 400px;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>">
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="target-custom" style="display: none;">
                        <th scope="row">
                            <label for="custom_ids">IDs des leads</label>
                        </th>
                        <td>
                            <textarea name="custom_ids" id="custom_ids" rows="5" class="large-text" placeholder="1, 2, 3, 4, 5"></textarea>
                            <p class="description">IDs séparés par des virgules</p>
                        </td>
                    </tr>
                    
                </table>
                
                <p class="submit">
                    <button type="button" class="button button-primary button-large lc-save-draft">
                        Enregistrer comme brouillon
                    </button>
                    <button type="button" class="button button-primary button-large lc-send-now">
                        Envoyer maintenant
                    </button>
                    <button type="button" class="button button-large lc-schedule">
                        Programmer
                    </button>
                    <a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns'); ?>" class="button button-large">
                        Annuler
                    </a>
                </p>
                
            </form>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Afficher/masquer les options de ciblage
            $('#target_type').on('change', function() {
                var type = $(this).val();
                
                $('#target-groups, #target-categories, #target-custom').hide();
                
                if (type === 'groups') {
                    $('#target-groups').show();
                } else if (type === 'categories') {
                    $('#target-categories').show();
                } else if (type === 'custom') {
                    $('#target-custom').show();
                }
            });
            
            // Enregistrer comme brouillon
            $('.lc-save-draft').on('click', function() {
                saveCampaign('draft');
            });
            
            // Envoyer maintenant
            $('.lc-send-now').on('click', function() {
                if (confirm('Confirmer l\'envoi immédiat de cette campagne ?')) {
                    saveCampaign('send');
                }
            });
            
            // Programmer
            $('.lc-schedule').on('click', function() {
                var datetime = prompt('Date et heure d\'envoi (YYYY-MM-DD HH:MM):');
                if (datetime) {
                    saveCampaign('schedule', datetime);
                }
            });
            
            function saveCampaign(action, scheduledAt) {
                var targetType = $('#target_type').val();
                var targetIds = [];
                
                if (targetType === 'groups') {
                    targetIds = $('select[name="groups[]"]').val() || [];
                } else if (targetType === 'categories') {
                    targetIds = $('select[name="categories[]"]').val() || [];
                } else if (targetType === 'custom') {
                    targetIds = $('#custom_ids').val().split(',').map(id => id.trim());
                }
                
                var formData = {
                    action: 'lc_create_campaign',
                    nonce: lcAdminAjax.nonce,
                    name: $('#name').val(),
                    subject: $('#subject').val(),
                    body: $('#body').val(),
                    target_type: targetType,
                    target_ids: targetIds,
                    campaign_action: action,
                    scheduled_at: scheduledAt
                };
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Campagne créée avec succès !');
                            window.location.href = '<?php echo LC_Admin_Menu::get_page_url('lc-campaigns'); ?>';
                        } else {
                            alert(response.data.message || 'Erreur lors de la création');
                        }
                    }
                });
            }
            
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le formulaire d'édition de campagne
     */
    private static function render_edit_campaign($campaign_id) {
        // TODO: Implémenter l'édition
        echo '<div class="wrap"><h1>Édition de campagne (à venir)</h1></div>';
    }
    
    /**
     * Afficher les statistiques d'une campagne
     */
    private static function render_campaign_stats($campaign_id) {
        
        $campaign = LC_Campaign_Manager::get_campaign($campaign_id);
        $stats = LC_Campaign_Manager::get_campaign_stats($campaign_id);
        
        ?>
        <div class="wrap lc-admin-page">
            
            <h1>Statistiques : <?php echo esc_html($campaign->name); ?></h1>
            
            <div class="lc-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--spacing); margin: 20px 0;">
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Envoyés</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--success);"><?php echo $stats['total_sent']; ?></div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Échecs</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--danger);"><?php echo $stats['total_failed']; ?></div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Taux d'ouverture</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--info);"><?php echo $stats['open_rate']; ?>%</div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        <?php echo $stats['total_opened']; ?> ouvertures
                    </div>
                </div>
                
                <div class="lc-stat-card" style="background: white; padding: var(--spacing); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <div style="color: var(--gray-500); font-size: 14px;">Taux de clic</div>
                    <div style="font-size: 32px; font-weight: bold; color: var(--accent);"><?php echo $stats['click_rate']; ?>%</div>
                    <div style="color: var(--gray-400); font-size: 12px; margin-top: 5px;">
                        <?php echo $stats['total_clicked']; ?> clics
                    </div>
                </div>
                
            </div>
            
            <p>
                <a href="<?php echo LC_Admin_Menu::get_page_url('lc-campaigns'); ?>" class="button">
                    ← Retour aux campagnes
                </a>
            </p>
            
        </div>
        <?php
    }
}
