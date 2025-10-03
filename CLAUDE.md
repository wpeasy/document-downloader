# Document Downloader WordPress Plugin

## Purpose

This WordPress plugin provides a document download management system with search functionality. It allows users to:

- Upload and manage documents (PDF, Office docs, images) via a custom post type
- Search documents through a frontend shortcode with AlpineJS-powered interface  
- List all documents with client-side filtering using the document list shortcode
- Download documents with optional email gate for lead capture
- Track downloads with logging to database
- Organize documents with custom taxonomy (Document Types)

The plugin stores documents in `/wp-content/uploads/documents/` and provides responsive interfaces that can be embedded anywhere using `[wpe_document_search]` (search interface) or `[wpe_document_list]` (document listing) shortcodes.

## Architecture

**Namespace**: `WP_Easy\DocumentDownloader`

**Custom Post Type**: `dd_document` 
**Taxonomy**: `document_type`
**Meta Key**: `_dd_file_id` (stores attachment ID)

### Core Classes

- `CPT` - Registers custom post type and taxonomy
- `Meta` - Handles file upload metabox and media library integration
- `Settings` - Admin settings page with CSS customization
- `Shortcode` - Frontend search and list interface rendering and asset management
- `REST_API` - Search endpoint and download logging API
- `Admin_Downloads` - Download tracking admin interface
- `Instructions` - Instructions admin page with documentation
- `Activator` - Plugin activation hooks

## Code Style Guidelines

### PHP Conventions

1. **Namespace**: All classes use `WP_Easy\DocumentDownloader` namespace
2. **Class Structure**: Final classes with static methods for WordPress hooks
3. **Security**: Always use `defined('ABSPATH') || exit;` at top of files
4. **Sanitization**: Extensive use of WordPress sanitization functions
5. **Nonces**: WordPress nonces for security, custom `doc_search_query` nonce for REST API
6. **Constants**: Plugin paths defined as constants (`DD_PLUGIN_PATH`, `DD_PLUGIN_URL`, `DD_META_KEY`)

### Method Patterns

- `init()`: Static method to register WordPress hooks
- `render()`, `handle_*()`: Methods that output HTML or handle requests
- Private helper methods prefixed with underscore when appropriate
- Extensive parameter validation and type checking

### Database

- Custom table: `{prefix}das_downloads` for tracking downloads
- Uses WordPress transients for rate limiting
- Prepared statements for all database operations

### Frontend Assets

1. **JavaScript**: Vanilla JS with AlpineJS integration, no jQuery dependency except admin
2. **CSS**: BEM methodology with `dd__` prefix, uses CSS cascade layers (`@layer dd`)
3. **Icons**: Inline SVG icons with `currentColor` for theme compatibility
4. **Responsive**: Mobile-first design approach

### Security Practices

- Rate limiting (30 requests per 60 seconds per IP)
- Same-origin enforcement in REST API
- Nonce validation on all endpoints
- File upload restrictions to documents directory
- Sanitization of all user inputs
- No direct file access (`.htaccess` protection)

### WordPress Integration

- Follows WordPress coding standards
- Uses WordPress APIs extensively (Settings API, REST API, Custom Post Types)
- Translation ready with `document-downloader` text domain
- Hooks into WordPress media library for file management
- Compatible with WordPress multisite

### Development Features

- CodeMirror 6 integration for CSS editing in admin
- Composer autoloading (PSR-4)
- Graceful fallbacks (Alpine.js optional, CSS editor fallback to textarea)
- Extensive error handling and validation

## Configuration

### Key Settings

- `require_email`: Enable/disable email gate for downloads
- `notification_email`: Email for download notifications  
- `disable_alpine`: Option to disable AlpineJS loading
- `frontend_css`: Customizable CSS for search interface
- Post type labels (plural/singular names)

### File Types Supported

- PDF documents
- Microsoft Office (DOC, DOCX, XLS, XLSX) 
- Images (JPG, PNG, GIF, WebP, SVG)

### Shortcode Usage

```php
[wpe_document_search] // Search interface (shows results after 3+ characters)
[wpe_document_list] // Document listing (shows all documents, filters as you type)
[wpe_document_search tax="type1,type2"] // Filter search by taxonomy
[wpe_document_list tax="type1"] // Filter list by taxonomy

// Pagination examples
[wpe_document_list paginate="true" rows_per_page="20" show_pagination="true"]
[wpe_document_search id="my-search" paginate="true" show_pagination="false"]
[wpe_document_pagination target_id="my-search"] // External pagination control
```

