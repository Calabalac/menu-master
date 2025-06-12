# Menu Master

**Menu Master** is a modern WordPress plugin for importing and managing restaurant menus from public Google Sheets links.

## ✨ Key Features

- ⚡️ Import menus from Google Sheets (public links only, no API required)
- 🧩 Minimalist UI with Bulma framework and glassmorphism design
- 🌙 Dark/Light theme toggle
- 🔄 One-click GitHub updates
- 📝 Column mapping support, batch import, and export functionality
- 🛡️ Security: Admin-only access with proper nonce verification

## 🚀 Quick Start

1. Install the plugin in `wp-content/plugins/menu-master`
2. Activate through WordPress admin
3. Create a menu, paste your public Google Sheets link
4. Configure column mapping and import data
5. Use "Update from GitHub" button on logs page for updates

## 📋 Requirements

- WordPress 5.0+
- PHP 7.4+
- GD extension (standard with most hosting)

## 🔧 Features

- **No API Keys Required**: Works only with public Google Sheets ("Anyone with the link can view")
- **No ImageMagick Dependency**: Uses only GD extension
- **Modern UI**: Glassmorphism design with dark/light themes
- **Comprehensive Logging**: Detailed import process logging
- **Batch Processing**: Import large datasets efficiently
- **Image Management**: Upload and manage product images
- **Export Options**: Export menu data in various formats
- **Column Mapping**: Flexible mapping between Google Sheets and menu fields

## 🛠️ Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/menu-master` directory
3. Activate through WordPress admin panel
4. Navigate to Menu Master in admin sidebar

## 📖 Usage

### Creating a Menu
1. Go to Menu Master → Add Menu
2. Enter menu name and description
3. Paste your public Google Sheets URL
4. Save the menu

### Importing Data
1. Open your menu for editing
2. Click "Import from Google Sheets"
3. Configure column mapping
4. Start the import process
5. Monitor progress in real-time

### Managing Items
- View all imported items in the data table
- Edit items directly in the interface
- Add new items manually
- Delete unwanted items
- Export data for backup

## 🔗 Google Sheets Setup

1. Create a Google Sheet with your menu data
2. Set sharing to "Anyone with the link can view"
3. Copy the share link
4. Use this link in Menu Master

**Supported URL formats:**
- `https://docs.google.com/spreadsheets/d/SHEET_ID/edit#gid=0`
- `https://docs.google.com/spreadsheets/d/SHEET_ID/edit?usp=sharing`

## 🎨 Themes

Menu Master includes built-in dark and light themes:
- Toggle between themes using the switcher in the header
- Theme preference is saved automatically
- Glassmorphism effects for modern appearance

## 🔄 Updates

Keep your plugin up-to-date with one-click GitHub updates:
1. Go to Menu Master → Logs and Debug
2. Click "Update from GitHub"
3. Plugin will download and install the latest version automatically

## 🐛 Troubleshooting

### Import Issues
- Ensure your Google Sheet is public
- Check that the URL is correct
- Verify column headers match expected format
- Review logs for detailed error messages

### Performance
- Large imports are processed in batches
- Monitor progress in the import interface
- Check server memory limits for very large datasets

## 📝 Changelog

### Version 0.1.0
- Initial release
- Public Google Sheets import
- Modern glassmorphism UI
- Dark/light theme support
- One-click GitHub updates
- Comprehensive logging system

## 📄 License

GPL v2 or later

## 🤝 Support

For support and bug reports, please visit our [GitHub repository](https://github.com/Calabalac/menu-master).

---

**Transform your restaurant menu management today!** 🍽️