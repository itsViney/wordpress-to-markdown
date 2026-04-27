# WordPress to Markdown

A lightweight WordPress plugin that exports your website content (posts, pages, and custom post types) into Markdown files, neatly organized into a handy ZIP archive.

Inspired by the [WordPress Export to Markdown](https://github.com/lonekorean/wordpress-export-to-markdown) command line tool.

## Features

- **Block-to-Markdown Parsing**: Converts standard WordPress blocks into clean Markdown with frontmatter.
- **Post Type Organization**: Automatically organizes exported files into subdirectories based on their post type (e.g., `posts/`, `pages/`).
- **Incremental Exports**: Tracks export history for each user so you can choose to only export new and updated posts.
- **ZIP Packaging**: All exported Markdown files are bundled into a single ZIP archive for easy download and portability.
- **Clean Output**: Automatically strips unnecessary HTML and UI elements during conversion.
- **Frontmatter**: Adds frontmatter to each Markdown file with post metadata, including title, date, author, terms and Yoast SEO data (if available).
- **Limit Exports**: Choose to only export the X most recent posts of each post type.
- **Generate llms.md**: Combine all of your site's content into a single `llms.md` file for easy reference by large language models.

## Installation

1. Download the latest version from the [releases](https://github.com/itsViney/wordpress-to-markdown/releases) page.
2. Upload the zip to the Plugins area in WordPress.
3. Activate the plugin.

## Usage

1. Navigate to **Tools > Export to Markdown** in WordPress.
2. Select the post types you wish to include in the export.
3. If you have exported previously, you can check the "Only export new and updated posts" option to perform an incremental export.
4. Click the **Export to Markdown** button.
5. The plugin will process your content and automatically trigger a download of the ZIP archive.

## Heads up!

* Users require the `export` capability to access the tool.
* I've only tested this on relatively small WordPress sites (under 1,000 posts). If you're exporting from a much larger site, you may need to increase the `WP_MAX_MEMORY_LIMIT` and `WP_MEMORY_LIMIT` constants in your `wp-config.php` file.
* SEO data won't be included in local/dev environments because Yoast SEO doesn't clean up its indexables properly in those environments.
* Should run fine in multisite environments, though not extensively tested. Each site is treated independently.
* Vibe-coded using Gemini 3 Flash in Antigravity.