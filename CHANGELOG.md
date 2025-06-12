# Changelog

All notable changes to Menu Master will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-19

### 🎉 Initial Release

Menu Master is a modern WordPress plugin for importing and managing restaurant menus from public Google Sheets.

#### ✨ Features Added
- **Google Sheets Integration** - Import menus directly from public Google Sheets (no API key required)
- **Modern Minimalist UI** - Clean, flat design with WordPress admin color scheme
- **Dark/Light Theme Toggle** - iPhone-style theme switcher with localStorage persistence
- **Responsive Design** - Works perfectly on desktop, tablet, and mobile devices
- **One-Click GitHub Updates** - Update the plugin directly from GitHub with a single click
- **Image Management** - Optional image downloading and processing (separated from data import)
- **Multiple Export Formats** - Export to CSV, JSON, XML, and Excel
- **Advanced Search & Filtering** - Find menu items quickly with real-time search
- **Flexible Column Mapping** - Intelligent auto-mapping with manual override options
- **Comprehensive Logging** - Detailed debug logs with structured format
- **No Dependencies** - Works without ImageMagick or complex server requirements

#### 🔧 Technical Features
- **PHP 7.4+ Compatibility** - Modern PHP with backward compatibility
- **WordPress 5.0+ Support** - Compatible with latest WordPress versions
- **Secure AJAX Handlers** - Proper nonce verification and capability checks
- **Batch Processing** - Efficient handling of large datasets
- **Error Handling** - Comprehensive error reporting and recovery
- **Database Optimization** - Efficient queries and proper indexing
- **File Management** - Organized uploads directory structure

#### 🎨 User Interface
- **Tabbed Interface** - Organized workflow with clear navigation
- **Real-time Validation** - Instant feedback on form inputs
- **Progress Indicators** - Visual feedback for long-running operations
- **Notification System** - Beautiful toast notifications for user feedback
- **Modal Dialogs** - Clean confirmation and detail dialogs
- **Responsive Tables** - Mobile-friendly data display

#### 🛠️ Admin Features
- **Menu Management** - Create, edit, and delete menus
- **Import Preview** - Preview data before importing
- **Column Mapping** - Visual mapping interface with auto-suggestions
- **Image Gallery** - File manager-style image browser with multiple view modes
- **Debug Dashboard** - System status and detailed logging
- **Export Tools** - Multiple format export with proper encoding

#### 🔒 Security
- **Admin-Only Access** - Restricted to users with manage_options capability
- **Nonce Verification** - All AJAX requests properly secured
- **Input Sanitization** - All user inputs properly sanitized
- **SQL Injection Protection** - Prepared statements throughout
- **File Upload Security** - Proper file type and size validation

#### 📊 Data Management
- **Flexible Schema** - Support for various menu data structures
- **Data Validation** - Type checking and format validation
- **Bulk Operations** - Efficient batch processing
- **Data Export** - Multiple format support with proper encoding
- **Backup Support** - Easy data export for backup purposes

---

### 🚀 Getting Started

1. **Installation**: Upload to `/wp-content/plugins/` and activate
2. **Create Menu**: Add a new menu with your Google Sheets URL
3. **Map Columns**: Configure how your spreadsheet columns map to menu fields
4. **Import Data**: Import your menu data with real-time progress
5. **Manage Images**: Optionally download and manage menu item images

### 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- `allow_url_fopen` enabled (for Google Sheets access)
- Modern web browser with JavaScript enabled

### 🔗 Links

- [GitHub Repository](https://github.com/Calabalac/menu-master)
- [Documentation](README.md)
- [License](LICENSE)

---

*Menu Master - Making restaurant menu management simple and efficient.*