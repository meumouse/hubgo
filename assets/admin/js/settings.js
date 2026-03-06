/**
 * HubGo Settings module.
 *
 * @version 2.1.0
 * @since 2.0.0
 * @param {jQueryStatic} $ jQuery instance.
 * @return {void}
 */
(function($) {
    'use strict';

    const Hubgo_Settings = {
        notificationTimer: null,

        elements: {
            $form: null,
            $saveButton: null,
            $tabs: null,
            $tabPanels: null,
            $toastContainer: null,
        },

        state: {
            originalValues: '',
            isSaving: false,
        },

        config: {
            formSelector: 'form[name="hubgo-shipping-management-wc"]',
            saveButtonSelector: 'button[name="save_settings"]',
            tabSelector: '.hubgo-shipping-management-wc-tab-wrapper .nav-tab',
            tabPanelSelector: '.hubgo-settings-tab',
            defaultTab: '#general',
            tabStorageKey: 'hubgo_admin_settings_tab',
            toastSelector: '.update-notice-spm-wp',
            toastContainerClass: 'hubgo-toast-container',
            notificationDuration: 3000,
            activeClass: 'active',
            fadeSpeed: 'fast',
            buttonOriginalHtml: '',
        },

        /**
         * Bootstrap settings UI behaviors.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        init: function() {
            this.cacheDom();
            this.initTabs();
            this.bindEvents();
            this.initColorFields();
            this.storeOriginalValues();
            this.updateSaveButtonState();

            if ( this.elements.$saveButton.length ) {
                this.config.buttonOriginalHtml = this.elements.$saveButton.html();
            }
        },

        /**
         * Cache frequently used DOM nodes.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        cacheDom: function() {
            this.elements = {
                $form: $( this.config.formSelector ),
                $saveButton: $(),
                $tabs: $( this.config.tabSelector ),
                $tabPanels: $( this.config.tabPanelSelector ),
                $toastContainer: null,
            };

            if ( this.elements.$form.length ) {
                this.elements.$saveButton = this.elements.$form.find( this.config.saveButtonSelector );
            }

            this.createToast();
        },

        /**
         * Bind DOM events for settings form and toast.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        bindEvents: function() {
            if ( ! this.elements.$form.length ) {
                return;
            }

            this.elements.$form.on( 'change input', 'input, select, textarea', this.handleFormChange.bind( this ) );
            this.elements.$form.on( 'submit', this.handleFormSubmit.bind( this ) );
            this.elements.$tabs.on( 'click', this.handleTabClick.bind( this ) );
            $( document ).on( 'click', this.config.toastSelector + ' .hide-toast', this.hideNotification.bind( this ) );
        },

        /**
         * Initialize tab state from hash/localStorage.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        initTabs: function() {
            if ( ! this.elements.$tabs.length || ! this.elements.$tabPanels.length ) {
                return;
            }

            const hash = window.location.hash;
            const storedTab = window.localStorage ? localStorage.getItem( this.config.tabStorageKey ) : '';
            this.activateTab( hash || storedTab || this.config.defaultTab );
        },

        /**
         * Handle tab click and activate selected tab.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {Event} event Click event.
         * @return {void}
         */
        handleTabClick: function( event ) {
            event.preventDefault();
            this.activateTab( $( event.currentTarget ).attr( 'href' ) );
        },

        /**
         * Activate tab and persist it.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} tabId Tab hash id.
         * @return {void}
         */
        activateTab: function( tabId ) {
            if ( ! tabId || '#' === tabId ) {
                tabId = this.config.defaultTab;
            }

            const $selectedTab = this.elements.$tabs.filter( '[href="' + tabId + '"]' );
            if ( ! $selectedTab.length ) {
                return;
            }

            this.elements.$tabs.removeClass( 'nav-tab-active' );
            $selectedTab.addClass( 'nav-tab-active' );
            this.elements.$tabPanels.hide();
            $( tabId ).show();

            if ( window.localStorage ) {
                localStorage.setItem( this.config.tabStorageKey, tabId );
            }

            if ( window.history && history.replaceState ) {
                history.replaceState( null, '', tabId );
            }
        },

        /**
         * Initialize all color field interactions.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        initColorFields: function() {
            const self = this;
            const $colorContainers = this.elements.$form.find( '.color-container' );

            if ( ! $colorContainers.length ) {
                return;
            }

            $colorContainers.each( function() {
                self.syncColorContainer( $( this ) );
            });

            this.elements.$form.on( 'input change', '.color-container input[type="color"]', function( event ) {
                const $container = $( event.currentTarget ).closest( '.color-container' );
                self.syncColorContainer( $container, $( event.currentTarget ).val() );
            });

            this.elements.$form.on( 'input change', '.color-container .get-color-selected', function( event ) {
                const $input = $( event.currentTarget );
                const normalized = self.normalizeHexColor( $input.val() );
                if ( normalized ) {
                    self.syncColorContainer( $input.closest( '.color-container' ), normalized );
                }
            });

            this.elements.$form.on( 'click', '.color-container .reset-color', function( event ) {
                event.preventDefault();
                const $button = $( event.currentTarget );
                const defaultColor = self.normalizeHexColor( $button.data( 'color' ) );

                if ( defaultColor ) {
                    const $container = $button.closest( '.color-container' );
                    self.syncColorContainer( $container, defaultColor );
                    $container.find( 'input[type="color"], .get-color-selected' ).trigger( 'change' );
                }
            });
        },

        /**
         * Sync visual color input and text color input.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {jQuery} $container Color field container.
         * @param {string} value Candidate hex value.
         * @return {void}
         */
        syncColorContainer: function( $container, value ) {
            const $colorInput = $container.find( 'input[type="color"]' );
            const $textInput = $container.find( '.get-color-selected' );
            const normalized = this.normalizeHexColor( value || $colorInput.val() || $textInput.val() );

            if ( normalized ) {
                $colorInput.val( normalized );
                $textInput.val( normalized );
            }
        },

        /**
         * Normalize and validate hex color value.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} value Color string.
         * @return {string} Normalized uppercase #RRGGBB or empty string.
         */
        normalizeHexColor: function( value ) {
            if ( 'string' !== typeof value ) {
                return '';
            }

            const normalized = value.trim();
            return /^#([0-9A-Fa-f]{6})$/.test( normalized ) ? normalized.toUpperCase() : '';
        },

        /**
         * React to form changes.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        handleFormChange: function() {
            this.updateSaveButtonState();
        },

        /**
         * Handle form submit and route to AJAX save.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {Event} event Submit event.
         * @return {void}
         */
        handleFormSubmit: function( event ) {
            event.preventDefault();

            if ( this.state.isSaving || ! this.hasUnsavedChanges() ) {
                return;
            }

            this.saveOptions();
        },

        /**
         * Send settings payload to AJAX endpoint.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        saveOptions: function() {
            const self = this;
            this.state.isSaving = true;
            const buttonState = this.keepButtonState();

            $.ajax({
                url: hubgo_settings_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hubgo_save_settings',
                    nonce: hubgo_settings_params.nonce,
                    form_data: this.elements.$form.serialize(),
                },
                beforeSend: function() {
                    if ( self.elements.$saveButton.length ) {
                        self.elements.$saveButton.prop( 'disabled', true ).html( '<span class="spinner-border spinner-border-sm"></span>' );
                    }
                },
                success: function( response ) {
                    self.handleSuccess( response );
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                    self.handleError( textStatus, errorThrown );
                    self.restoreButtonState( buttonState );
                },
                complete: function() {
                    self.state.isSaving = false;
                    self.updateSaveButtonState();
                },
            });
        },

        /**
         * Process successful AJAX response contract.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {Object} response AJAX response payload.
         * @return {void}
         */
        handleSuccess: function( response ) {
            if ( response.success ) {
                this.storeOriginalValues();
                this.showNotification( 'success' );
                this.restoreButtonState();
                $( document ).trigger( 'hubgo:settings_saved', [ response.data ] );
                return;
            }

            const message = response && response.data && response.data.message ? response.data.message : '';
            this.handleSaveError( message );
            this.restoreButtonState();
        },

        /**
         * Handle backend save errors.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} message Error message.
         * @return {void}
         */
        handleSaveError: function( message ) {
            this.logError( 'Save Error:', message );
            this.showNotification(
                'error',
                message || this.getParam( 'toast_error_message', 'Nao foi possivel salvar as configuracoes. Tente novamente.' ),
                this.getParam( 'toast_error_title', 'Erro ao salvar' )
            );
            $( document ).trigger( 'hubgo:settings_save_error', [ message ] );
        },

        /**
         * Handle low-level AJAX request errors.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} textStatus jQuery text status.
         * @param {string} errorThrown Error string.
         * @return {void}
         */
        handleError: function( textStatus, errorThrown ) {
            this.logError( 'AJAX Error:', textStatus, errorThrown );
            this.showNotification(
                'error',
                this.getParam( 'toast_error_message', 'Nao foi possivel salvar as configuracoes. Tente novamente.' ),
                this.getParam( 'toast_error_title', 'Erro ao salvar' )
            );
            $( document ).trigger( 'hubgo:ajax_error', [ textStatus, errorThrown ] );
        },

        /**
         * Show toast notification with semantic variant.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} type success|error.
         * @param {string} message Toast body message.
         * @param {string} title Toast title message.
         * @return {void}
         */
        showNotification: function( type, message, title ) {
            const self = this;
            const toastType = type || 'success';
            const toastTitle = title || this.getParam( 'toast_title', 'Salvo com sucesso' );
            const toastMessage = message || this.getParam( 'toast_message', 'As configuracoes foram atualizadas!' );

            if ( ! this.elements.$toastContainer || ! this.elements.$toastContainer.length ) {
                return;
            }

            const $toast = this.elements.$toastContainer.find( this.config.toastSelector );
            const $header = $toast.find( '.toast-header' );
            const $icon = $toast.find( '.hubgo-toast-icon' );
            const $title = $toast.find( '.hubgo-toast-title' );
            const $body = $toast.find( '.toast-body' );

            $toast.removeClass( 'toast-danger' );
            $header.removeClass( 'bg-success bg-danger' ).addClass( 'text-white' );

            if ( 'error' === toastType ) {
                $toast.addClass( 'toast-danger' );
                $header.addClass( 'bg-danger' );
                $icon.html( this.getToastIcon( 'error' ) );
            } else {
                $header.addClass( 'bg-success' );
                $icon.html( this.getToastIcon( 'success' ) );
            }

            $title.text( toastTitle );
            $body.text( toastMessage );

            $toast.addClass( this.config.activeClass ).fadeIn( this.config.fadeSpeed );

            this.clearNotificationTimer();
            this.notificationTimer = setTimeout( function() {
                self.hideNotification();
            }, this.config.notificationDuration );

            $( document ).trigger( 'hubgo:notification_shown' );
        },

        /**
         * Hide current toast notification.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        hideNotification: function() {
            const self = this;
            const $toast = this.elements.$toastContainer ? this.elements.$toastContainer.find( this.config.toastSelector ) : $();

            if ( ! $toast.length ) {
                return;
            }

            $toast.fadeOut( this.config.fadeSpeed, function() {
                $( this ).removeClass( self.config.activeClass ).css( 'display', '' );
            });

            this.clearNotificationTimer();
            $( document ).trigger( 'hubgo:notification_hidden' );
        },

        /**
         * Clear pending auto-hide timer.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        clearNotificationTimer: function() {
            if ( this.notificationTimer ) {
                clearTimeout( this.notificationTimer );
                this.notificationTimer = null;
            }
        },

        /**
         * Get localized parameter with fallback.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {string} key Localization key.
         * @param {string} fallback Fallback value.
         * @return {string} Resolved value.
         */
        getParam: function( key, fallback ) {
            if ( typeof hubgo_settings_params === 'undefined' || typeof hubgo_settings_params[ key ] === 'undefined' ) {
                return fallback;
            }
            return hubgo_settings_params[ key ];
        },

        /**
         * Return toast icon markup by type.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {string} type success|error.
         * @return {string} SVG markup.
         */
        getToastIcon: function( type ) {
            if ( 'error' === type ) {
                return '<svg class="icon icon-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>';
            }

            return '<svg class="icon icon-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg>';
        },

        /**
         * Build and inject toast markup.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        createToast: function() {
            if ( ! this.elements.$form.length ) {
                return;
            }

            const toastHtml = [
                '<div class="toast update-notice-spm-wp" style="display:none;">',
                    '<div class="toast-header bg-success text-white">',
                        '<span class="hubgo-toast-icon me-2">' + this.getToastIcon( 'success' ) + '</span>',
                        '<span class="me-auto hubgo-toast-title">' + this.getParam( 'toast_title', 'Salvo com sucesso' ) + '</span>',
                        '<button class="btn-close btn-close-white ms-2 hide-toast" type="button" aria-label="Close"></button>',
                    '</div>',
                    '<div class="toast-body">' + this.getParam( 'toast_message', 'As configuracoes foram atualizadas!' ) + '</div>',
                '</div>'
            ].join( '' );

            const $container = $( '<div/>' ).addClass( this.config.toastContainerClass ).html( toastHtml );
            this.elements.$form.before( $container );
            this.elements.$toastContainer = $container;
        },

        /**
         * Capture save button dimensions and markup.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {Object} Button state snapshot.
         */
        keepButtonState: function() {
            if ( ! this.elements.$saveButton.length ) {
                return { html: '' };
            }

            const btn = this.elements.$saveButton;
            const state = {
                width: btn.width(),
                height: btn.height(),
                html: btn.html(),
            };

            btn.width( state.width );
            btn.height( state.height );

            return state;
        },

        /**
         * Restore save button state after request.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {Object} buttonState Previous button state.
         * @return {void}
         */
        restoreButtonState: function( buttonState ) {
            const self = this;
            if ( ! this.elements.$saveButton.length ) {
                return;
            }

            const htmlToRestore = buttonState && buttonState.html ? buttonState.html : this.config.buttonOriginalHtml;
            setTimeout( function() {
                self.elements.$saveButton.prop( 'disabled', false ).html( htmlToRestore );
                self.state.isSaving = false;
                self.updateSaveButtonState();
            }, 100 );
        },

        /**
         * Toggle save button enabled state.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        updateSaveButtonState: function() {
            if ( ! this.elements.$saveButton.length ) {
                return;
            }

            this.elements.$saveButton.prop( 'disabled', this.state.isSaving || ! this.hasUnsavedChanges() );
        },

        /**
         * Check if form differs from original snapshot.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {boolean} True when there are unsaved changes.
         */
        hasUnsavedChanges: function() {
            return this.elements.$form.serialize() !== this.state.originalValues;
        },

        /**
         * Store baseline serialized form value.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {void} _ Unused.
         * @return {void}
         */
        storeOriginalValues: function() {
            this.state.originalValues = this.elements.$form.serialize();
        },

        /**
         * Standardized console error logger.
         *
         * @version 2.1.0
         * @since 2.0.0
         * @param {...*} args Values to log.
         * @return {void}
         */
        logError: function() {
            if ( window.console && console.error ) {
                console.error( 'HubGo Settings:', ...arguments );
            }
        },
    };

    /**
     * Initialize settings module when the form exists.
     *
     * @version 2.1.0
     * @since 2.0.0
     * @param {void} _ Unused.
     * @return {void}
     */
    $( document ).ready( function() {
        if ( $( 'form[name="hubgo-shipping-management-wc"]' ).length > 0 ) {
            Hubgo_Settings.init();
        }
    });

    /**
     * Warn user before leaving with unsaved changes.
     *
     * @version 2.1.0
     * @since 2.0.0
     * @param {Event} event Before unload event.
     * @return {string|undefined} Warning message when needed.
     */
    $( window ).on( 'beforeunload', function( event ) {
        if ( Hubgo_Settings.hasUnsavedChanges && Hubgo_Settings.hasUnsavedChanges() ) {
            return hubgo_settings_params.unsaved_changes_warning || 'Existem alteracoes nao salvas. Deseja realmente sair?';
        }

        return undefined;
    });

    window.Hubgo_Settings = Hubgo_Settings;
})(jQuery);

