# Menu Master

A modern WordPress plugin for importing and managing restaurant menus from public Google Sheets. Features a clean, minimalist interface with dark/light theme support and one-click GitHub updates.

## ✨ Features

- **📊 Google Sheets Integration** - Import menus directly from public Google Sheets (no API key required)
- **🎨 Modern UI** - Clean, minimalist interface with dark/light theme toggle
- **📱 Responsive Design** - Works perfectly on desktop, tablet, and mobile devices
- **🔄 One-Click Updates** - Update the plugin directly from GitHub with a single click
- **🖼️ Image Management** - Optional image downloading and processing
- **📤 Multiple Export Formats** - Export to CSV, JSON, XML, and Excel
- **🔍 Advanced Search & Filtering** - Find menu items quickly
- **📋 Column Mapping** - Flexible mapping of spreadsheet columns to menu fields
- **🚀 No Dependencies** - Works without ImageMagick or complex server requirements

## 🚀 Quick Start

### Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Navigate to **Menu Master** in your admin menu

### Basic Usage

1. **Create a Menu**
   - Go to Menu Master → Add Menu
   - Enter a name and description
   - Add your public Google Sheets URL

2. **Import Data**
   - Open your menu for editing
   - Go to the "Column Mapping" tab
   - Load headers from your Google Sheet
   - Map columns to menu fields
   - Switch to "Import Data" tab and start import

3. **Manage Images** (Optional)
   - After importing, use the "Download Images" button to save images locally
   - View and manage images in the Images section

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- `allow_url_fopen` enabled (for Google Sheets access)

## 🔧 Google Sheets Setup

### Making Your Sheet Public

1. Open your Google Sheet
2. Click **Share** → **Change to anyone with the link**
3. Set permission to **Viewer**
4. Copy the share URL

### Recommended Column Structure

| Column Name | Description | Required |
|-------------|-------------|----------|
| Name | Item name | ✅ |
| Description | Item description | ✅ |
| Price | Item price | ✅ |
| Category | Menu category | ✅ |
| Image URL | Link to item image | ❌ |
| Ingredients | List of ingredients | ❌ |
| Allergens | Allergen information | ❌ |
| Calories | Calorie count | ❌ |

## 🎨 Theme Support

Menu Master includes a beautiful theme system:

- **Light Theme** - Clean, bright interface
- **Dark Theme** - Easy on the eyes for extended use
- **Auto-Save** - Your theme preference is remembered
- **System Integration** - Follows WordPress admin color scheme

## 🔄 Updates

The plugin includes a built-in update system:

1. Go to any Menu Master page
2. Click the **Update from GitHub** button
3. Confirm the update
4. The plugin will automatically download and install the latest version

## 📊 Export Options

Export your menu data in multiple formats:

- **CSV** - Excel-compatible format with UTF-8 support
- **JSON** - Structured data for web applications
- **XML** - Standard markup format
- **Excel** - Native .xlsx format

## 🛠️ Advanced Features

### Debug Mode

Enable debug mode to troubleshoot issues:

1. Go to Menu Master → Logs and Debug
2. Click **Enable Debug**
3. View detailed logs of all operations

### Image Processing

- **Automatic Download** - Fetch images from URLs in your spreadsheet
- **Local Storage** - Images are stored in your WordPress uploads directory
- **No Compression** - Images are saved in their original quality
- **Batch Processing** - Download multiple images efficiently

### Column Mapping

The plugin intelligently maps your spreadsheet columns:

- **Auto-Detection** - Automatically suggests mappings based on column names
- **Flexible Mapping** - Map any column to any field
- **Required Fields** - Ensures essential data is mapped
- **Preview Mode** - See how your data will look before importing

## 🔍 Troubleshooting

### Common Issues

**Import not working?**
- Check that your Google Sheet is public
- Verify `allow_url_fopen` is enabled in PHP
- Check the debug logs for detailed error messages

**Images not downloading?**
- Ensure image URLs are publicly accessible
- Check file permissions in your uploads directory
- Verify your server can make outbound HTTP requests

**Theme toggle not working?**
- Clear your browser cache
- Check for JavaScript errors in browser console
- Ensure you're using a modern browser

### Getting Help

1. Enable debug mode and check the logs
2. Test with a simple Google Sheet first
3. Check the system status in the debug page
4. Verify all requirements are met

## 📝 License

This plugin is licensed under the GPL v2 or later.

## 🤝 Contributing

This is an open-source project. Contributions are welcome!

## 📞 Support

For support and feature requests, please use the GitHub repository.

---

**Menu Master** - Making restaurant menu management simple and efficient.