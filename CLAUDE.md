# Document Downloader WordPress Plugin

## Purpose

This WordPress plugin provides a document download management system with search functionality. It allows users to:

- Upload and manage documents (PDF, Office docs, images) via a custom post type
- Search documents through a frontend shortcode with AlpineJS-powered interface  
- Download documents with optional email gate for lead capture
- Track downloads with logging to database
- Organize documents with custom taxonomy (Document Types)

The plugin stores documents in `/wp-content/uploads/documents/` and provides a responsive search interface that can be embedded anywhere using the `[wpe_document_search]` shortcode.

## Architecture

**Namespace**: `WP_Easy\DocumentDownloader`

**Custom Post Type**: `das_document` 
**Taxonomy**: `document_type`
**Meta Key**: `_dd_file_id` (stores attachment ID)

### Core Classes

- `CPT` - Registers custom post type and taxonomy
- `Meta` - Handles file upload metabox and media library integration
- `Settings` - Admin settings page with CSS customization
- `Shortcode` - Frontend search interface rendering and asset management
- `REST_API` - Search endpoint and download logging API
- `Admin_Downloads` - Download tracking admin interface
- `Activator` - Plugin activation hooks

## Code Style Guidelines

### PHP Conventions

1. **Namespace**: All classes use `WP_Easy\DocumentAddressSearch` namespace
2. **Class Structure**: Final classes with static methods for WordPress hooks
3. **Security**: Always use `defined('ABSPATH') || exit;` at top of files
4. **Sanitization**: Extensive use of WordPress sanitization functions
5. **Nonces**: WordPress nonces for security, custom `dd_query` nonce for REST API
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
[wpe_document_search] // Basic usage
[wpe_document_search tax="type1,type2"] // Filter by taxonomy
```

### REST Endpoints

- `POST /wp-json/document-downloader/v1/query` - Document search
- `POST /wp-json/document-downloader/v1/log` - Download logging

## Development Notes

- Plugin follows WordPress plugin standards
- Uses modern PHP features while maintaining 7.4 compatibility
- Frontend uses modern JavaScript (ES6+) with graceful degradation
- CSS uses modern features (Grid, Flexbox, CSS Custom Properties)
- Extensive use of WordPress core functions and APIs
- No external dependencies beyond WordPress and optional AlpineJS CDN