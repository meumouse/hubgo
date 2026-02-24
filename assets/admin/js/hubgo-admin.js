/**
 * HubGo Admin Module
 *
 * Handles admin settings page functionality including auto-save and notifications
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
            $toast: null,
            $hideToast: null,
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
            toastSelector: '.update-notice-spm-wp',
            hideToastSelector: '.hide-toast',
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
            this.bindEvents();
            this.storeOriginalValues();
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
                $toast: $(this.config.toastSelector),
                $hideToast: $(this.config.hideToastSelector),
            };
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

            this.elements.$form.on(
                'change keyup',
                this.handleFormChange.bind(this)
            );
            
            this.elements.$hideToast.on(
                'click',
                this.hideNotification.bind(this)
            );
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
            // Prevent multiple simultaneous saves
            if ( this.state.isSaving ) {
                return;
            }

            const currentValues = this.elements.$form.serialize();
            
            if ( currentValues !== this.state.originalValues ) {
                this.saveOptions();
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
                success: function( response ) {
                    self.handleSuccess( response );
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                    self.handleError( textStatus, errorThrown );
                },
                complete: function() {
                    self.setState( 'isSaving', false );
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
         * Show success notification
         *
         * @since 2.0.0
         * @return {void}
         */
        showNotification: function() {
            const self = this;

            if ( ! this.elements.$toast.length ) {
                return;
            }

            this.elements.$toast
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

            if ( ! this.elements.$toast.length ) {
                return;
            }

            this.elements.$toast.fadeOut(
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
         * Manually trigger save
         *
         * Useful for external triggers
         *
         * @since 2.0.0
         * @return {void}
         */
        triggerSave: function() {
            this.handleFormChange();
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