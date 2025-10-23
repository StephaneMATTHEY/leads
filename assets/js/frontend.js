/**
 * Fichier: assets/js/frontend.js
 * JavaScript pour les formulaires d'inscription frontend
 * Gestion de la soumission AJAX, validation, messages d'erreur
 */

(function($) {
    'use strict';

    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        initFormSubmission();
    });

    /**
     * Initialiser la soumission des formulaires
     */
    function initFormSubmission() {
        
        // Écouter la soumission de tous les formulaires Lead Collector
        $('.lc-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $container = $form.closest('.lc-form-container');
            const $submitBtn = $form.find('.lc-submit-btn');
            const $messages = $form.find('.lc-form-messages');
            
            // Validation côté client
            if (!validateForm($form)) {
                return;
            }
            
            // Désactiver le bouton et afficher le chargement
            $submitBtn.prop('disabled', true).addClass('loading');
            $messages.removeClass('visible success error info');
            
            // Préparer les données du formulaire
            const formData = $form.serialize() + '&action=lc_submit_lead&nonce=' + lcAjax.nonce;
            
            // Envoyer la requête AJAX
            $.ajax({
                url: lcAjax.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    handleSuccess(response, $form, $submitBtn, $messages, $container);
                },
                error: function(xhr, status, error) {
                    handleError(error, $submitBtn, $messages);
                }
            });
        });
    }

    /**
     * Valider le formulaire côté client
     */
    function validateForm($form) {
        let isValid = true;
        
        // Supprimer les erreurs précédentes
        $form.find('.lc-form-field').removeClass('has-error shake');
        $form.find('.field-error').remove();
        
        // Valider chaque champ requis
        $form.find('[required]').each(function() {
            const $field = $(this);
            const $fieldWrapper = $field.closest('.lc-form-field');
            const fieldType = $field.attr('type');
            const fieldValue = $field.val().trim();
            
            // Vérifier si le champ est vide
            if (!fieldValue) {
                showFieldError($fieldWrapper, $field, lcAjax.messages.required);
                isValid = false;
                return;
            }
            
            // Validation spécifique pour l'email
            if (fieldType === 'email' && !isValidEmail(fieldValue)) {
                showFieldError($fieldWrapper, $field, lcAjax.messages.invalid_email);
                isValid = false;
                return;
            }
            
            // Validation pour les checkbox (conditions)
            if (fieldType === 'checkbox' && !$field.is(':checked')) {
                showFieldError($fieldWrapper, $field, lcAjax.messages.terms_required);
                isValid = false;
                return;
            }
        });
        
        return isValid;
    }

    /**
     * Afficher une erreur sur un champ spécifique
     */
    function showFieldError($fieldWrapper, $field, message) {
        $fieldWrapper.addClass('has-error shake');
        
        // Créer le message d'erreur
        const $error = $('<div class="field-error"></div>').text(message);
        $field.after($error);
        
        // Retirer l'animation shake après 500ms
        setTimeout(function() {
            $fieldWrapper.removeClass('shake');
        }, 500);
        
        // Focus sur le premier champ en erreur
        if ($fieldWrapper.find('.field-error').length === 1) {
            $field.focus();
        }
    }

    /**
     * Valider une adresse email
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Gérer le succès de la soumission
     */
    function handleSuccess(response, $form, $submitBtn, $messages, $container) {
        
        // Réactiver le bouton
        $submitBtn.prop('disabled', false).removeClass('loading');
        
        if (response.success) {
            
            // Afficher le message de succès
            $messages
                .addClass('visible success')
                .html('✓ ' + response.data.message);
            
            // Réinitialiser le formulaire
            $form[0].reset();
            
            // Scroll vers le message
            scrollToElement($messages);
            
            // Redirection si configurée
            const redirectUrl = $form.data('redirect');
            if (redirectUrl) {
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, 2000);
            }
            
            // Événement personnalisé pour tracking
            $(document).trigger('lc_lead_submitted', [response.data]);
            
            // Si double opt-in, afficher un message spécifique
            if (response.data.requires_confirmation) {
                $messages.html('✉️ ' + response.data.message);
            }
            
        } else {
            
            // Afficher le message d'erreur
            $messages
                .addClass('visible error')
                .html('✗ ' + (response.data.message || lcAjax.messages.error));
            
            scrollToElement($messages);
        }
    }

    /**
     * Gérer les erreurs de soumission
     */
    function handleError(error, $submitBtn, $messages) {
        
        // Réactiver le bouton
        $submitBtn.prop('disabled', false).removeClass('loading');
        
        // Afficher le message d'erreur générique
        $messages
            .addClass('visible error')
            .html('✗ ' + lcAjax.messages.error);
        
        scrollToElement($messages);
        
        console.error('Lead Collector Error:', error);
    }

    /**
     * Scroller vers un élément
     */
    function scrollToElement($element) {
        $('html, body').animate({
            scrollTop: $element.offset().top - 100
        }, 500);
    }

    /**
     * Afficher/masquer les champs conditionnels (si utilisé)
     */
    function handleConditionalFields() {
        // TODO: Implémenter la logique des champs conditionnels si nécessaire
    }

    /**
     * Auto-complétion (si activé)
     */
    function handleAutocomplete() {
        // Les navigateurs modernes gèrent l'autocomplete automatiquement
        // Nous n'avons rien à faire ici sauf si on veut le désactiver
    }

    /**
     * Détection de bot via le temps de remplissage
     */
    function detectBot($form) {
        // Stocker le timestamp de chargement du formulaire
        const formLoadTime = Date.now();
        $form.data('load-time', formLoadTime);
        
        // Au moment de la soumission, vérifier le temps écoulé
        // Si < 2 secondes, c'est probablement un bot
        const submitTime = Date.now();
        const timeDiff = submitTime - formLoadTime;
        
        return timeDiff < 2000; // Retourne true si suspect
    }

    /**
     * Gestion des événements clavier (Entrée pour soumettre)
     */
    $(document).on('keypress', '.lc-form input:not([type="submit"])', function(e) {
        if (e.which === 13 && !$(this).is('textarea')) {
            e.preventDefault();
            $(this).closest('.lc-form').find('.lc-submit-btn').click();
        }
    });

    /**
     * Amélioration UX : Supprimer l'erreur au focus
     */
    $(document).on('focus', '.lc-form input, .lc-form textarea, .lc-form select', function() {
        const $field = $(this).closest('.lc-form-field');
        $field.removeClass('has-error');
        $field.find('.field-error').remove();
    });

    /**
     * Support du copier-coller pour éviter les espaces inutiles
     */
    $(document).on('paste', '.lc-form input[type="email"]', function() {
        const $input = $(this);
        setTimeout(function() {
            $input.val($input.val().trim());
        }, 10);
    });

    /**
     * Tracking Google Analytics (si GTM/GA est présent)
     */
    $(document).on('lc_lead_submitted', function(event, data) {
        // Google Analytics 4
        if (typeof gtag !== 'undefined') {
            gtag('event', 'generate_lead', {
                'event_category': 'Lead Collector',
                'event_label': 'Form Submission',
                'value': 1
            });
        }
        
        // Google Tag Manager
        if (typeof dataLayer !== 'undefined') {
            dataLayer.push({
                'event': 'lead_submitted',
                'leadId': data.lead_id,
                'requiresConfirmation': data.requires_confirmation
            });
        }
        
        // Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Lead');
        }
    });

    /**
     * Protection contre le spam : Désactiver le copier-coller multiple
     */
    let pasteCount = 0;
    $(document).on('paste', '.lc-form input', function() {
        pasteCount++;
        if (pasteCount > 5) {
            console.warn('Lead Collector: Activité suspecte détectée (trop de copier-coller)');
        }
    });

})(jQuery);
