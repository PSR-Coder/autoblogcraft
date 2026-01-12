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
 * @package Diyara_Core
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Campaign Detail Controller
	 */
	const ABCCampaignDetail = {
		campaignId: null,
		currentTab: 'overview',
		refreshInterval: null,
		refreshRate: 30000, // 30 seconds

		/**
		 * Initialize campaign detail page
		 */
		init: function() {
			this.cacheElements();
			this.getCampaignId();
			this.bindEvents();
			this.initTabs();
			this.startAutoRefresh();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$container = $('.abc-campaign-detail');
			this.$tabs = $('.abc-tab-nav a');
			this.$tabContents = $('.abc-tab-content');
			this.$statsCards = $('.abc-campaign-stats .abc-stat-card');
		},

		/**
		 * Get campaign ID from data attribute
		 */
		getCampaignId: function() {
			this.campaignId = this.$container.data('campaign-id');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Tab navigation
			this.$tabs.on('click', function(e) {
				e.preventDefault();
				const tab = $(this).data('tab');
				self.switchTab(tab);
			});

			// Quick actions
			$(document).on('click', '.abc-pause-campaign', function(e) {
				e.preventDefault();
				self.pauseCampaign();
			});

			$(document).on('click', '.abc-resume-campaign', function(e) {
				e.preventDefault();
				self.resumeCampaign();
			});

			$(document).on('click', '.abc-discover-now', function(e) {
				e.preventDefault();
				self.discoverNow();
			});

			$(document).on('click', '.abc-process-queue', function(e) {
				e.preventDefault();
				self.processQueue();
			});

			$(document).on('click', '.abc-clone-campaign', function(e) {
				e.preventDefault();
				self.cloneCampaign();
			});

			$(document).on('click', '.abc-delete-campaign', function(e) {
				e.preventDefault();
				self.deleteCampaign();
			});

			// Queue actions
			$(document).on('click', '.abc-process-queue-item', function(e) {
				e.preventDefault();
				self.processQueueItem($(this));
			});

			$(document).on('click', '.abc-delete-queue-item', function(e) {
				e.preventDefault();
				self.deleteQueueItem($(this));
			});

			$(document).on('click', '.abc-clear-failed-queue', function(e) {
				e.preventDefault();
				self.clearFailedQueue();
			});

			// Source management
			$(document).on('click', '.abc-add-source', function(e) {
				e.preventDefault();
				self.addSource($(this));
			});

			$(document).on('click', '.abc-remove-source', function(e) {
				e.preventDefault();
				self.removeSource($(this));
			});

			$(document).on('click', '.abc-save-sources', function(e) {
				e.preventDefault();
				self.saveSources();
			});

			// Settings
			$(document).on('click', '.abc-save-settings', function(e) {
				e.preventDefault();
				self.saveSettings();
			});

			// Logs
			$(document).on('click', '.abc-export-logs', function(e) {
				e.preventDefault();
				self.exportLogs();
			});

			$(document).on('click', '.abc-clear-logs', function(e) {
				e.preventDefault();
				self.clearLogs();
			});

			$(document).on('click', '.abc-log-item', function(e) {
				if (!$(e.target).is('a, button')) {
					self.toggleLogDetails($(this));
				}
			});

			// Filter changes
			$(document).on('change', '.abc-filter-select, .abc-filter-input', function() {
				self.applyFilters($(this));
			});

			// Refresh stats
			$(document).on('click', '.abc-refresh-stats', function(e) {
				e.preventDefault();
				self.refreshStats();
			});

			// Handle hash changes
			$(window).on('hashchange', function() {
				self.handleHashChange();
			});
		},

		/**
		 * Initialize tabs from URL hash
		 */
		initTabs: function() {
			this.handleHashChange();
		},

		/**
		 * Handle URL hash change
		 */
		handleHashChange: function() {
			const hash = window.location.hash.substring(1);
			if (hash && this.$tabs.filter('[data-tab="' + hash + '"]').length) {
				this.switchTab(hash, false);
			}
		},

		/**
		 * Switch tab
		 */
		switchTab: function(tab, updateHash = true) {
			// Update active tab
			this.$tabs.removeClass('nav-tab-active');
			this.$tabs.filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');

			// Show tab content
			this.$tabContents.hide();
			$('.abc-tab-content[data-tab="' + tab + '"]').fadeIn(200);

			// Update hash
			if (updateHash) {
				window.location.hash = tab;
			}

			this.currentTab = tab;

			// Load tab data if needed
			this.loadTabData(tab);
		},

		/**
		 * Load tab data via AJAX
		 */
		loadTabData: function(tab) {
			const self = this;

			// Only load data for specific tabs
			if (['queue', 'posts', 'logs'].indexOf(tab) === -1) {
				return;
			}

			const $tabContent = $('.abc-tab-content[data-tab="' + tab + '"]');
			const $loadingIndicator = $tabContent.find('.abc-loading');

			if ($loadingIndicator.length && !$loadingIndicator.hasClass('loaded')) {
				$loadingIndicator.show();

				$.ajax({
					url: abcCampaignDetail.ajaxUrl,
					type: 'POST',
					data: {
						action: 'abc_load_campaign_tab',
						nonce: abcCampaignDetail.nonce,
						campaign_id: self.campaignId,
						tab: tab
					},
					success: function(response) {
						if (response.success && response.data.html) {
							$tabContent.html(response.data.html);
							$loadingIndicator.addClass('loaded');
						}
					},
					error: function(xhr, status, error) {
						console.error('Failed to load tab data:', error);
						$loadingIndicator.hide();
					}
				});
			}
		},

		/**
		 * Pause campaign
		 */
		pauseCampaign: function() {
			this.updateCampaignStatus('paused', 'Pausing campaign...');
		},

		/**
		 * Resume campaign
		 */
		resumeCampaign: function() {
			this.updateCampaignStatus('active', 'Resuming campaign...');
		},

		/**
		 * Update campaign status
		 */
		updateCampaignStatus: function(status, loadingText) {
			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_update_campaign_status',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId,
					status: status
				},
				beforeSend: function() {
					self.showLoading(loadingText);
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message, 'success');
						self.refreshStats();
						
						// Update action buttons
						if (status === 'paused') {
							$('.abc-pause-campaign').hide();
							$('.abc-resume-campaign').show();
						} else {
							$('.abc-pause-campaign').show();
							$('.abc-resume-campaign').hide();
						}
					} else {
						self.showNotice(response.data.message || 'Failed to update campaign status.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while updating the campaign.', 'error');
					console.error('Update status error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Discover content now
		 */
		discoverNow: function() {
			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_discover_campaign',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Discovering content...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message, 'success');
						self.refreshStats();
					} else {
						self.showNotice(response.data.message || 'Discovery failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred during discovery.', 'error');
					console.error('Discovery error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Process queue
		 */
		processQueue: function() {
			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_process_campaign_queue',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Processing queue...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message, 'success');
						self.refreshStats();
						
						// Reload queue tab if active
						if (self.currentTab === 'queue') {
							self.loadTabData('queue');
						}
					} else {
						self.showNotice(response.data.message || 'Processing failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while processing.', 'error');
					console.error('Processing error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Process single queue item
		 */
		processQueueItem: function($btn) {
			const self = this;
			const queueId = $btn.data('queue-id');
			const $row = $btn.closest('tr');

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_process_queue_item',
					nonce: abcCampaignDetail.nonce,
					queue_id: queueId
				},
				beforeSend: function() {
					$btn.prop('disabled', true);
					$row.addClass('processing');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Queue item processed successfully.', 'success');
						$row.fadeOut(300, function() {
							$(this).remove();
						});
						self.refreshStats();
					} else {
						self.showNotice(response.data.message || 'Processing failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while processing.', 'error');
					console.error('Process item error:', error);
				},
				complete: function() {
					$btn.prop('disabled', false);
					$row.removeClass('processing');
				}
			});
		},

		/**
		 * Delete queue item
		 */
		deleteQueueItem: function($btn) {
			if (!confirm('Are you sure you want to delete this queue item?')) {
				return;
			}

			const self = this;
			const queueId = $btn.data('queue-id');
			const $row = $btn.closest('tr');

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_delete_queue_item',
					nonce: abcCampaignDetail.nonce,
					queue_id: queueId
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Queue item deleted.', 'success');
						$row.fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						self.showNotice(response.data.message || 'Delete failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while deleting.', 'error');
					console.error('Delete item error:', error);
				}
			});
		},

		/**
		 * Clear failed queue items
		 */
		clearFailedQueue: function() {
			if (!confirm('Are you sure you want to clear all failed queue items?')) {
				return;
			}

			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_clear_failed_queue',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Clearing failed items...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message, 'success');
						self.loadTabData('queue');
						self.refreshStats();
					} else {
						self.showNotice(response.data.message || 'Clear failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while clearing.', 'error');
					console.error('Clear failed error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Clone campaign
		 */
		cloneCampaign: function() {
			if (!confirm('Clone this campaign with all its settings?')) {
				return;
			}

			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_clone_campaign',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Cloning campaign...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Campaign cloned successfully!', 'success');
						
						// Redirect to new campaign
						setTimeout(() => {
							window.location.href = response.data.redirect_url;
						}, 1500);
					} else {
						self.showNotice(response.data.message || 'Clone failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while cloning.', 'error');
					console.error('Clone error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Delete campaign
		 */
		deleteCampaign: function() {
			if (!confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
				return;
			}

			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_delete_campaign',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Deleting campaign...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Campaign deleted successfully.', 'success');
						
						// Redirect to campaigns list
						setTimeout(() => {
							window.location.href = abcCampaignDetail.campaignsUrl;
						}, 1500);
					} else {
						self.showNotice(response.data.message || 'Delete failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while deleting.', 'error');
					console.error('Delete error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Add source field
		 */
		addSource: function($btn) {
			const $container = $btn.closest('.abc-sources-container');
			const $template = $container.find('.abc-source-template');
			const $list = $container.find('.abc-sources-list');

			const $newSource = $template.clone();
			$newSource.removeClass('abc-source-template').addClass('abc-source-item');
			$newSource.find('input').val('').prop('disabled', false);
			$list.append($newSource.fadeIn(200));
		},

		/**
		 * Remove source field
		 */
		removeSource: function($btn) {
			$btn.closest('.abc-source-item').fadeOut(200, function() {
				$(this).remove();
			});
		},

		/**
		 * Save sources
		 */
		saveSources: function() {
			const self = this;
			const $form = $('#abc-sources-form');
			const formData = $form.serialize();

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: formData + '&action=abc_save_campaign_sources&nonce=' + abcCampaignDetail.nonce + '&campaign_id=' + self.campaignId,
				beforeSend: function() {
					self.showLoading('Saving sources...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Sources saved successfully.', 'success');
					} else {
						self.showNotice(response.data.message || 'Save failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while saving.', 'error');
					console.error('Save sources error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Save campaign settings
		 */
		saveSettings: function() {
			const self = this;
			const $form = $('#abc-settings-form');
			const formData = $form.serialize();

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: formData + '&action=abc_save_campaign_settings&nonce=' + abcCampaignDetail.nonce + '&campaign_id=' + self.campaignId,
				beforeSend: function() {
					self.showLoading('Saving settings...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Settings saved successfully.', 'success');
					} else {
						self.showNotice(response.data.message || 'Save failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while saving.', 'error');
					console.error('Save settings error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Export logs
		 */
		exportLogs: function() {
			const filters = this.getLogFilters();
			const params = new URLSearchParams({
				action: 'abc_export_campaign_logs',
				nonce: abcCampaignDetail.nonce,
				campaign_id: this.campaignId,
				...filters
			});

			window.location.href = abcCampaignDetail.ajaxUrl + '?' + params.toString();
		},

		/**
		 * Clear logs
		 */
		clearLogs: function() {
			if (!confirm('Are you sure you want to clear all campaign logs?')) {
				return;
			}

			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_clear_campaign_logs',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				beforeSend: function() {
					self.showLoading('Clearing logs...');
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('Logs cleared successfully.', 'success');
						self.loadTabData('logs');
					} else {
						self.showNotice(response.data.message || 'Clear failed.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while clearing logs.', 'error');
					console.error('Clear logs error:', error);
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Toggle log details
		 */
		toggleLogDetails: function($logItem) {
			const $details = $logItem.find('.abc-log-details');
			
			if ($details.is(':visible')) {
				$details.slideUp(200);
				$logItem.removeClass('expanded');
			} else {
				$details.slideDown(200);
				$logItem.addClass('expanded');
			}
		},

		/**
		 * Get log filters
		 */
		getLogFilters: function() {
			return {
				level: $('.abc-filter-level').val() || '',
				category: $('.abc-filter-category').val() || '',
				search: $('.abc-filter-search').val() || ''
			};
		},

		/**
		 * Apply filters
		 */
		applyFilters: function($element) {
			this.loadTabData(this.currentTab);
		},

		/**
		 * Refresh statistics
		 */
		refreshStats: function() {
			const self = this;

			$.ajax({
				url: abcCampaignDetail.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_get_campaign_stats',
					nonce: abcCampaignDetail.nonce,
					campaign_id: self.campaignId
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						self.updateStatsDisplay(response.data.stats);
					}
				},
				error: function(xhr, status, error) {
					console.error('Refresh stats error:', error);
				}
			});
		},

		/**
		 * Update statistics display
		 */
		updateStatsDisplay: function(stats) {
			for (const [key, value] of Object.entries(stats)) {
				const $stat = $('.abc-stat-card[data-stat="' + key + '"]');
				if ($stat.length) {
					$stat.find('.abc-stat-value').text(value);
				}
			}
		},

		/**
		 * Start auto-refresh interval
		 */
		startAutoRefresh: function() {
			const self = this;
			
			this.refreshInterval = setInterval(function() {
				if (self.currentTab === 'overview') {
					self.refreshStats();
				}
			}, this.refreshRate);
		},

		/**
		 * Stop auto-refresh interval
		 */
		stopAutoRefresh: function() {
			if (this.refreshInterval) {
				clearInterval(this.refreshInterval);
			}
		},

		/**
		 * Show loading overlay
		 */
		showLoading: function(message) {
			const $overlay = $('<div class="abc-loading-overlay"><div class="abc-loading-spinner"></div><p>' + message + '</p></div>');
			this.$container.append($overlay);
		},

		/**
		 * Hide loading overlay
		 */
		hideLoading: function() {
			$('.abc-loading-overlay').fadeOut(200, function() {
				$(this).remove();
			});
		},

		/**
		 * Show notice message
		 */
		showNotice: function(message, type) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			this.$container.prepend($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);

			// Scroll to top
			$('html, body').animate({
				scrollTop: this.$container.offset().top - 32
			}, 300);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		if ($('.abc-campaign-detail').length) {
			ABCCampaignDetail.init();
		}
	});

	// Cleanup on page unload
	$(window).on('beforeunload', function() {
		if (typeof ABCCampaignDetail !== 'undefined') {
			ABCCampaignDetail.stopAutoRefresh();
		}
	});

})(jQuery);
