<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Query Export
 * @see http://codex.bonips.me/classes/bonips_query_export/ 
 * @since 1.7
 * @version 1.0
 */
if ( ! class_exists( 'boniPS_Query_Export' ) ) :
	class boniPS_Query_Export {

		protected $db       = '';

		public $args        = array();
		public $headers     = array();
		public $raw         = false;
		public $file_name   = '';
		public $references  = array();
		public $orderby     = '';
		public $limit       = '';
		public $user_id     = false;

		protected $data     = array();
		protected $types    = array();

		/**
		 * Construct
		 */
		public function __construct( $args = array(), $headers = array() ) {

			global $bonips_log_table;

			$this->args = apply_filters( 'bonips_export_args', shortcode_atts( array(
				'raw'         => false,
				'number'      => -1,
				'order'       => 'time',
				'orderby'     => 'DESC',
				'date_format' => get_option( 'date_format' )
			), $args ), $args );

			$this->db          = $bonips_log_table;
			$this->raw         = $this->args['raw'];
			$this->references  = bonips_get_all_references();

			$this->set_orderby();
			$this->set_limit();
			$this->set_column_headers( $headers );

		}

		/**
		 * Set Export File Name
		 * Sets the file name we will use when we export.
		 * @version 1.0
		 */
		public function set_export_file_name( $name = '' ) {

			$file = mb_ereg_replace( "([^\w\s\d\-_~,;\[\]\(\).])", '', $name );
			$file = mb_ereg_replace( "([\.]{2,})", '', $name );

			if ( $file === NULL || $file === false || strlen( $file ) == 0 )
				$file = 'bonips-export-' . date( $this->args['date_format'], current_time( 'timestamp' ) ) . '.csv';

			$username   = '';
			$point_type = 'default';

			if ( $this->user_id !== false ) {

				$user = get_userdata( $this->user_id );
				if ( isset( $user->user_login ) )
					$username = $user->user_login;

			}

			if ( ! empty( $this->types ) && count( $this->types ) == 1 ) {
				foreach ( $this->types as $type_id => $bonips )
					$point_type = $type_id;
			}

			$file = str_replace( '%username%',   $username, $file );
			$file = str_replace( '%point_type%', $point_type, $file );

			$this->file_name = apply_filters( 'bonips_export_file_name', $file, $name, $this );

		}

		/**
		 * Get Data by IDs
		 * Retreaves log entries based on a set of entry ids.
		 * @version 1.0
		 */
		public function get_data_by_ids( $ids = array() ) {

			$ids = $this->clean_ids( $ids );
			if ( $ids === true || empty( $ids ) || empty( $this->headers ) ) return false;

			global $wpdb;

			$id_list = implode( ', ', $ids );
			$data    = $wpdb->get_results( "SELECT * FROM {$this->db} WHERE id IN ({$id_list}) ORDER BY {$this->orderby} {$this->limit};" );

			$exportable_data = array();
			if ( ! empty( $data ) ) {

				$this->set_point_types( $data );

				foreach ( $data as $entry ) {

					if ( $this->raw )
						$exportable_data[] = $this->get_raw_entry( $entry );
					else
						$exportable_data[] = $this->get_rendered_entry( $entry );

				}

			}
			$this->data = $exportable_data;

			return $exportable_data;

		}

		/**
		 * Get Data by User
		 * Retreaves log entries based on a given user ID.
		 * @version 1.0
		 */
		public function get_data_by_user( $user_id = NULL ) {

			$user_id = absint( $user_id );
			if ( $user_id === 0 ) return false;

			global $wpdb;

			$this->user_id = $user_id;
			$data          = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->db} WHERE user_id = %d ORDER BY {$this->orderby} {$this->limit};", $user_id ) );

			$exportable_data = array();
			if ( ! empty( $data ) ) {

				$this->set_point_types( $data );

				foreach ( $data as $entry ) {

					if ( $this->raw )
						$exportable_data[] = $this->get_raw_entry( $entry );
					else
						$exportable_data[] = $this->get_rendered_entry( $entry );

				}

			}
			$this->data = $exportable_data;

			return $exportable_data;

		}

		/**
		 * Get Data by Type
		 * Retreaves log entries based on a given point type.
		 * @version 1.0
		 */
		public function get_data_by_type( $point_type = NULL ) {

			$point_type = sanitize_key( $point_type );
			if ( ! bonips_point_type_exists( $point_type ) ) return false;

			global $wpdb;

			$data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->db} WHERE ctype = %s ORDER BY {$this->orderby} {$this->limit};", $point_type ) );

			$exportable_data = array();
			if ( ! empty( $data ) ) {

				$types = array();
				$types[ $point_type ] = bonips( $point_type );
				$this->types = $types;

				foreach ( $data as $entry ) {

					if ( $this->raw )
						$exportable_data[] = $this->get_raw_entry( $entry );
					else
						$exportable_data[] = $this->get_rendered_entry( $entry );

				}

			}
			$this->data = $exportable_data;

			return $exportable_data;

		}

		/**
		 * Get Data by Query
		 * Retreaves log entries based on an array of query arguments.
		 * @version 1.0
		 */
		public function get_data_by_query( $args = array() ) {

			$log = new boniPS_Query_Log( $args );

			if ( $log->have_entries() )
				$this->data = $log->results;

			$log->reset_query();

		}

		/**
		 * Set Orderby
		 * Converts a valid set of order arguments into the order arguments the export
		 * db queries will use.
		 * @version 1.0
		 */
		protected function set_orderby() {

			$default = 'time DESC';
			$order   = sanitize_key( $this->args['order'] );
			$by      = sanitize_text_field( $this->args['orderby'] );

			if ( ! in_array( $order, array( 'id', 'time', 'user_id', 'creds', 'ctype', 'entry', 'data', 'ref', 'ref_id' ) ) )
				$order = 'time';

			if ( ! in_array( $by, array( 'ASC', 'DESC' ) ) )
				$by = 'DESC';

			$orderby = $order . ' ' . $by;
			if ( strlen( $orderby ) === 1 )
				$orderby = $default;

			$this->orderby = apply_filters( 'bonips_export_orderby', $orderby, $this->args );

		}

		/**
		 * Set Limit
		 * Sets the limit argument to be used in the db queries based on the argument we gave.
		 * @version 1.0
		 */
		protected function set_limit() {

			$number = (int) sanitize_text_field( $this->args['number'] );
			if ( $number > 0 )
				$number = absint( $number );

			if ( $number > 0 )
				$this->limit = 'LIMIT 0,' . $number;

		}

		/**
		 * Set Column Headers
		 * Sets a valid set of column headers for the export.
		 * @version 1.0
		 */
		protected function set_column_headers( $headers = array() ) {

			if ( empty( $headers ) || ! is_array( $headers ) )
				$headers = array( 'ref' => __( 'Reference', 'bonips' ), 'ref_id' => __( 'Reference ID', 'bonips' ), 'user_id' => __( 'User', 'bonips' ), 'creds' => __( 'Amount', 'bonips' ), 'ctype' => __( 'Point Type', 'bonips' ), 'time' => __( 'Date', 'bonips' ), 'entry' => __( 'Entry', 'bonips' ), 'data' => __( 'Data', 'bonips' ) );

			if ( ! $this->raw ) {
				unset( $headers['ref_id'] );
				unset( $headers['data'] );
			}

			$headers = apply_filters( 'bonips_export_headers', $headers, $this->raw );

			if ( $this->raw )
				$this->headers = array_keys( $headers );

			else
				$this->headers = array_values( $headers );

		}

		/**
		 * Clean IDs
		 * Sanitization function for array of entry ids. Also eliminates duplicates.
		 * @returns array of intregers or false
		 * @version 1.0
		 */
		protected function clean_ids( $data = array() ) {

			if ( empty( $data ) ) return false;

			$clean_ids = array();
			foreach ( $data as $unknown_id ) {
				$abs_int     = absint( $unknown_id );
				if ( $abs_int === 0 || in_array( $abs_int, $clean_ids ) ) continue;
				$clean_ids[] = $abs_int;
			}

			return $clean_ids;

		}

		/**
		 * Set Point Types
		 * Populates $this->types with all the point types found int the data.
		 * @version 1.0
		 */
		protected function set_point_types( $data ) {

			$types = array();
			foreach ( $data as $entry ) {

				if ( isset( $entry->ctype ) && sanitize_text_field( $entry->ctype ) !== '' && ! array_key_exists( $entry->ctype, $types ) )
					$types[ $entry->ctype ] = bonips( $entry->ctype );

			}

			$this->types = $types;

		}

		/**
		 * Get Raw Entry
		 * Returns the values for all columns in the current export row in raw format.
		 * @version 1.0
		 */
		protected function get_raw_entry( $entry ) {

			$row = array();
			if ( ! empty( $this->headers ) ) {
				foreach ( $this->headers as $header_id ) {

					$value = '';
					if ( isset( $entry->$header_id ) )
						$value = $entry->$header_id;

					$row[ $header_id ] = $value;

				}
			}

			return $row;

		}

		/**
		 * Get Rendered Entry
		 * Returns the values for all columns in the current export row formatted.
		 * @version 1.0
		 */
		protected function get_rendered_entry( $entry ) {

			$row  = array();
			$type = $entry->ctype;

			if ( ! empty( $this->headers ) ) {
				foreach ( $this->headers as $header_id ) {

					switch ( $header_id ) {

						case 'ref' :
						case __( 'Reference', 'bonips' ) :

							$content = '';
							if ( array_key_exists( $entry->ref, $this->references ) )
								$content = $this->references[ $entry->ref ];

							$content = apply_filters( 'bonips_log_ref', $content, $entry->ref, $entry );

						break;

						case 'user_id' :
						case __( 'User', 'bonips' ) :

							$user         = get_userdata( $entry->user_id );
							$display_name = '<span>' . __( 'User Missing', 'bonips' ) . ' (ID: ' . $entry->user_id . ')</span>';
							if ( isset( $user->display_name ) )
								$display_name = $user->display_name;

							$content = apply_filters( 'bonips_log_username', $display_name, $entry->user_id, $entry );

						break;

						case 'creds' :
						case __( 'Amount', 'bonips' ) :

							$content = $this->types[ $type ]->format_creds( $entry->creds );
							$content = apply_filters( 'bonips_log_creds', $content, $entry->creds, $entry );

						break;

						case 'ctype' :
						case __( 'Point Type', 'bonips' ) :

							$content = $this->types[ $type ]->plural();
							$content = apply_filters( 'bonips_log_ctype', $content, $entry->ctype, $entry );

						break;

						case 'time' :
						case __( 'Date', 'bonips' ) :

							$content = apply_filters( 'bonips_log_date', date( $this->args['date_format'], $entry->time ), $entry->time, $entry );

						break;

						case 'entry' :
						case __( 'Entry', 'bonips' ) :

							$content = $this->types[ $type ]->parse_template_tags( $entry->entry, $entry );
							$content = apply_filters( 'bonips_log_entry', $content, $entry->entry, $entry );

						break;

						// Let others play
						default :

							$content = apply_filters( 'bonips_log_' . $header_id, '', $entry );

						break;

					}

					$row[ $header_id ] = $content;

				}
			}

			return $row;

		}

		/**
		 * Do Export
		 * If data is available for export, we run the export tool.
		 * @version 1.0.1
		 */
		public function do_export() {

			if ( empty( $this->data ) ) return false;

			// Load parseCSV
			if ( ! class_exists( 'parseCSV' ) )
				require_once boniPS_ASSETS_DIR . 'libs/parsecsv.lib.php';

			$csv = new parseCSV();
			$csv->output( true, $this->file_name, $this->data, $this->headers );

			exit;

		}

	}
