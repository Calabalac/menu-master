<?php
/**
 * Plugin Name: Menu Master
 * Plugin URI: https://github.com/Calabalac/menu-master
 * GitHub Plugin URI: https://github.com/Calabalac/menu-master
 * Description: Modern WordPress plugin for importing menus from public Google Sheets links. Minimal UI, one-click GitHub update, no ImageMagick.
 * Version: 0.1.0
 * Author: Calabalac
 * Text Domain: menu-master
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Network: false
 * 
 * @package MenuMaster
 * @version 0.1.0
 * @author Calabalac
 * @license GPL v2 or later
 * @updated YYYY-MM-DD
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// IDE Helper for WordPress functions (only for development)
if (!defined('ABSPATH') && file_exists(__DIR__ . '/includes/ide-helper.php')) {
    require_once __DIR__ . '/includes/ide-helper.php';
}

// Define plugin constants
define('MENU_MASTER_VERSION', '0.1.0');
define('MENU_MASTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MENU_MASTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MENU_MASTER_PLUGIN_FILE', __FILE__);

/**
 * Plugin activation function
 */
function menu_master_activate() {
    // Load required dependencies first
    require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
    require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-database.php';
    require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-images.php';
    
    // Initialize logger first
    MenuMaster_Logger::init();
    
    // Log activation
    MenuMaster_Logger::info('Plugin activation started');
    
    try {
        // Create database tables
        MenuMaster_Database::create_tables();
        
        // Initialize images directory
        MenuMaster_Images::get_instance();
        
        // Schedule cleanup task
        if (!wp_next_scheduled('menu_master_cleanup_temp')) {
            wp_schedule_event(time(), 'daily', 'menu_master_cleanup_temp');
        }
        add_action('menu_master_cleanup_temp', array(MenuMaster_Images::get_instance(), 'cleanup_temp_files'));
        
        // Log completion
        MenuMaster_Logger::info('Plugin activation completed successfully');
    } catch (Exception $e) {
        MenuMaster_Logger::error('Plugin activation failed: ' . $e->getMessage());
        throw $e; // Re-throw to prevent activation if there's an error
    }
}

/**
 * Plugin deactivation function  
 */
function menu_master_deactivate() {
    // Load logger for deactivation logging
    if (file_exists(MENU_MASTER_PLUGIN_PATH . 'includes/class-logger.php')) {
        require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
        MenuMaster_Logger::init();
        MenuMaster_Logger::info('Plugin deactivated');
    }
}

/**
 * Plugin uninstall function
 */
function menu_master_uninstall() {
    // Load required files for uninstall
    require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
    require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-database.php';
    
    // Initialize logger
    MenuMaster_Logger::init();
    MenuMaster_Logger::info('Plugin uninstall started');
    
    // Drop database tables
    MenuMaster_Database::drop_tables();
    
    // Clear logs (optional)
    MenuMaster_Logger::clear_logs();
    
    MenuMaster_Logger::info('Plugin uninstall completed');
}

// Load required classes in dependency order
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-logger.php';    // Logger must be first
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-utils.php';     // Utility functions
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-database.php';  // Database operations
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-images.php';    // Image handling
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-google-sheets.php'; // Google Sheets integration
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-exporter.php';  // Data export functionality
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-ajax.php';      // AJAX handlers
require_once MENU_MASTER_PLUGIN_PATH . 'includes/class-admin.php';     // Admin interface last

// Register activation/deactivation/uninstall hooks
register_activation_hook(__FILE__, 'menu_master_activate');
register_deactivation_hook(__FILE__, 'menu_master_deactivate');
register_uninstall_hook(__FILE__, 'menu_master_uninstall');

// Main plugin class
class MenuMaster {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
      public function init() {
        // Initialize plugin components
        $this->includes();
        $this->init_hooks();
    }    private function includes() {
        // Classes are already loaded at the top of the file
        // Initialize logger
        MenuMaster_Logger::init();
    }
    
    private function init_hooks() {
        // Initialize admin interface
        if (is_admin()) {
            new MenuMaster_Admin();
        }
        
        // Initialize AJAX handlers
        new MenuMaster_Ajax();
        
        // Initialize Exporter
        new MenuMaster_Exporter();
    }
}

// Initialize the plugin
MenuMaster::get_instance();