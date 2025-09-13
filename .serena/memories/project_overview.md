# WP Gmail Plugin Project Overview

## Project Purpose
WordPress plugin that replaces WordPress's native `wp_mail()` function with Gmail SMTP for reliable email delivery. The plugin uses SMTP protocol (not Gmail API) to send emails through Gmail servers, providing a more reliable alternative to WordPress's default mail function.

## Tech Stack
- **Language**: PHP 7.2+ (main plugin code)
- **Platform**: WordPress 5.8+
- **Build Tools**: Node.js 14.0+ with npm
- **Package Manager**: npm for JavaScript dependencies
- **Build System**: Custom Node.js build tool (`build-tool.js`)
- **Mail Library**: PHPMailer (WordPress built-in)
- **Protocol**: SMTP (smtp.gmail.com)
- **Localization**: i18n support with Japanese and English translations

## Key Dependencies (npm)
- archiver: ^5.3.1 (for creating ZIP distributions)
- chalk: ^4.1.2 (CLI output formatting)
- fs-extra: ^11.1.1 (file system operations)
- glob: ^10.3.3 (file pattern matching)
- rimraf: ^5.0.1 (directory cleaning)
- yargs: ^17.7.2 (CLI argument parsing)

## Project Structure
- `wp-gmail-plugin.php` - Main plugin file with WPGP_Plugin class
- `uninstall.php` - WordPress uninstall handler
- `assets/` - CSS, JS, and SVG assets
- `languages/` - Translation files (.po, .mo)
- `includes/` - Reserved for future PHP classes
- `templates/` - Reserved for future templates
- `dist/` - Build output directory
- Build tools: `build-tool.js`, `build-plugin.ps1`, `build-plugin.bat`

## Repository
- Git repository: https://github.com/junjiq/wp-gmail-plugin.git
- Current version: 1.0.0
- License: GPL-2.0-or-later