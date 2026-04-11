<?php
/**
 * Block to Markdown Parser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts post content (blocks) to Markdown.
 *
 * @param string $content The post content.
 * @return string The converted markdown.
 */
function viney_markdown_export_convert_content( $content ) {
	$blocks   = parse_blocks( $content );
	$markdown = '';

	foreach ( $blocks as $block ) {
		$markdown .= viney_markdown_export_render_block_as_md( $block );
	}

	return trim( $markdown );
}

/**
 * Renders an individual block as Markdown.
 *
 * @param array $block The block object from parse_blocks.
 * @return string Markdown content.
 */
function viney_markdown_export_render_block_as_md( $block ) {
	$block_name = $block['blockName'];
	$content    = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';
	$attrs      = isset( $block['attrs'] ) ? $block['attrs'] : array();

	if ( ! $block_name ) {
		// This is usually raw HTML or whitespace between blocks.
		return viney_markdown_export_html_to_md( $content );
	}

	switch ( $block_name ) {
		case 'core/paragraph':
			return viney_markdown_export_html_to_md( $content ) . "\n\n";

		case 'core/heading':
			// The innerHTML already contains the <hX> tags which are converted by html_to_md.
			return viney_markdown_export_html_to_md( $content ) . "\n\n";

		case 'core/list':
			return viney_markdown_export_handle_list_block( $block ) . "\n\n";

		case 'core/quote':
			$text = viney_markdown_export_html_to_md( $content );
			$lines = explode( "\n", trim( $text ) );
			$quoted = array_map( function( $line ) {
				return '> ' . $line;
			}, $lines );
			return implode( "\n", $quoted ) . "\n\n";

		case 'core/code':
			$text = viney_markdown_export_html_to_md( $content );
			return "```\n" . trim( $text ) . "\n```\n\n";

		case 'core/image':
			return viney_markdown_export_handle_image_block( $block ) . "\n\n";

		case 'core/separator':
			return "---\n\n";

		case 'core/spacer':
			return "\n";

		case 'core/pullquote':
			$text = viney_markdown_export_html_to_md( $content );
			return "> " . trim( $text ) . "\n\n";

		case 'core/gallery':
			// Handle galleries as a series of images or just mention them.
			// Simplified: just render contents.
			$inner = '';
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$inner .= viney_markdown_export_render_block_as_md( $inner_block );
			}
			return $inner;

		default:
			// Fallback for custom blocks: render innerBlocks if they exist, else convert HTML.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner = '';
				foreach ( $block['innerBlocks'] as $inner_block ) {
					$inner .= viney_markdown_export_render_block_as_md( $inner_block );
				}
				return $inner;
			}
			return viney_markdown_export_html_to_md( $content ) . "\n\n";
	}
}

/**
 * Handles list blocks specifically.
 */
function viney_markdown_export_handle_list_block( $block ) {
	$is_ordered = isset( $block['attrs']['ordered'] ) && $block['attrs']['ordered'];
	$md         = '';
	$index      = 1;

	// In modern WP, list items are inner blocks core/list-item.
	if ( ! empty( $block['innerBlocks'] ) ) {
		foreach ( $block['innerBlocks'] as $item ) {
			$prefix = $is_ordered ? $index . '. ' : '- ';
			$text   = viney_markdown_export_html_to_md( $item['innerHTML'] );
			$md    .= $prefix . trim( $text ) . "\n";
			$index++;
		}
	} else {
		// Older WP or simple list HTML.
		$content = $block['innerHTML'];
		// Very basic regex to pull out <li> contents.
		preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $match ) {
				$prefix = $is_ordered ? $index . '. ' : '- ';
				$md    .= $prefix . trim( viney_markdown_export_html_to_md( $match ) ) . "\n";
				$index++;
			}
		}
	}
	return $md;
}

/**
 * Handles image blocks specifically.
 */
function viney_markdown_export_handle_image_block( $block ) {
	$attrs = $block['attrs'];
	$url   = isset( $attrs['url'] ) ? $attrs['url'] : '';
	$alt   = isset( $attrs['alt'] ) ? $attrs['alt'] : '';
	$id    = isset( $attrs['id'] ) ? $attrs['id'] : 0;

	if ( ! $url && $id ) {
		$url = wp_get_attachment_url( $id );
	}

	if ( ! $url ) {
		// Try to find src in innerHTML.
		preg_match( '/src="([^"]+)"/', $block['innerHTML'], $matches );
		if ( ! empty( $matches[1] ) ) {
			$url = $matches[1];
		}
	}

	if ( ! $url ) {
		return '';
	}

	// If alt is empty, try to find it in the rendered innerHTML.
	if ( ! $alt ) {
		preg_match( '/alt="([^"]*)"/i', $block['innerHTML'], $alt_matches );
		if ( ! empty( $alt_matches[1] ) ) {
			$alt = $alt_matches[1];
		}
	}

	// If alt is still empty, try to get it from the attachment metadata.
	if ( ! $alt && $id ) {
		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
	}

	return sprintf( '![%s](%s)', $alt, $url );
}

/**
 * Converts basic HTML to Markdown.
 */
function viney_markdown_export_html_to_md( $html ) {
	$md = $html;

	// Remove block comments.
	$md = preg_replace( '/<!--(.|\s)*?-->/', '', $md );

	// Clean up accordion UI elements (toggle icons/symbols like '+').
	$md = preg_replace( '/<span[^>]*class="[^"]*toggle-icon[^"]*"[^>]*>.*?<\/span>/is', '', $md );
	$md = preg_replace( '/<(button|svg)[^>]*>(.*?)<\/\1>/is', '$2', $md ); // Strip tags but keep content.
	$md = preg_replace( '/<svg[^>]*>.*?<\/svg>/is', '', $md ); // Actually svgs should be stripped entirely.

	// Headers.
	$md = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/is', "# $1\n\n", $md );
	$md = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/is', "## $1\n\n", $md );
	$md = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/is', "### $1\n\n", $md );
	$md = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/is', "#### $1\n\n", $md );
	$md = preg_replace( '/<h5[^>]*>(.*?)<\/h5>/is', "##### $1\n\n", $md );
	$md = preg_replace( '/<h6[^>]*>(.*?)<\/h6>/is', "###### $1\n\n", $md );

	// Images.
	$md = preg_replace_callback( '/<img[^>]+>/i', function( $matches ) {
		$img_tag = $matches[0];
		preg_match( '/src="([^"]+)"/i', $img_tag, $src_matches );
		preg_match( '/alt="([^"]*)"/i', $img_tag, $alt_matches );
		
		$src = $src_matches[1] ?? '';
		$alt = $alt_matches[1] ?? '';
		
		return $src ? sprintf( '![%s](%s)', $alt, $src ) : '';
	}, $md );

	// Bold/Italic/Strikethrough.
	$md = preg_replace( '/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $md );
	$md = preg_replace( '/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $md );
	$md = preg_replace( '/<(del|s|strike)[^>]*>(.*?)<\/\1>/is', '~~$2~~', $md );

	// Links.
	$md = preg_replace( '/<a[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '[$2]($1)', $md );

	// Code.
	$md = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $md );
	$md = preg_replace( '/<pre[^>]*>(.*?)<\/pre>/is', "```\n$1\n```\n\n", $md );

	// Line breaks.
	$md = preg_replace( '/<br\s*\/?>/i', "\n", $md );

	// Paragraphs.
	$md = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $md );

	// Strip remaining tags.
	$md = strip_tags( $md );

	// Decode entities.
	$md = html_entity_decode( $md );

	return trim( $md );
}
