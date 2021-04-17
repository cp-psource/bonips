<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Query Statistics
 * @see http://codex.bonipress.me/classes/bonipress_query_stats/ 
 * @since 1.7
 * @version 1.0
 */
if ( ! class_exists( 'boniPRESS_Query_Stats' ) ) :
	class boniPRESS_Query_Stats {

		protected $db = '';

		/**
		 * Construct
		 */
		public function __construct() {

			global $bonipress;

			$this->db = $bonipress->log_table;

		}

	}
endif;
