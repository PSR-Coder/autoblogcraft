/**
 * AutoBlogCraft AI - Admin JavaScript
 * 
 * Handles admin interface interactivity
 * 
 * @package AutoBlogCraft
 * @version 2.0.0
 */

(function($) {
    'use strict';

    const AutoBlogCraft = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initForms();
            this.initBulkActions();
            this.initWizard();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Show add API key form
            $('.abc-show-add-key-form').on('click', function(e) {
                e.preventDefault();
                $('#abc-add-key-form').slideDown();
            });

            // Cancel add API key
            $('.abc-cancel-add-key').on('click', function(e) {
                e.preventDefault();
                $('#abc-add-key-form').slideUp();
            });

            // Campaign actions
            $('.abc-campaign-action').on('click', this.handleCampaignAction);
            $('.abc-pause-campaign').on('click', this.handleCampaignAction.bind(this, 'pause'));
            $('.abc-activate-campaign').on('click', this.handleCampaignAction.bind(this, 'activate'));

            // Confirm deletes
            $('button[name="abc_delete_api_key"]').on('click', function(e) {
                if (!confirm(abcAdmin.strings.confirm_delete)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Initialize filters
         */
        initFilters: function() {
            // Apply filters button
            $('#abc-apply-filters').on('click', function() {
                const status = $('#abc-status-filter').val();
                const campaign = $('#abc-campaign-filter').val();
                const url = new URL(window.location.href);
                
                if (status && status !== 'all') {
                    url.searchParams.set('status', status);
                } else {
                    url.searchParams.delete('status');
                }
                
                if (campaign && campaign !== '0') {
                    url.searchParams.set('campaign_id', campaign);
                } else {
                    url.searchParams.delete('campaign_id');
                }
                
                window.location.href = url.toString();
            });

            // Clear filters
            $('#abc-clear-filters').on('click', function() {
                const url = new URL(window.location.href);
                url.searchParams.delete('status');
                url.searchParams.delete('campaign_id');
                url.searchParams.delete('search');
                window.location.href = url.toString();
            });

            // Auto-submit on filter change
            $('#abc-status-filter, #abc-campaign-filter').on('change', function() {
                $('#abc-apply-filters').trigger('click');
            });
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            const self = this;

            // Select all checkbox
            $('#abc-select-all').on('change', function() {
                $('.abc-campaign-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Bulk action apply
            $('#abc-bulk-action-apply').on('click', function() {
                const action = $('#abc-bulk-action-select').val();
                const selected = $('.abc-campaign-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!action) {
                    alert('Please select an action.');
                    return;
                }

                if (selected.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }

                if (action === 'delete') {
                    if (!confirm('Are you sure you want to delete ' + selected.length + ' campaign(s)?')) {
                        return;
                    }
                }

                self.handleBulkAction(action, selected);
            });
        },

        /**
         * Initialize wizard
         */
        initWizard: function() {
            const self = this;

            // Validate buttons
            $('.abc-validate-btn').on('click', function() {
                const field = $(this).data('field');
                const $input = $('#' + field);
                const type = $input.data('validation');

                self.validateField($input, type);
            });

            // Auto-validate on blur
            $('.abc-validate-field').on('blur', function() {
                const type = $(this).data('validation');
                if ($(this).val()) {
                    self.validateField($(this), type);
                }
            });

            // Campaign type change
            $('#campaign_type').on('change', function() {
                const type = $(this).val();
                $('.abc-source-config').hide();
                $('.abc-source-config-' + type).show();
            });
        },

        /**
         * Validate field
         */
        validateField: function($field, type) {
            const $btn = $field.siblings('.abc-validate-btn');
            const $result = $field.siblings('.abc-validation-result');
            const url = $field.val();

            if (!url) {
                return;
            }

            $btn.prop('disabled', true).text('Validating...');
            $result.html('');

            const ajaxAction = type === 'rss' ? 'abc_validate_rss_feed' : 'abc_validate_url';

            $.ajax({
                url: abcAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    url: url,
                    nonce: abcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="abc-validation-success">✓ ' + response.data.message + '</span>');
                        if (response.data.feed_title) {
                            $result.append('<br><small>' + response.data.feed_title + ' (' + response.data.item_count + ' items)</small>');
                        }
                    } else {
                        $result.html('<span class="abc-validation-error">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="abc-validation-error">✗ Validation failed</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(type === 'rss' ? 'Validate Feed' : 'Validate URL');
                }
            });
        },

        /**
         * Handle bulk action
         */
        handleBulkAction: function(action, campaignIds) {
            $.ajax({
                url: abcAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'abc_bulk_campaign_action',
                    bulk_action: action,
                    campaign_ids: campaignIds,
                    nonce: abcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        },

        /**
         * Initialize forms
         */
        initForms: function() {
            // Color picker
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        },

        /**
         * Handle campaign action
         */
        handleCampaignAction: function(action, e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const campaignId = $button.data('campaign-id') || $button.data('campaign');
            
            $button.prop('disabled', true).text(abcAdmin.strings.processing || 'Processing...');
            
            $.ajax({
                url: abcAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'abc_campaign_' + action,
                    campaign_id: campaignId,
                    nonce: abcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || abcAdmin.strings.error || 'An error occurred');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(abcAdmin.strings.error || 'An error occurred');
                    $button.prop('disabled', false);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AutoBlogCraft.init();
    });

})(jQuery);
