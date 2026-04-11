<?php
/**
 * Exporter Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the export process.
 *
 * @param array $post_types    Array of post types to export.
 * @param array $post_statuses Array of post statuses to include.
 * @param bool  $only_new      Whether to only export new/updated posts.
 * @return bool True on success, false if no posts found.
 */
function viney_markdown_export_run( $post_types, $post_statuses = array( 'publish' ), $only_new = false ) {
	if ( empty( $post_types ) ) {
		return false;
	}

	// Increase memory and time limit for large exports.
	@set_time_limit( 0 );
	@ini_set( 'memory_limit', '512M' );

	$user_id  = get_current_user_id();
	$last_run = get_user_meta( $user_id, 'viney_markdown_export_last_run', true );

	$upload_dir = wp_upload_dir();
	$base_dir   = $upload_dir['basedir'] . '/viney-markdown-export-' . time();
	
	if ( ! wp_mkdir_p( $base_dir ) ) {
		wp_die( 'Failed to create temporary directory.' );
	}

	$exported_count = 0;

	foreach ( $post_types as $post_type ) {
		$type_dir = $base_dir . '/' . $post_type;
		wp_mkdir_p( $type_dir );

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => $post_statuses,
		);

		if ( $only_new && $last_run ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => gmdate( 'Y-m-d H:i:s', $last_run ),
				),
			);
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();
				
				$md_content = viney_markdown_export_generate_post_md( $post );
				$filename   = $post_type . '-' . ( $post->post_name ?: 'post-' . $post->ID ) . '.md';
				file_put_contents( $type_dir . '/' . $filename, $md_content );
				$exported_count++;
			}
		}
		wp_reset_postdata();
	}

	if ( $exported_count === 0 ) {
		viney_markdown_export_recursive_rmdir( $base_dir );
		return false;
	}

	// Create Zip.
	require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
	// get the site title
	$site_title = get_bloginfo( 'name' );
	$zip_filename = sanitize_title( $site_title ) . '-markdown-export-' . date( 'Y-m-d' ) . '.zip';
	$zip_path     = $upload_dir['basedir'] . '/' . $zip_filename;
	
	$archive = new PclZip( $zip_path );
	$v_list = $archive->create( $base_dir, PCLZIP_OPT_REMOVE_PATH, $base_dir );
	
	if ( $v_list == 0 ) {
		wp_die( 'Error : ' . $archive->errorInfo( true ) );
	}

	// Cleanup temp dir.
	viney_markdown_export_recursive_rmdir( $base_dir );

	// Update last run meta BEFORE streaming.
	update_user_meta( $user_id, 'viney_markdown_export_last_run', time() );

	// Download Zip.
	if ( file_exists( $zip_path ) ) {
		// Set a cookie so the frontend knows the download has started and can refresh.
		setcookie( 'viney_markdown_export_complete', '1', time() + 30, '/' );

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		readfile( $zip_path );
		
		// Delete zip after download.
		unlink( $zip_path );
		exit;
	} else {
		wp_die( 'Zip file could not be created.' );
	}
}

/**
 * Generates the Markdown content for a post including frontmatter.
 */
function viney_markdown_export_generate_post_md( $post ) {
	$author_name = get_the_author_meta( 'display_name', $post->post_author );
	
	$frontmatter = array(
		'post_type'  => $post->post_type,
		'title'      => '"' . str_replace( '"', '\"', $post->post_title ) . '"',
		'created_at' => $post->post_date,
		'updated_at' => $post->post_modified,
		'author'     => $author_name,
		'status'     => $post->post_status,
		'path'       => wp_make_link_relative( get_permalink( $post->ID ) ),
		'url'        => get_permalink( $post->ID ),
	);

	$meta_title = '';
	$meta_desc  = '';

	if ( function_exists( 'YoastSEO' ) ) {
		$yoast_meta = YoastSEO()->meta->for_post( $post->ID );
		if ( $yoast_meta ) {
			$meta_title = $yoast_meta->title;
			$meta_desc  = $yoast_meta->description;
		}
	}

	if ( empty( $meta_title ) ) {
		// Mock a singular context for wp_get_document_title if it returns the admin title.
		$meta_title = wp_get_document_title();
	}

	if ( ! empty( $meta_title ) ) {
		$frontmatter['meta_title'] = '"' . str_replace( '"', '\"', $meta_title ) . '"';
	}
	if ( ! empty( $meta_desc ) ) {
		$frontmatter['meta_description'] = '"' . str_replace( '"', '\"', $meta_desc ) . '"';
	}

	$output = "---\n";
	foreach ( $frontmatter as $key => $value ) {
		$output .= "$key: $value\n";
	}
	$output .= "---\n\n";
	$output .= "# " . $post->post_title . "\n\n";
	$output .= viney_markdown_export_convert_content( $post->post_content );

	return $output;
}

/**
 * Recursive rmdir utility.
 */
function viney_markdown_export_recursive_rmdir( $dir ) {
	if ( is_dir( $dir ) ) {
		$objects = scandir( $dir );
		foreach ( $objects as $object ) {
			if ( $object != "." && $object != ".." ) {
				if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . "/" . $object ) ) {
					viney_markdown_export_recursive_rmdir( $dir . DIRECTORY_SEPARATOR . $object );
				} else {
					unlink( $dir . DIRECTORY_SEPARATOR . $object );
				}
			}
		}
		rmdir( $dir );
	}
}