endif;

/**
 * Get Export Formats
 * Returns an arry of supported formats.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_export_formats' ) ) :
	function bonips_get_export_formats() {

		return apply_filters( 'bonips_export_formats', array(
			'raw'       => __( 'Export log entries raw', 'bonips' ),
			'formatted' => __( 'Export log entries formatted', 'bonips' )
		) );

	}
endif;

/**
 * Get Log Exports
 * Returns an associative array of log export options.
 * @since 1.4
 * @version 1.2
 */
if ( ! function_exists( 'bonips_get_log_exports' ) ) :
	function bonips_get_log_exports() {

		$defaults = array(
			'all'      => array(
				'label'    => __( 'Alle Protokolleinträge', 'bonips' ),
				'my_label' => NULL,
				'class'    => 'btn btn-primary button button-primary'
			),
			'search'   => array(
				'label'    => __( 'Suchergebnisse', 'bonips' ),
				'my_label' => NULL,
				'class'    => 'btn btn-primary button button-secondary'
			),
			'user'     => array(
				'label'    => __( 'Benutzerprotokolleinträge', 'bonips' ),
				'my_label' => __( 'Verlauf exportieren', 'bonips' ),
				'class'    => 'btn btn-primary button button-secondary'
			)
		);

		return apply_filters( 'bonips_log_exports', $defaults );

	}
