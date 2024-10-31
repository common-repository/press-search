<table class="report-table wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col" class="manage-column"><?php esc_html_e( 'Keyword', 'press-search' ); ?></th>
			<th scope="col" class="manage-column"><?php esc_html_e( 'Total searches', 'press-search' ); ?></th>
			<th scope="col" class="manage-column"><?php esc_html_e( 'Hits', 'press-search' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( isset( $result ) && is_array( $result ) && ! empty( $result ) ) { ?>
			<?php foreach ( $result as $res ) { ?>
				<?php if ( isset( $res['query'] ) && isset( $res['hits'] ) ) { ?>
				<tr>
					<td><strong><?php echo esc_html( $res['query'] ); ?></strong></td>
					<td><?php echo esc_html( $res['query_count'] ); ?></td>
					<td><?php echo esc_html( $res['hits'] ); ?></td>
				</tr>
				<?php } ?>
			<?php } ?>
		<?php } else { ?>
			<tr>
				<td colspan="3"><?php esc_html_e( 'No data', 'press-search' ); ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
<?php if ( isset( $enable_count ) && $enable_count ) { ?>
	<div class="tablenav bottom">
		<div class="alignleft actions bulkactions"></div>
		<div class="alignleft actions">
		</div>
		<div class="tablenav-pages one-page">
			<span class="displaying-num"><?php echo sprintf( '%s %s', esc_html( count( $result ) ), esc_html__( 'items', 'press-search' ) ); ?></span>
		</div>
		<br class="clear">
	</div>
<?php } ?>
