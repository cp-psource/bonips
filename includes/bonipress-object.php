<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Account class
 * @see http://codex.bonipress.me/objects/bonipress_account/
 * @since 1.7
 * @version 1.1
 */
if ( ! class_exists( 'boniPRESS_Account' ) ) :
	class boniPRESS_Account extends boniPRESS_Object {

		/**
		 * User ID
		 */
		public $user_id       = false;

		/**
		 * Available Point Types
		 * The point types that are available for the given user.
		 */
		public $point_types   = array();

		/**
		 * Balance
		 * The users balances.
		 */
		public $balance       = false;

		/**
		 * Total Balance
		 * The users total balance where all balances are added up into one.
		 */
		public $total_balance = 0;

		/**
		 * Construct
		 */
		public function __construct( $user_id = NULL ) {

			parent::__construct();

			if ( $user_id === NULL )
				$user_id = get_current_user_id();

			$user_id           = absint( $user_id );
			if ( $user_id === 0 ) return false;

			$this->set( 'user_id', $user_id );

			$this->populate();

		}

		/**
		 * Populate this object
		 */
		protected function populate( $fresh = false ) {

			$this->balance = array();

			$point_types   = bonipress_get_types();
			$total_balance = 0;

			if ( ! empty( $point_types ) ) {
				foreach ( $point_types as $type_id => $label ) {

					$bonipress = bonipress( $type_id );

					if ( $bonipress->exclude_user( $this->user_id ) ) {
						$this->balance[ $type_id ] = false;
						continue;
					}

					$balance                   = $this->get_balance( $type_id );

					$this->balance[ $type_id ] = $balance;
					$this->point_types[]       = $type_id;

					$total_balance            += $balance->current;

				}
			}

			$this->total_balance = $total_balance;

		}

		/**
		 * Get a users balance object
		 */
		public function get_balance( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			if ( ! empty( $this->balance ) && array_key_exists( $point_type, $this->balance ) )
				$balance = $this->balance[ $point_type ];

			else
				$balance = new boniPRESS_Balance( $this->user_id, $point_type );

			return $balance;

		}

	}
endif;

/**
 * boniPRESS_Balance class
 * @see http://codex.bonipress.me/objects/bonipress_balance/
 * @since 1.7
 * @version 1.1
 */
if ( ! class_exists( 'boniPRESS_Balance' ) ) :
	class boniPRESS_Balance extends boniPRESS_Object {

		/**
		 * Users Current Balance for this point type
		 */
		public $current     = 0;

		/**
		 * Users Accumilated Balance for this point type
		 * also known as the users "total balance".
		 */
		public $accumulated = 0;

		/**
		 * The point type object
		 */
		public $point_type  = false;

		/**
		 * Construct
		 */
		public function __construct( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct();

			if ( $user_id === NULL )
				$user_id = get_current_user_id();

			$user_id    = absint( $user_id );
			if ( $user_id === 0 ) return false;

			$point_type = sanitize_key( $point_type );

			$this->populate( $user_id, $point_type );

		}

		/**
		 * Populate this object
		 */
		protected function populate( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			$bonipress = bonipress( $point_type );

			$this->set( 'current',     $bonipress->get_users_balance( $user_id ) );
			$this->set( 'accumulated', $bonipress->get_users_total_balance( $user_id ) );

			$this->set( 'point_type',  new boniPRESS_Point_Type( $point_type ) );

		}

	}
endif;

/**
 * boniPRESS_Point_Type class
 * @see http://codex.bonipress.me/objects/bonipress_point_type/
 * @since 1.7
 * @version 1.1
 */
