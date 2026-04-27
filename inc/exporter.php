<?php
/**
 * Exporter Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the export process.
 */
function viney_markdown_export_run( $post_types, $post_statuses = array( 'publish' ), $only_new = false, $include_llms = false, $export_limits = array(), $llm_limits = array() ) {
	if ( empty( $post_types ) ) {
		return false;
	}

	// Increase memory and time limit for large exports.
	@set_time_limit( 0 );
	@ini_set( 'memory_limit', '512M' );

	$upload_dir = wp_upload_dir();
	$base_dir   = $upload_dir['basedir'] . '/viney-markdown-export-' . time();
	
	if ( ! wp_mkdir_p( $base_dir ) ) {
		wp_die( 'Failed to create temporary directory.' );
	}

	// Sort post types: pages first, then posts, then others.
	usort( $post_types, function( $a, $b ) {
		if ( $a === 'page' ) return -1;
		if ( $b === 'page' ) return 1;
		if ( $a === 'post' ) return -1;
		if ( $b === 'post' ) return 1;
		return strcmp( $a, $b );
	} );

	$site_title = get_bloginfo( 'name' );
	$site_url   = get_bloginfo( 'url' );

	$llms_content = '';
	if ( $include_llms ) {
		$llms_content  = "---\n";
		$llms_content .= "site_title: $site_title\n";
		$llms_content .= "site_url: $site_url\n";
		$llms_content .= "export_date: " . date( 'Y-m-d H:i:s' ) . "\n";
		$llms_content .= "---\n\n";
		$llms_content .= "# LLM Context\n\n";
		$llms_content .= "This file contains a comprehensive export of content from $site_title. It is formatted for optimal consumption by Large Language Models (LLMs). Each section starts with a Level 1 heading followed by metadata about the content.\n\n";
		$llms_content .= "---\n\n";
	}

	$exported_count = 0;
	$processed_ids  = array();
	$homepage_id    = (int) get_option( 'page_on_front' );

	// If the homepage is being exported, process it first.
	// Note: Homepage is always included and doesn't count towards limits.
	if ( $homepage_id && in_array( 'page', $post_types ) ) {
		$homepage_post = get_post( $homepage_id );
		if ( $homepage_post && in_array( $homepage_post->post_status, $post_statuses ) && ! empty( trim( $homepage_post->post_content ) ) ) {
			$type_dir = $base_dir . '/page';
			wp_mkdir_p( $type_dir );

			// Individual post file.
			$md_content = viney_markdown_export_generate_post_md( $homepage_post );
			$filename   = 'page-' . ( $homepage_post->post_name ?: 'post-' . $homepage_id ) . '.md';
			file_put_contents( $type_dir . '/' . $filename, $md_content );

			// Aggregate LLM file.
			if ( $include_llms ) {
				$llms_content .= viney_markdown_export_generate_llm_post_content( $homepage_post );
			}

			// Update per-post tracking.
			update_post_meta( $homepage_id, '_viney_markdown_export_last_run', time() );

			$processed_ids[] = $homepage_id;
			$exported_count++;
		}
	}

	foreach ( $post_types as $post_type ) {
		$type_dir = $base_dir . '/' . $post_type;
		wp_mkdir_p( $type_dir );

		$export_max = isset( $export_limits[ $post_type ] ) && ! empty( $export_limits[ $post_type ] ) ? (int) $export_limits[ $post_type ] : -1;
		$llm_max    = isset( $llm_limits[ $post_type ] ) && ! empty( $llm_limits[ $post_type ] ) ? (int) $llm_limits[ $post_type ] : -1;

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1, // We filter limits in the loop to support "Only New" correctly.
			'post_status'    => $post_statuses,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post__not_in'   => array( $homepage_id ),
		);

		$query = new WP_Query( $args );

		$type_export_count = 0;
		$type_llm_count    = 0;

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				// If we've reached both limits for this type, stop.
				$export_limit_reached = $export_max > 0 && $type_export_count >= $export_max;
				$llm_limit_reached    = ! $include_llms || ( $llm_max > 0 && $type_llm_count >= $llm_max );

				if ( $export_limit_reached && $llm_limit_reached ) {
					break;
				}

				// Omit posts with no content.
				if ( empty( trim( $post->post_content ) ) ) {
					continue;
				}

				// If only new is selected (and we're not doing a full LLM export), check per-post tracking.
				if ( $only_new && ! $include_llms ) {
					$post_last_run = get_post_meta( $post->ID, '_viney_markdown_export_last_run', true );
					if ( $post_last_run && strtotime( $post->post_modified_gmt ) <= (int) $post_last_run ) {
						continue;
					}
				}
				
				$processed_this_post = false;

				// Individual post file.
				if ( ! $export_limit_reached ) {
					$md_content = viney_markdown_export_generate_post_md( $post );
					$filename   = $post_type . '-' . ( $post->post_name ?: 'post-' . $post->ID ) . '.md';
					file_put_contents( $type_dir . '/' . $filename, $md_content );
					$type_export_count++;
					$processed_this_post = true;
				}

				// Aggregate LLM file.
				if ( $include_llms && ! $llm_limit_reached ) {
					$llms_content .= viney_markdown_export_generate_llm_post_content( $post );
					$type_llm_count++;
					$processed_this_post = true;
				}

				if ( $processed_this_post ) {
					// Update per-post tracking.
					update_post_meta( $post->ID, '_viney_markdown_export_last_run', time() );
					$exported_count++;
				}
			}
		}
		wp_reset_postdata();
	}

	if ( $exported_count === 0 ) {
		viney_markdown_export_recursive_rmdir( $base_dir );
		return false;
	}

	// Save LLM file.
	if ( $include_llms ) {
		file_put_contents( $base_dir . '/llms.md', $llms_content );
	}

	// Create Zip.
	require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
	$zip_filename = sanitize_title( $site_title ) . '-markdown-export-' . date( 'Y-m-d' ) . '.zip';
	$zip_path     = $upload_dir['basedir'] . '/' . $zip_filename;
	
	$archive = new PclZip( $zip_path );
	$v_list = $archive->create( $base_dir, PCLZIP_OPT_REMOVE_PATH, $base_dir );
	
	if ( $v_list == 0 ) {
		wp_die( 'Error : ' . $archive->errorInfo( true ) );
	}

	// Cleanup temp dir.
	viney_markdown_export_recursive_rmdir( $base_dir );

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

	// Add taxonomy terms.
	$taxonomies = get_object_taxonomies( $post->post_type );
	if ( ! empty( $taxonomies ) ) {
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term_names = wp_list_pluck( $terms, 'name' );
				$frontmatter[ $taxonomy ] = implode( ', ', $term_names );
			}
		}
	}

	$seo_data = viney_markdown_export_get_post_seo_data( $post );
	$meta_title = $seo_data['title'];
	$meta_desc  = $seo_data['description'];

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
 * Retrieves SEO title and description for a post.
 */
