<?php
/**
 * Plugin Name: WordPress to Markdown
 * Description: Exports website content (posts, pages, and custom post types) as Markdown files in a ZIP archive.
 * Version: 1.3.0
 * Author: Andrew Viney, Antigravity
 * Github Repository: https://github.com/itsViney/wordpress-to-markdown
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include plugin files.
require_once plugin_dir_path( __FILE__ ) . 'inc/parser.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/exporter.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/admin-page.php';

/**
 * Register the admin menu page.
 */
function viney_markdown_export_add_menu_page() {
	add_submenu_page(
		'tools.php',
		'Markdown Export',
		'Export to Markdown',
		'export',
		'wp-markdown-export',
		'viney_markdown_export_render_admin_page'
	);
}
add_action( 'admin_menu', 'viney_markdown_export_add_menu_page' );

/**
 * Handle the export request.
 */
function viney_markdown_export_handle_request() {
	if ( ! isset( $_POST['viney_markdown_export_submit'] ) ) {
		return;
	}

	if ( ! isset( $_POST['viney_markdown_export_nonce'] ) || ! wp_verify_nonce( $_POST['viney_markdown_export_nonce'], 'viney_markdown_export_action' ) ) {
		wp_die( 'Security check failed.' );
	}

	if ( ! current_user_can( 'export' ) ) {
		wp_die( 'You do not have permission to export.' );
	}

	$post_types    = isset( $_POST['post_types'] ) ? (array) $_POST['post_types'] : array();
	$post_statuses = isset( $_POST['post_statuses'] ) ? (array) $_POST['post_statuses'] : array();
	$only_new      = isset( $_POST['viney_markdown_export_only_new'] ) && '1' === $_POST['viney_markdown_export_only_new'];
	$include_llms  = isset( $_POST['viney_markdown_export_include_llms'] ) && '1' === $_POST['viney_markdown_export_include_llms'];
	$export_limits = isset( $_POST['export_limits'] ) ? (array) $_POST['export_limits'] : array();
	$llm_limits    = isset( $_POST['llm_limits'] ) ? (array) $_POST['llm_limits'] : array();

	if ( empty( $post_types ) ) {
		add_settings_error( 'viney_markdown_export', 'no_post_types', 'Please select at least one post type to export.', 'error' );
		return;
	}

	if ( empty( $post_statuses ) ) {
		add_settings_error( 'viney_markdown_export', 'no_post_statuses', 'Please select at least one post status to export.', 'error' );
		return;
	}

	// Save preferences.
	update_user_meta( get_current_user_id(), 'viney_markdown_export_prefs', array(
		'post_types'    => $post_types,
		'post_statuses' => $post_statuses,
		'only_new'      => $only_new,
		'include_llms'  => $include_llms,
		'export_limits' => $export_limits,
		'llm_limits'    => $llm_limits,
	) );

	// Trigger the file download.
	$result = viney_markdown_export_run( $post_types, $post_statuses, $only_new, $include_llms, $export_limits, $llm_limits );

	if ( false === $result ) {
		add_settings_error( 'viney_markdown_export', 'no_posts_found', "The exporter didn't find any posts to export with the options you selected.", 'error' );
	}
}
add_action( 'admin_init', 'viney_markdown_export_handle_request' );

/**
 * Add action links to the plugin page.
 *
 * @param array $links The existing action links.
 * @return array The updated action links.
 */
function viney_wordpress_to_markdown_action_links( $links ) {
	$exporter_link = '<a href="' . admin_url( 'tools.php?page=wp-markdown-export' ) . '">Go to exporter</a>';
	array_unshift( $links, $exporter_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'viney_wordpress_to_markdown_action_links' );

