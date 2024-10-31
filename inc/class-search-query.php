<?php
class Press_Search_Query {
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action(
			'init',
			function() {
				$this->get_post_exclusion_from_setting();
			}
		);
	}

	/**
	 * Search post title and redirect if has single template
	 *
	 * @return void
	 */
	public function search_title_sql() {
	
	}

	/**
	 * Auto redirect if has setting redirect
	 *
	 * @param array $custom_redirect_settings
	 * @return void
	 */
	public function search_auto_redirect( $custom_redirect_settings = array() ) {
	
	}

	public function maybe_add_synonyms_keywords( $origin_keywords = '' ) {
		if ( ! is_array( $origin_keywords ) ) {
			$origin_keywords = press_search_string()->explode_keywords( $origin_keywords );
		}
		$synonyms_settings = press_search_get_setting( 'synonymns', '' );
		$synonyms_settings = explode( PHP_EOL, $synonyms_settings );
		$synonyms_settings = array_map( 'trim', $synonyms_settings );
		$synonyms = array();
		foreach ( $synonyms_settings as $synonym ) {
			$split_words = explode( '=', $synonym );
			if ( isset( $split_words[0] ) && isset( $split_words[1] ) ) {
				$synonyms[ $split_words[0] ][] = $split_words[1];
				$synonyms[ $split_words[1] ][] = $split_words[0];
			}
		}
		foreach ( $origin_keywords as $keyword ) {
			if ( array_key_exists( $keyword, $synonyms ) ) {
				$origin_keywords = array_merge( $origin_keywords, $synonyms[ $keyword ] );
			}
		}
		return $origin_keywords;
	}

	public function search_index_sql_group_by_posttype( $keywords = '', $engine_slug = 'engine_default', $extra_args = array() ) {
		$query = array();
		$engine_settings = array();
		$db_engine_settings = press_search_engines()->get_engine_settings();
		if ( isset( $db_engine_settings[ $engine_slug ] ) ) {
			$engine_settings = $db_engine_settings[ $engine_slug ];
		}

		$engine_posttype = apply_filters( 'press_search_engine_settings', $engine_settings, $engine_slug );
		if ( isset( $engine_settings['post_type'] ) && ! empty( $engine_settings['post_type'] ) ) {
			$engine_posttype = apply_filters( 'press_search_engine_post_type', $engine_settings['post_type'], $engine_slug );
			foreach ( $engine_posttype as $k => $post_type ) {
				$post_type = "post_{$post_type}";
				$args = array(
					'post_type_condition' => array(
						'compare'   => '=',
						'value'     => $post_type,
					),
				);
				$sql = $this->search_index_sql( $keywords, $engine_slug, $args, $extra_args );
				$query[ $post_type ] = $sql;
			}
		}
		return $query;
	}

	public function search_index_sql( $keywords = '', $engine_slug = 'engine_default', $args = array(), $extra_args = array() ) {
		global $wpdb;
	

		$engine_settings = array();
		$db_engine_settings = press_search_engines()->get_engine_settings();
		$table_index_name = press_search_get_var( 'tbl_index' );
		if ( array_key_exists( $engine_slug, $db_engine_settings ) ) {
			$engine_settings = $db_engine_settings[ $engine_slug ];
		}
		$default_operator = press_search_get_var( 'default_operator' );
		$searching_weight = press_search_get_var( 'default_searching_weights' );
		if ( isset( $engine_settings['default_operator'] ) && ! empty( $engine_settings['default_operator'] ) ) {
			$default_operator = $engine_settings['default_operator'];
		}
		if ( isset( $engine_settings['searching_weight'] ) && ! empty( $engine_settings['searching_weight'] ) ) {
			$searching_weight = $engine_settings['searching_weight'];
		}

		$search_keywords = $keywords;
		if ( ! is_array( $keywords ) ) {
			$search_keywords = press_search_string()->explode_keywords( $keywords );
		}

		// Make sure all keywords .
		$search_keywords = array_map( 'esc_sql', $search_keywords );

		$has_post_query = false;
		$where_object_type_in = '';
		if ( isset( $engine_settings['post_type'] ) && ! empty( $engine_settings['post_type'] ) && isset( $args['post_type_condition'] ) ) {
			$compare = ( isset( $args['post_type_condition']['compare'] ) ) ? $args['post_type_condition']['compare'] : '=';
			$value = ( isset( $args['post_type_condition']['value'] ) ) ? $args['post_type_condition']['value'] : '';
			$where_object_type_in = " AND i1.object_type {$compare} '{$value}' ";
			$has_post_query = true;
		} elseif ( isset( $engine_settings['post_type'] ) && ! empty( $engine_settings['post_type'] ) ) {
			foreach ( $engine_settings['post_type'] as $k => $post_type ) {
				$engine_settings['post_type'][ $k ] = "post_{$post_type}";
			}
			$post_type_in = implode( "', '", $engine_settings['post_type'] );
			$where_object_type_in = " AND i1.object_type IN ( '{$post_type_in}' )";
			$has_post_query = true;
		}

		$c_weight             = array();
		$sql                  = 'SELECT SQL_CALC_FOUND_ROWS i1.object_id,';
		$keyword_like         = array();
		$keyword_reverse_like = array();

		$sql_group_by = ' GROUP BY i1.object_id';

		$post_in_terms = array();
		if ( isset( $extra_args['posts_in_terms'] ) && is_array( $extra_args['posts_in_terms'] ) && ! empty( $extra_args['posts_in_terms'] ) ) {
			$post_in_terms = $extra_args['posts_in_terms'];
		}
		$left_join       = array();
		$left_join_post = '';
		$post_in_terms_leftjoin = '';
		$post_in_terms_where = '';
		if ( $has_post_query ) {
			$left_join_post = " LEFT JOIN {$wpdb->posts} AS post ON ( i1.object_id = post.ID AND i1.object_type LIKE 'post_%' ) ";
		}

		if ( is_array( $post_in_terms ) && ! empty( $post_in_terms ) ) {
			if ( count( $post_in_terms ) !== count( $post_in_terms, COUNT_RECURSIVE ) ) {
				$__post_in_terms_leftjoin = array();
				$__post_in_terms_where = array();
				foreach ( $post_in_terms as $_index => $__term_in ) {
					$__post_in_terms_leftjoin[] = " LEFT JOIN {$wpdb->term_relationships} AS tr_{$_index} ON (i1.`object_id` = tr_{$_index}.object_id)";
					$__post_in_terms_where[] = " tr_{$_index}.term_taxonomy_id IN (" . implode( ', ', $__term_in ) . ') ';
				}
				$post_in_terms_leftjoin = implode( ' ', $__post_in_terms_leftjoin );
				$post_in_terms_where = ' AND ( ' . implode( strtoupper( $default_operator ), $__post_in_terms_where ) . ' ) ';
			} else {
				$post_in_terms_leftjoin = " LEFT JOIN {$wpdb->term_relationships} ON (i1.`object_id` = {$wpdb->term_relationships}.object_id)";
				$post_in_terms_where = " AND ( {$wpdb->term_relationships}.term_taxonomy_id IN (" . implode( ', ', $post_in_terms ) . ') )';
			}
		}

		if ( $has_post_query ) {
			$left_join[] = $left_join_post;
			$orignal_keywords = wp_unslash( get_query_var( 's' ) );

			if ( $orignal_keywords ) {
				$sql_order_by[0] = $wpdb->prepare( " WHEN ( post.post_title LIKE %s ) THEN 1 \r\n", $wpdb->esc_like( $orignal_keywords ) . '%' );
				$sql_order_by[1] = $wpdb->prepare( " WHEN ( post.post_title LIKE %s ) THEN 1 \r\n", '%' . $wpdb->esc_like( $orignal_keywords ) . '%' );
			}
			$sql_order_by[2] = " WHEN ( post.post_title LIKE '%" . $wpdb->esc_like( join( ' ', $search_keywords ) ) . "%' ) THEN 3 \r\n";
			if ( count( $search_keywords ) > 1 ) {
				$order_containts_all = array();
				foreach ( $search_keywords as $kw ) {
					$order_containts_all[] = "post.post_title LIKE '%" . $wpdb->esc_like( $kw ) . "%'";
				}
				// If the post title container all keywords.
				$sql_order_by[3] = ' WHEN ( ' . join( ' AND ', $order_containts_all ) . ' ) THEN 4 ';
			}
		}

		// Start keywords operator.
		if ( 'or' == $default_operator ) { // If use `OR` condtional for keywords.

			foreach ( $search_keywords as $keyword ) {
				if ( press_search_string()->is_cjk( $keyword ) ) { // If is cjk, we no need search term reverse.
					$keyword_like[] = "`term` = '${keyword}'";
				} else {
					$keyword_like[] = "`term` LIKE '${keyword}%'";
					$keyword_reverse_like[] = "`term_reverse` LIKE CONCAT(REVERSE('{$keyword}'), '%')";
				}
				$sql_order_by[500] = "
					WHEN ( i1.term LIKE '{$keyword}' AND i1.title > 0 ) THEN 10
					WHEN ( i1.term LIKE '{$keyword}%' AND i1.title > 0 ) THEN 11
					WHEN i1.term LIKE '{$keyword}' THEN 12
					WHEN i1.term_reverse LIKE CONCAT(REVERSE('{$keyword}'), '%') and i1.title > 0 THEN 13
					WHEN i1.term_reverse LIKE CONCAT(REVERSE('{$keyword}'), '%') THEN 14
				";
			}

			foreach ( $searching_weight as $column => $weight ) {
				$c_weight[] = "{$weight} * i1.{$column}";
			}
			if ( '' !== $post_in_terms_leftjoin ) {
				$left_join[] = $post_in_terms_leftjoin;
			}

			$sql .= ' i1.term AS c_term, i1.object_id AS c_object_id, i1.title AS c_title, i1.content AS c_content';
			$sql .= ', ' . implode( ' + ', $c_weight ) . ' AS total_weights';
			$sql .= " FROM {$table_index_name} AS i1 ";
			$sql .= ' ' . implode( ' ', $left_join );
			$sql .= ' WHERE (';
			$sql .= implode( ' OR ', $keyword_like );
			if ( ! empty( $keyword_reverse_like ) ) {
				$sql .= ' OR ' . implode( ' OR ', $keyword_reverse_like );
			}
			$sql .= ')';

		} else { // If use `END` condtional for keywords.
			$select_title    = array();
			$select_content  = array();
			$select_weight   = array();
			$where           = array();
			$number_keywords = count( $search_keywords );
			foreach ( $search_keywords as $k => $keyword ) {
				$key = $k + 1;
				$next_key = $key + 1;
				$select_title[]                   = "i{$key}.title";
				$select_content[]                 = "i{$key}.content";
				$select_weight['title']           = $select_title;
				$select_weight['content']         = $select_content;
				$select_weight['excerpt'][]       = "i{$key}.excerpt";
				$select_weight['category'][]      = "i{$key}.category";
				$select_weight['tag'][]           = "i{$key}.tag";
				$select_weight['custom_field'][]  = "i{$key}.custom_field";
				if ( $key < $number_keywords ) {
					$left_join[] = "LEFT JOIN {$table_index_name} as i{$next_key} ON i{$key}.object_id = i{$next_key}.object_id";
				}
				if ( press_search_string()->is_cjk( $keyword ) ) { // If is cjk, we no need search term reverse.
					$where[] = "i{$key}.`term` = '{$keyword}'";
				} else {
					$where[] = "( i{$key}.`term` = '{$keyword}' OR i{$key}.`term` LIKE '{$keyword}%' OR i{$key}.`term_reverse` LIKE CONCAT(REVERSE('{$keyword}'), '%') )";
				}

				$sql_order_by[500] = "
					WHEN ( i{$key}.term LIKE '{$keyword}' AND i{$key}.title > 0 ) THEN 10
					WHEN ( i{$key}.term LIKE '{$keyword}%' AND i{$key}.title > 0 ) THEN 11
					WHEN i{$key}.term LIKE '{$keyword}' THEN 12
					WHEN i{$key}.term_reverse LIKE CONCAT(REVERSE('{$keyword}'), '%') and i{$key}.title > 0 THEN 13
					WHEN i{$key}.term_reverse LIKE CONCAT(REVERSE('{$keyword}'), '%') THEN 14
				";

			}

			$sql .= ' i1.term AS c_term, i1.`object_id` AS c_object_id, ';
			$sql .= ' ' . implode( ' + ', $select_title ) . ' AS c_title,';
			$sql .= ' ' . implode( ' + ', $select_content ) . ' AS c_content';
			foreach ( $select_weight as $k => $val ) {
				$weight = $searching_weight[ $k ];
				$c_weight[] = " {$weight} * ( " . implode( ' + ', $val ) . ' )';
			}
			$sql .= ', ' . implode( ' + ', $c_weight ) . ' AS total_weights';
			$sql .= " FROM {$table_index_name} AS i1 ";
			$sql .= ' ' . implode( ' ', $left_join );
			if ( '' !== $post_in_terms_leftjoin ) {
				$sql .= $post_in_terms_leftjoin;
			}
			$sql .= ' WHERE (' . implode( ' AND ', $where ) . ')';
		} // end check query operator.

		if ( '' !== $where_object_type_in ) {
			$sql .= $where_object_type_in;
		}
		if ( '' !== $post_in_terms_where ) {
			$sql .= $post_in_terms_where;
		}
		$post_exclusion = $this->get_post_exclusion_from_setting();
		if ( is_array( $post_exclusion ) && ! empty( $post_exclusion ) ) {
			$object_id_not_in = implode( ', ', $post_exclusion );
			$object_id_not_in = " AND i1.object_id NOT IN ( {$object_id_not_in} )";
			$sql .= $object_id_not_in;
		}
		$sql .= apply_filters( 'press_search_sql_query_where_clause', '', $args, $extra_args, $keywords, $engine_slug, $has_post_query );

		$sql .= $sql_group_by;
		$order_by = ' ORDER BY ( CASE ' . implode( ' ', $sql_order_by ) . ' ELSE 100 END ) ASC, total_weights DESC';
		$sql .= $order_by;

		$sql = apply_filters( 'press_search_create_sql_query', $sql, $args, $extra_args, $keywords, $engine_slug );

		if ( isset( $_GET['dev'] ) && $_GET['dev'] ) {
			if ( current_user_can( 'administrator' ) ) {
				echo '<pre>SQL: ' . $sql . '</pre>';
			}
		}

		return $sql;
	}

	function get_post_exclusion_from_setting() {
		global $wpdb;
		$searching_post_exclusion = press_search_get_setting( 'searching_post_exclusion', '' );
		$searching_terms_exclusion = press_search_get_setting( 'searching_category_exclusion', '' );
		$post_exclusion = press_search_string()->explode_comma_str( $searching_post_exclusion );
		$term_exclusion = press_search_string()->explode_comma_str( $searching_terms_exclusion );
		if ( ! empty( $term_exclusion ) ) {
			$post_in_terms = $wpdb->get_results( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ', ', $term_exclusion ) . ')', ARRAY_A ); // WPCS: unprepared SQL OK.
			if ( is_array( $post_in_terms ) && ! empty( $post_in_terms ) ) {
				foreach ( $post_in_terms as $term ) {
					if ( isset( $term['object_id'] ) && is_numeric( $term['object_id'] ) ) {
						$post_exclusion[] = $term['object_id'];
					}
				}
			}
		}
		return $post_exclusion;
	}

	function get_post_ids_from_term( $term_ids = array() ) {
		global $wpdb;
		$return = array();
		$table_term_relationships = $wpdb->term_relationships;
		if ( is_array( $term_ids ) && ! empty( $term_ids ) ) {
			$results = $wpdb->get_results( "SELECT DISTINCT ( t_r.object_id ) FROM {$table_term_relationships} AS t_r WHERE term_taxonomy_id IN (" . implode( ',', $term_ids ) . ')' ); // WPCS: unprepared SQL OK.
			if ( is_array( $results ) && ! empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( isset( $result->object_id ) && is_numeric( $result->object_id ) ) {
						$return[] = $result->object_id;
					}
				}
			}
		}
		return $return;
	}

	public function get_sql_limit_query( $limit_args = array() ) {
		$defaults = array(
			'page' => 1,
			'posts_per_page' => get_option( 'posts_per_page' ),
			'offset' => false,
		);
		$limit_args = wp_parse_args( $limit_args, $defaults );
		if ( -1 == $limit_args['posts_per_page'] ) {
			$limits = '';
		} else {
			$page = absint( $limit_args['page'] );
			if ( ! $page ) {
				$page = 1;
			}
			// If 'offset' is provided, it takes precedence over 'paged'.
			if ( isset( $limit_args['offset'] ) && is_numeric( $limit_args['offset'] ) ) {
				$offset = absint( $limit_args['offset'] );
				$pgstrt = $offset . ', ';
			} else {
				$pgstrt = absint( ( $page - 1 ) * $limit_args['posts_per_page'] ) . ', ';
			}
			$limits = ' LIMIT ' . $pgstrt . $limit_args['posts_per_page'];
		}
		return array(
			'limit_str'  => $limits,
			'limit_args' => $limit_args,
		);
	}

	function calc_found_rows( $limit_args = array() ) {
		$found_rows = $this->get_found_rows();
		$max_num_pages = 1;
		if ( isset( $limit_args['posts_per_page'] ) && is_numeric( $limit_args['posts_per_page'] ) && $limit_args['posts_per_page'] > 0 ) {
			$max_num_pages = ceil( $found_rows / $limit_args['posts_per_page'] );
		}
		$return = array(
			'found_rows' => $found_rows,
			'max_num_pages' => $max_num_pages,
		);
		return $return;
	}

	function get_object_ids( $keywords = '', $engine_slug = 'engine_default', $limit_args = array(), $extra_args = array() ) {
		global $wpdb;
		$query = $this->search_index_sql( $keywords, $engine_slug, array(), $extra_args );
		$limit_query = $this->get_sql_limit_query( $limit_args );
		$query .= $limit_query['limit_str'];
		$object_ids = array();
		$result = $wpdb->get_results( $query ); // WPCS: unprepared SQL OK.

		if ( is_array( $result ) && ! empty( $result ) ) {
			foreach ( $result as $object ) {
				if ( isset( $object->c_object_id ) && ! empty( $object->c_object_id ) ) {
					$object_ids[] = $object->c_object_id;
				}
			}
		}

		$return = $this->calc_found_rows( $limit_query['limit_args'] );
		$return['object_ids'] = $object_ids;
		return $return;
	}

	function get_found_rows() {
		global $wpdb;
		$found_rows = 0;
		$sql = 'SELECT FOUND_ROWS() as found_rows';
		$result = $wpdb->get_results( $sql, ARRAY_A ); // WPCS: unprepared SQL OK.
		if ( is_array( $result ) && isset( $result[0] ) && isset( $result[0]['found_rows'] ) ) {
			$found_rows = $result[0]['found_rows'];
		}
		return $found_rows;
	}

	function get_object_ids_group_by_posttype( $keywords = '', $engine_slug = 'engine_default', $extra_args = array() ) {
		global $wpdb;
		$object_ids = array();
		$queries = $this->search_index_sql_group_by_posttype( $keywords, $engine_slug, $extra_args );
		$ajax_limit_items = press_search_get_setting( 'searching_ajax_limit_items', 10 );
		$limit_args = array(
			'posts_per_page' => $ajax_limit_items,
		);
		$limit_query = $this->get_sql_limit_query( $limit_args );
		foreach ( $queries as $key => $query ) {
			$query .= $limit_query['limit_str'];
			$result = $wpdb->get_results( $query ); // WPCS: unprepared SQL OK.
			$calc_found_rows = $this->calc_found_rows();
			$found_rows = $calc_found_rows['found_rows'];
			$_object_ids = array();
			if ( is_array( $result ) && ! empty( $result ) ) {
				foreach ( $result as $object ) {
					if ( isset( $object->c_object_id ) && ! empty( $object->c_object_id ) ) {
						$_object_ids[] = $object->c_object_id;
					}
				}
			}

			$_object_ids = array_unique( $_object_ids );
			if ( ! empty( $_object_ids ) ) {
				$object_ids[ $key ] = array(
					'object_ids' => $_object_ids,
					'found_rows' => $found_rows,
				);
			}
		}
		return $object_ids;
	}

	public function get_post_exclusion() {
		$exclude_post_ids = press_search_get_setting( 'searching_post_exclusion', '' );
		$exclude_ids = array();
		if ( '' !== $exclude_post_ids ) {
			$exclude_ids = array_unique( array_filter( explode( ',', $exclude_post_ids ), 'absint' ) );
		}
	}

	public function get_tax_exclusion() {
		$exclude_term_ids = press_search_get_setting( 'searching_category_exclusion', '' );
		$exclude_ids = array();
		if ( '' !== $exclude_term_ids ) {
			$exclude_ids = array_unique( array_filter( explode( ',', $exclude_term_ids ), 'absint' ) );
		}
	}
}

/**
 * Main instance of Press_Search_Query.
 *
 * Returns the main instance of Press_Search_Query to prevent the need to use globals.
 *
 * @since  0.1.0
 * @return Press_Search_Query
 */
function press_search_query() {
	return Press_Search_Query::instance();
}

