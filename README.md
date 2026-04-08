# WordPress to Markdown

A lightweight WordPress plugin that exports your website content (posts, pages, and custom post types) into Markdown files, neatly organized into a handy ZIP archive.

The parser is based largely on the [WordPress Export to Markdown](https://github.com/lonekorean/wordpress-export-to-markdown) command line tool.

*Vibe-coded using Gemini 3 Flash in Antigravity*

## Features

- **Block-to-Markdown Parsing**: Converts standard WordPress blocks into clean Markdown with frontmatter.
- **Post Type Organization**: Automatically organizes exported files into subdirectories based on their post type (e.g., `posts/`, `pages/`).
- **Incremental Exports**: Tracks export history for each user. After the first export, you can choose to only export content that has been added or modified since your last run.
- **ZIP Packaging**: All exported Markdown files are bundled into a single ZIP archive for easy download and portability.
- **Clean Output**: Automatically strips unnecessary HTML and UI elements during conversion.

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

**Note:** Users require the `export` capability to access the tool.