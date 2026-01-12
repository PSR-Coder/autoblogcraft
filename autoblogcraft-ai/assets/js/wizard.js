/**
 * Campaign Wizard JavaScript
 * 
 * Handles campaign creation wizard interactions including:
 * - Step navigation and validation
 * - Campaign type selection
 * - Dynamic field management
 * - Form data persistence
 * - AJAX submission
 *
 * @package Diyara_Core
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Wizard Controller
	 */
	const ABCWizard = {
		currentStep: 1,
		totalSteps: 3,
		formData: {},
		
		/**
		 * Initialize wizard
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.loadSavedData();
			this.updateStepIndicators();
			this.initConditionalFields();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$wizard = $('.abc-wizard-container');
			this.$steps = $('.abc-wizard-step');
			this.$stepContents = $('.abc-step-content');
			this.$nextBtn = $('.abc-wizard-next');
			this.$prevBtn = $('.abc-wizard-prev');
			this.$submitBtn = $('.abc-wizard-submit');
			this.$typeCards = $('.abc-campaign-type-card');
			this.$progressBar = $('.abc-progress-bar');
			this.$progressText = $('.abc-progress-text');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Navigation buttons
			this.$nextBtn.on('click', function(e) {
				e.preventDefault();
				self.nextStep();
			});

			this.$prevBtn.on('click', function(e) {
				e.preventDefault();
				self.prevStep();
			});

			this.$submitBtn.on('click', function(e) {
				e.preventDefault();
				self.submitForm();
			});

			// Campaign type selection
			this.$typeCards.on('click', function() {
				self.selectCampaignType($(this));
			});

			// Dynamic field management
			$(document).on('click', '.abc-add-field-btn', function(e) {
				e.preventDefault();
				self.addField($(this));
			});

			$(document).on('click', '.abc-remove-field-btn', function(e) {
				e.preventDefault();
				self.removeField($(this));
			});

			// AJAX validation buttons
			$(document).on('click', '.abc-validate-url-btn', function(e) {
				e.preventDefault();
				self.validateUrl($(this));
			});

			$(document).on('click', '.abc-validate-rss-btn', function(e) {
				e.preventDefault();
				self.validateRss($(this));
			});

			$(document).on('click', '.abc-validate-youtube-btn', function(e) {
				e.preventDefault();
				self.validateYouTube($(this));
			});

			$(document).on('click', '.abc-validate-api-key-btn', function(e) {
				e.preventDefault();
				self.validateApiKey($(this));
			});

			// Conditional field visibility
			$(document).on('change', '[data-controls]', function() {
				self.updateConditionalFields($(this));
			});

			// Auto-save form data
			this.$wizard.on('change', 'input, select, textarea', function() {
				self.saveFormData();
			});

			// Prevent Enter key from submitting form
			this.$wizard.on('keypress', 'input', function(e) {
				if (e.which === 13 && !$(this).is('textarea')) {
					e.preventDefault();
					return false;
				}
			});

			// Step indicator click navigation
			this.$steps.on('click', function() {
				const stepNumber = $(this).index() + 1;
				if (stepNumber < self.currentStep) {
					self.goToStep(stepNumber);
				}
			});
		},

		/**
		 * Navigate to next step
		 */
		nextStep: function() {
			if (!this.validateCurrentStep()) {
				return;
			}

			this.saveFormData();

			if (this.currentStep < this.totalSteps) {
				this.currentStep++;
				this.showStep(this.currentStep);
			}
		},

		/**
		 * Navigate to previous step
		 */
		prevStep: function() {
			if (this.currentStep > 1) {
				this.currentStep--;
				this.showStep(this.currentStep);
			}
		},

		/**
		 * Go to specific step
		 */
		goToStep: function(stepNumber) {
			if (stepNumber >= 1 && stepNumber <= this.totalSteps) {
				this.currentStep = stepNumber;
				this.showStep(this.currentStep);
			}
		},

		/**
		 * Show specific step
		 */
		showStep: function(stepNumber) {
			// Hide all step contents
			this.$stepContents.hide();
			
			// Show current step content
			$('.abc-step-content[data-step="' + stepNumber + '"]').fadeIn(300);

			// Update step indicators
			this.updateStepIndicators();

			// Update progress bar
			this.updateProgressBar();

			// Update navigation buttons
			this.updateNavigationButtons();

			// Scroll to top
			$('html, body').animate({
				scrollTop: this.$wizard.offset().top - 32
			}, 300);
		},

		/**
		 * Update step indicators
		 */
		updateStepIndicators: function() {
			this.$steps.each((index, step) => {
				const $step = $(step);
				const stepNumber = index + 1;

				$step.removeClass('active completed');

				if (stepNumber < this.currentStep) {
					$step.addClass('completed');
				} else if (stepNumber === this.currentStep) {
					$step.addClass('active');
				}
			});
		},

		/**
		 * Update progress bar
		 */
		updateProgressBar: function() {
			const progress = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
			
			this.$progressBar.css('width', progress + '%');
			this.$progressText.text('Step ' + this.currentStep + ' of ' + this.totalSteps);
		},

		/**
		 * Update navigation buttons visibility
		 */
		updateNavigationButtons: function() {
			// Previous button
			if (this.currentStep === 1) {
				this.$prevBtn.hide();
			} else {
				this.$prevBtn.show();
			}

			// Next/Submit buttons
			if (this.currentStep === this.totalSteps) {
				this.$nextBtn.hide();
				this.$submitBtn.show();
			} else {
				this.$nextBtn.show();
				this.$submitBtn.hide();
			}
		},

		/**
		 * Validate current step
		 */
		validateCurrentStep: function() {
			const $currentContent = $('.abc-step-content[data-step="' + this.currentStep + '"]');
			let isValid = true;

			// Clear previous errors
			$currentContent.find('.abc-form-field').removeClass('has-error');
			$currentContent.find('.abc-field-error').remove();

			// Validate required fields
			$currentContent.find('[required]').each(function() {
				const $field = $(this);
				const $formField = $field.closest('.abc-form-field');

				if (!$field.val() || $field.val().trim() === '') {
					isValid = false;
					$formField.addClass('has-error');
					
					const fieldName = $field.prev('label').text().replace('*', '').trim();
					$field.after('<span class="abc-field-error">' + fieldName + ' is required.</span>');
				}
			});

			// Step 1: Campaign type selection
			if (this.currentStep === 1) {
				if (!$('.abc-campaign-type-card.selected').length) {
					isValid = false;
					this.showNotice('Please select a campaign type.', 'error');
				}
			}

			// Step 2: Basic configuration validation
			if (this.currentStep === 2) {
				const campaignType = this.formData.campaign_type;

				// Website: At least one URL
				if (campaignType === 'website') {
					const urls = $currentContent.find('input[name="urls[]"]').filter(function() {
						return $(this).val().trim() !== '';
					});

					if (urls.length === 0) {
						isValid = false;
						this.showNotice('Please add at least one website URL.', 'error');
					}

					// Validate URL format
					urls.each(function() {
						const url = $(this).val();
						if (!ABCWizard.isValidUrl(url)) {
							isValid = false;
							$(this).closest('.abc-form-field').addClass('has-error');
							$(this).after('<span class="abc-field-error">Invalid URL format.</span>');
						}
					});
				}

				// YouTube: At least one channel or playlist
				if (campaignType === 'youtube') {
					const channels = $currentContent.find('input[name="channels[]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					const playlists = $currentContent.find('input[name="playlists[]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					if (channels === 0 && playlists === 0) {
						isValid = false;
						this.showNotice('Please add at least one YouTube channel or playlist.', 'error');
					}
				}

				// Amazon: At least one keyword or category
				if (campaignType === 'amazon') {
					const keywords = $currentContent.find('input[name="keywords[]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					const categories = $currentContent.find('input[name="categories[]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					if (keywords === 0 && categories === 0) {
						isValid = false;
						this.showNotice('Please add at least one keyword or category.', 'error');
					}
				}

				// News: At least one keyword
				if (campaignType === 'news') {
					const keywords = $currentContent.find('input[name="keywords[]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					if (keywords === 0) {
						isValid = false;
						this.showNotice('Please add at least one news keyword.', 'error');
					}
				}
			}

			if (!isValid) {
				// Scroll to first error
				const $firstError = $currentContent.find('.has-error').first();
				if ($firstError.length) {
					$('html, body').animate({
						scrollTop: $firstError.offset().top - 100
					}, 300);
				}
			}

			return isValid;
		},

		/**
		 * Validate URL format
		 */
		isValidUrl: function(url) {
			try {
				const urlObj = new URL(url);
				return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
			} catch (e) {
				return false;
			}
		},

		/**
		 * Select campaign type
		 */
		selectCampaignType: function($card) {
			// Remove selection from all cards
			this.$typeCards.removeClass('selected');
			this.$typeCards.find('input[type="radio"]').prop('checked', false);

			// Select clicked card
			$card.addClass('selected');
			$card.find('input[type="radio"]').prop('checked', true);

			// Save campaign type
			this.formData.campaign_type = $card.find('input[type="radio"]').val();
			this.saveFormData();

			// Auto-advance after selection
			setTimeout(() => {
				this.nextStep();
			}, 500);
		},

		/**
		 * Add dynamic field
		 */
		addField: function($btn) {
			const $fieldList = $btn.closest('.abc-field-group').find('.abc-field-list');
			const fieldName = $btn.data('field-name');
			const fieldType = $btn.data('field-type') || 'text';
			const placeholder = $btn.data('placeholder') || '';

			const $newField = $('<div class="abc-field-item"></div>');
			$newField.html(
				'<input type="' + fieldType + '" name="' + fieldName + '[]" ' +
				'placeholder="' + placeholder + '" class="regular-text" />' +
				'<button type="button" class="button abc-remove-field-btn">' +
				'<span class="dashicons dashicons-no"></span> Remove' +
				'</button>'
			);

			$fieldList.append($newField);
			$newField.find('input').focus();
		},

		/**
		 * Remove dynamic field
		 */
		removeField: function($btn) {
			$btn.closest('.abc-field-item').fadeOut(200, function() {
				$(this).remove();
			});
		},

		/**
		 * Save form data to localStorage
		 */
		saveFormData: function() {
			const formData = this.$wizard.find('form').serializeArray();
			
			formData.forEach(item => {
				if (item.name.endsWith('[]')) {
					// Handle array fields
					const key = item.name.replace('[]', '');
					if (!this.formData[key]) {
						this.formData[key] = [];
					}
					this.formData[key].push(item.value);
				} else {
					this.formData[item.name] = item.value;
				}
			});

			this.formData.current_step = this.currentStep;

			try {
				localStorage.setItem('abc_wizard_data', JSON.stringify(this.formData));
			} catch (e) {
				console.warn('Failed to save wizard data:', e);
			}
		},

		/**
		 * Load saved form data from localStorage
		 */
		loadSavedData: function() {
			try {
				const savedData = localStorage.getItem('abc_wizard_data');
				if (savedData) {
					this.formData = JSON.parse(savedData);
					
					// Restore form values
					for (const [key, value] of Object.entries(this.formData)) {
						if (key === 'current_step') continue;

						if (Array.isArray(value)) {
							// Handle array fields
							value.forEach((val, index) => {
								const $field = $('input[name="' + key + '[]"]').eq(index);
								if ($field.length) {
									$field.val(val);
								}
							});
						} else {
							const $field = $('[name="' + key + '"]');
							if ($field.is(':radio, :checkbox')) {
								$field.filter('[value="' + value + '"]').prop('checked', true);
							} else {
								$field.val(value);
							}
						}
					}

					// Restore campaign type selection
					if (this.formData.campaign_type) {
						$('.abc-campaign-type-card input[value="' + this.formData.campaign_type + '"]')
							.closest('.abc-campaign-type-card').addClass('selected');
					}
				}
			} catch (e) {
				console.warn('Failed to load wizard data:', e);
			}
		},

		/**
		 * Clear saved form data
		 */
		clearSavedData: function() {
			try {
				localStorage.removeItem('abc_wizard_data');
				this.formData = {};
			} catch (e) {
				console.warn('Failed to clear wizard data:', e);
			}
		},

		/**
		 * Submit wizard form
		 */
		submitForm: function() {
			if (!this.validateCurrentStep()) {
				return;
			}

			const $form = this.$wizard.find('form');
			const formData = new FormData($form[0]);

			// Add action and nonce
			formData.append('action', 'abc_create_campaign');
			formData.append('nonce', abcWizard.nonce);

			// Disable submit button
			this.$submitBtn.prop('disabled', true).addClass('disabled');
			this.$submitBtn.html('<span class="dashicons dashicons-update spinning"></span> Creating Campaign...');

			// Submit via AJAX
			$.ajax({
				url: abcWizard.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success');
						this.clearSavedData();

						// Redirect to campaign detail page
						setTimeout(() => {
							window.location.href = response.data.redirect_url;
						}, 1500);
					} else {
						this.showNotice(response.data.message || 'Failed to create campaign.', 'error');
						this.$submitBtn.prop('disabled', false).removeClass('disabled');
						this.$submitBtn.html('Create Campaign');
					}
				},
				error: (xhr, status, error) => {
					this.showNotice('An error occurred. Please try again.', 'error');
					this.$submitBtn.prop('disabled', false).removeClass('disabled');
					this.$submitBtn.html('Create Campaign');
					console.error('Campaign creation error:', error);
				}
			});
		},

		/**
		 * Validate URL via AJAX
		 */
		validateUrl: function($btn) {
			const $input = $btn.closest('.abc-field-item').find('input[type="url"]');
			const url = $input.val().trim();

			if (!url) {
				this.showFieldError($input, 'Please enter a URL.');
				return;
			}

			// Disable button and show loading
			$btn.prop('disabled', true).addClass('loading');
			$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update spinning');

			// Clear previous status
			$btn.siblings('.abc-field-status').remove();

			$.ajax({
				url: abcWizard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_validate_url',
					nonce: abcWizard.nonce,
					url: url
				},
				success: (response) => {
					if (response.success) {
						this.showFieldSuccess($input, $btn, response.data.message);
					} else {
						this.showFieldError($input, response.data.message);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
					}
				},
				error: () => {
					this.showFieldError($input, 'Validation failed. Please try again.');
					$btn.prop('disabled', false).removeClass('loading');
					$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
				}
			});
		},

		/**
		 * Validate RSS feed via AJAX
		 */
		validateRss: function($btn) {
			const $input = $btn.closest('.abc-field-item').find('input[type="url"]');
			const url = $input.val().trim();

			if (!url) {
				this.showFieldError($input, 'Please enter a feed URL.');
				return;
			}

			$btn.prop('disabled', true).addClass('loading');
			$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update spinning');
			$btn.siblings('.abc-field-status').remove();

			$.ajax({
				url: abcWizard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_validate_rss',
					nonce: abcWizard.nonce,
					url: url
				},
				success: (response) => {
					if (response.success) {
						this.showFieldSuccess($input, $btn, response.data.message);
					} else {
						this.showFieldError($input, response.data.message);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
					}
				},
				error: () => {
					this.showFieldError($input, 'RSS validation failed. Please try again.');
					$btn.prop('disabled', false).removeClass('loading');
					$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
				}
			});
		},

		/**
		 * Validate YouTube channel/playlist via AJAX
		 */
		validateYouTube: function($btn) {
			const $input = $btn.closest('.abc-field-item').find('input[type="text"]');
			const value = $input.val().trim();
			const type = $btn.data('type'); // 'channel' or 'playlist'

			if (!value) {
				this.showFieldError($input, 'Please enter a ' + type + ' ID or URL.');
				return;
			}

			$btn.prop('disabled', true).addClass('loading');
			$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update spinning');
			$btn.siblings('.abc-field-status').remove();

			$.ajax({
				url: abcWizard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_validate_youtube',
					nonce: abcWizard.nonce,
					value: value,
					type: type
				},
				success: (response) => {
					if (response.success) {
						this.showFieldSuccess($input, $btn, response.data.message);
					} else {
						this.showFieldError($input, response.data.message);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
					}
				},
				error: () => {
					this.showFieldError($input, 'YouTube validation failed. Please try again.');
					$btn.prop('disabled', false).removeClass('loading');
					$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
				}
			});
		},

		/**
		 * Validate API key via AJAX
		 */
		validateApiKey: function($btn) {
			const $input = $btn.closest('.abc-form-field').find('input[type="text"], input[type="password"]');
			const apiKey = $input.val().trim();
			const provider = $input.data('provider'); // e.g., 'youtube', 'newsapi', 'serpapi'

			if (!apiKey) {
				this.showFieldError($input, 'Please enter an API key.');
				return;
			}

			$btn.prop('disabled', true).addClass('loading');
			$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update spinning');
			$btn.siblings('.abc-field-status').remove();

			$.ajax({
				url: abcWizard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'abc_validate_api_key',
					nonce: abcWizard.nonce,
					api_key: apiKey,
					provider: provider
				},
				success: (response) => {
					if (response.success) {
						this.showFieldSuccess($input, $btn, response.data.message);
					} else {
						this.showFieldError($input, response.data.message);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
					}
				},
				error: () => {
					this.showFieldError($input, 'API key validation failed. Please try again.');
					$btn.prop('disabled', false).removeClass('loading');
					$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
				}
			});
		},

		/**
		 * Show field validation success
		 */
		showFieldSuccess: function($input, $btn, message) {
			$input.removeClass('has-error').addClass('validated');
			$input.siblings('.abc-field-error').remove();
			
			const $status = $('<span class="abc-field-status success"><span class="dashicons dashicons-yes"></span> ' + message + '</span>');
			$btn.after($status);

			$btn.prop('disabled', false).removeClass('loading');
			$btn.find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-yes');
		},

		/**
		 * Show field validation error
		 */
		showFieldError: function($input, message) {
			$input.addClass('has-error').removeClass('validated');
			$input.siblings('.abc-field-error').remove();
			
			const $error = $('<span class="abc-field-error">' + message + '</span>');
			$input.after($error);
		},

		/**
		 * Update conditional fields based on control field value
		 */
		updateConditionalFields: function($control) {
			const controlValue = $control.val();
			const targetSelector = $control.data('controls');

			if (!targetSelector) return;

			const $targets = $(targetSelector);

			$targets.each(function() {
				const $target = $(this);
				const showWhen = $target.data('show-when');
				const showValue = $target.data('show-value');
				const dependsNot = $target.data('depends-not');

				let shouldShow = false;

				if (dependsNot) {
					// Hide when value matches
					shouldShow = (controlValue !== dependsNot);
				} else if (showValue) {
					// Show when value matches (supports comma-separated values)
					const allowedValues = showValue.split(',').map(v => v.trim());
					shouldShow = allowedValues.includes(controlValue);
				} else {
					// Show when field has any value
					shouldShow = (controlValue !== '');
				}

				if (shouldShow) {
					$target.slideDown(200);
					$target.find('input, select, textarea').prop('disabled', false);
				} else {
					$target.slideUp(200);
					$target.find('input, select, textarea').prop('disabled', true);
				}
			});
		},

		/**
		 * Initialize conditional fields on page load
		 */
		initConditionalFields: function() {
			const self = this;
			$('[data-controls]').each(function() {
				self.updateConditionalFields($(this));
			});
		},

		/**
		 * Show notice message
		 */
		showNotice: function(message, type) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			$('.abc-wizard-header').after($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);

			// Scroll to notice
			$('html, body').animate({
				scrollTop: $notice.offset().top - 32
			}, 300);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		if ($('.abc-wizard-container').length) {
			ABCWizard.init();
		}
	});

})(jQuery);
