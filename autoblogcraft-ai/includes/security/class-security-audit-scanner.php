<?php
/**
 * Security Audit Scanner
 *
 * Automated security scanner for AutoBlogCraft AI plugin.
 * Detects common security vulnerabilities and generates a detailed report.
 *
 * @package AutoBlogCraft\Security
 * @since 2.0.0
 */

namespace AutoBlogCraft\Security;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Security Audit Scanner Class
 *
 * Scans codebase for:
 * - SQL injection vulnerabilities
 * - XSS vulnerabilities
 * - CSRF vulnerabilities
 * - Input validation issues
 * - Nonce verification issues
 * - Output escaping issues
 *
 * @since 2.0.0
 */
class Security_Audit_Scanner {

	/**
	 * Scan results
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Files scanned
	 *
	 * @var int
	 */
	private $files_scanned = 0;

	/**
	 * Issues found
	 *
	 * @var int
	 */
	private $issues_found = 0;

	/**
	 * Patterns for security issues
	 *
	 * @var array
	 */
	private $patterns = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_patterns();
	}

	/**
	 * Initialize security check patterns
	 */
	private function init_patterns() {
		// SQL Injection Patterns (dangerous practices)
		$this->patterns['sql_injection'] = array(
			'raw_query'              => '/\$wpdb->query\s*\(\s*["\'].*\$/',
			'unescaped_like'         => '/\$wpdb->esc_like\s*\(\s*\$[^)]+\)/',
			'direct_concatenation'   => '/\$wpdb->(?:query|get_results|get_var|get_row)\s*\(\s*["\'].*\.\s*\$/',
			'missing_prepare'        => '/\$wpdb->(?:query|get_results|get_var|get_row)\s*\(\s*["\'][^"\']*%[sd][^"\']*["\'](?!\s*,)/',
		);

		// XSS Patterns (missing output escaping)
		$this->patterns['xss'] = array(
			'echo_variable'          => '/echo\s+\$[a-zA-Z_]/',
			'print_variable'         => '/print\s+\$[a-zA-Z_]/',
			'unescaped_option'       => '/echo\s+get_option\s*\(/',
			'unescaped_post_meta'    => '/echo\s+get_post_meta\s*\(/',
			'unescaped_user_input'   => '/echo\s+\$_(?:GET|POST|REQUEST)\[/',
			'printf_variable'        => '/printf\s*\(\s*\$[a-zA-Z_]/',
		);

		// CSRF Patterns (missing nonce verification)
		$this->patterns['csrf'] = array(
			'post_without_nonce'     => '/if\s*\(\s*\$_POST\s*\)(?!.*wp_verify_nonce)/',
			'get_without_nonce'      => '/if\s*\(\s*\$_GET\[.*action.*\](?!.*wp_verify_nonce)/',
			'request_without_nonce'  => '/if\s*\(\s*\$_REQUEST\s*\)(?!.*wp_verify_nonce)/',
		);

		// Input Validation Patterns
		$this->patterns['input_validation'] = array(
			'unsanitized_post'       => '/\$_POST\[[^\]]+\](?!.*(?:sanitize|intval|absint|floatval))/',
			'unsanitized_get'        => '/\$_GET\[[^\]]+\](?!.*(?:sanitize|intval|absint|floatval))/',
			'unsanitized_request'    => '/\$_REQUEST\[[^\]]+\](?!.*(?:sanitize|intval|absint|floatval))/',
		);

		// Nonce Patterns (missing in AJAX)
		$this->patterns['ajax_nonce'] = array(
			'missing_ajax_nonce'     => '/wp_ajax_[a-zA-Z_]+["\'](?!.*wp_verify_nonce)/',
			'missing_nopriv_nonce'   => '/wp_ajax_nopriv_[a-zA-Z_]+["\'](?!.*wp_verify_nonce)/',
		);
	}

	/**
	 * Run security audit
	 *
	 * @param string $directory Directory to scan.
	 * @return array Audit results
	 */
	public function run_audit( $directory ) {
		echo "Starting Security Audit...\n\n";

		$this->results = array(
			'sql_injection'     => array(),
			'xss'               => array(),
			'csrf'              => array(),
			'input_validation'  => array(),
			'ajax_nonce'        => array(),
			'summary'           => array(),
		);

		$this->scan_directory( $directory );

		$this->generate_summary();

		return $this->results;
	}

	/**
	 * Scan directory recursively
	 *
	 * @param string $directory Directory path.
	 */
	private function scan_directory( $directory ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				// Skip vendor and libraries
				if ( strpos( $file->getPathname(), 'vendor' ) !== false ||
				     strpos( $file->getPathname(), 'libraries' ) !== false ) {
					continue;
				}

				$this->scan_file( $file->getPathname() );
			}
		}
	}

	/**
	 * Scan individual file
	 *
	 * @param string $filepath File path.
	 */
	private function scan_file( $filepath ) {
		$this->files_scanned++;

		$content = file_get_contents( $filepath );
		$lines   = explode( "\n", $content );

		$relative_path = str_replace( ABSPATH, '', $filepath );

		// Check SQL Injection
		$this->check_sql_injection( $relative_path, $content, $lines );

		// Check XSS
		$this->check_xss( $relative_path, $content, $lines );

		// Check CSRF
		$this->check_csrf( $relative_path, $content, $lines );

		// Check Input Validation
		$this->check_input_validation( $relative_path, $content, $lines );

		// Check AJAX Nonce
		$this->check_ajax_nonce( $relative_path, $content, $lines );
	}

	/**
	 * Check for SQL injection vulnerabilities
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 * @param array  $lines   File lines.
	 */
	private function check_sql_injection( $file, $content, $lines ) {
		foreach ( $this->patterns['sql_injection'] as $name => $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_number = $this->get_line_number( $match[1], $content );

					$this->add_issue(
						'sql_injection',
						$file,
						$line_number,
						$name,
						trim( $lines[ $line_number - 1 ] ),
						$this->get_sql_recommendation( $name )
					);
				}
			}
		}
	}

	/**
	 * Check for XSS vulnerabilities
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 * @param array  $lines   File lines.
	 */
	private function check_xss( $file, $content, $lines ) {
		foreach ( $this->patterns['xss'] as $name => $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_number = $this->get_line_number( $match[1], $content );
					$line_content = trim( $lines[ $line_number - 1 ] );

					// Skip if already escaped
					if ( $this->is_already_escaped( $line_content ) ) {
						continue;
					}

					$this->add_issue(
						'xss',
						$file,
						$line_number,
						$name,
						$line_content,
						$this->get_xss_recommendation( $name )
					);
				}
			}
		}
	}

	/**
	 * Check for CSRF vulnerabilities
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 * @param array  $lines   File lines.
	 */
	private function check_csrf( $file, $content, $lines ) {
		foreach ( $this->patterns['csrf'] as $name => $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_number = $this->get_line_number( $match[1], $content );

					$this->add_issue(
						'csrf',
						$file,
						$line_number,
						$name,
						trim( $lines[ $line_number - 1 ] ),
						'Add nonce verification: wp_verify_nonce( $_POST[\'_wpnonce\'], \'action_name\' )'
					);
				}
			}
		}
	}

	/**
	 * Check for input validation issues
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 * @param array  $lines   File lines.
	 */
	private function check_input_validation( $file, $content, $lines ) {
		foreach ( $this->patterns['input_validation'] as $name => $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_number = $this->get_line_number( $match[1], $content );

					$this->add_issue(
						'input_validation',
						$file,
						$line_number,
						$name,
						trim( $lines[ $line_number - 1 ] ),
						'Sanitize input: sanitize_text_field(), intval(), absint(), or sanitize_email()'
					);
				}
			}
		}
	}

	/**
	 * Check for AJAX nonce verification
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 * @param array  $lines   File lines.
	 */
	private function check_ajax_nonce( $file, $content, $lines ) {
		// Only check AJAX handler files
		if ( strpos( $file, 'ajax' ) === false && strpos( $file, 'Admin' ) === false ) {
			return;
		}

		foreach ( $this->patterns['ajax_nonce'] as $name => $pattern ) {
			if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_number = $this->get_line_number( $match[1], $content );

					$this->add_issue(
						'ajax_nonce',
						$file,
						$line_number,
						$name,
						trim( $lines[ $line_number - 1 ] ),
						'Add nonce verification in AJAX handler'
					);
				}
			}
		}
	}

	/**
	 * Add issue to results
	 *
	 * @param string $category       Issue category.
	 * @param string $file           File path.
	 * @param int    $line           Line number.
	 * @param string $type           Issue type.
	 * @param string $code           Code snippet.
	 * @param string $recommendation Recommendation.
	 */
	private function add_issue( $category, $file, $line, $type, $code, $recommendation ) {
		$this->issues_found++;

		$this->results[ $category ][] = array(
			'file'           => $file,
			'line'           => $line,
			'type'           => $type,
			'code'           => $code,
			'recommendation' => $recommendation,
			'severity'       => $this->get_severity( $category ),
		);
	}

	/**
	 * Get line number from offset
	 *
	 * @param int    $offset  Character offset.
	 * @param string $content File content.
	 * @return int Line number
	 */
	private function get_line_number( $offset, $content ) {
		return substr_count( substr( $content, 0, $offset ), "\n" ) + 1;
	}

	/**
	 * Check if output is already escaped
	 *
	 * @param string $line Line content.
	 * @return bool
	 */
	private function is_already_escaped( $line ) {
		$escape_functions = array( 'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses' );

		foreach ( $escape_functions as $func ) {
			if ( strpos( $line, $func ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get SQL injection recommendation
	 *
	 * @param string $type Issue type.
	 * @return string
	 */
	private function get_sql_recommendation( $type ) {
		$recommendations = array(
			'raw_query'            => 'Use $wpdb->prepare() with placeholders: $wpdb->query( $wpdb->prepare( "...", $var ) )',
			'unescaped_like'       => 'Ensure LIKE queries use $wpdb->esc_like() before prepare()',
			'direct_concatenation' => 'Use $wpdb->prepare() instead of concatenation',
			'missing_prepare'      => 'Add parameters to $wpdb->prepare(): $wpdb->prepare( "...%s", $value )',
		);

		return isset( $recommendations[ $type ] ) ? $recommendations[ $type ] : 'Use prepared statements';
	}

	/**
	 * Get XSS recommendation
	 *
	 * @param string $type Issue type.
	 * @return string
	 */
	private function get_xss_recommendation( $type ) {
		$recommendations = array(
			'echo_variable'       => 'Use esc_html( $var ) or esc_attr( $var )',
			'print_variable'      => 'Use esc_html( $var ) instead of print',
			'unescaped_option'    => 'Use esc_html( get_option() )',
			'unescaped_post_meta' => 'Use esc_html( get_post_meta() )',
			'unescaped_user_input' => 'Always escape user input with esc_html() or esc_attr()',
			'printf_variable'     => 'Use esc_html() in printf: printf( esc_html( $format ), $args )',
		);

		return isset( $recommendations[ $type ] ) ? $recommendations[ $type ] : 'Escape output';
	}

	/**
	 * Get severity level
	 *
	 * @param string $category Issue category.
	 * @return string
	 */
	private function get_severity( $category ) {
		$severity_map = array(
			'sql_injection'    => 'CRITICAL',
			'xss'              => 'HIGH',
			'csrf'             => 'HIGH',
			'input_validation' => 'MEDIUM',
			'ajax_nonce'       => 'HIGH',
		);

		return isset( $severity_map[ $category ] ) ? $severity_map[ $category ] : 'LOW';
	}

	/**
	 * Generate summary
	 */
	private function generate_summary() {
		$this->results['summary'] = array(
			'files_scanned'        => $this->files_scanned,
			'total_issues'         => $this->issues_found,
			'critical_issues'      => count( $this->results['sql_injection'] ),
			'high_issues'          => count( $this->results['xss'] ) + count( $this->results['csrf'] ) + count( $this->results['ajax_nonce'] ),
			'medium_issues'        => count( $this->results['input_validation'] ),
			'by_category'          => array(
				'SQL Injection'    => count( $this->results['sql_injection'] ),
				'XSS'              => count( $this->results['xss'] ),
				'CSRF'             => count( $this->results['csrf'] ),
				'Input Validation' => count( $this->results['input_validation'] ),
				'AJAX Nonce'       => count( $this->results['ajax_nonce'] ),
			),
		);
	}

	/**
	 * Print report
	 */
	public function print_report() {
		$summary = $this->results['summary'];

		echo "========================================\n";
		echo "  SECURITY AUDIT REPORT\n";
		echo "========================================\n\n";

		echo "Files Scanned:    {$summary['files_scanned']}\n";
		echo "Total Issues:     {$summary['total_issues']}\n";
		echo "  - Critical:     {$summary['critical_issues']}\n";
		echo "  - High:         {$summary['high_issues']}\n";
		echo "  - Medium:       {$summary['medium_issues']}\n\n";

		echo "Issues by Category:\n";
		foreach ( $summary['by_category'] as $category => $count ) {
			echo "  - {$category}: {$count}\n";
		}

		echo "\n";

		// Print detailed issues
		foreach ( array( 'sql_injection', 'xss', 'csrf', 'input_validation', 'ajax_nonce' ) as $category ) {
			if ( ! empty( $this->results[ $category ] ) ) {
				$this->print_category_issues( $category );
			}
		}

		echo "\n========================================\n";
		echo "  END OF REPORT\n";
		echo "========================================\n";
	}

	/**
	 * Print category issues
	 *
	 * @param string $category Category name.
	 */
	private function print_category_issues( $category ) {
		$title = strtoupper( str_replace( '_', ' ', $category ) );
		echo "\n--- {$title} ISSUES ---\n\n";

		foreach ( $this->results[ $category ] as $issue ) {
			echo "[{$issue['severity']}] {$issue['file']}:{$issue['line']}\n";
			echo "  Type: {$issue['type']}\n";
			echo "  Code: {$issue['code']}\n";
			echo "  Fix:  {$issue['recommendation']}\n\n";
		}
	}

	/**
	 * Export report to file
	 *
	 * @param string $filename Output filename.
	 */
	public function export_report( $filename ) {
		ob_start();
		$this->print_report();
		$report = ob_get_clean();

		file_put_contents( $filename, $report );
		echo "Report exported to: {$filename}\n";
	}
}

// Run audit if executed directly
if ( defined( 'ABSPATH' ) && defined( 'WP_CLI' ) && WP_CLI ) {
	$scanner = new Security_Audit_Scanner();
	$results = $scanner->run_audit( dirname( __DIR__ ) );
	$scanner->print_report();
	$scanner->export_report( dirname( __DIR__, 2 ) . '/Docs/SECURITY-AUDIT-REPORT.txt' );
}
