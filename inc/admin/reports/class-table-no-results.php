<?php
class Press_Search_Report_No_Search_Table extends WP_List_Table {
	protected static $_instance = null;
	protected $per_page = 20;
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
			self::$_instance->init();
		}
		return self::$_instance;
	}

	public function init() {
		$items_per_page = press_search_reports()->get_screen_items_per_page();
		$this->per_page = $items_per_page;
	}

	protected function get_table_data( $orderby = 'date_time', $order = 'desc' ) {
		$result = press_search_reports()->get_no_results_search( -1, $orderby, $order );
		return $result;
	}

	public function __construct() {
		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'press_seach_report',
				'plural'   => 'press_seach_reports',
				'ajax'     => false,
			)
		);
	}
	public function get_columns() {
		$columns = array(
			'query'    => _x( 'Keywords', 'Column label', 'press-search' ),
			'query_count'    => _x( 'Total searches', 'Column label', 'press-search' ),
			'date_time' => _x( 'Latest time', 'Column label', 'press-search' ),
		);
		return $columns;
	}
	protected function get_sortable_columns() {
		$sortable_columns = array();
	
		return $sortable_columns;
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'query':
			case 'hits':
			case 'query_count':
			case 'date_time':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); // Show the whole array for troubleshooting purposes.
		}
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			$item['ID']                // The value of the checkbox should be the record's ID.
		);
	}

	protected function get_bulk_actions() {
		$actions = array();
		return $actions;
	}
	protected function process_bulk_action() {
		// Detect when a bulk action is being triggered.
		if ( 'delete' === $this->current_action() ) {
			wp_die( 'Items deleted (or they would be if we had items to delete)!' );
		}
	}
	function prepare_items() {
		global $wpdb;
		$per_page = $this->per_page;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();
		$orderby = ! empty( $_REQUEST['orderby'] ) ? wp_unslash( $_REQUEST['orderby'] ) : 'date_time'; // WPCS: Input var ok.
		$order = ! empty( $_REQUEST['order'] ) ? wp_unslash( $_REQUEST['order'] ) : 'desc'; // WPCS: Input var ok.
		$data = $this->get_table_data( $orderby, $order );
		$current_page = $this->get_pagenum();
		$total_items = count( $data );
		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
		$this->items = $data;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,                     // WE have to calculate the total number of items.
				'per_page'    => $per_page,                        // WE have to determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ), // WE have to calculate the total number of pages.
			)
		);
	}
}

function press_search_report_table_no_results() {
	return Press_Search_Report_No_Search_Table::get_instance();
}
