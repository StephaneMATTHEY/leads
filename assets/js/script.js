/**
 * Fichier: assets/js/script.js
 * JavaScript pour le formulaire de collecte de leads (Frontend)
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Gestion de la soumission du formulaire
         */
        $('#lc-lead-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('.lc-submit-btn');
            const $message = $('#lc-form-message');
            const $email = $form.find('input[name="email"]');
            const $terms = $form.find('input[name="terms"]');
            
            // Réinitialiser les messages
            $message.removeClass('lc-success lc-error').hide();
            
            // Validation côté client
            const email = $email.val().trim();
            const termsChecked = $terms.is(':checked');
            
            if (!email) {
                showMessage($message, lcAjax.messages.required, 'error');
                $email.focus();
                return;
            }
            
            if (!isValidEmail(email)) {
                showMessage($message, lcAjax.messages.invalid_email, 'error');
                $email.focus();
                return;
            }
            
            if (!termsChecked) {
                showMessage($message, lcAjax.messages.terms_required, 'error');
                return;
            }
            
            // Désactiver le bouton et ajouter un état de chargement
            $submitBtn.prop('disabled', true);
            $form.addClass('lc-loading');
            
            // Envoyer la requête AJAX
            $.ajax({
                url: lcAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'submit_lead',
                    nonce: lcAjax.nonce,
                    email: email,
                    terms: termsChecked
                },
                success: function(response) {
                    if (response.success) {
                        // Succès
                        showMessage($message, response.data.message, 'success');
                        $form[0].reset();
                        
                        // Décocher la checkbox
                        $terms.prop('checked', false);
                        
                        // Événement personnalisé pour le tracking
                        $(document).trigger('leadCollectorSuccess', [email]);
                        
                    } else {
                        // Erreur
                        showMessage($message, response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error);
                    showMessage($message, lcAjax.messages.error, 'error');
                },
                complete: function() {
                    // Réactiver le bouton
                    $submitBtn.prop('disabled', false);
                    $form.removeClass('lc-loading');
                }
            });
        });
        
        /**
         * Validation email en temps réel
         */
        $('#lc-lead-form input[name="email"]').on('blur', function() {
            const $input = $(this);
            const email = $input.val().trim();
            
            if (email && !isValidEmail(email)) {
                $input.css('border-color', 'var(--danger)');
            } else {
                $input.css('border-color', '');
            }
        });
        
        /**
         * Reset de la bordure au focus
         */
        $('#lc-lead-form input[name="email"]').on('focus', function() {
            $(this).css('border-color', '');
        });
        
    });
    
    /**
     * Valider une adresse email
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Afficher un message
     */
    function showMessage($element, message, type) {
        $element
            .removeClass('lc-success lc-error')
            .addClass('lc-' + type)
            .html(message)
            .slideDown(300);
        
        // Auto-masquer après 5 secondes pour les succès
        if (type === 'success') {
            setTimeout(function() {
                $element.slideUp(300);
            }, 5000);
        }
    }
    
})(jQuery);
