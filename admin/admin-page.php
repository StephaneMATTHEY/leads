<?php
/**
 * Fichier: admin/admin-page.php
 * Page d'administration pour gérer les leads
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Admin_Page {
    
    /**
     * Rendu de la page principale
     */
    public static function render_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions nécessaires pour accéder à cette page.'));
        }
        
        // Récupérer les statistiques
        $stats = LC_Database::get_stats();
        
        // Récupérer tous les leads
        $leads = LC_Database::get_all_leads();
        
        ?>
        <div class="wrap lc-admin-wrap">
            <h1 class="lc-admin-title">
                <span class="dashicons dashicons-email-alt"></span>
                Leads Collectés
            </h1>
            
            <!-- Statistiques -->
            <div class="lc-stats-grid">
                <div class="lc-stat-card">
                    <div class="lc-stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="lc-stat-content">
                        <div class="lc-stat-value"><?php echo number_format($stats['total'], 0, ',', ' '); ?></div>
                        <div class="lc-stat-label">Total de leads</div>
                    </div>
                </div>
                
                <div class="lc-stat-card">
                    <div class="lc-stat-icon lc-stat-icon-today">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="lc-stat-content">
                        <div class="lc-stat-value"><?php echo number_format($stats['today'], 0, ',', ' '); ?></div>
                        <div class="lc-stat-label">Aujourd'hui</div>
                    </div>
                </div>
                
                <div class="lc-stat-card">
                    <div class="lc-stat-icon lc-stat-icon-week">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="lc-stat-content">
                        <div class="lc-stat-value"><?php echo number_format($stats['week'], 0, ',', ' '); ?></div>
                        <div class="lc-stat-label">Cette semaine</div>
                    </div>
                </div>
                
                <div class="lc-stat-card">
                    <div class="lc-stat-icon lc-stat-icon-month">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="lc-stat-content">
                        <div class="lc-stat-value"><?php echo number_format($stats['month'], 0, ',', ' '); ?></div>
                        <div class="lc-stat-label">Ce mois</div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="lc-actions-bar">
                <button type="button" class="button button-primary lc-export-btn" id="lc-export-leads">
                    <span class="dashicons dashicons-download"></span>
                    Exporter en CSV
                </button>
                
                <button type="button" class="button lc-refresh-btn" onclick="location.reload()">
                    <span class="dashicons dashicons-update"></span>
                    Actualiser
                </button>
            </div>
            
            <!-- Shortcode info -->
            <div class="lc-shortcode-info">
                <h3>Utilisation du shortcode</h3>
                <p>Copiez ce shortcode dans vos pages ou articles pour afficher le formulaire de collecte :</p>
                <div class="lc-shortcode-box">
                    <code>[lead_collector]</code>
                    <button type="button" class="button button-small lc-copy-shortcode" data-shortcode="[lead_collector]">
                        <span class="dashicons dashicons-clipboard"></span>
                        Copier
                    </button>
                </div>
                
                <details class="lc-shortcode-options">
                    <summary>Options du shortcode</summary>
                    <ul>
                        <li><code>title</code> : Titre principal (défaut : "Ne Manquez Aucun Épisode")</li>
                        <li><code>subtitle</code> : Sous-titre (défaut : "Recevez les alertes directement dans votre boîte mail.")</li>
                        <li><code>button_text</code> : Texte du bouton (défaut : "S'INSCRIRE")</li>
                        <li><code>style</code> : Style du formulaire - "dark" ou "light" (défaut : "dark")</li>
                        <li><code>terms_link</code> : Lien vers les conditions d'utilisation</li>
                        <li><code>privacy_link</code> : Lien vers la politique de confidentialité</li>
                    </ul>
                    <p><strong>Exemple :</strong></p>
                    <code>[lead_collector title="Restez Informé" subtitle="Inscrivez-vous à notre newsletter" button_text="JE M'INSCRIS" style="light"]</code>
                </details>
            </div>
            
            <!-- Tableau des leads -->
            <div class="lc-table-container">
                <h2 class="lc-section-title">Liste des leads (<?php echo count($leads); ?>)</h2>
                
                <?php if (empty($leads)): ?>
                    <div class="lc-empty-state">
                        <span class="dashicons dashicons-email-alt"></span>
                        <p>Aucun lead collecté pour le moment.</p>
                        <p class="lc-empty-subtitle">Les nouveaux leads apparaîtront ici automatiquement.</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped lc-leads-table">
                        <thead>
                            <tr>
                                <th class="lc-col-id">ID</th>
                                <th class="lc-col-email">Email</th>
                                <th class="lc-col-date">Date d'inscription</th>
                                <th class="lc-col-ip">Adresse IP</th>
                                <th class="lc-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                                <tr data-lead-id="<?php echo esc_attr($lead->id); ?>">
                                    <td class="lc-col-id"><?php echo esc_html($lead->id); ?></td>
                                    <td class="lc-col-email">
                                        <strong><?php echo esc_html($lead->email); ?></strong>
                                    </td>
                                    <td class="lc-col-date">
                                        <?php 
                                        $date = new DateTime($lead->created_at);
                                        echo $date->format('d/m/Y à H:i:s');
                                        ?>
                                    </td>
                                    <td class="lc-col-ip">
                                        <code><?php echo esc_html($lead->ip_address); ?></code>
                                    </td>
                                    <td class="lc-col-actions">
                                        <button type="button" 
                                                class="button button-small lc-delete-lead" 
                                                data-lead-id="<?php echo esc_attr($lead->id); ?>"
                                                title="Supprimer ce lead">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Messages -->
            <div id="lc-admin-message" class="lc-admin-message" style="display:none;"></div>
        </div>
        <?php
    }
}
