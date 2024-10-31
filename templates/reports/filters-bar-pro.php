<div class="filter-bars">
	<select class="filter-item" name="search_engine" id="report-search-engine">
	<?php
	if ( is_array( $all_engines_name ) && ! empty( $all_engines_name ) ) {
		$engine_args = $filter_link_args;
		foreach ( $all_engines_name as $engine ) {
			$engine_args['search_engine'] = $engine['slug'];
			$filter_link = add_query_arg( $engine_args, admin_url( 'admin.php?page=press-search-report' ) );
			echo sprintf( '<option data-src="%s" value="%s" %s>%s</option>', esc_url( $filter_link ), esc_attr( $engine['slug'] ), selected( $engine['slug'], $filter_search_engine, false ), esc_html( $engine['name'] ) );
		}
	}
	?>
	</select>
	<?php
	$fixed_date = array(
		'current_year' => esc_html__( 'This Year', 'press-search' ),
		'current_month' => esc_html__( 'This Month', 'press-search' ),
		'last_7_days' => esc_html__( 'Last 7 Days', 'press-search' ),
	);
	$fixed_date_args = $filter_link_args;
	foreach ( $fixed_date as $k => $title ) {
		$fixed_date_args['date'] = $k;
		$filter_link = add_query_arg( $fixed_date_args, admin_url( 'admin.php?page=press-search-report' ) );
		$extra_class = '';
		if ( isset( $filter_date ) && is_string( $filter_date ) && $k == $filter_date ) {
			$extra_class = 'button-primary';
		}
		echo sprintf( '<a href="%s" class="button filter-item %s">%s</a>', esc_url( $filter_link ), $extra_class, $title );
	}

	$custom_date_args = $filter_link_args;
	$custom_date_args['date'] = 'custom_date';
	$custom_date_filter = add_query_arg( $custom_date_args, admin_url( 'admin.php?page=press-search-report' ) );
	$start_date = '';
	$end_date = '';
	if ( is_array( $filter_date ) ) {
		if ( isset( $filter_date[0] ) && ! empty( $filter_date[0] ) ) {
			$start_date = $filter_date[0];
		}
		if ( isset( $filter_date[1] ) && ! empty( $filter_date[1] ) ) {
			$end_date = $filter_date[1];
		}
	}
	?>
	<div class="custom-date filter-item">
		<input type="text" autocomplete="off" autocorrect="off" id="report-date-from" class="report-date-picker" spellcheck="false" placeholder="<?php esc_attr_e( 'From', 'press-search' ); ?>" value="<?php echo esc_attr( $start_date ); ?>"/>
		<input type="text" autocomplete="off" autocorrect="off" id="report-date-to" class="report-date-picker" spellcheck="false" id="report-date-to" class="report-date-picker" autocapitalize="none" placeholder="<?php esc_attr_e( 'To', 'press-search' ); ?>" value="<?php echo esc_attr( $end_date ); ?>"/>
		<button class="get-report button" id="report-custom-date" data-src="<?php echo esc_url( $custom_date_filter ); ?>"><?php esc_html_e( 'Go', 'press-search' ); ?></button>
	</div>
</div>
