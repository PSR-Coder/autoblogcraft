/**
 * API Keys Management JavaScript
 * 
 * Handles REST API key management interactions including:
 * - Generate new API keys
 * - Copy keys to clipboard
 * - Toggle key visibility
 * - Revoke keys
 * - Regenerate keys
 * - Display usage statistics
 *
 * @package Diyara_Core
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * API Keys Manager
	 */
	const ABCAPIKeys = {
		
		/**
		 * Initialize API keys management
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.loadKeys();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$container = $('.abc-api-keys-container');
			this.$keysList = $('.abc-api-keys-list');
			this.$generateBtn = $('.abc-generate-key-btn');
			this.$noKeysMessage = $('.abc-no-keys-message');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Generate new key
			this.$generateBtn.on('click', function(e) {
				e.preventDefault();
				self.generateKey($(this));
			});

			// Copy key to clipboard
			$(document).on('click', '.abc-copy-key-btn', function(e) {
				e.preventDefault();
				self.copyKey($(this));
			});

			// Toggle key visibility
			$(document).on('click', '.abc-toggle-key-visibility', function(e) {
				e.preventDefault();
				self.toggleKeyVisibility($(this));
			});

			// Revoke key
			$(document).on('click', '.abc-revoke-key-btn', function(e) {
				e.preventDefault();
				self.revokeKey($(this));
			});

			// Regenerate key
			$(document).on('click', '.abc-regenerate-key-btn', function(e) {
				e.preventDefault();
				self.regenerateKey($(this));
			});

			// Edit key name
			$(document).on('click', '.abc-edit-key-name-btn', function(e) {
				e.preventDefault();
				self.editKeyName($(this));
			});

			// Save key name
			$(document).on('click', '.abc-save-key-name-btn', function(e) {
				e.preventDefault();
				self.saveKeyName($(this));
			});

			// Cancel edit key name
			$(document).on('click', '.abc-cancel-key-name-btn', function(e) {
				e.preventDefault();
				self.cancelEditKeyName($(this));
			});

			// Refresh usage stats
			$(document).on('click', '.abc-refresh-stats-btn', function(e) {
				e.preventDefault();
				self.refreshUsageStats($(this));
			});
		},

		/**
		 * Load API keys list
		 */
		loadKeys: function() {
			const self = this;

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_get_api_keys',
					nonce: abcAPIKeys.nonce
				},
				success: function(response) {
					if (response.success && response.data.keys) {
						self.renderKeys(response.data.keys);
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to load API keys:', error);
				}
			});
		},

		/**
		 * Render API keys list
		 */
		renderKeys: function(keys) {
			if (!keys || keys.length === 0) {
				this.$keysList.html('');
				this.$noKeysMessage.show();
				return;
			}

			this.$noKeysMessage.hide();

			const $list = $('<div class="abc-api-keys-table"></div>');

			keys.forEach(key => {
				const $keyRow = this.createKeyRow(key);
				$list.append($keyRow);
			});

			this.$keysList.html($list);
		},

		/**
		 * Create key row HTML
		 */
		createKeyRow: function(key) {
			const maskedKey = this.maskKey(key.key);
			const createdDate = new Date(key.created_at).toLocaleDateString();
			const lastUsedDate = key.last_used_at ? new Date(key.last_used_at).toLocaleDateString() : 'Never';

			const $row = $('<div class="abc-api-key-row"></div>');
			$row.attr('data-key-id', key.id);

			$row.html(`
				<div class="abc-key-info">
					<div class="abc-key-header">
						<h3 class="abc-key-name">
							<span class="abc-key-name-display">${key.name || 'Unnamed Key'}</span>
							<input type="text" class="abc-key-name-input" value="${key.name || ''}" style="display:none;" />
						</h3>
						<div class="abc-key-actions">
							<button type="button" class="button abc-edit-key-name-btn" title="Edit Name">
								<span class="dashicons dashicons-edit"></span>
							</button>
							<button type="button" class="button abc-save-key-name-btn" title="Save Name" style="display:none;">
								<span class="dashicons dashicons-yes"></span> Save
							</button>
							<button type="button" class="button abc-cancel-key-name-btn" title="Cancel" style="display:none;">
								<span class="dashicons dashicons-no"></span>
							</button>
						</div>
					</div>
					
					<div class="abc-key-value-container">
						<code class="abc-key-value" data-key="${key.key}">${maskedKey}</code>
						<div class="abc-key-buttons">
							<button type="button" class="button abc-toggle-key-visibility" title="Show/Hide Key">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" class="button abc-copy-key-btn" title="Copy to Clipboard">
								<span class="dashicons dashicons-clipboard"></span> Copy
							</button>
						</div>
					</div>

					<div class="abc-key-meta">
						<span class="abc-meta-item">
							<span class="dashicons dashicons-calendar"></span> 
							Created: ${createdDate}
						</span>
						<span class="abc-meta-item">
							<span class="dashicons dashicons-clock"></span> 
							Last Used: ${lastUsedDate}
						</span>
						<span class="abc-meta-item">
							<span class="dashicons dashicons-chart-bar"></span> 
							Requests: <strong>${key.request_count || 0}</strong>
						</span>
					</div>
				</div>

				<div class="abc-key-stats">
					<div class="abc-stats-header">
						<h4>Usage Statistics</h4>
						<button type="button" class="button-link abc-refresh-stats-btn">
							<span class="dashicons dashicons-update"></span> Refresh
						</button>
					</div>
					<div class="abc-stats-grid">
						<div class="abc-stat-item">
							<span class="abc-stat-label">Today</span>
							<span class="abc-stat-value">${key.stats?.today || 0}</span>
						</div>
						<div class="abc-stat-item">
							<span class="abc-stat-label">This Week</span>
							<span class="abc-stat-value">${key.stats?.week || 0}</span>
						</div>
						<div class="abc-stat-item">
							<span class="abc-stat-label">This Month</span>
							<span class="abc-stat-value">${key.stats?.month || 0}</span>
						</div>
						<div class="abc-stat-item">
							<span class="abc-stat-label">Total</span>
							<span class="abc-stat-value">${key.request_count || 0}</span>
						</div>
					</div>
				</div>

				<div class="abc-key-actions-footer">
					<button type="button" class="button abc-regenerate-key-btn">
						<span class="dashicons dashicons-update"></span> Regenerate
					</button>
					<button type="button" class="button abc-revoke-key-btn">
						<span class="dashicons dashicons-trash"></span> Revoke
					</button>
				</div>
			`);

			return $row;
		},

		/**
		 * Mask API key for display
		 */
		maskKey: function(key) {
			if (!key || key.length < 12) {
				return key;
			}
			const start = key.substring(0, 8);
			const end = key.substring(key.length - 4);
			return start + '••••••••••••' + end;
		},

		/**
		 * Generate new API key
		 */
		generateKey: function($btn) {
			const self = this;
			const keyName = prompt('Enter a name for this API key (optional):');

			if (keyName === null) {
				return; // User cancelled
			}

			// Disable button
			$btn.prop('disabled', true).addClass('disabled');
			const originalText = $btn.html();
			$btn.html('<span class="dashicons dashicons-update spinning"></span> Generating...');

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_generate_api_key',
					nonce: abcAPIKeys.nonce,
					name: keyName
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('API key generated successfully!', 'success');
						
						// Show the new key in a modal/alert
						self.showNewKeyModal(response.data.key, response.data.secret);

						// Reload keys list
						self.loadKeys();
					} else {
						self.showNotice(response.data.message || 'Failed to generate API key.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while generating the API key.', 'error');
					console.error('Generate key error:', error);
				},
				complete: function() {
					$btn.prop('disabled', false).removeClass('disabled');
					$btn.html(originalText);
				}
			});
		},

		/**
		 * Show new key modal with the secret
		 */
		showNewKeyModal: function(keyId, secret) {
			const $modal = $(`
				<div class="abc-modal-overlay">
					<div class="abc-modal abc-new-key-modal">
						<div class="abc-modal-header">
							<h2>API Key Generated</h2>
						</div>
						<div class="abc-modal-body">
							<p><strong>Important:</strong> This is the only time you'll see the complete API key. Please copy and save it securely.</p>
							<div class="abc-key-display">
								<code>${secret}</code>
								<button type="button" class="button button-primary abc-copy-new-key-btn">
									<span class="dashicons dashicons-clipboard"></span> Copy Key
								</button>
							</div>
						</div>
						<div class="abc-modal-footer">
							<button type="button" class="button button-primary abc-close-modal-btn">I've Saved the Key</button>
						</div>
					</div>
				</div>
			`);

			$('body').append($modal);

			// Copy new key
			$modal.on('click', '.abc-copy-new-key-btn', function() {
				navigator.clipboard.writeText(secret).then(() => {
					$(this).html('<span class="dashicons dashicons-yes"></span> Copied!');
					setTimeout(() => {
						$(this).html('<span class="dashicons dashicons-clipboard"></span> Copy Key');
					}, 2000);
				});
			});

			// Close modal
			$modal.on('click', '.abc-close-modal-btn, .abc-modal-overlay', function(e) {
				if (e.target === this) {
					$modal.fadeOut(200, function() {
						$(this).remove();
					});
				}
			});
		},

		/**
		 * Copy key to clipboard
		 */
		copyKey: function($btn) {
			const $keyValue = $btn.closest('.abc-key-value-container').find('.abc-key-value');
			const key = $keyValue.data('key');

			navigator.clipboard.writeText(key).then(() => {
				const originalHtml = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
				$btn.addClass('success');

				setTimeout(() => {
					$btn.html(originalHtml);
					$btn.removeClass('success');
				}, 2000);

				this.showNotice('API key copied to clipboard!', 'success');
			}).catch(err => {
				this.showNotice('Failed to copy API key.', 'error');
				console.error('Copy failed:', err);
			});
		},

		/**
		 * Toggle key visibility
		 */
		toggleKeyVisibility: function($btn) {
			const $keyValue = $btn.closest('.abc-key-value-container').find('.abc-key-value');
			const key = $keyValue.data('key');
			const $icon = $btn.find('.dashicons');

			if ($icon.hasClass('dashicons-visibility')) {
				// Show full key
				$keyValue.text(key);
				$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
				$btn.attr('title', 'Hide Key');
			} else {
				// Hide key
				$keyValue.text(this.maskKey(key));
				$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
				$btn.attr('title', 'Show Key');
			}
		},

		/**
		 * Revoke API key
		 */
		revokeKey: function($btn) {
			if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
				return;
			}

			const self = this;
			const $row = $btn.closest('.abc-api-key-row');
			const keyId = $row.data('key-id');

			// Disable button
			$btn.prop('disabled', true).addClass('disabled');
			const originalText = $btn.html();
			$btn.html('<span class="dashicons dashicons-update spinning"></span> Revoking...');

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_revoke_api_key',
					nonce: abcAPIKeys.nonce,
					key_id: keyId
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('API key revoked successfully.', 'success');
						$row.fadeOut(300, function() {
							$(this).remove();
							
							// Check if no keys left
							if ($('.abc-api-key-row').length === 0) {
								self.$noKeysMessage.show();
							}
						});
					} else {
						self.showNotice(response.data.message || 'Failed to revoke API key.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while revoking the API key.', 'error');
					console.error('Revoke key error:', error);
				},
				complete: function() {
					$btn.prop('disabled', false).removeClass('disabled');
					$btn.html(originalText);
				}
			});
		},

		/**
		 * Regenerate API key
		 */
		regenerateKey: function($btn) {
			if (!confirm('Are you sure you want to regenerate this API key? The old key will stop working immediately.')) {
				return;
			}

			const self = this;
			const $row = $btn.closest('.abc-api-key-row');
			const keyId = $row.data('key-id');

			// Disable button
			$btn.prop('disabled', true).addClass('disabled');
			const originalText = $btn.html();
			$btn.html('<span class="dashicons dashicons-update spinning"></span> Regenerating...');

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_regenerate_api_key',
					nonce: abcAPIKeys.nonce,
					key_id: keyId
				},
				success: function(response) {
					if (response.success) {
						self.showNotice('API key regenerated successfully!', 'success');
						self.showNewKeyModal(keyId, response.data.secret);
						self.loadKeys();
					} else {
						self.showNotice(response.data.message || 'Failed to regenerate API key.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while regenerating the API key.', 'error');
					console.error('Regenerate key error:', error);
				},
				complete: function() {
					$btn.prop('disabled', false).removeClass('disabled');
					$btn.html(originalText);
				}
			});
		},

		/**
		 * Edit key name
		 */
		editKeyName: function($btn) {
			const $row = $btn.closest('.abc-api-key-row');
			const $nameDisplay = $row.find('.abc-key-name-display');
			const $nameInput = $row.find('.abc-key-name-input');
			const $editBtn = $row.find('.abc-edit-key-name-btn');
			const $saveBtn = $row.find('.abc-save-key-name-btn');
			const $cancelBtn = $row.find('.abc-cancel-key-name-btn');

			$nameDisplay.hide();
			$nameInput.show().focus();
			$editBtn.hide();
			$saveBtn.show();
			$cancelBtn.show();
		},

		/**
		 * Save key name
		 */
		saveKeyName: function($btn) {
			const self = this;
			const $row = $btn.closest('.abc-api-key-row');
			const $nameInput = $row.find('.abc-key-name-input');
			const keyId = $row.data('key-id');
			const newName = $nameInput.val().trim();

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_update_api_key_name',
					nonce: abcAPIKeys.nonce,
					key_id: keyId,
					name: newName
				},
				success: function(response) {
					if (response.success) {
						const $nameDisplay = $row.find('.abc-key-name-display');
						$nameDisplay.text(newName || 'Unnamed Key');
						self.cancelEditKeyName($btn);
						self.showNotice('Key name updated successfully.', 'success');
					} else {
						self.showNotice(response.data.message || 'Failed to update key name.', 'error');
					}
				},
				error: function(xhr, status, error) {
					self.showNotice('An error occurred while updating the key name.', 'error');
					console.error('Update key name error:', error);
				}
			});
		},

		/**
		 * Cancel edit key name
		 */
		cancelEditKeyName: function($btn) {
			const $row = $btn.closest('.abc-api-key-row');
			const $nameDisplay = $row.find('.abc-key-name-display');
			const $nameInput = $row.find('.abc-key-name-input');
			const $editBtn = $row.find('.abc-edit-key-name-btn');
			const $saveBtn = $row.find('.abc-save-key-name-btn');
			const $cancelBtn = $row.find('.abc-cancel-key-name-btn');

			$nameDisplay.show();
			$nameInput.hide();
			$editBtn.show();
			$saveBtn.hide();
			$cancelBtn.hide();
		},

		/**
		 * Refresh usage statistics
		 */
		refreshUsageStats: function($btn) {
			const self = this;
			const $row = $btn.closest('.abc-api-key-row');
			const keyId = $row.data('key-id');
			const $icon = $btn.find('.dashicons');

			$icon.addClass('spinning');

			$.ajax({
				url: abcAPIKeys.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_get_api_key_stats',
					nonce: abcAPIKeys.nonce,
					key_id: keyId
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						const stats = response.data.stats;
						$row.find('.abc-stats-grid .abc-stat-value').eq(0).text(stats.today || 0);
						$row.find('.abc-stats-grid .abc-stat-value').eq(1).text(stats.week || 0);
						$row.find('.abc-stats-grid .abc-stat-value').eq(2).text(stats.month || 0);
						$row.find('.abc-stats-grid .abc-stat-value').eq(3).text(stats.total || 0);
					}
				},
				error: function(xhr, status, error) {
					console.error('Refresh stats error:', error);
				},
				complete: function() {
					$icon.removeClass('spinning');
				}
			});
		},

		/**
		 * Show notice message
		 */
		showNotice: function(message, type) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			this.$container.before($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		if ($('.abc-api-keys-container').length) {
			ABCAPIKeys.init();
		}
	});

})(jQuery);