### Pagination Parameters

Both shortcodes support comprehensive pagination:
- `id` - Unique identifier for external pagination linking
- `paginate` - Enable/disable pagination (default: false)  
- `rows_per_page` - Items per page (default: 50)
- `page_count` - Number of page links to show (default: 10)
- `show_pagination` - Show automatic pagination controls (default: true)

### REST Endpoints

- `POST /wp-json/document-downloader/v1/query` - Document search
- `POST /wp-json/document-downloader/v1/log` - Download logging

## Development Notes

- Plugin follows WordPress plugin standards
- Uses modern PHP features while maintaining 7.4 compatibility
- Frontend uses modern JavaScript (ES6+) with graceful degradation
- CSS uses modern features (Grid, Flexbox, CSS Custom Properties) with `@layer docSearch` for easy theme overrides
- Extensive use of WordPress core functions and APIs
- Alpine.js served locally from `assets/vendor/alpine.min.js`, CodeMirror uses CDN (esm.sh)
- Role-based access control: Settings for administrators, other features for editors and above

## ZIP Creation for WordPress Plugin Installation

When using `/commit-version`, the ZIP file creation process ensures proper WordPress plugin installation:

### Requirements
- **Forward slash separators** - Essential for cross-platform compatibility and Linux extraction
- **Plugin directory structure** - All files must be under `document-downloader/` root directory
- **Excluded files** - Dotfiles, composer files, CHANGELOG.md, CLAUDE.md, PowerShell scripts, existing ZIPs

### PowerShell Script Template
```powershell
# WordPress Plugin ZIP Creator with forward slash separators
$pluginFolderName = Split-Path (Get-Location) -Leaf
$zipPath = Join-Path (Get-Location) "$pluginFolderName.zip"

Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

# Add files with forward slash paths: "$pluginFolderName/" + ($relativePath -replace '\\', '/')
foreach ($file in $filesToInclude) {
    $zipEntryName = "$pluginFolderName/" + ($relativePath -replace '\\', '/')
    $entry = $zip.CreateEntry($zipEntryName)
    # Stream file content to entry
}
$zip.Dispose()
```

This ensures the ZIP extracts to a single `document-downloader/` folder suitable for WordPress plugin installation.

## WordPress Theme Compatibility - wptexturize Issue

### Problem
WordPress block themes (especially Twenty Twenty-Five) apply `wptexturize()` to content, which converts straight quotes to curly quotes and breaks Alpine.js attributes in shortcode output.

### Root Cause
1. **Classic themes**: Apply `the_content` filter with wptexturize at priority 10, before do_shortcode at priority 11. Works fine.
2. **Block themes (2024)**: Use `<!-- wp:post-content /-->` which calls wptexturize through filters. Generally works.
3. **Block themes (2025)**: Use pattern blocks (`<!-- wp:pattern /-->`) that trigger `template-part.php` which calls `wptexturize()` **DIRECTLY** without filters.

The direct call in `wp-includes/blocks/template-part.php:158` bypasses:
- `run_wptexturize` filter
- `no_texturize_shortcodes` filter

### Solution
Wrap shortcode output in `<script type="text/template">` tags:

```php
return '<div id="doc-search-container-' . esc_attr($unique_id) . '"></div>
<script type="text/template" id="doc-search-template-' . esc_attr($unique_id) . '">
' . $html . '
</script>
<script>
(function() {
    var container = document.getElementById("doc-search-container-' . esc_js($unique_id) . '");
    var template = document.getElementById("doc-search-template-' . esc_js($unique_id) . '");
    if (container && template) {
        container.innerHTML = template.innerHTML;
        template.remove();
    }
})();
</script>';
```

**Why it works**: WordPress `wptexturize()` skips content inside `<script>` tags (see wp-includes/formatting.php). JavaScript moves the HTML from template to container after page load.

**Applied to**:
- `Shortcode::render_search()` (src/Shortcode.php:354-367)
- Should be applied to `render_list()` if same issue occurs

### Alternative Approaches Tested
- ❌ `run_wptexturize` filter - Not called when wptexturize() is invoked directly
- ❌ `no_texturize_shortcodes` filter - Only protects content BETWEEN shortcode tags, not output
- ❌ Base64 encoding - Works but adds complexity and requires ASCII-only content
- ✅ Script template wrapper - Clean, reliable, works across all themes