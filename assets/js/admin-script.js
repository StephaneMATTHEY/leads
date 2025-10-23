/**
 * Fichier: assets/js/admin-script.js
 * JavaScript pour l'interface d'administration des leads
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Exporter les leads en CSV
         */
        $('#lc-export-leads').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Désactiver le bouton
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Export en cours...');
            
            $.ajax({
                url: lcAdminAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'export_leads',
                    nonce: lcAdminAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Créer un lien de téléchargement
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        showAdminMessage('Export réussi ! Le fichier CSV a été téléchargé.', 'success');
                    } else {
                        showAdminMessage(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error);
                    showAdminMessage('Erreur lors de l\'export. Veuillez réessayer.', 'error');
                },
                complete: function() {
                    // Réactiver le bouton
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        /**
         * Supprimer un lead
         */
        $(document).on('click', '.lc-delete-lead', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const leadId = $btn.data('lead-id');
            const $row = $btn.closest('tr');
            
            // Demander confirmation
            if (!confirm(lcAdminAjax.messages.confirm_delete)) {
                return;
            }
            
            // Désactiver le bouton
            $btn.prop('disabled', true).addClass('lc-loading');
            
            $.ajax({
                url: lcAdminAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_lead',
                    nonce: lcAdminAjax.nonce,
                    lead_id: leadId
                },
                success: function(response) {
                    if (response.success) {
                        // Supprimer la ligne avec animation
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Vérifier s'il reste des leads
                            const remainingRows = $('.lc-leads-table tbody tr').length;
                            if (remainingRows === 0) {
                                location.reload();
                            }
                        });
                        
                        showAdminMessage(lcAdminAjax.messages.delete_success, 'success');
                        
                    } else {
                        showAdminMessage(response.data.message, 'error');
                        $btn.prop('disabled', false).removeClass('lc-loading');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error);
                    showAdminMessage(lcAdminAjax.messages.delete_error, 'error');
                    $btn.prop('disabled', false).removeClass('lc-loading');
                }
            });
        });
        
        /**
         * Copier le shortcode
         */
        $('.lc-copy-shortcode').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const shortcode = $btn.data('shortcode');
            const originalText = $btn.html();
            
            // Copier dans le presse-papier
            copyToClipboard(shortcode);
            
            // Feedback visuel
            $btn.html('<span class="dashicons dashicons-yes"></span> Copié !');
            
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        });
        
        /**
         * Animation au survol des statistiques
         */
        $('.lc-stat-card').hover(
            function() {
                $(this).find('.lc-stat-value').css('color', 'var(--primary)');
            },
            function() {
                $(this).find('.lc-stat-value').css('color', '');
            }
        );
        
    });
    
    /**
     * Copier du texte dans le presse-papier
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            // API moderne
            navigator.clipboard.writeText(text).catch(function(err) {
                console.error('Erreur lors de la copie:', err);
                fallbackCopyToClipboard(text);
            });
        } else {
            // Fallback pour les anciens navigateurs
            fallbackCopyToClipboard(text);
        }
    }
    
    /**
     * Fallback pour copier dans le presse-papier
     */
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Erreur lors de la copie:', err);
        }
        
        document.body.removeChild(textArea);
    }
    
    /**
     * Afficher un message dans l'admin
     */
    function showAdminMessage(message, type) {
        const $messageDiv = $('#lc-admin-message');
        
        $messageDiv
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .slideDown(300);
        
        // Auto-masquer après 5 secondes
        setTimeout(function() {
            $messageDiv.slideUp(300);
        }, 5000);
        
        // Scroller vers le message
        $('html, body').animate({
            scrollTop: $messageDiv.offset().top - 100
        }, 500);
    }
    
    /**
     * Animation de rotation pour le bouton d'actualisation
     */
    $(document).on('click', '.lc-refresh-btn', function() {
        const $icon = $(this).find('.dashicons');
        $icon.css('animation', 'spin 0.5s linear');
        
        setTimeout(function() {
            $icon.css('animation', '');
        }, 500);
    });
    
})(jQuery);

/**
 * Animation CSS pour le spinner
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite !important;
    }
`;
document.head.appendChild(style);
