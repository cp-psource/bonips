<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Query Statistics
 * @see http://codex.bonips.me/classes/bonips_query_stats/ 
 * @since 1.7
 * @version 1.0
 */
if ( ! class_exists( 'boniPS_Query_Stats' ) ) :
	class boniPS_Query_Stats {

		protected $db = '';

		/**
		 * Construct
		 */
		public function __construct() {

			global $bonips;

			$this->db = $bonips->log_table;

		}

	}
endif;
