/**
 * Fichier: assets/js/admin.js
 * JavaScript pour l'interface d'administration du plugin
 * Gestion des actions AJAX, validation, interactions
 */

(function($) {
    'use strict';

    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        initDeleteActions();
        initBulkActions();
        initFormValidation();
        initCodeEditor();
        initTooltips();
        initConfirmations();
    });

    /**
     * Initialiser les actions de suppression
     */
    function initDeleteActions() {
        
        // Suppression générique avec confirmation
        $(document).on('click', '[data-confirm-delete]', function(e) {
            e.preventDefault();
            
            const message = $(this).data('confirm-delete') || lcAdminAjax.messages.confirm_delete;
            
            if (!confirm(message)) {
                return;
            }
            
            const $element = $(this);
            const action = $element.data('action');
            const id = $element.data('id');
            
            performAjaxAction(action, { id: id }, function(response) {
                if (response.success) {
                    // Supprimer la ligne du tableau ou recharger
                    $element.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                    showNotice('success', lcAdminAjax.messages.delete_success);
                }
            });
        });
    }

    /**
     * Initialiser les actions groupées
     */
    function initBulkActions() {
        
        // Sélectionner tous les éléments
        $('#select-all').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.bulk-checkbox').prop('checked', isChecked);
            updateBulkActionsUI();
        });
        
        // Mise à jour de l'UI des actions groupées
        $(document).on('change', '.bulk-checkbox', function() {
            updateBulkActionsUI();
        });
        
        // Appliquer l'action groupée
        $('#bulk-action-apply').on('click', function() {
            const action = $('#bulk-action-select').val();
            
            if (!action || action === '-1') {
                alert('Veuillez sélectionner une action.');
                return;
            }
            
            const selectedIds = getSelectedIds();
            
            if (selectedIds.length === 0) {
                alert('Veuillez sélectionner au moins un élément.');
                return;
            }
            
            if (action === 'delete' && !confirm('Supprimer les éléments sélectionnés ?')) {
                return;
            }
            
            performBulkAction(action, selectedIds);
        });
    }

    /**
     * Mettre à jour l'interface des actions groupées
     */
    function updateBulkActionsUI() {
        const selectedCount = $('.bulk-checkbox:checked').length;
        
        if (selectedCount > 0) {
            $('#bulk-actions-bar').show();
            $('#selected-count').text(selectedCount);
        } else {
            $('#bulk-actions-bar').hide();
        }
    }

    /**
     * Récupérer les IDs sélectionnés
     */
    function getSelectedIds() {
        const ids = [];
        $('.bulk-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    /**
     * Exécuter une action groupée
     */
    function performBulkAction(action, ids) {
        showLoader();
        
        $.ajax({
            url: lcAdminAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'lc_bulk_action',
                nonce: lcAdminAjax.nonce,
                bulk_action: action,
                ids: ids
            },
            success: function(response) {
                hideLoader();
                
                if (response.success) {
                    showNotice('success', response.data.message || 'Action effectuée avec succès');
                    location.reload();
                } else {
                    showNotice('error', response.data.message || 'Erreur');
                }
            },
            error: function() {
                hideLoader();
                showNotice('error', 'Erreur de connexion');
            }
        });
    }

    /**
     * Validation des formulaires
     */
    function initFormValidation() {
        
        // Validation en temps réel
        $('form[data-validate]').on('submit', function(e) {
            const $form = $(this);
            let isValid = true;
            
            // Supprimer les erreurs précédentes
            $form.find('.error').removeClass('error');
            $form.find('.error-message').remove();
            
            // Valider les champs requis
            $form.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    showFieldError($field, 'Ce champ est requis');
                    isValid = false;
                }
                
                // Validation email
                if ($field.attr('type') === 'email' && value && !isValidEmail(value)) {
                    showFieldError($field, 'Email invalide');
                    isValid = false;
                }
                
                // Validation URL
                if ($field.attr('type') === 'url' && value && !isValidUrl(value)) {
                    showFieldError($field, 'URL invalide');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                scrollToFirstError();
            }
        });
    }

    /**
     * Afficher une erreur sur un champ
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        $field.after('<span class="error-message" style="color: var(--danger); font-size: 13px; display: block; margin-top: 5px;">' + message + '</span>');
    }

    /**
     * Scroller vers la première erreur
     */
    function scrollToFirstError() {
        const $firstError = $('.error').first();
        if ($firstError.length) {
            $('html, body').animate({
                scrollTop: $firstError.offset().top - 100
            }, 300);
            $firstError.focus();
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
     * Valider une URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Initialiser l'éditeur de code (pour les templates)
     */
    function initCodeEditor() {
        
        // CodeMirror pour les templates HTML
        if (typeof wp !== 'undefined' && wp.codeEditor) {
            
            $('textarea[data-code-editor]').each(function() {
                const $textarea = $(this);
                const editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                
                editorSettings.codemirror = _.extend(
                    {},
                    editorSettings.codemirror,
                    {
                        indentUnit: 2,
                        tabSize: 2,
                        mode: 'htmlmixed',
                        lineNumbers: true,
                        lineWrapping: true,
                        theme: 'default'
                    }
                );
                
                wp.codeEditor.initialize($textarea, editorSettings);
            });
        }
    }

    /**
     * Initialiser les tooltips
     */
    function initTooltips() {
        
        // Tooltip simple au survol
        $('[data-tooltip]').on('mouseenter', function() {
            const $element = $(this);
            const text = $element.data('tooltip');
            const position = $element.data('tooltip-position') || 'top';
            
            const $tooltip = $('<div class="lc-tooltip"></div>')
                .text(text)
                .css({
                    position: 'absolute',
                    background: 'var(--gray-900)',
                    color: 'white',
                    padding: '8px 12px',
                    borderRadius: 'var(--radius)',
                    fontSize: '13px',
                    whiteSpace: 'nowrap',
                    zIndex: 10000,
                    pointerEvents: 'none'
                });
            
            $('body').append($tooltip);
            
            // Positionner le tooltip
            const offset = $element.offset();
            const elementWidth = $element.outerWidth();
            const elementHeight = $element.outerHeight();
            const tooltipWidth = $tooltip.outerWidth();
            const tooltipHeight = $tooltip.outerHeight();
            
            let top, left;
            
            if (position === 'top') {
                top = offset.top - tooltipHeight - 10;
                left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
            } else if (position === 'bottom') {
                top = offset.top + elementHeight + 10;
                left = offset.left + (elementWidth / 2) - (tooltipWidth / 2);
            }
            
            $tooltip.css({ top: top, left: left }).fadeIn(200);
            
            $element.data('tooltip-element', $tooltip);
            
        }).on('mouseleave', function() {
            const $tooltip = $(this).data('tooltip-element');
            if ($tooltip) {
                $tooltip.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });
    }

    /**
     * Initialiser les confirmations
     */
    function initConfirmations() {
        
        // Confirmation avant de quitter une page avec formulaire modifié
        let formChanged = false;
        
        $('form').on('change input', 'input, textarea, select', function() {
            formChanged = true;
        });
        
        $('form').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function(e) {
            if (formChanged) {
                const message = 'Vous avez des modifications non enregistrées. Voulez-vous vraiment quitter ?';
                e.returnValue = message;
                return message;
            }
        });
    }

    /**
     * Exécuter une action AJAX
     */
    function performAjaxAction(action, data, callback) {
        
        showLoader();
        
        const ajaxData = $.extend({}, data, {
            action: action,
            nonce: lcAdminAjax.nonce
        });
        
        $.ajax({
            url: lcAdminAjax.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                hideLoader();
                
                if (callback) {
                    callback(response);
                }
                
                if (!response.success && response.data && response.data.message) {
                    showNotice('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoader();
                showNotice('error', 'Erreur de connexion : ' + error);
                console.error('AJAX Error:', xhr, status, error);
            }
        });
    }

    /**
     * Afficher un loader global
     */
    function showLoader() {
        if ($('#lc-loader').length === 0) {
            const $loader = $('<div id="lc-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 99999; display: flex; align-items: center; justify-content: center;"><div class="lc-spinner" style="border: 4px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; width: 40px; height: 40px; animation: lc-spin 0.6s linear infinite;"></div></div>');
            $('body').append($loader);
        }
    }

    /**
     * Masquer le loader
     */
    function hideLoader() {
        $('#lc-loader').fadeOut(200, function() {
            $(this).remove();
        });
    }

    /**
     * Afficher une notification
     */
    function showNotice(type, message) {
        
        // Supprimer les anciennes notifications
        $('.lc-notice').remove();
        
        const typeClass = type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-info';
        
        const $notice = $('<div class="notice lc-notice ' + typeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').first().after($notice);
        
        // Scroll vers la notification
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 300);
        
        // Auto-masquer après 5 secondes
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Bouton de fermeture
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(200, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Copier dans le presse-papier
     */
    window.lcCopyToClipboard = function(text) {
        const $temp = $('<textarea>').val(text).appendTo('body').select();
        document.execCommand('copy');
        $temp.remove();
        showNotice('success', 'Copié dans le presse-papier !');
    };

    /**
     * Gérer les modals
     */
    function openModal(modalId) {
        $('#' + modalId).addClass('active').fadeIn(200);
    }

    function closeModal(modalId) {
        $('#' + modalId).removeClass('active').fadeOut(200);
    }

    $(document).on('click', '[data-modal]', function(e) {
        e.preventDefault();
        openModal($(this).data('modal'));
    });

    $(document).on('click', '.lc-modal-close, .lc-modal', function(e) {
        if (e.target === this) {
            $(this).closest('.lc-modal').removeClass('active').fadeOut(200);
        }
    });

    // Fermer avec Échap
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.lc-modal.active').removeClass('active').fadeOut(200);
        }
    });

    /**
     * Export CSV (déclenché depuis les pages)
     */
    window.lcExportCSV = function(data, filename) {
        const csv = convertToCSV(data);
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename || 'export.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    /**
     * Convertir un tableau en CSV
     */
    function convertToCSV(data) {
        if (!data || !data.length) return '';
        
        const headers = Object.keys(data[0]);
        const csvRows = [];
        
        // En-têtes
        csvRows.push(headers.join(','));
        
        // Lignes
        data.forEach(row => {
            const values = headers.map(header => {
                const value = row[header] || '';
                return '"' + String(value).replace(/"/g, '""') + '"';
            });
            csvRows.push(values.join(','));
        });
        
        return csvRows.join('\n');
    }

    /**
     * Gestion des onglets (si nécessaire)
     */
    $(document).on('click', '.nav-tab', function(e) {
        e.preventDefault();
        
        const $tab = $(this);
        const target = $tab.attr('href');
        
        // Activer l'onglet
        $('.nav-tab').removeClass('nav-tab-active');
        $tab.addClass('nav-tab-active');
        
        // Afficher le contenu
        $('.tab-content').hide();
        $(target).show();
    });

    /**
     * Auto-save (sauvegarde automatique des brouillons)
     */
    let autoSaveTimeout;
    
    function enableAutoSave($form, action) {
        $form.on('change input', 'input, textarea, select', function() {
            clearTimeout(autoSaveTimeout);
            
            autoSaveTimeout = setTimeout(function() {
                const formData = $form.serialize() + '&action=' + action + '&nonce=' + lcAdminAjax.nonce + '&auto_save=1';
                
                $.ajax({
                    url: lcAdminAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            showNotice('info', 'Brouillon sauvegardé automatiquement');
                        }
                    }
                });
            }, 5000); // 5 secondes après la dernière modification
        });
    }

    /**
     * Exposer les fonctions globales
     */
    window.lcAdmin = {
        showNotice: showNotice,
        showLoader: showLoader,
        hideLoader: hideLoader,
        performAjaxAction: performAjaxAction,
        openModal: openModal,
        closeModal: closeModal
    };

})(jQuery);