endif;

/**
 * Get Export URL
 * Returns the URL for triggering an export (if allowed).
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_export_url' ) ) :
	function bonips_get_export_url( $set = 'all', $raw = false ) {

		$export_url = false;
		$is_admin   = ( ( function_exists( 'is_admin' ) && is_admin() ) ? true : false );

		if ( ! $is_admin ) {

			global $wp;
			$current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request . '/' ) );

		}
		else {

			$current_url = admin_url( 'admin.php' );

		}

		if ( is_user_logged_in() ) {

			$args = array();

			if ( $is_admin ) {

				if ( isset( $_GET['page'] ) )
					$args['page'] = $_GET['page'];

				$args['bonips-action'] = 'export';
				$args['_token']        = wp_create_nonce( 'bonips-export-request-admin' );

			}
			else {

				$args['bonips-action'] = 'export';
				$args['_token']        = wp_create_nonce( 'bonips-export-request' );

			}

			$args['set'] = sanitize_key( $set );

			if ( $raw )
				$args['raw'] = 'export-raw';

			$export_url = add_query_arg( $args, $current_url );

		}

		return apply_filters( 'bonips_get_export_url', $export_url, $set, $is_admin );

	}
endif;

/**
 * Is Valid Export URL
 * Checks if a valid export URL is present.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_is_valid_export_url' ) ) :
	function bonips_is_valid_export_url( $admin = false ) {

		$valid = false;
		$token = 'bonips-export-request';
		if ( $admin )
			$token = 'bonips-export-request-admin';

		if ( is_user_logged_in() ) {

			if ( isset( $_REQUEST['bonips-action'] ) && isset( $_REQUEST['_token'] ) && substr( $_REQUEST['bonips-action'], 0, 6 ) === 'export' ) {

				if ( wp_verify_nonce( $_REQUEST['_token'], $token ) )
					$valid = true;

			}

		}

		return apply_filters( 'bonips_is_valid_export_url', $valid, $admin );

	}
endif;
