# Changelog

All notable changes to the Document Downloader WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.6(beta)] - 2025-09-08

### Confirmed
- Document results already sorted alphabetically by title for both search and list shortcodes via REST API endpoint

## [1.0.5(beta)] - 2025-09-08

### Fixed
- Downloads admin page reset button now properly clears filters and stays on downloads page instead of redirecting to CPT list page
- Fixed URL generation for downloads admin page actions (reset, export, clear log)

## [1.0.4(beta)] - 2025-09-08

### Added
- Comprehensive pagination system for both search and list shortcodes
- New `[wpe_document_pagination target_id="x"]` shortcode for custom pagination placement
- Pagination parameters: `id`, `paginate`, `rows_per_page`, `page_count`, `show_pagination`
- Client-side pagination with visible page ranges and prev/next navigation
- FOUC prevention for pagination controls with proper hiding/showing logic
- Accessibility support with ARIA attributes and semantic navigation markup
- External pagination linking - standalone pagination controls can target any shortcode instance

### Changed
- Pagination controls now appear inside styled result containers (with borders and shadows)
- Both search and list shortcodes support identical pagination functionality
- Updated Instructions page with comprehensive pagination documentation and examples
- Enhanced HTML structure with proper wrappers around pagination and list items
- Improved CSS with higher z-index for pagination controls and responsive design
- Search shortcode now properly resets all state when clearing input via X button

### Fixed
- Search pagination now hides properly when search is cleared or has no results
- Bottom pagination no longer disappears behind absolutely positioned result lists
- Pagination display logic correctly respects `show_pagination` parameter
- External pagination JavaScript properly handles wrapper structure and visibility
- Form validation and pagination state management work correctly together

### Technical
- Added `doc-search__list-items` class for cleaner HTML structure separation
- Implemented `doc-search__pagination-wrapper` for consistent layout control
- Enhanced JavaScript with proper pagination state management and external linking
- CSS improvements with dedicated pagination styling inside result containers
- Proper z-index layering (pagination: 1001, results: 1000) for correct display order

## [1.0.3(beta)] - 2025-09-08

### Added
- New `[wpe_document_list]` shortcode that displays all documents by default with client-side filtering
- Instructions admin page with comprehensive documentation for shortcodes, settings, and troubleshooting
- Role-based access control: Settings restricted to administrators, other features available to editors
- Form validation with visual feedback (red borders, validation messages) for download dialogs
- Required field indicators (*) and real-time validation for email, name, and phone fields
- Support for both search and list functionality with shared validation logic

### Changed
- Complete codebase refactoring from 'dd'/'das' to 'doc-search'/'docSearch' naming convention
- CSS layer renamed from `doc-search` to `docSearch` for better JavaScript compatibility
- BEM class methodology updated to use `doc-search__` prefix throughout
- Original shortcode now has `doc-search-search` class, new list shortcode uses `doc-search-list`
- CSS custom properties updated to `--doc-search-*` naming
- Removed legacy `dd dd--component` classes from HTML templates
- Admin Downloads page now accessible to editors (not just administrators)

### Fixed
- Document file uploader metabox now appears correctly (was completely missing)
- Fixed metabox registration using proper WordPress `add_meta_boxes` hook instead of custom action
- AlpineJS class binding syntax updated from object notation to ternary operators for validation
- CSS validation styles now use `!important` to override theme styles
- Form validation feedback now shows proper visual indicators
- JavaScript factory functions renamed and updated consistently across all files

### Technical
- REST API modified to support empty queries for document listing functionality
- JavaScript files renamed: `alpine-search.js` → `doc-search-alpine-search.js`, `alpine-list.js` → `doc-search-alpine-list.js`
- Enhanced debugging added to document uploader for troubleshooting
- Systematic naming convention applied across PHP classes, JavaScript variables, CSS classes, and HTML IDs

## [1.0.2(beta)] - Previous Release

### Added
- New "Notifications" settings tab for email customization
- Email notification templates with WYSIWYG editor and placeholder system
- Click-to-copy placeholders: `{file_name}`, `{title}`, `{email}`, `{date}`, `{url}`, `{ip}`
- HTML email support with `wpautop()` formatting for proper paragraph handling
- Excluded search text functionality with wildcard support
- Search input enhancements: spinner icon while loading and clear button
- "Clear Log" button in Downloads admin page with confirmation dialog
- Error handling for network connection issues in search
- Improved file upload directory handling to ensure all files go to `uploads/documents/`
- Downloads table now shows newest entries first (descending order)

### Changed
- Namespace updated from `WP_Easy\DocumentAddressSearch` to `WP_Easy\DocumentDownloader`
- Search results list now positioned absolutely with fade-in animation and custom scrollbar
- CSS margin for `.dd` class set to 0 for better layout control
- Notification email field visibility now controlled by "Notify by email" setting
- Enhanced input styling with wrapper for icons and proper z-index handling

### Fixed
- File upload filter now applies conditionally only for document post types
- Search results list properly hides when empty (was visible due to AlpineJS templates)
- Icons in search input remain visible when input has focus (z-index issue)
- AlpineJS expression error with aria-label resolved by proper string escaping
- Browser extension conflicts reduced through improved DOM manipulation
- Excluded search text now properly blocks search queries (not results) with correct whole-word matching

### Security
- Enhanced input sanitization and validation throughout the plugin
- Proper nonce verification for all admin actions
- Rate limiting maintained for API endpoints

## [1.0.1(beta)] - Initial Release

### Added
- Custom post type `das_document` for document management
- Document taxonomy `document_type` for organization
- File upload functionality with support for PDF, Office docs, and images
- Frontend search interface with AlpineJS integration
- REST API endpoints for search and download logging
- Email gate functionality for lead capture
- Download tracking with custom database table
- Admin interface for viewing and filtering download logs
- CSV export functionality for download data
- Customizable frontend CSS with CodeMirror editor
- Settings page with configurable options
- Translation ready with `document-downloader` text domain
- WordPress multisite compatibility

### Features
- Document storage in `/wp-content/uploads/documents/` directory
- Responsive search interface via `[wpe_document_search]` shortcode
- Rate limiting (30 requests per 60 seconds per IP)
- Same-origin enforcement for API security
- Comprehensive sanitization and security measures
- File type restrictions and MIME type validation
- Graceful fallbacks for JavaScript dependencies