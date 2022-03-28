<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Object class
 * @since 1.7
 * @version 1.1
 */
if ( ! class_exists( 'boniPS_Object' ) ) :
	abstract class boniPS_Object {

		/**
		 * Construct
		 */
		function __construct() { }

		/**
		 * Get
		 */
		public function get( $name = '', $nothing = false ) {

			if ( $name == '' ) return $nothing;

			$value = $nothing;
			if ( is_array( $name ) && ! empty( $name ) ) {

				foreach ( $name as $key => $array_value ) {

					// Example 1: array( 'balance' => 'bonips_default' )
					// $this->balance['bonips_default']
					if ( isset( $this->$key ) ) {

						if ( $array_value != '' && is_array( $this->$key ) && ! empty( $this->$key ) && array_key_exists( $array_value, $this->$key ) )
							$value = $this->$key[ $array_value ];

					}

					// Example 2: array( 'total' )
					// $this->total
					elseif ( isset( $this->$array_value ) )
						$value = $this->$array_value;

				}

			}
			elseif ( ! is_array( $name ) && ! empty( $name ) ) {

				if ( isset( $this->$name ) )
					$value = $this->$name;

			}

			return $value;

		}

		/**
		 * Set
		 */
		public function set( $name = '', $new_value = false ) {

			if ( $name == '' ) return false;

			if ( is_array( $name ) && ! empty( $name ) ) {

				foreach ( $name as $key => $array_value ) {

					// Example 1: array( 'balance' => 'bonips_default' )
					// $this->balance['bonips_default']
					if ( isset( $this->$key ) ) {

						if ( $array_value != '' ) {

							if ( ! is_array( $this->$key ) )
								$this->$key = array();
						
							$this->$key[ $array_value ] = $new_value;

						}

					}

					// Example 2: array( 'total' )
					// $this->total
					elseif ( isset( $this->$array_value ) )
						$this->$value = $new_value;

				}

			}
			elseif ( ! is_array( $name ) && ! empty( $name ) && isset( $this->$name ) ) {

				$this->$name = $new_value;

			}

			return true;

		}

	}
endif;
