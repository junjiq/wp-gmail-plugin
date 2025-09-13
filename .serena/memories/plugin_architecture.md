# WP Gmail Plugin Architecture

## Core Architecture

### Main Class: WPGP_Plugin
Singleton pattern implementation that manages all plugin functionality.

#### Key Components:
1. **Hook System**
   - `pre_wp_mail` filter - Intercepts all WordPress email
   - `admin_menu` - Adds settings page
   - `admin_init` - Registers settings
   - `admin_notices` - Shows error notifications

2. **Mail Handling Flow**
   ```
   wp_mail() call → pre_wp_mail filter → WPGP_Plugin::pre_wp_mail()
   → PHPMailer configuration → Gmail SMTP → Log result
   ```

3. **Settings Management**
   - Database key: `wpgp_options`
   - Auto-validation on save via `sanitize()` method
   - Encrypted password storage
   - Transient errors: `wpgp_error_notice`

4. **SMTP Configuration**
   - Server: smtp.gmail.com
   - Ports: 587 (TLS) or 465 (SSL)
   - Auth: Gmail address + App password
   - PHPMailer: WordPress built-in library

5. **Logging System**
   - Path: `wp-content/uploads/wpgp-logs/wpgp-mail.log`
   - Features: Rotation, retention days, max size
   - Levels: INFO, ERROR, WARNING
   - Admin controls: View, download, clear

## Data Flow

### Email Sending:
1. WordPress calls `wp_mail()`
2. Plugin intercepts via `pre_wp_mail` filter
3. Parses headers, recipients, attachments
4. Configures PHPMailer with Gmail SMTP
5. Sends email and logs result
6. Returns success/failure to WordPress

### Settings Update:
1. Admin submits settings form
2. `sanitize()` validates all inputs
3. `validate_smtp()` tests connection
4. Settings saved to database
5. Error/success notice displayed

## File Structure Purpose

### Active Components:
- `wp-gmail-plugin.php` - All plugin logic
- `uninstall.php` - Cleanup on uninstall
- `assets/` - Admin UI assets
- `languages/` - Translations

### Reserved for Future:
- `includes/` - PHP classes (currently unused)
- `templates/` - View templates (currently unused)

## Security Model
1. Capability checks (`manage_options`)
2. Nonce verification for actions
3. Password encryption
4. Input sanitization
5. SMTP validation before save

## Integration Points
- WordPress Options API
- WordPress Transients API
- WordPress Mail System
- WordPress Admin UI
- WordPress i18n System