function viney_markdown_export_get_post_seo_data( $post ) {
	$meta_title = '';
	$meta_desc  = '';

	// Yoast data is often unreliable in local or dev environments.
	if ( in_array( wp_get_environment_type(), array( 'local', 'development' ) ) ) {
		return array(
			'title'       => '',
			'description' => '',
		);
	}

	if ( function_exists( 'YoastSEO' ) ) {
		$has_custom_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		$has_custom_desc  = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );

		if ( $has_custom_title || $has_custom_desc ) {
			$yoast_meta = YoastSEO()->meta->for_post( $post->ID );
			if ( $yoast_meta ) {
				$meta_title = $yoast_meta->title;
				$meta_desc  = $yoast_meta->description;
			}
		}
	}

	if ( empty( $meta_title ) ) {
		$meta_title = wp_get_document_title();
	}

	return array(
		'title'       => $meta_title,
		'description' => $meta_desc,
	);
}

/**
 * Generates the Markdown content for a post optimized for LLM consumption.
 */
function viney_markdown_export_generate_llm_post_content( $post ) {
	$seo_data    = viney_markdown_export_get_post_seo_data( $post );
	$meta_title  = $seo_data['title'];
	$status      = $post->post_status;
	$type        = $post->post_type;
	$created     = $post->post_date;
	$updated     = $post->post_modified;
	$url         = get_permalink( $post->ID );
	$is_homepage = (int) get_option( 'page_on_front' ) === $post->ID;

	$output = "# " . $post->post_title . "\n\n";

	if ( $is_homepage ) {
		$output .= "This is the website homepage. ";
	} else {
		$output .= "The status of this $type post is '$status'. ";
	}

	if ( ! empty( $meta_title ) ) {
		$output .= "Its SEO title is \"$meta_title\". ";
	}

	$output .= "It was created on $created and updated on $updated. ";
	$output .= "It's located at $url. Its content is as follows:\n\n";
	$output .= viney_markdown_export_convert_content( $post->post_content );
	$output .= "\n\n---\n\n";

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

