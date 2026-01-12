<?php
/**
 * Security Hardening
 *
 * Centralized security functions for input sanitization, output escaping,
 * nonce management, and security headers.
 *
 * @package AutoBlogCraft\Security
 * @since 2.0.0
 */

namespace AutoBlogCraft\Security;

/**
 * Security Hardening Class
 *
 * Provides security utilities for:
 * - Input sanitization
 * - Output escaping
 * - Nonce generation and verification
 * - Security headers
 * - SQL injection prevention
 *
 * @since 2.0.0
 */
class Security_Hardening {

	/**
	 * Nonce actions
	 *
	 * @var array
	 */
	private static $nonce_actions = array(
		'ajax_campaign_create'  => 'abc_ajax_campaign_create',
		'ajax_campaign_update'  => 'abc_ajax_campaign_update',
		'ajax_campaign_delete'  => 'abc_ajax_campaign_delete',
		'ajax_queue_action'     => 'abc_ajax_queue_action',
		'ajax_api_key_save'     => 'abc_ajax_api_key_save',
		'ajax_api_key_delete'   => 'abc_ajax_api_key_delete',
		'ajax_settings_save'    => 'abc_ajax_settings_save',
		'ajax_validate_url'     => 'abc_ajax_validate_url',
		'ajax_validate_rss'     => 'abc_ajax_validate_rss',
		'campaign_wizard_step'  => 'abc_campaign_wizard_step',
		'bulk_action'           => 'abc_bulk_action',
	);

