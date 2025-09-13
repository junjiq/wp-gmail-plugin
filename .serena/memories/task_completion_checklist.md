# Task Completion Checklist

When completing any development task on the WP Gmail Plugin, follow these steps:

## Before Starting
1. Review CLAUDE.md for project-specific instructions
2. Check existing code style and conventions
3. Understand the WordPress hooks and filters being used

## During Development
1. Follow WordPress coding standards
2. Use existing patterns and utilities (don't reinvent)
3. Maintain backward compatibility
4. Add proper error handling
5. Use WordPress i18n functions for all user-facing text
6. Never commit secrets or passwords

## After Code Changes

### 1. Validation
```bash
# Validate files without building
npm run validate
```

### 2. Build Testing
```bash
# Test development build
npm run build:dev

# For production release
npm run build:prod
```

### 3. Manual Testing
- Enable plugin in WordPress admin
- Test the specific feature/fix
- Verify Gmail SMTP connection works
- Check error handling
- Test with different email types (HTML, plain text, attachments)
- Review logs if logging is enabled

### 4. Check Logs
- Location: `wp-content/uploads/wpgp-logs/wpgp-mail.log`
- Verify no errors in WordPress debug.log
- Check for PHP warnings or notices

### 5. Version Update (if needed)
```bash
# For bug fixes
npm run version:patch

# For new features
npm run version:minor

# For breaking changes
npm run version:major
```

### 6. Final Checks
- Ensure no debugging code remains
- Verify translation strings are properly wrapped
- Check that uninstall.php handles cleanup
- Confirm dist/ contains proper ZIP structure

## Important Reminders
- **NO automated tests** - all testing is manual
- WordPress 5.8+ and PHP 7.2+ compatibility required
- Gmail requires app password, not regular password
- SMTP validation runs automatically on settings save
- Plugin completely replaces wp_mail() function