if ( ! class_exists( 'boniPRESS_Point_Type' ) ) :
	class boniPRESS_Point_Type extends boniPRESS_Object {

		/**
		 * The point type id
		 */
		public $cred_id  = '';

		/**
		 * The point types name in Singular
		 */
		public $singular = '';

		/**
		 * The point types name in Plural
		 */
		public $plural   = '';

		/**
		 * The point types prefix
		 */
		public $prefix   = '';

		/**
		 * The point types suffix
		 */
		public $suffix   = '';

		/**
		 * The point types format settings
		 */
		public $format   = array();

		/**
		 * The lowest value
		 * This is the lowest point value that can be processed based on the
		 * format setup.
		 */
		public $lowest_value = 1;

		/**
		 * The SQL Format
		 */
		public $sql_format   = '%d';

		/**
		 * Construct
		 */
		function __construct( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			$point_type   = sanitize_key( $point_type );

			$this->populate( $point_type );

		}

		/**
		 * Populate this object
		 */
		protected function populate( $type_id = BONIPS_DEFAULT_TYPE_KEY ) {

			$bonipress = bonipress( $type_id );

			$this->set( 'cred_id',      $type_id );
			$this->set( 'singular',     $bonipress->singular() );
			$this->set( 'plural',       $bonipress->plural() );
			$this->set( 'prefix',       $bonipress->before );
			$this->set( 'suffix',       $bonipress->after );
			$this->set( 'format',       $bonipress->format );
			$this->set( 'sql_format',   '%d' );

			if ( $this->format['decimals'] > 0 )
				$this->sql_format = 'CAST( %f AS DECIMAL( ' . ( 65 - $this->format['decimals'] ) . ', ' . $this->format['decimals'] . ' ) )';

			$this->set( 'lowest_value', $this->get_lowest_value(), 1 );

		}

		/**
		 * Get Lowest Value
		 * Returns the lowest point value we can handle based on this point type's setup.
		 */
		public function get_lowest_value() {

			$lowest   = 1;
			$decimals = $this->format['decimals'] - 1;

			if ( $decimals > 0 ) {

				$lowest = '0.' . str_repeat( '0', $decimals ) . '1';
				$lowest = (float) $lowest;

			}

			return $lowest;

		}

		/**
		 * Number
		 * Formats a given string into a proper value based on the point type's setup.
		 */
		public function number( $number = '' ) {

			$number = str_replace( '+', '', $number );

			if ( ! isset( $this->format['decimals'] ) )
				$decimals = (int) $this->core['format']['decimals'];

			else
				$decimals = (int) $this->format['decimals'];

			$result = intval( $number );
			if ( $decimals > 0 )
				$result = floatval( number_format( (float) $number, $decimals, '.', '' ) );

			return apply_filters( 'bonipress_type_number', $result, $number, $this );

		}

		/**
		 * Format
		 * Formats a given value based on the point type's setup.
		 */
		public function format( $number = '' ) {

			$number   = $this->number( $number );
			$decimals = $this->format['decimals'];
			$sep_dec  = $this->format['separators']['decimal'];
			$sep_tho  = $this->format['separators']['thousand'];

			// Format
			$number = number_format( $number, (int) $decimals, $sep_dec, $sep_tho );

			$prefix = '';
			if ( ! empty( $this->prefix ) )
				$prefix = $this->prefix . ' ';

			// Suffix
			$suffix = '';
			if ( ! empty( $this->suffix ) )
				$suffix = ' ' . $this->suffix;

			return apply_filters( 'bonipress_type_format', $prefix . $number . $suffix, $number, $this );

		}

	}
endif;

/**
 * boniPRESS_History class
 * @see http://codex.bonipress.me/objects/bonipress_history-2/
 * @since 1.8
 * @version 1.0
 */
if ( ! class_exists( 'boniPRESS_History' ) ) :
	class boniPRESS_History extends boniPRESS_Object {

		/**
		 * Indicates if the user has any history entries.
		 */
		public $has_history = false;

		/**
		 * Indicates if the given users actions should be logged or not.
		 */
		public $should_log  = true;

		/**
		 * Verlauf data
		 */
		public $data        = array();

		/**
		 * Total log entries based on $data
		 */
		public $total_data  = 0;

		/**
		 * Construct
		 */
		public function __construct( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct();

			if ( $user_id === NULL )
				$user_id = get_current_user_id();

			$user_id    = absint( $user_id );
			if ( $user_id === 0 ) return false;

			$this->populate( $user_id, $point_type );

		}

		/**
		 * Populate this object
		 */
		protected function populate( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			$this->data = bonipress_get_users_history( $user_id, $point_type );
			if ( ! empty( $this->data ) ) {

				foreach ( $this->data as $reference => $data )
					$this->total_data += $data->rows;

				if ( $this->total_data > 0 )
					$this->has_history = true;

			}

		}

		/**
		 * Update history data
		 */
		public function update_data( $reference = '', $value = '' ) {

			if ( empty( $reference ) || ! is_object( $value ) ) return false;

			$this->data[ $reference ] = $value;

		}

	}
endif;
