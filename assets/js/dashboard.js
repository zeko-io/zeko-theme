/**
 * Zeko Dashboard JavaScript
 *
 * Handles all interactive functionality for the frontend dashboard
 */

jQuery(document).ready(function($) {
    'use strict';

    // Cycle focus within a modal on Tab/Shift+Tab (WCAG 2.1.2 / audit #36)
    function trapTabFocus($container, e) {
        var $focusables = $container
            .find('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
            .filter(':visible');

        if (!$focusables.length) {
            e.preventDefault();
            return;
        }

        var first = $focusables[0];
        var last = $focusables[$focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        } else if (!$.contains($container[0], document.activeElement)) {
            e.preventDefault();
            first.focus();
        }
    }

    // Dashboard initialization
    var dashboard = {
        isCustomizing: false,
        currentUserId: $('.zeko-dashboard').data('user-id'),
        init: function() {
            this.showLoading();
            this.bindEvents();
            this.setupSortableWidgets();
            this.checkEmptyState();
            this.hideLoading();
        },

        bindEvents: function() {
            // Edit dashboard button
            $(document).on('click', '.zeko-edit-dashboard-btn', this.toggleCustomizeMode.bind(this));

            // Reset dashboard button
            $(document).on('click', '.zeko-reset-dashboard-btn', this.resetDashboardLayout.bind(this));

            // Widget edit buttons
            $(document).on('click', '.zeko-widget-edit-btn', this.openWidgetEditModal.bind(this));

            // Widget remove buttons
            $(document).on('click', '.zeko-widget-remove-btn', this.removeWidget.bind(this));

            // Add widget button
            $(document).on('click', '.zeko-add-widget-btn', this.openAddWidgetPanel.bind(this));

            // Save layout button
            $(document).on('click', '.zeko-save-layout-btn', this.saveWidgetPositions.bind(this));

            // Cancel customize button
            $(document).on('click', '.zeko-cancel-customize-btn', this.toggleCustomizeMode.bind(this));

            // Add widget type selection
            $(document).on('click', '.zeko-widget-type-item', this.addWidget.bind(this));

            // Close add widget panel
            $(document).on('click', '.zeko-add-widget-panel-close, .zeko-widget-type-overlay', this.closeAddWidgetPanel.bind(this));

            // Close widget edit modal
            $(document).on('click', '.zeko-widget-edit-close, .zeko-widget-edit-cancel', this.closeWidgetEditModal.bind(this));

            // Save widget edit
            $(document).on('click', '.zeko-widget-edit-save', this.saveWidgetEdit.bind(this));

            // Modal keyboard handling (Tab trap + Escape)
            $(document).on('keydown', this.handleModalKeydown.bind(this));
        },

        handleModalKeydown: function(e) {
            var $modal = $('.zeko-widget-edit-modal');
            if (!$modal.length) {
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                this.closeWidgetEditModal();
            } else if (e.key === 'Tab') {
                trapTabFocus($modal, e);
            }
        },

        setupSortableWidgets: function() {
            if (!this.isCustomizing) return;

            $('.zeko-widgets-container').sortable({
                connectWith: '.zeko-widgets-container',
                handle: '.zeko-widget-header',
                placeholder: 'zeko-widget-placeholder',
                cursor: 'move',
                opacity: 0.7,
                tolerance: 'pointer',
                start: function(event, ui) {
                    ui.item.addClass('zeko-widget-dragging');
                },
                stop: function(event, ui) {
                    ui.item.removeClass('zeko-widget-dragging');
                }
            }).disableSelection();
        },

        toggleCustomizeMode: function() {
            this.isCustomizing = !this.isCustomizing;
            $('.zeko-dashboard').toggleClass('customize-mode', this.isCustomizing);

            if (this.isCustomizing) {
                $('.zeko-dashboard').addClass('customize-mode');
                this.setupSortableWidgets();
                this.showCustomizeControls();
                this.showMessage('Customize your dashboard by dragging widgets. Click "Save Layout" when done.', 'info');
            } else {
                $('.zeko-dashboard').removeClass('customize-mode');
                $('.zeko-customize-controls').remove();
                this.showMessage('Customization mode disabled.', 'info');
            }
        },

        showCustomizeControls: function() {
            if ($('.zeko-customize-controls').length) return;

            var controls = $('<div class="zeko-customize-controls">' +
                '<button class="btn zeko-save-layout-btn">' + zekoDashboardData.i18n.save + '</button>' +
                '<button class="btn btn-outline zeko-cancel-customize-btn">' + zekoDashboardData.i18n.cancel + '</button>' +
                '</div>');

            $('body').append(controls);
        },

        saveWidgetPositions: function() {
            if (!this.isCustomizing) return;

            var positions = {};

            $('.zeko-widgets-container').each(function() {
                var column = $(this).data('column');
                $(this).find('.zeko-dashboard-widget').each(function(index) {
                    var widgetId = $(this).data('widget-id');
                    positions[widgetId] = {
                        position: (index + 1) * 10,
                        column: column
                    };
                });
            });

            this.showLoading();

            $.ajax({
                url: zekoDashboardData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_save_widget_positions',
                    nonce: zekoDashboardData.nonce,
                    positions: positions
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        this.toggleCustomizeMode();
                    } else {
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(zekoDashboardData.i18n.error + 'Saving widget positions', 'error');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },

        resetDashboardLayout: function() {
            if (!confirm(zekoDashboardData.i18n.confirm_reset)) return;

            this.showLoading();

            $.ajax({
                url: zekoDashboardData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_reset_dashboard_layout',
                    nonce: zekoDashboardData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(zekoDashboardData.i18n.error + 'Resetting dashboard layout', 'error');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },

        openWidgetEditModal: function(e) {
            e.stopPropagation();
            var widget = $(e.target).closest('.zeko-dashboard-widget');
            var widgetId = widget.data('widget-id');
            var widgetType = widget.data('widget-type');
            var widgetTitle = widget.find('.zeko-widget-title').text();
            var widgetContent = widget.find('.zeko-widget-content').text();

            var modal = $('<div class="zeko-widget-edit-modal" role="dialog" aria-modal="true" aria-labelledby="zeko-widget-edit-title">' +
                '<div class="zeko-widget-edit-content">' +
                '<div class="zeko-widget-edit-header">' +
                '<h3 class="zeko-widget-edit-title" id="zeko-widget-edit-title">' + zekoDashboardData.i18n.edit_widget + '</h3>' +
                '<button class="zeko-widget-edit-close" aria-label="' + zekoDashboardData.i18n.cancel + '">&times;</button>' +
                '</div>' +
                '<div class="zeko-widget-edit-body">' +
                '<form class="zeko-widget-edit-form">' +
                '<input type="hidden" name="widget_id" value="' + widgetId + '">' +
                '<div class="form-group">' +
                '<label for="widget_title">' + zekoDashboardData.i18n.widget_title + '</label>' +
                '<input type="text" id="widget_title" name="widget_title">' +
                '</div>' +
                '<div class="form-group">' +
                '<label for="widget_content">' + zekoDashboardData.i18n.widget_content + '</label>' +
                '<textarea id="widget_content" name="widget_content"></textarea>' +
                '</div>' +
                '</form>' +
                '</div>' +
                '<div class="zeko-widget-edit-footer">' +
                '<button type="button" class="btn zeko-widget-edit-save">' + zekoDashboardData.i18n.save + '</button>' +
                '<button type="button" class="btn btn-outline zeko-widget-edit-cancel">' + zekoDashboardData.i18n.cancel + '</button>' +
                '</div>' +
                '</div>' +
                '</div>');

            modal.find('#widget_title').val(widgetTitle);
            modal.find('#widget_content').val(widgetContent);
            modal.data('trigger', document.activeElement);
            $('body').append(modal);
            modal.find('#widget_title').focus();
        },

        closeWidgetEditModal: function() {
            var $modal = $('.zeko-widget-edit-modal');
            var $trigger = $modal.data('trigger');
            $modal.remove();
            if ($trigger && $trigger.focus) {
                $trigger.focus();
            }
        },

        saveWidgetEdit: function() {
            var form = $('.zeko-widget-edit-form');
            var widgetId = form.find('input[name="widget_id"]').val();
            var widgetTitle = form.find('input[name="widget_title"]').val();
            var widgetContent = form.find('textarea[name="widget_content"]').val();

            this.showLoading();

            $.ajax({
                url: zekoDashboardData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_save_widget',
                    nonce: zekoDashboardData.nonce,
                    widget_id: widgetId,
                    widget_title: widgetTitle,
                    widget_content: widgetContent
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        this.closeWidgetEditModal();

                        // Update widget in DOM
                        var widget = $('.zeko-dashboard-widget[data-widget-id="' + widgetId + '"]');
                        widget.find('.zeko-widget-title').text(widgetTitle);
                        widget.find('.zeko-widget-content').text(widgetContent);
                    } else {
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(zekoDashboardData.i18n.error + 'Saving widget', 'error');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },

        removeWidget: function(e) {
            e.stopPropagation();
            if (!confirm(zekoDashboardData.i18n.confirm_remove_widget)) return;

            var widget = $(e.target).closest('.zeko-dashboard-widget');
            var widgetId = widget.data('widget-id');

            this.showLoading();

            $.ajax({
                url: zekoDashboardData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_remove_widget',
                    nonce: zekoDashboardData.nonce,
                    widget_id: widgetId
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        widget.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(zekoDashboardData.i18n.error + 'Removing widget', 'error');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },

        openAddWidgetPanel: function() {
            var panel = $('<div class="zeko-widget-type-overlay show"></div>' +
                '<div class="zeko-add-widget-panel open">' +
                '<div class="zeko-add-widget-panel-header">' +
                '<h3 class="zeko-add-widget-panel-title">' + zekoDashboardData.i18n.add_widget + '</h3>' +
                '<button class="zeko-add-widget-panel-close" aria-label="' + zekoDashboardData.i18n.cancel + '">&times;</button>' +
                '</div>' +
                '<div class="zeko-widget-types-list">' +
                '<div class="zeko-widget-type-item" data-widget-type="welcome">' +
                '<span class="dashicons dashicons-welcome-write-blog"></span>' +
                '<span>' + zekoDashboardData.i18n.welcome_widget + '</span>' +
                '</div>' +
                '<div class="zeko-widget-type-item" data-widget-type="quick_links">' +
                '<span class="dashicons dashicons-admin-links"></span>' +
                '<span>' + zekoDashboardData.i18n.quick_links + '</span>' +
                '</div>' +
                '<div class="zeko-widget-type-item" data-widget-type="stats">' +
                '<span class="dashicons dashicons-chart-bar"></span>' +
                '<span>' + zekoDashboardData.i18n.stats + '</span>' +
                '</div>' +
                '<div class="zeko-widget-type-item" data-widget-type="activity">' +
                '<span class="dashicons dashicons-megaphone"></span>' +
                '<span>' + zekoDashboardData.i18n.activity_feed + '</span>' +
                '</div>' +
                '<div class="zeko-widget-type-item" data-widget-type="friends">' +
                '<span class="dashicons dashicons-groups"></span>' +
                '<span>' + zekoDashboardData.i18n.friends + '</span>' +
                '</div>' +
                '<div class="zeko-widget-type-item" data-widget-type="messages">' +
                '<span class="dashicons dashicons-email-alt"></span>' +
                '<span>' + zekoDashboardData.i18n.messages + '</span>' +
                '</div>' +
                '</div>' +
                '</div>');

            $('body').append(panel);
        },

        closeAddWidgetPanel: function() {
            $('.zeko-add-widget-panel').removeClass('open');
            $('.zeko-widget-type-overlay').removeClass('show');
            setTimeout(function() {
                $('.zeko-add-widget-panel, .zeko-widget-type-overlay').remove();
            }, 300);
        },

        addWidget: function(e) {
            var widgetType = $(e.target).closest('.zeko-widget-type-item').data('widget-type');
            var column = 'main'; // Default to main column

            this.showLoading();

            $.ajax({
                url: zekoDashboardData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_add_widget',
                    nonce: zekoDashboardData.nonce,
                    widget_type: widgetType,
                    column: column
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        this.closeAddWidgetPanel();

                        // Add widget to DOM
                        var container = $('.zeko-dashboard-main-column .zeko-widgets-container');
                        if (container.length === 0) {
                            container = $('<div class="zeko-widgets-container" data-column="main"></div>');
                            $('.zeko-dashboard-main-column').append(container);
                        }

                        container.append(response.data.widget_html);
                        this.setupSortableWidgets();
                    } else {
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(zekoDashboardData.i18n.error + 'Adding widget', 'error');
                }.bind(this),
                complete: function() {
                    this.hideLoading();
                }.bind(this)
            });
        },

        showLoading: function() {
            if ($('.zeko-loading-overlay').length) return;

            var overlay = $('<div class="zeko-loading-overlay">' +
                '<div class="zeko-loading-spinner"></div>' +
                '</div>');

            $('body').append(overlay);
        },

        hideLoading: function() {
            $('.zeko-loading-overlay').remove();
        },

        checkEmptyState: function() {
            var $mainColumn = $('.zeko-dashboard-main-column .zeko-widgets-container');
            var $sidebar = $('.zeko-dashboard-sidebar .zeko-widgets-container');
            
            // Check if main column has any widgets
            if ($mainColumn.length && $mainColumn.find('.zeko-dashboard-widget').length === 0) {
                if ($mainColumn.find('.zeko-empty-widgets').length === 0) {
                    $mainColumn.append('<div class="zeko-empty-state"><span class="dashicons dashicons-layout"></span><p>No widgets added yet. Click "Add Widget" to get started.</p></div>');
                }
            }
        },

        showMessage: function(message, type) {
            var messageDiv = $('<div class="zeko-dashboard-message ' + type + '">' + message + '</div>');

            $('.zeko-dashboard').prepend(messageDiv);

            setTimeout(function() {
                messageDiv.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize dashboard if on dashboard page
    if ($('.zeko-dashboard').length) {
        dashboard.init();
    }

    // Add missing i18n strings
    zekoDashboardData.i18n = $.extend({
        edit_widget: 'Edit Widget',
        widget_title: 'Widget Title',
        widget_content: 'Widget Content',
        save: 'Save',
        cancel: 'Cancel',
        add_widget: 'Add Widget',
        welcome_widget: 'Welcome Widget',
        quick_links: 'Quick Links',
        stats: 'Statistics',
        activity_feed: 'Activity Feed',
        friends: 'Friends',
        messages: 'Messages',
        confirm_remove_widget: 'Are you sure you want to remove this widget?'
    }, zekoDashboardData.i18n || {});
});