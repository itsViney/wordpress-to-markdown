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
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	
	// Exclude post types that probably don't need exporting.
	$exclude = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
	
	$default_selected_types = array_diff( array_keys( (array) $post_types ), $exclude );

	// Load preferences.
	$prefs = get_user_meta( get_current_user_id(), 'viney_markdown_export_prefs', true ) ?: array();
	$selected_types    = isset( $prefs['post_types'] ) ? $prefs['post_types'] : $default_selected_types;
	$selected_statuses = isset( $prefs['post_statuses'] ) ? $prefs['post_statuses'] : array( 'publish' );
	$only_new_pref     = isset( $prefs['only_new'] ) ? $prefs['only_new'] : false;
	$include_llms_pref = isset( $prefs['include_llms'] ) ? $prefs['include_llms'] : false;
	$export_limits_pref = isset( $prefs['export_limits'] ) ? $prefs['export_limits'] : array();
	$llm_limits_pref    = isset( $prefs['llm_limits'] ) ? $prefs['llm_limits'] : array();

	// Check if any posts have been exported before.
	$has_exported_posts = count( get_posts( array(
		'post_type'      => $default_selected_types,
		'meta_key'       => '_viney_markdown_export_last_run',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	) ) ) > 0;

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
						<table class="wp-list-table fixed striped" style="max-width: 800px;">
							<thead>
								<tr>
									<th style="width: 40px;"></th>
									<th style="padding: 15px 10px;">Post Type</th>
									<th style="width: 120px; padding: 15px 10px;">Limit post files</th>
									<th class="viney-llm-column" style="padding: 15px 10px; width: 120px; <?php echo $include_llms_pref ? '' : 'display: none;'; ?>">Limit in llms.md</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $post_types as $slug => $post_type ) : 
									if ( in_array( $slug, $exclude ) ) continue;
									$is_checked = in_array( $slug, $selected_types );
									$export_max = isset( $export_limits_pref[ $slug ] ) ? $export_limits_pref[ $slug ] : '';
									$llm_max    = isset( $llm_limits_pref[ $slug ] ) ? $llm_limits_pref[ $slug ] : '';
									?>
									<tr>
										<td>
											<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_checked ); ?>>
										</td>
										<td>
											<strong><?php echo esc_html( $post_type->labels->name ); ?></strong> (<?php echo esc_html( $slug ); ?>)
										</td>
										<td>
											<input type="number" name="export_limits[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $export_max ); ?>" min="1" step="1" style="width: 100%;">
										</td>
										<td class="viney-llm-column" style="<?php echo $include_llms_pref ? '' : 'display: none;'; ?>">
											<input type="number" name="llm_limits[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $llm_max ); ?>" min="1" step="1" style="width: 100%;">
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description">Choose which posts types to include in the export and (optionally) limit the number of each post type to include. Leave blank for unlimited. When limited, most recent posts will be prioritised.</p>
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
								<input type="checkbox" name="post_statuses[]" value="<?php echo esc_attr( $status_slug ); ?>" <?php checked( in_array( $status_slug, $selected_statuses ) ); ?>>
								<?php echo esc_html( $status_label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Export Options</th>
					<td>
						<label style="display: block; margin-bottom: 10px;">
							<input type="checkbox" name="viney_markdown_export_include_llms" id="viney_markdown_export_include_llms" value="1" <?php checked( $include_llms_pref ); ?>>
							Include whole site <strong>llms.md</strong> file
						</label>
						
						<?php if ( $has_exported_posts ) : ?>
						<label id="viney_markdown_export_only_new_wrapper" style="<?php echo $include_llms_pref ? 'opacity: 0.5;' : ''; ?>">
							<input type="checkbox" name="viney_markdown_export_only_new" id="viney_markdown_export_only_new" value="1" <?php checked( $only_new_pref && ! $include_llms_pref ); ?> <?php disabled( $include_llms_pref ); ?>>
							Only export posts that have been created or updated since their last export.
						</label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="viney_markdown_export_submit" id="submit" class="button button-primary" value="Export to Markdown">
			</p>
		</form>
	</div>
	<script>
		/**
		 * UI Logic for Export Options
		 */
		(function() {
			var llmCheckbox = document.getElementById('viney_markdown_export_include_llms');
			var onlyNewCheckbox = document.getElementById('viney_markdown_export_only_new');
			var onlyNewWrapper = document.getElementById('viney_markdown_export_only_new_wrapper');
			var llmColumns = document.querySelectorAll('.viney-llm-column');

			if (llmCheckbox) {
				llmCheckbox.addEventListener('change', function() {
					// Toggle LLM columns.
					llmColumns.forEach(function(col) {
						col.style.display = llmCheckbox.checked ? '' : 'none';
					});

					// Handle Only New checkbox.
					if (onlyNewCheckbox) {
						if (this.checked) {
							onlyNewCheckbox.checked = false;
							onlyNewCheckbox.disabled = true;
							if (onlyNewWrapper) onlyNewWrapper.style.opacity = '0.5';
						} else {
							onlyNewCheckbox.disabled = false;
							if (onlyNewWrapper) onlyNewWrapper.style.opacity = '1';
						}
					}
				});
			}
		})();

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

