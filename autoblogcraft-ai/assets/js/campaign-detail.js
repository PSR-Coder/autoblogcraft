/**
 * Campaign Detail JavaScript
 * 
 * Handles campaign detail page interactions including:
 * - Tab navigation with hash support
 * - Queue processing (single and batch)
 * - Real-time status updates
 * - Log filtering and export
 * - Statistics auto-refresh
 * - AJAX actions
 *
 * @package AutoBlogCraft_AI
 * @since 2.0.0
 */

(function($) {
	'use strict';

	/**
	 * ABCCampaignDetail
	 * Optimized for performance and maintainability.
	 */
	const ABCCampaignDetail = {
		campaignId: null,
		currentTab: 'overview',
		refreshInterval: null,
		refreshRate: 30000,
		
		// Settings localized from WordPress via wp_localize_script
		config: typeof abcCampaignDetail !== 'undefined' ? abcCampaignDetail : {},

		init: function() {
			this.cacheElements();
			if (!this.$container.length) return;

			// Robust campaign ID retrieval
			this.campaignId = this.$container.data('campaign-id') || this.config.campaignId || this.config.campaign_id;
			
			this.bindEvents();
			this.handleHashChange();
			this.startAutoRefresh();
		},

		cacheElements: function() {
			this.$container = $('.abc-campaign-detail');
			this.$tabs = $('.abc-tab-nav a');
			this.$tabContents = $('.abc-tab-content');
		},

		/**
		 * Centralized AJAX wrapper
		 */
		request: function(action, data = {}, options = {}) {
			const self = this;
			const defaults = {
				url: self.config.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
				type: 'POST',
				dataType: 'json',
				data: $.extend({
					action: action,
					nonce: self.config.nonce,
					campaign_id: self.campaignId
				}, data),
				beforeSend: function() {
					if (options.loadingText) self.showLoading(options.loadingText);
				},
				complete: function() {
					if (options.loadingText) self.hideLoading();
				}
			};

			return $.ajax($.extend(defaults, options));
		},

        bindEvents: function() {
            const self = this;

            // 1. Static tab clicks
            this.$tabs.on('click', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // 2. Delegate Action Buttons
            $(document).on('click', '[class^="abc-"]', function(e) {
                const $btn = $(this);
                
                // Status Actions
                if ($btn.hasClass('abc-pause-campaign')) { e.preventDefault(); self.updateStatus('paused'); }
                if ($btn.hasClass('abc-resume-campaign')) { e.preventDefault(); self.updateStatus('active'); }
                if ($btn.hasClass('abc-discover-now')) { e.preventDefault(); self.discoverNow(); }
                
                // Queue Management
                if ($btn.hasClass('abc-process-queue') || $btn.attr('id') === 'process-queue-btn') {
                    e.preventDefault();
                    self.processQueue();
                }
                if ($btn.hasClass('abc-process-item') || $btn.hasClass('abc-process-queue-item')) {
                    e.preventDefault();
                    self.processQueueItem($btn);
                }
                if ($btn.hasClass('abc-delete-item') || $btn.hasClass('abc-delete-queue-item')) {
                    e.preventDefault();
                    self.deleteQueueItem($btn);
                }
                if ($btn.attr('id') === 'clear-failed-btn' || $btn.hasClass('abc-clear-failed-queue')) {
                    e.preventDefault();
                    self.clearFailedQueue();
                }

                // Campaign Ops
                if ($btn.hasClass('abc-clone-campaign')) { e.preventDefault(); self.cloneCampaign(); }
                if ($btn.hasClass('abc-delete-campaign')) { e.preventDefault(); self.deleteCampaign(); }

                // Source Logic
                if ($btn.hasClass('abc-add-source')) { e.preventDefault(); self.addSource($btn); }
                if ($btn.hasClass('abc-remove-source')) { e.preventDefault(); self.removeSource($btn); }
                if ($btn.hasClass('abc-save-sources')) { e.preventDefault(); self.saveSources(); }

                // UI Helpers
                if ($btn.hasClass('abc-toggle-context')) self.toggleContext($btn);
                if ($btn.hasClass('abc-refresh-stats')) { e.preventDefault(); self.refreshStats(); }
                
                // Log list toggling
                if ($btn.hasClass('abc-log-item') && !$(e.target).is('a, button')) {
                    self.toggleLogDetails($btn);
                }
            });

            // 3. Specialized Listeners
            $(document).on('click', '#export-logs-btn, .abc-export-logs', (e) => { e.preventDefault(); self.exportLogs(); });
            $(document).on('click', '#clear-logs-btn, .abc-clear-logs', (e) => { e.preventDefault(); self.clearLogs(); });
            $(document).on('click', '.abc-save-settings', (e) => { e.preventDefault(); self.saveSettings(); });
            
            // Filters
            $(document).on('change', '.abc-filter-select, .abc-filter-input', () => self.applyFilters());

            $(window).on('hashchange', () => self.handleHashChange());
        },

        handleHashChange: function() {
            const hash = window.location.hash.substring(1);
            if (hash && this.$tabs.filter(`[data-tab="${hash}"]`).length) {
                this.switchTab(hash, false);
            }
        },

        switchTab: function(tab, updateHash = true) {
            this.$tabs.removeClass('nav-tab-active').filter(`[data-tab="${tab}"]`).addClass('nav-tab-active');
            this.$tabContents.hide();
            
            const $activeContent = this.$tabContents.filter(`[data-tab="${tab}"]`);
            $activeContent.fadeIn(200);

            if (updateHash) window.location.hash = tab;
            this.currentTab = tab;
            this.loadTabData(tab);
        },

        loadTabData: function(tab) {
            if (['queue', 'posts', 'logs'].indexOf(tab) === -1) return;

            const $tabContent = this.$tabContents.filter(`[data-tab="${tab}"]`);
            const $loader = $tabContent.find('.abc-loading');

            if ($loader.length && !$loader.hasClass('loaded')) {
                $loader.show();
                this.request('abc_load_campaign_tab', { tab: tab })
                    .done(response => {
                        if (response.success && response.data.html) {
                            $tabContent.html(response.data.html);
                            $loader.addClass('loaded');
                        }
                    })
                    .fail(() => $loader.hide());
            }
        },

        updateStatus: function(status) {
            const loadingText = this.config.i18n?.updatingStatus || 'Updating...';
            this.request('abc_update_campaign_status', { status: status }, { loadingText: loadingText })
                .done(response => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        $('.abc-pause-campaign').toggle(status !== 'paused');
                        $('.abc-resume-campaign').toggle(status === 'paused');
                        this.refreshStats();
                    } else {
                        this.showNotice(response.data.message || 'Failed to update status.', 'error');
                    }
                })
                .fail(() => this.showNotice('An error occurred while updating status.', 'error'));
        },

        discoverNow: function() {
            const loadingText = this.config.i18n?.discovering || 'Discovering content...';
            this.request('abc_discover_campaign', {}, { loadingText: loadingText })
                .done(response => {
                    const type = response.success ? 'success' : 'error';
                    this.showNotice(response.data.message || 'Discovery processed.', type);
                    if (response.success) this.refreshStats();
                })
                .fail(() => this.showNotice('An error occurred during discovery.', 'error'));
        },

        processQueue: function() {
            const loadingText = this.config.i18n?.processing || 'Processing...';
            this.request('abc_process_campaign_queue', {}, { loadingText: loadingText })
                .done(response => {
                    this.showNotice(response.data.message, response.success ? 'success' : 'error');
                    if (response.success) {
                        this.refreshStats();
                        if (this.currentTab === 'queue') this.loadTabData('queue');
                    }
                })
                .fail(() => this.showNotice('An error occurred while processing.', 'error'));
        },

        processQueueItem: function($btn) {
            const id = $btn.data('item-id') || $btn.data('queue-id');
            const $row = $btn.closest('tr');

            $btn.prop('disabled', true);
            $row.css('opacity', '0.5');

            this.request('abc_process_queue_item', { item_id: id, queue_id: id })
                .done(response => {
                    if (response.success) {
                        this.showNotice(this.config.i18n?.itemProcessed || 'Item processed.', 'success');
                        $row.fadeOut(300, () => $row.remove());
                        this.refreshStats();
                    } else {
                        this.showNotice(response.data.message || 'Processing failed.', 'error');
                        $btn.prop('disabled', false);
                        $row.css('opacity', '1');
                    }
                })
                .fail(() => {
                    this.showNotice('An error occurred.', 'error');
                    $btn.prop('disabled', false);
                    $row.css('opacity', '1');
                });
        },

        deleteQueueItem: function($btn) {
            if (!confirm(this.config.i18n?.confirmDeleteItem || 'Delete this item?')) return;
            const id = $btn.data('item-id') || $btn.data('queue-id');
            const $row = $btn.closest('tr');
            
            this.request('abc_delete_queue_item', { item_id: id, queue_id: id })
                .done(response => {
                    if (response.success) {
                        $row.fadeOut(300, () => $row.remove());
                    } else {
                        this.showNotice(response.data.message || 'Delete failed.', 'error');
                    }
                });
        },

        clearFailedQueue: function() {
            if (!confirm(this.config.i18n?.confirmClearFailed || 'Clear all failed items?')) return;

            this.request('abc_clear_failed_queue', {}, { loadingText: 'Clearing failed items...' })
                .done(response => {
                    if (response.success) {
                        this.loadTabData('queue');
                        this.refreshStats();
                    }
                    this.showNotice(response.data.message, response.success ? 'success' : 'error');
                });
        },

        cloneCampaign: function() {
            if (!confirm('Clone this campaign?')) return;

            this.request('abc_clone_campaign', {}, { loadingText: 'Cloning...' })
                .done(response => {
                    if (response.success && response.data.redirect_url) {
                        this.showNotice('Cloned! Redirecting...', 'success');
                        setTimeout(() => window.location.href = response.data.redirect_url, 1500);
                    } else {
                        this.showNotice(response.data.message || 'Clone failed.', 'error');
                    }
                });
        },

        deleteCampaign: function() {
            if (!confirm('Permanently delete this campaign?')) return;

            this.request('abc_delete_campaign', {}, { loadingText: 'Deleting...' })
                .done(response => {
                    if (response.success) {
                        this.showNotice('Deleted. Redirecting...', 'success');
                        const url = this.config.campaignsUrl || 'admin.php?page=abc-campaigns';
                        setTimeout(() => window.location.href = url, 1500);
                    } else {
                        this.showNotice(response.data.message || 'Delete failed.', 'error');
                    }
                });
        },

        addSource: function($btn) {
            const $container = $btn.closest('.abc-sources-container');
            const $newSource = $container.find('.abc-source-template').clone();
            $newSource.removeClass('abc-source-template').addClass('abc-source-item').show();
            $newSource.find('input').val('').prop('disabled', false);
            $container.find('.abc-sources-list').append($newSource);
        },

        removeSource: function($btn) {
            $btn.closest('.abc-source-item').fadeOut(200, function() { $(this).remove(); });
        },

        saveSources: function() {
            // Collect form data and send as a single object
            const fields = $('#abc-sources-form').serializeArray();
            const data = {};
            fields.forEach(field => {
                if (data[field.name]) {
                    if (!Array.isArray(data[field.name])) data[field.name] = [data[field.name]];
                    data[field.name].push(field.value);
                } else {
                    data[field.name] = field.value;
                }
            });

            this.request('abc_save_campaign_sources', data, { loadingText: 'Saving sources...' })
                .done(res => this.showNotice(res.data.message, res.success ? 'success' : 'error'));
        },

        saveSettings: function() {
            const fields = $('#abc-settings-form').serializeArray();
            const data = {};
            fields.forEach(f => data[f.name] = f.value);

            this.request('abc_save_campaign_settings', data, { loadingText: 'Saving settings...' })
                .done(res => this.showNotice(res.data.message, res.success ? 'success' : 'error'));
        },

        exportLogs: function() {
            const $btn = $('#export-logs-btn');
            const params = new URLSearchParams({
                action: 'abc_export_logs',
                campaign_id: $btn.data('campaign-id') || this.campaignId,
                level: $('.abc-filter-level').val() || '',
                category: $('.abc-filter-category').val() || '',
                nonce: this.config.exportLogsNonce || this.config.nonce
            });
            window.location.href = (this.config.ajaxUrl || ajaxurl) + '?' + params.toString();
        },

        clearLogs: function() {
            if (!confirm(this.config.i18n?.confirmClearLogs || 'Clear logs?')) return;
            this.request('abc_clear_campaign_logs', {}, { loadingText: 'Clearing...' })
                .done(res => {
                    if (res.success) location.reload();
                    else this.showNotice(res.data.message, 'error');
                });
        },

        toggleLogDetails: function($logItem) {
            $logItem.find('.abc-log-details').slideToggle(200);
            $logItem.toggleClass('expanded');
        },

        toggleContext: function($btn) {
            const $context = $btn.siblings('.abc-context-data');
            $context.slideToggle(200, () => {
                const isVisible = $context.is(':visible');
                const text = isVisible ? (this.config.i18n?.hideDetails || 'Hide') : (this.config.i18n?.showDetails || 'Show');
                $btn.html(`<span class="dashicons dashicons-arrow-${isVisible ? 'up' : 'down'}-alt2"></span> ${text}`);
            });
        },

        applyFilters: function() {
            if (this.currentTab) this.loadTabData(this.currentTab);
        },

        refreshStats: function() {
            this.request('abc_get_campaign_stats')
                .done(res => {
                    if (res.success && res.data.stats) {
                        Object.entries(res.data.stats).forEach(([key, val]) => {
                            $(`.abc-stat-card[data-stat="${key}"] .abc-stat-value`).text(val);
                        });
                    }
                });
        },

        startAutoRefresh: function() {
            this.refreshInterval = setInterval(() => {
                if (this.currentTab === 'overview') this.refreshStats();
            }, this.refreshRate);
        },

        stopAutoRefresh: function() {
            if (this.refreshInterval) clearInterval(this.refreshInterval);
        },

        showNotice: function(msg, type) {
            const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${msg}</p></div>`);
            this.$container.prepend($notice);
            setTimeout(() => $notice.fadeOut(300, () => $notice.remove()), 5000);
            $('html, body').animate({ scrollTop: this.$container.offset().top - 32 }, 300);
        },

        showLoading: function(msg) {
            this.$container.append(`<div class="abc-loading-overlay"><div class="abc-loading-spinner"></div><p>${msg}</p></div>`);
        },

        hideLoading: function() {
            $('.abc-loading-overlay').fadeOut(200, function() { $(this).remove(); });
        }
    };

    $(document).ready(() => {
        if ($('.abc-campaign-detail').length) ABCCampaignDetail.init();
    });

    $(window).on('beforeunload', () => ABCCampaignDetail.stopAutoRefresh());

})(jQuery);