	/**
	 * Initialize security
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'set_security_headers' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_capabilities' ) );
	}

	/**
	 * Set security headers
	 */
	public static function set_security_headers() {
		if ( is_admin() && ! headers_sent() ) {
			// X-Content-Type-Options: prevent MIME sniffing
			header( 'X-Content-Type-Options: nosniff' );

			// X-Frame-Options: prevent clickjacking
			header( 'X-Frame-Options: SAMEORIGIN' );

			// X-XSS-Protection: enable XSS filtering
			header( 'X-XSS-Protection: 1; mode=block' );

			// Referrer-Policy: control referrer information
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}

	/**
	 * Check user capabilities
	 */
	public static function check_capabilities() {
		if ( ! current_user_can( 'manage_options' ) && strpos( $_SERVER['REQUEST_URI'], 'autoblogcraft' ) !== false ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'autoblogcraft' ) );
		}
	}

	/**
	 * Sanitize input array
	 *
	 * @param array  $data     Input data.
	 * @param array  $rules    Sanitization rules.
	 * @param string $context  Context (save, display).
	 * @return array Sanitized data
	 */
	public static function sanitize_input( $data, $rules = array(), $context = 'save' ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			if ( isset( $rules[ $key ] ) ) {
				$sanitized[ $key ] = self::sanitize_value( $value, $rules[ $key ], $context );
			} else {
				// Default: sanitize as text
				$sanitized[ $key ] = self::sanitize_value( $value, 'text', $context );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize single value
	 *
	 * @param mixed  $value   Value to sanitize.
	 * @param string $type    Type of sanitization.
	 * @param string $context Context.
	 * @return mixed Sanitized value
	 */
	public static function sanitize_value( $value, $type, $context = 'save' ) {
		switch ( $type ) {
			case 'text':
			case 'string':
				return sanitize_text_field( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'int':
			case 'integer':
				return intval( $value );

			case 'absint':
				return absint( $value );

			case 'float':
				return floatval( $value );

			case 'bool':
			case 'boolean':
				return (bool) $value;

			case 'slug':
				return sanitize_title( $value );

			case 'key':
				return sanitize_key( $value );

			case 'html':
				return wp_kses_post( $value );

			case 'array':
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_map( 'sanitize_text_field', $value );

			case 'json':
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					return is_array( $decoded ) ? $decoded : array();
				}
				return $value;

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Escape output
	 *
	 * @param mixed  $value   Value to escape.
	 * @param string $context Context (html, attr, url, js).
	 * @return string Escaped value
	 */
	public static function escape_output( $value, $context = 'html' ) {
		switch ( $context ) {
			case 'html':
				return esc_html( $value );

			case 'attr':
			case 'attribute':
				return esc_attr( $value );

			case 'url':
				return esc_url( $value );

			case 'js':
			case 'javascript':
				return esc_js( $value );

			case 'textarea':
				return esc_textarea( $value );

			default:
				return esc_html( $value );
		}
	}

	/**
	 * Generate nonce
	 *
	 * @param string $action Action name or key from $nonce_actions.
	 * @return string Nonce
	 */
	public static function create_nonce( $action ) {
		$action_name = self::get_nonce_action( $action );
		return wp_create_nonce( $action_name );
	}

	/**
	 * Verify nonce
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Action name or key.
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action ) {
		$action_name = self::get_nonce_action( $action );
		return wp_verify_nonce( $nonce, $action_name );
	}

	/**
	 * Verify AJAX nonce
	 *
	 * @param string $action   Action name or key.
	 * @param string $nonce_key Nonce key in $_POST (default: '_wpnonce').
	 * @param bool   $die       Whether to die if verification fails.
	 * @return bool
	 */
	public static function verify_ajax_nonce( $action, $nonce_key = '_wpnonce', $die = true ) {
		if ( ! isset( $_POST[ $nonce_key ] ) ) {
			if ( $die ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed: missing nonce.', 'autoblogcraft' ) ) );
			}
			return false;
		}

		$nonce  = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) );
		$action_name = self::get_nonce_action( $action );

		if ( ! wp_verify_nonce( $nonce, $action_name ) ) {
			if ( $die ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed: invalid nonce.', 'autoblogcraft' ) ) );
			}
			return false;
		}

		return true;
	}

	/**
	 * Get nonce action name
	 *
	 * @param string $key Action key or full action name.
	 * @return string Full action name
	 */
	private static function get_nonce_action( $key ) {
		return isset( self::$nonce_actions[ $key ] ) ? self::$nonce_actions[ $key ] : $key;
	}

	/**
	 * Verify admin referer
	 *
	 * @param string $action Action name.
	 * @return bool
	 */
	public static function verify_admin_referer( $action ) {
		$action_name = self::get_nonce_action( $action );
		check_admin_referer( $action_name );
		return true;
	}

	/**
	 * Sanitize POST data
	 *
	 * @param array $rules Sanitization rules.
	 * @return array Sanitized $_POST data
	 */
	public static function sanitize_post( $rules = array() ) {
		if ( empty( $_POST ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = wp_unslash( $_POST );

		return self::sanitize_input( $post_data, $rules );
	}

	/**
	 * Sanitize GET data
	 *
	 * @param array $rules Sanitization rules.
	 * @return array Sanitized $_GET data
	 */
	public static function sanitize_get( $rules = array() ) {
		if ( empty( $_GET ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get_data = wp_unslash( $_GET );

		return self::sanitize_input( $get_data, $rules );
	}

	/**
	 * Prepare SQL value for LIKE query
	 *
	 * @param string $value Value to escape.
	 * @return string Escaped value
	 */
	public static function prepare_like( $value ) {
		global $wpdb;
		return '%' . $wpdb->esc_like( $value ) . '%';
	}

	/**
	 * Validate and sanitize campaign data
	 *
	 * @param array $data Campaign data.
	 * @return array Sanitized campaign data
	 */
	public static function sanitize_campaign_data( $data ) {
		$rules = array(
			'post_title'            => 'text',
			'post_content'          => 'html',
			'campaign_type'         => 'key',
			'status'                => 'key',
			'discovery_interval'    => 'text',
			'source_url'            => 'url',
			'source_urls'           => 'array',
			'rss_feed'              => 'url',
			'category_id'           => 'absint',
			'author_id'             => 'absint',
			'post_status'           => 'key',
			'tags'                  => 'array',
		);

		return self::sanitize_input( $data, $rules );
	}

	/**
	 * Validate and sanitize API key data
	 *
	 * @param array $data API key data.
	 * @return array Sanitized API key data
	 */
	public static function sanitize_api_key_data( $data ) {
		$rules = array(
			'provider'    => 'key',
			'api_key'     => 'text',
			'label'       => 'text',
			'quota_limit' => 'int',
			'is_active'   => 'bool',
		);

		return self::sanitize_input( $data, $rules );
	}

	/**
	 * Validate and sanitize settings data
	 *
	 * @param array $data Settings data.
	 * @return array Sanitized settings data
	 */
	public static function sanitize_settings_data( $data ) {
		$rules = array(
			'max_concurrent_campaigns' => 'absint',
			'max_concurrent_ai_calls'  => 'absint',
			'enable_rate_limiting'     => 'bool',
			'log_level'                => 'key',
			'cleanup_interval'         => 'absint',
			'cache_ttl'                => 'absint',
		);

		return self::sanitize_input( $data, $rules );
	}

	/**
	 * Check if current request is AJAX
	 *
	 * @return bool
	 */
	public static function is_ajax() {
		return wp_doing_ajax();
	}

	/**
	 * Check if current user can manage plugin
	 *
	 * @return bool
	 */
	public static function can_manage_plugin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Require capability or die
	 *
	 * @param string $capability Required capability.
	 * @param string $message    Error message.
	 */
	public static function require_capability( $capability = 'manage_options', $message = '' ) {
		if ( ! current_user_can( $capability ) ) {
			if ( empty( $message ) ) {
				$message = __( 'You do not have sufficient permissions to perform this action.', 'autoblogcraft' );
			}

			if ( self::is_ajax() ) {
				wp_send_json_error( array( 'message' => $message ) );
			} else {
				wp_die( esc_html( $message ) );
			}
		}
	}

	/**
	 * Sanitize file upload
	 *
	 * @param array $file File data from $_FILES.
	 * @return array|WP_Error Sanitized file data or error
	 */
	public static function sanitize_file_upload( $file ) {
		// Check if file was uploaded
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new \WP_Error( 'invalid_upload', __( 'Invalid file upload.', 'autoblogcraft' ) );
		}

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'upload_error', __( 'File upload error.', 'autoblogcraft' ) );
		}

		// Validate file size (10MB max)
		if ( $file['size'] > 10485760 ) {
			return new \WP_Error( 'file_too_large', __( 'File size exceeds maximum (10MB).', 'autoblogcraft' ) );
		}

		// Validate file type
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'text/csv', 'application/json' );
		$finfo         = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type     = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_file_type', __( 'Invalid file type.', 'autoblogcraft' ) );
		}

		// Sanitize filename
		$file['name'] = sanitize_file_name( $file['name'] );

		return $file;
	}

	/**
	 * Log security event
	 *
	 * @param string $event   Event type.
	 * @param string $message Event message.
	 * @param array  $context Additional context.
	 */
	public static function log_security_event( $event, $message, $context = array() ) {
		$logger = \AutoBlogCraft\Core\Logger::instance();

		$context['event_type'] = $event;
		$context['user_id']    = get_current_user_id();
		$context['ip_address'] = self::get_client_ip();
		$context['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$logger->warning( "Security Event: {$event} - {$message}", $context );
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address
	 */
	private static function get_client_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Rate limit security
	 *
	 * Prevents brute force attacks on sensitive operations.
	 *
	 * @param string $key       Transient key.
	 * @param int    $max       Maximum attempts.
	 * @param int    $duration  Duration in seconds.
	 * @return bool True if allowed, false if rate limited
	 */
	public static function check_rate_limit( $key, $max = 5, $duration = 300 ) {
		$transient_key = 'abc_security_rate_' . md5( $key . self::get_client_ip() );
		$attempts      = get_transient( $transient_key );

		if ( false === $attempts ) {
			set_transient( $transient_key, 1, $duration );
			return true;
		}

		if ( $attempts >= $max ) {
			self::log_security_event( 'rate_limit', "Rate limit exceeded for {$key}" );
			return false;
		}

		set_transient( $transient_key, $attempts + 1, $duration );
		return true;
	}
}
