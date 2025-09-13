# WP Gmail Plugin - Suggested Commands

## Development Commands

### Build Commands
```bash
# Install dependencies (required first time)
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
# Increment patch version (1.0.0 → 1.0.1) and build
npm run version:patch

# Increment minor version (1.0.0 → 1.1.0) and build
npm run version:minor

# Increment major version (1.0.0 → 2.0.0) and build
npm run version:major
```

### Alternative Build Commands
```bash
# Standard build (alias for build:dev)
npm run build

# Package for distribution (alias for build:prod)
npm run package
```

## Build System Details
- Development builds: Creates unoptimized ZIP in `dist/`
- Production builds: Creates optimized ZIP with `--optimize` flag
- Build output: `dist/wp-gmail-plugin-{version}.zip`
- Validation: Checks file existence without building

## Testing
- **Manual testing only** - No automated test suite
- Test in WordPress admin after enabling plugin
- Use test email feature in settings page
- Check logs at `wp-content/uploads/wpgp-logs/wpgp-mail.log`

## Git Commands
```bash
# Check status
git status

# Stage changes
git add .

# Commit changes
git commit -m "Your message"

# Push to remote
git push origin main
```

## System Commands (Linux)
```bash
# List files
ls -la

# Change directory
cd directory_name

# Search files
find . -name "*.php"

# Search in files
grep -r "search_term" .

# View file
cat filename.php

# Edit file
nano filename.php
```