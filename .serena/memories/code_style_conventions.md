# WP Gmail Plugin - Code Style and Conventions

## PHP Code Conventions

### General Style
- PHP version: 7.2+ features allowed
- WordPress coding standards applied
- Class-based architecture with singleton pattern
- Main class: `WPGP_Plugin` with static instance

### Naming Conventions
- **Classes**: PascalCase with prefix `WPGP_` (e.g., `WPGP_Plugin`)
- **Methods**: snake_case (e.g., `validate_smtp()`, `parse_list()`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `OPTIONS_KEY`, `TRANSIENT_ERROR_NOTICE`)
- **Variables**: snake_case with `$` prefix (e.g., `$gmail_user`, `$from_email`)
- **WordPress hooks**: snake_case (e.g., `pre_wp_mail`, `admin_notices`)

### Code Organization
- Single main class handles all functionality
- Methods grouped by functionality:
  - Initialization: `instance()`, `hooks()`, `load_textdomain()`
  - Admin: `admin_menu()`, `admin_init()`, `render_settings()`
  - Mail: `pre_wp_mail()`, `validate_smtp()`
  - Utilities: `parse_list()`, `parse_mixed()`, `parse_one()`
  - Logging: `log()`, `log_path()`, `tail()`

### WordPress Integration
- Options stored with key: `wpgp_options`
- Transients for error notices: `wpgp_error_notice`
- Text domain: `wp-gmail-plugin`
- Nonce actions: `wpgp_test`, `wpgp_dl`, `wpgp_cl`
- Capability required: `manage_options`

### Security Practices
- App passwords encrypted before storage
- SMTP validation before saving settings
- Nonce verification for all admin actions
- Capability checks for admin functions
- Input sanitization and validation

## JavaScript Conventions
- Files in `assets/js/`
- Admin scripts: `admin-script.js`
- Frontend scripts: `script.js`

## CSS Conventions
- Files in `assets/css/`
- Admin styles: `admin-style.css`
- Frontend styles: `style.css`

## Localization
- Text domain: `wp-gmail-plugin`
- Translation functions: `__()`, `_e()`, `esc_html__()`
- Language files in `languages/` directory
- Supports Japanese and English

## File Headers
- Plugin header follows WordPress standards
- Version synced with package.json
- Proper PHPDoc comments for main class

## Build Conventions
- Source files remain unminified
- Production builds use `--optimize` flag
- Version numbers updated via npm scripts
- Distribution excludes development files