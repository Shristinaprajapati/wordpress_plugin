# wordpress_plugin
Custom WordPress plugin with CSV upload and A-Z tabs display

## Features

- CSV file upload with Replace/Append options
- Custom database table creation
- A-Z tabs display matching Divi layout
- Responsive design for mobile devices
- Error handling and validation
- Transaction-safe database operations

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Perpetual Register' menu in admin sidebar
4. Upload your CSV file (format: Id, entry, lifeStats, sort)
5. Use shortcode `[perpetual_register_tabs]` in any page/post

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Usage

### Admin Interface
- Navigate to 'Perpetual Register' in admin menu
- Choose CSV file and select update method
- Click 'Upload and Process'

### Frontend Display
- Add shortcode `[perpetual_register_tabs]` to any page
- A-Z tabs appear with names grouped by first two letters

## CSV Format

Your CSV must have these headers:
