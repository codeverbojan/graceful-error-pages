<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads Composer autoloader and sets up Brain\Monkey.
 * Does NOT load WordPress — unit tests run with WP stubs only.
 *
 * @package GracefulErrorPages\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'GCEP_VERSION' ) ) {
	define( 'GCEP_VERSION', '1.0.0' );
}
if ( ! defined( 'GCEP_FILE' ) ) {
	define( 'GCEP_FILE', dirname( __DIR__ ) . '/graceful-error-pages.php' );
}
if ( ! defined( 'GCEP_DIR' ) ) {
	define( 'GCEP_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GCEP_URL' ) ) {
	define( 'GCEP_URL', 'https://example.com/wp-content/plugins/graceful-error-pages/' );
}

require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Minimal wpdb stub for unit tests.
if ( ! class_exists( 'wpdb' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch
	class wpdb {
		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public string $prefix = 'wp_';

		/**
		 * Last error message.
		 *
		 * @var string|null
		 */
		public ?string $last_error = null;

		/**
		 * Last insert ID.
		 *
		 * @var int|null
		 */
		public ?int $insert_id = null;

		/**
		 * Prepare a SQL query.
		 *
		 * @param string $query SQL query with placeholders.
		 * @param mixed  ...$args Values to substitute.
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			return $query;
		}

		/**
		 * Insert a row.
		 *
		 * @param string     $table  Table name.
		 * @param array      $data   Column data.
		 * @param array|null $format Data formats.
		 * @return int|false
		 */
		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		/**
		 * Update rows.
		 *
		 * @param string     $table        Table name.
		 * @param array      $data         Column data.
		 * @param array      $where        Where clause.
		 * @param array|null $format       Data formats.
		 * @param array|null $where_format Where formats.
		 * @return int|false
		 */
		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		/**
		 * Delete rows.
		 *
		 * @param string     $table        Table name.
		 * @param array      $where        Where clause.
		 * @param array|null $where_format Where formats.
		 * @return int|false
		 */
		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}

		/**
		 * Get a single row.
		 *
		 * @param string|null $query  SQL query.
		 * @param string      $output Output type.
		 * @param int         $y      Row offset.
		 * @return object|array|null
		 */
		public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
			return null;
		}

		/**
		 * Get results.
		 *
		 * @param string $query  SQL query.
		 * @param string $output Output type.
		 * @return array|null
		 */
		public function get_results( $query, $output = OBJECT ) {
			return [];
		}

		/**
		 * Get a single value.
		 *
		 * @param string|null $query SQL query.
		 * @param int         $x     Column offset.
		 * @param int         $y     Row offset.
		 * @return string|null
		 */
		public function get_var( $query = null, $x = 0, $y = 0 ) {
			return null;
		}

		/**
		 * Run a query.
		 *
		 * @param string $query SQL query.
		 * @return int|bool
		 */
		public function query( $query ) {
			return true;
		}

		/**
		 * Escape for LIKE.
		 *
		 * @param string $text Text to escape.
		 * @return string
		 */
		public function esc_like( $text ) {
			return addcslashes( $text, '_%\\' );
		}
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	/**
	 * Stub for wp_doing_cron() — returns false in tests.
	 *
	 * @return bool
	 */
	function wp_doing_cron(): bool {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}

// WordPress constants used by $wpdb methods.
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

// Minimal WP_List_Table stub for unit tests.
if ( ! class_exists( 'WP_List_Table' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch
	abstract class WP_List_Table {
		/**
		 * Items.
		 *
		 * @var array
		 */
		public array $items = [];

		/**
		 * Constructor.
		 *
		 * @param array $args Arguments.
		 */
		public function __construct( $args = [] ) {
		}

		/**
		 * Get columns.
		 *
		 * @return array
		 */
		abstract public function get_columns();
	}
}

// Minimal WP_Error stub for unit tests.
if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch
	class WP_Error {
		/**
		 * Error storage: code => [ messages ].
		 *
		 * @var array<string, array<string>>
		 */
		private array $errors = [];

		/**
		 * Error data storage: code => data.
		 *
		 * @var array<string, mixed>
		 */
		private array $error_data = [];

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->add( $code, $message, $data );
			}
		}

		/**
		 * Add an error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 * @return void
		 */
		public function add( string $code, string $message, $data = '' ): void {
			$this->errors[ $code ][] = $message;
			if ( '' !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		/**
		 * Get first error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return ! empty( $codes ) ? $codes[0] : '';
		}

		/**
		 * Get first error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			$code = $this->get_error_code();
			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		/**
		 * Get all error messages across all codes.
		 *
		 * @return array<string>
		 */
		public function get_error_messages(): array {
			$messages = [];
			foreach ( $this->errors as $code_messages ) {
				$messages = array_merge( $messages, $code_messages );
			}
			return $messages;
		}

		/**
		 * Get error data for a code.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			$code = $this->get_error_code();
			return $this->error_data[ $code ] ?? '';
		}
	}
}

// Minimal WP_REST_Response stub for unit tests.
if ( ! class_exists( 'WP_REST_Response' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * HTTP status code.
		 *
		 * @var int
		 */
		private int $status;

		/**
		 * Response headers.
		 *
		 * @var array
		 */
		private array $headers = [];

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Get response data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Get status code.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * Set a header.
		 *
		 * @param string $key   Header key.
		 * @param string $value Header value.
		 * @return void
		 */
		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/**
		 * Get all headers.
		 *
		 * @return array
		 */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}

// Minimal WP_REST_Server stub for unit tests.
if ( ! class_exists( 'WP_REST_Server' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT, PATCH';
		const DELETABLE = 'DELETE';
	}
}
