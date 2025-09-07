# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is "Markup by Attribute for WooCommerce" - a WordPress plugin that adds dynamic pricing markup/markdown to WooCommerce product attributes. It allows shop owners to set price modifiers on global product attributes (e.g., +$5 for blue color, +7.5% for X-Large size) that automatically apply to product variations.

**Current Version**: 4.3.8
**WordPress Compatibility**: 4.6+ (tested up to 6.8.3)
**WooCommerce Compatibility**: 3.0+ (tested up to 10.0.4)
**PHP Compatibility**: 7.4+ (tested up to 8.4.5)

## Architecture Overview

### Plugin Structure

The plugin follows WordPress coding standards and uses a PSR-4 compliant autoloader:

- **Main plugin file**: `markup-by-attribute-for-woocommerce.php` - Contains plugin initialization, constants, and hooks
- **Autoloader**: `autoloader.php` - PSR-4 compliant class autoloading
- **Source code**: `src/` directory with namespace `mt2Tech\MarkupByAttribute`
- **Languages**: `languages/` directory contains translation files (.po, .mo, .l10n.php)

### Namespace Organization

```
mt2Tech\MarkupByAttribute\
├── Backend\          # Admin-facing functionality
│   ├── Product       # Product editor integration
│   ├── ProductList   # Products list page modifications
│   ├── Settings      # WooCommerce settings integration
│   ├── Term          # Attribute term editor
│   └── Handlers\     # Price calculation handlers
├── Frontend\         # Customer-facing functionality  
│   └── Options       # Product option display
└── Utility\          # Shared utility classes
    ├── General       # Core utilities (singleton)
    ├── Notices       # Admin notices (singleton)
    └── Pointers      # WordPress admin pointers (singleton)
```

### Key Classes

- **Backend\Product**: Main product editor integration, handles AJAX and bulk actions
- **Backend\Settings**: WooCommerce settings API integration (extends `WC_Settings_API`)
- **Frontend\Options**: Modifies product variation dropdowns for customers
- **Utility\General**: Core utility functions (singleton pattern)

### Design Patterns

- **Singleton Pattern**: Used for utility classes (`General`, `Notices`, `Pointers`)
- **Non-Singleton**: `Backend\Product` cannot be singleton due to WordPress hook requirements
- **Handler Pattern**: Price calculations organized in `Backend\Handlers\` directory

## Development Workflow

### No Build System
This plugin has no build system, package.json, or composer.json. It's pure PHP following WordPress standards.

### Testing
No automated test framework is configured. Testing should be done manually in a WordPress/WooCommerce environment.

### File Organization
- PHP classes follow PSR-4 autoloading with lowercase filenames
- CSS files in `src/css/`
- JavaScript files in `src/js/`
- Translation files in `languages/`

## Key Plugin Functionality

### Core Features
1. **Attribute Markup**: Add fixed amounts ($5) or percentages (7.5%) to global product attributes
2. **Price Application**: Markup applied when using WooCommerce's "Set regular prices" bulk action
3. **Variation Descriptions**: Auto-generates price breakdown descriptions
4. **Frontend Display**: Shows price differences in product option dropdowns
5. **Bulk Updates**: Tools to reapply markups when values change

### Price Calculation Flow
1. User sets markup on global attribute terms (e.g., +$5 for "Blue" color)
2. Product variations are created using these attributes
3. When "Set regular prices" bulk action is used, markups are automatically applied
4. Price breakdown is added to variation description
5. Frontend shows modified prices in dropdown options

### Settings Integration
Plugin settings are integrated into WooCommerce's settings at:
`WooCommerce > Settings > Products > Markup-by-Attribute`

## Important Constants

```php
MT2MBA_VERSION = '4.3.8'
MT2MBA_INTERNAL_PRECISION = 6
MT2MBA_DEFAULT_MAX_VARIATIONS = 50
MT2MBA_TEXT_DOMAIN = 'markup-by-attribute-for-woocommerce'
```

## Code Conventions

### PHP Standards
- Follow WordPress PHP Coding Standards
- Use type hints for all parameters and return values
- Comprehensive PHPDoc comments
- //region tags for code organization
- Namespace `mt2Tech\MarkupByAttribute`

### WordPress Integration
- Uses WordPress hooks and filters extensively
- Integrates with WooCommerce's bulk actions and AJAX handlers
- Supports WordPress internationalization (i18n)
- Compatible with HPOS (High-Performance Order Storage)

### Security
- Always check `ABSPATH` to prevent direct access
- Sanitize all user inputs
- Use WordPress nonces for AJAX requests
- No hardcoded credentials or sensitive data

## Plugin Dependencies

### Required
- WordPress 4.6+
- WooCommerce 3.0+
- PHP 7.4+

### WordPress Features Used
- Custom post meta for storing markup data
- WordPress AJAX API
- WooCommerce settings API
- WordPress admin pointers
- WordPress localization system

## Debugging and Development

### Debug Mode
When `WP_DEBUG` is enabled, the plugin provides additional error information.

### AJAX Endpoints
- `handleMarkupReapplication` - Reapply markups to product variations
- `getFormattedBasePrice` - Get formatted base price for UI
- `mt2mba_refresh_general_panel` - Refresh product general panel

### Database Storage
- Markup values stored as term meta
- Product variation markup data stored as post meta
- Settings stored using WordPress options API

## Common Development Tasks

### Adding New Settings
1. Extend `Backend\Settings` class
2. Add setting definition to `get_form_fields()`
3. Update initialization logic if needed

### Modifying Price Calculations
- Core logic in `Utility\General` class
- Handler classes in `Backend\Handlers\` directory
- Validation functions centralized in utilities

### Frontend Modifications
- Customer-facing changes in `Frontend\Options`
- CSS modifications in `src/css/admin-style.css`
- JavaScript in `src/js/` directory

## Translation Support

The plugin supports internationalization with translations for:
- German (multiple variants)
- French
- Italian
- Polish
- Spanish
- Swedish

Translation files located in `/languages` directory with text domain `markup-by-attribute-for-woocommerce`.