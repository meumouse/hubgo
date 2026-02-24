/**
 * HubGo Admin Module
 *
 * Handles admin settings page functionality including tabs, manual save and notifications
 *
 * @since 2.0.0
 * @package HubGo
 * @author MeuMouse.com
 */

( function($) {
    'use strict';

    /**
     * HubGo Admin Object
     *
     * @since 2.0.0
     */
    const HubgoAdmin = {

        /**
         * Timer for notification auto-hide
         * 
         * @since 2.0.0
         * @type {number|null}
         */
        notificationTimer: null,

        /**
         * DOM elements cache
         * 
         * @since 2.0.0
         */
        elements: {
            $form: null,
            $saveButton: null,
            $tabs: null,
            $tabPanels: null,
            $toastContainer: null,
        },

        /**
         * Form state
         * 
         * @since 2.0.0
         */
        state: {
            originalValues: '',
            isSaving: false,
        },

        /**
         * Configuration
         * 
         * @since 2.0.0
         */
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
        },


        /**
         * Initialize the module
         *
         * @since 2.0.0
         * @return {void}
         */
        init: function() {
            this.cacheDom();
            this.initTabs();
            this.bindEvents();
            this.storeOriginalValues();
            this.updateSaveButtonState();
        },


        /**
         * Cache DOM elements for better performance
         *
         * @since 2.0.0
         * @return {void}
         */
        cacheDom: function() {
            this.elements = {
                $form: $(this.config.formSelector),
                $saveButton: $(),
                $tabs: $(this.config.tabSelector),
                $tabPanels: $(this.config.tabPanelSelector),
                $toastContainer: null,
            };

            if ( this.elements.$form.length ) {
                this.elements.$saveButton = this.elements.$form.find( this.config.saveButtonSelector );
            }

            this.createToast();
        },


        /**
         * Initialize settings tabs
         *
         * @since 2.0.0
         * @return {void}
         */
        initTabs: function() {
            if ( ! this.elements.$tabs.length || ! this.elements.$tabPanels.length ) {
                return;
            }

            const hash = window.location.hash;
            const storedTab = window.localStorage ? localStorage.getItem( this.config.tabStorageKey ) : '';
            const initialTab = hash || storedTab || this.config.defaultTab;

            this.activateTab( initialTab );
        },


        /**
         * Bind event listeners
         *
         * @since 2.0.0
         * @return {void}
         */
        bindEvents: function() {
            if ( ! this.elements.$form.length ) {
                return;
            }

            this.elements.$form.on( 'change input', 'input, select, textarea', this.handleFormChange.bind(this) );
            this.elements.$form.on( 'submit', this.handleFormSubmit.bind(this) );

            this.elements.$tabs.on( 'click', this.handleTabClick.bind(this) );
            
            $(document).on( 'click', this.config.toastSelector + ' .hide-toast', this.hideNotification.bind(this) );
        },


        /**
         * Store original form values for comparison
         *
         * @since 2.0.0
         * @return {void}
         */
        storeOriginalValues: function() {
            this.state.originalValues = this.elements.$form.serialize();
        },


        /**
         * Handle form changes
         *
         * @since 2.0.0
         * @param {Event} event
         * @return {void}
         */
        handleFormChange: function( event ) {
            this.updateSaveButtonState();
        },


        /**
         * Handle settings form submit
         *
         * @since 2.0.0
         * @param {Event} event
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
         * Handle tab click event
         *
         * @since 2.0.0
         * @param {Event} event
         * @return {void}
         */
        handleTabClick: function( event ) {
            event.preventDefault();

            const tabId = $( event.currentTarget ).attr( 'href' );
            this.activateTab( tabId );
        },


        /**
         * Activate settings tab by ID/hash
         *
         * @since 2.0.0
         * @param {string} tabId
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
         * Save options via AJAX
         *
         * @since 2.0.0
         * @return {void}
         */
        saveOptions: function() {
            const self = this;

            this.setState( 'isSaving', true );

            $.ajax({
                url: hubgo_admin_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hubgo_save_settings',
                    nonce: hubgo_admin_params.nonce,
                    form_data: this.elements.$form.serialize(),
                },
                beforeSend: function() {
                    if ( self.elements.$saveButton.length ) {
                        self.elements.$saveButton.prop( 'disabled', true );
                    }
                },
                success: function( response ) {
                    self.handleSuccess( response );
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                    self.handleError( textStatus, errorThrown );
                },
                complete: function() {
                    self.setState( 'isSaving', false );
                    self.updateSaveButtonState();
                },
            });
        },


        /**
         * Handle successful save response
         *
         * @since 2.0.0
         * @param {Object} response
         * @return {void}
         */
        handleSuccess: function( response ) {
            if ( response.success ) {
                this.state.originalValues = this.elements.$form.serialize();
                this.updateSaveButtonState();
                this.showNotification();
                
                /**
                 * Trigger event after settings saved
                 *
                 * @since 2.0.0
                 */
                $(document).trigger( 'hubgo:settings_saved', [ response.data ] );
            } else {
                this.handleSaveError( response.data.message );
            }
        },


        /**
         * Handle save error from response
         *
         * @since 2.0.0
         * @param {string} message
         * @return {void}
         */
        handleSaveError: function( message ) {
            this.logError( 'Save Error:', message );
            
            /**
             * Trigger event on save error
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:settings_save_error', [ message ] );
        },


        /**
         * Handle AJAX error
         *
         * @since 2.0.0
         * @param {string} textStatus
         * @param {string} errorThrown
         * @return {void}
         */
        handleError: function( textStatus, errorThrown ) {
            this.logError( 'AJAX Error:', textStatus, errorThrown );
            
            /**
             * Trigger event on AJAX error
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:ajax_error', [ textStatus, errorThrown ] );
        },


        /**
         * Log error messages
         *
         * @since 2.0.0
         * @param {...*} args
         * @return {void}
         */
        logError: function() {
            if ( window.console && console.error ) {
                console.error( 'HubGo Admin:', ...arguments );
            }
        },


        /**
         * Set state property
         *
         * @since 2.0.0
         * @param {string} key
         * @param {*} value
         * @return {void}
         */
        setState: function( key, value ) {
            this.state[ key ] = value;
        },


        /**
         * Enable/disable save button based on form state
         *
         * @since 2.0.0
         * @return {void}
         */
        updateSaveButtonState: function() {
            if ( ! this.elements.$saveButton.length ) {
                return;
            }

            const isDisabled = this.state.isSaving || ! this.hasUnsavedChanges();
            this.elements.$saveButton.prop( 'disabled', isDisabled );
        },


        /**
         * Show success notification
         *
         * @since 2.0.0
         * @return {void}
         */
        showNotification: function() {
            const self = this;

            if ( ! this.elements.$toastContainer || ! this.elements.$toastContainer.length ) {
                return;
            }

            this.elements.$toastContainer.find( this.config.toastSelector )
                .addClass( this.config.activeClass )
                .fadeIn( this.config.fadeSpeed );

            this.clearNotificationTimer();
            
            this.notificationTimer = setTimeout(
                function() {
                    self.hideNotification();
                },
                this.config.notificationDuration
            );

            /**
             * Trigger event when notification is shown
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:notification_shown' );
        },


        /**
         * Hide notification
         *
         * @since 2.0.0
         * @return {void}
         */
        hideNotification: function() {
            const self = this;
            const $toast = this.elements.$toastContainer ? this.elements.$toastContainer.find( this.config.toastSelector ) : $();

            if ( ! $toast.length ) {
                return;
            }

            $toast.fadeOut(
                this.config.fadeSpeed,
                function() {
                    $(this)
                        .removeClass( self.config.activeClass )
                        .css( 'display', '' );
                }
            );

            this.clearNotificationTimer();

            /**
             * Trigger event when notification is hidden
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:notification_hidden' );
        },


        /**
         * Clear notification timer
         *
         * @since 2.0.0
         * @return {void}
         */
        clearNotificationTimer: function() {
            if ( this.notificationTimer ) {
                clearTimeout( this.notificationTimer );
                this.notificationTimer = null;
            }
        },


        /**
         * Get localized parameter with fallback
         *
         * @since 2.0.0
         * @param {string} key
         * @param {string} fallback
         * @return {string}
         */
        getParam: function( key, fallback ) {
            if ( typeof hubgo_admin_params === 'undefined' || typeof hubgo_admin_params[ key ] === 'undefined' ) {
                return fallback;
            }

            return hubgo_admin_params[ key ];
        },


        /**
         * Create toast markup dynamically
         *
         * @since 2.0.0
         * @return {void}
         */
        createToast: function() {
            if ( ! this.elements.$form.length ) {
                return;
            }

            const toastHtml = [
                '<div class="toast update-notice-spm-wp" style="display:none;">',
                    '<div class="toast-header bg-success text-white">',
                        '<span class="me-auto">' + this.getParam( 'toast_title', 'Salvo com sucesso' ) + '</span>',
                        '<button class="btn-close btn-close-white ms-2 hide-toast" type="button" aria-label="Close"></button>',
                    '</div>',
                    '<div class="toast-body">' + this.getParam( 'toast_message', 'As configurações foram atualizadas!' ) + '</div>',
                '</div>'
            ].join('');

            const $container = $( '<div/>' ).addClass( this.config.toastContainerClass ).html( toastHtml );

            this.elements.$form.before( $container );
            this.elements.$toastContainer = $container;
        },


        /**
         * Manually trigger save
         *
         * Useful for external triggers
         *
         * @since 2.0.0
         * @return {void}
         */
        triggerSave: function() {
            if ( this.hasUnsavedChanges() ) {
                this.saveOptions();
            }
        },


        /**
         * Check if form has unsaved changes
         *
         * @since 2.0.0
         * @return {boolean}
         */
        hasUnsavedChanges: function() {
            const currentValues = this.elements.$form.serialize();
            return currentValues !== this.state.originalValues;
        },


        /**
         * Reset form to original values
         *
         * @since 2.0.0
         * @return {void}
         */
        resetToOriginal: function() {
            // This would require parsing originalValues back to form fields
            // Implementation depends on form structure
            console.warn( 'HubGo Admin: resetToOriginal not implemented' );
        },
    };


    /**
     * Initialize on document ready
     *
     * @since 2.0.0
     */
    $(document).ready(function() {
        // Check if form exists before initializing
        if ( $( 'form[name="hubgo-shipping-management-wc"]' ).length > 0 ) {
            HubgoAdmin.init();
        }
    });


    /**
     * Warn before leaving page with unsaved changes
     *
     * @since 2.0.0
     */
    $(window).on('beforeunload', function() {
        if ( HubgoAdmin.hasUnsavedChanges && HubgoAdmin.hasUnsavedChanges() ) {
            return hubgo_admin_params.unsaved_changes_warning || 
                   'Existem alterações não salvas. Deseja realmente sair?';
        }
    });


    /**
     * Make HubgoAdmin available globally
     *
     * @since 2.0.0
     */
    window.HubgoAdmin = HubgoAdmin;

})(jQuery);