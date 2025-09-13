# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that replaces WordPress's native `wp_mail()` function with Gmail SMTP for reliable email delivery. The plugin uses SMTP protocol (not Gmail API) and supports Japanese/English localization.

## Common Development Commands

### Build Commands
```bash
# Install dependencies
npm install

# Build for development (no optimization)
npm run build:dev

# Build for production (with optimization)
npm run build:prod

# Clean build directory
npm run clean

# Validate files without building
npm run validate
```

### Version Management
```bash
# Increment patch version and build
npm run version:patch

# Increment minor version and build
npm run version:minor

# Increment major version and build
npm run version:major
```

## Architecture Overview

### Main Plugin Structure
- **Entry Point**: `wp-gmail-plugin.php` - Main plugin file containing the `WPGP_Plugin` class
- **Hook System**: Uses `pre_wp_mail` filter to completely replace WordPress mail functionality
- **Settings Storage**: Options stored in WordPress database with key `wpgp_options`
- **Security**: App passwords are encrypted before storage, automatic SMTP validation on settings save

### Key Components
1. **SMTP Integration**: Uses PHPMailer (WordPress built-in) with Gmail SMTP servers
2. **Logging System**: 
   - Logs stored in `wp-content/uploads/wpgp-logs/wpgp-mail.log`
   - Automatic log rotation when size exceeds configured limit
   - Configurable retention days (0-365)
3. **Admin Interface**: Settings page under WordPress Settings menu
4. **Localization**: Full i18n support with Japanese and English translations

### Critical Functions
- `pre_wp_mail()`: Main filter that intercepts and handles all WordPress email
- `validate_smtp()`: Validates Gmail SMTP connection before saving settings
- `write_log()`: Handles email logging with rotation
- `sanitize()`: Validates and sanitizes all settings with connection verification

## Important Configuration

### Gmail SMTP Settings
- **Server**: smtp.gmail.com
- **Ports**: 587 (TLS) or 465 (SSL)
- **Authentication**: Requires Google App Password (not regular password)
- **2FA Required**: Google account must have 2-factor authentication enabled

### Plugin Options Structure
```php
[
    'gmail_user'      => '', // Gmail address
    'gmail_pass'      => '', // App password (16 chars)
    'from_email'      => '', // From address (optional)
    'from_name'       => '', // From name
    'encryption'      => 'tls', // tls or ssl
    'port'            => 0, // Auto-detect based on encryption
    'logging_enabled' => true,
    'log_retain_days' => 14,
    'log_max_size_kb' => 1024,
    'notify_admin'    => true
]
```

## Testing and Validation

### Manual Testing
1. Enable plugin in WordPress admin
2. Configure Gmail settings with app password
3. Use test email feature in settings page
4. Check logs at `wp-content/uploads/wpgp-logs/wpgp-mail.log`

### No Automated Tests
This project currently has no automated test suite. All testing should be done manually in a WordPress environment.

## Deployment Notes

### Building for Distribution
The build process creates a WordPress-compatible ZIP file in the `dist/` directory:
- Excludes development files (node_modules, .git, build scripts)
- Updates version numbers in plugin header
- Optimizes assets when using `--optimize` flag

### Files Included in Distribution
- Main plugin file and uninstall.php
- includes/ directory (currently unused, reserved for future)
- templates/ directory (currently unused, reserved for future)  
- assets/ directory (CSS, JS, SVG icons)
- languages/ directory (translation files)

### WordPress Compatibility
- Requires WordPress 5.8+
- Requires PHP 7.2+
- Uses WordPress built-in PHPMailer library