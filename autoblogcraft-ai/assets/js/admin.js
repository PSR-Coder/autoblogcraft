/**
 * AutoBlogCraft AI - Admin JavaScript
 * * Handles admin interface interactivity, wizard steps, 
 * AI model population, and campaign management.
 * * @package AutoBlogCraft
 * @version 3.0.0
 */

(function($) {
    'use strict';

    const AutoBlogCraft = {
        
        /**
         * AI Model Mapping
         */
        models: {
            'openai': ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            'gemini': ['gemini-1.5-pro', 'gemini-1.5-flash'],
            'claude': ['claude-3-5-sonnet', 'claude-3-opus'],
            'deepseek': ['deepseek-chat', 'deepseek-coder']
        },

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initForms();
            this.initBulkActions();
            this.initWizard();
            this.initAdvancedFeatures();
            
            // Initial UI state checks
            this.checkInitialStates();
        },

        /**
         * Bind Core UI events
         */
        bindEvents: function() {
            const self = this;

            // Show/Hide API key form
            $('.abc-show-add-key-form').on('click', function(e) {
                e.preventDefault();
                $('#abc-add-key-form').slideDown();
            });

            $('.abc-cancel-add-key').on('click', function(e) {
                e.preventDefault();
                $('#abc-add-key-form').slideUp();
            });

            // Campaign actions (Direct and Bound)
            $('.abc-campaign-action').on('click', this.handleCampaignAction.bind(this, 'action'));
            $('.abc-pause-campaign').on('click', this.handleCampaignAction.bind(this, 'pause'));
            $('.abc-activate-campaign').on('click', this.handleCampaignAction.bind(this, 'activate'));

            // Confirm deletes
            $('button[name="abc_delete_api_key"]').on('click', function(e) {
                if (!confirm(abcAdmin.strings.confirm_delete || 'Are you sure?')) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Filter & Search logic
         */
        initFilters: function() {
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

            $('#abc-clear-filters').on('click', function() {
                const url = new URL(window.location.href);
                url.searchParams.delete('status');
                url.searchParams.delete('campaign_id');
                url.searchParams.delete('search');
                window.location.href = url.toString();
            });

            $('#abc-status-filter, #abc-campaign-filter').on('change', function() {
                $('#abc-apply-filters').trigger('click');
            });
        },

        /**
         * Bulk Action logic
         */
        initBulkActions: function() {
            const self = this;

            $('#abc-select-all').on('change', function() {
                $('.abc-campaign-checkbox').prop('checked', $(this).prop('checked'));
            });

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
         * Wizard & Campaign Creation logic
         */
        initWizard: function() {
            const self = this;

            // Campaign Type Cards Selection
            $('.abc-type-option').on('click', function() {
                $('.abc-type-option').removeClass('selected');
                $(this).addClass('selected');
                
                const type = $(this).data('value');
                $('#campaign_type').val(type).trigger('change');
                
                // Show relevant config section
                $('.abc-config-group').addClass('abc-hidden');
                $('#config-' + type).removeClass('abc-hidden');
            });

            // Source Input Toggle (Checkboxes that enable/disable textareas)
            $('.abc-toggle-input').on('change', function() {
                let target = $(this).data('target');
                let $box = $('#' + target);
                if($(this).is(':checked')) {
                    $box.find('textarea').prop('disabled', false).focus();
                } else {
                    $box.find('textarea').prop('disabled', true);
                }
            }).trigger('change');

            // Source Configuration Visibility (Select-based)
            $('#campaign_type').on('change', function() {
                const type = $(this).val();
                $('.abc-source-config').hide();
                $('.abc-source-config-' + type).show();
            });

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
        },

        /**
         * Custom Interval, API Refresh, and Model Population
         */
        initAdvancedFeatures: function() {
            const self = this;

            // 1. Custom Interval Logic
            $('#use-custom-interval').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#discovery-interval-select').prop('disabled', true);
                    $('#custom-interval-inputs').removeClass('abc-hidden');
                    self.updateCustomString();
                } else {
                    $('#discovery-interval-select').prop('disabled', false);
                    $('#custom-interval-inputs').addClass('abc-hidden');
                    $('#discovery-interval-custom').val('');
                }
            });

            $('#custom-hours, #custom-minutes').on('input', this.updateCustomString.bind(this));

            // 2. Refresh API Keys
            $('#refresh-api-keys').on('click', function() {
                const $btn = $(this);
                $btn.addClass('abc-spin');
                
                $.post(ajaxurl, { 
                    action: 'abc_refresh_api_keys', 
                    nonce: abcAdmin.nonce 
                }, function(res) {
                    $btn.removeClass('abc-spin');
                    if(res.success) {
                        let $select = $('#ai-api-key-select').empty();
                        $select.append(new Option('Select API Key...', ''));
                        res.data.forEach(key => {
                            let opt = new Option(key.key_name, key.id);
                            $(opt).data('provider', key.provider);
                            $select.append(opt);
                        });
                    } else {
                        alert('Failed to refresh keys');
                    }
                });
            });

            // 3. AI Model Population based on selected Key
            $('#ai-api-key-select').on('change', this.updateModels.bind(this));
        },

        /**
         * State check for initial load
         */
        checkInitialStates: function() {
            // Pre-select saved type if exists
            const savedType = $('#campaign_type').val();
            if(savedType) {
                $(`.abc-type-option[data-value="${savedType}"]`).addClass('selected');
                $('#config-' + savedType).removeClass('abc-hidden');
            }

            // Populate models if key is pre-selected
            if ($('#ai-api-key-select').val()) {
                this.updateModels();
            }
        },

        /**
         * Logic Helpers
         */
        updateCustomString: function() {
            let h = $('#custom-hours').val() || 0;
            let m = $('#custom-minutes').val() || 0;
            if(h == 0 && m < 15) m = 15; // Minimum enforcement
            $('#discovery-interval-custom').val(`custom_${h}h_${m}m`);
        },

        updateModels: function() {
            const provider = $('#ai-api-key-select option:selected').data('provider');
            const $modelSelect = $('#ai-model-select');
            $modelSelect.empty();
            
            if (this.models[provider]) {
                this.models[provider].forEach(m => {
                    $modelSelect.append(new Option(m, m));
                });
            }
        },

        validateField: function($field, type) {
            const $btn = $field.siblings('.abc-validate-btn');
            const $result = $field.siblings('.abc-validation-result');
            const url = $field.val();

            if (!url) return;

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

        handleCampaignAction: function(action, e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            
            // If action is passed as 'action', try to get specific action from button class or data
            let effectiveAction = action;
            if (action === 'action') {
                effectiveAction = $button.hasClass('abc-pause-campaign') ? 'pause' : 'activate';
            }

            const campaignId = $button.data('campaign-id') || $button.data('campaign');
            $button.prop('disabled', true).text(abcAdmin.strings.processing || 'Processing...');
            
            $.ajax({
                url: abcAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'abc_campaign_' + effectiveAction,
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
        },

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

        initForms: function() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        }
    };

    $(document).ready(function() {
        AutoBlogCraft.init();
    });

})(jQuery);