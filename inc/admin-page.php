<?php
/**
 * Admin Page UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Markdown Export admin page.
 */
function viney_markdown_export_render_admin_page() {
	$user_id    = get_current_user_id();
	$last_run   = get_user_meta( $user_id, 'viney_markdown_export_last_run', true );
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	
	// Exclude post types that probably don't need exporting.
	$exclude = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
	
	$selected_types = array_diff( array_keys( (array) $post_types ), $exclude );
	$new_count      = 0;

	if ( $last_run ) {
		$query = new WP_Query( array(
			'post_type'      => $selected_types,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => gmdate( 'Y-m-d H:i:s', $last_run ),
				),
			),
		) );
		$new_count = $query->found_posts;
	}

	?>
	<div class="wrap">
		<h1>WordPress to Markdown Export</h1>
		<p>Select the post types and statuses you would like to export as Markdown files. Each post will be created as an individual .md file and organized into folders by post type.</p>
		
		<?php settings_errors( 'viney_markdown_export' ); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'viney_markdown_export_action', 'viney_markdown_export_nonce' ); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row">Select Post Types</th>
					<td>
						<?php foreach ( $post_types as $slug => $post_type ) : 
							if ( in_array( $slug, $exclude ) ) continue;
							?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" checked>
								<?php echo esc_html( $post_type->labels->name ); ?> (<?php echo esc_html( $slug ); ?>)
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Select Post Statuses</th>
					<td>
						<?php
						$statuses = array(
							'publish' => 'Published',
							'draft'   => 'Draft',
							'pending' => 'Pending Review',
							'private' => 'Private',
							'future'  => 'Scheduled',
						);
						foreach ( $statuses as $status_slug => $status_label ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="post_statuses[]" value="<?php echo esc_attr( $status_slug ); ?>" <?php checked( $status_slug, 'publish' ); ?>>
								<?php echo esc_html( $status_label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php if ( $last_run && $new_count > 0 ) : ?>
				<tr>
					<th scope="row">Export Options</th>
					<td>
						<label>
							<input type="checkbox" name="viney_markdown_export_only_new" value="1">
							Only export <strong><?php echo $new_count; ?></strong> new and updated posts
						</label>
					</td>
				</tr>
				<?php endif; ?>
			</table>
			<?php if ( $last_run && $new_count === 0 ) : ?>
				<p class="description">No posts have been created or updated since your last export (<?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>).</p>
			<?php endif; ?>
			
			<p class="submit">
				<input type="submit" name="viney_markdown_export_submit" id="submit" class="button button-primary" value="Export to Markdown">
			</p>
		</form>
	</div>
	<script>
		/**
		 * Detects the completion cookie and refreshes the page.
		 */
		(function() {
			var checkExport = setInterval(function() {
				var cookieName = 'viney_markdown_export_complete=';
				var cookies = document.cookie.split(';');
				for (var i = 0; i < cookies.length; i++) {
					var c = cookies[i].trim();
					if (c.indexOf(cookieName) === 0) {
						// Clear the cookie.
						document.cookie = "viney_markdown_export_complete=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
						clearInterval(checkExport);
						// Reload the page to refresh the "Last Run" and "New Posts" info.
						window.location.reload();
						break;
					}
				}
			}, 1000);
		})();
	</script>
	<?php
}

