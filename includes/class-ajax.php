<?php
/**
 * AJAX handlers class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MenuMaster_Ajax {
    
    const IMPORT_BATCH_SIZE = 25; // Number of rows to process per batch
    
    public function __construct() {
        add_action('wp_ajax_menu_master_test_sheets_connection', array($this, 'test_sheets_connection'));
        add_action('wp_ajax_menu_master_get_sheets_headers', array($this, 'get_sheets_headers'));
        add_action('wp_ajax_menu_master_get_headers', array($this, 'get_headers'));
        add_action('wp_ajax_menu_master_save_column_mapping', array($this, 'save_column_mapping'));
        add_action('wp_ajax_menu_master_import_data', array($this, 'import_data'));
        add_action('wp_ajax_menu_master_get_catalog_data', array($this, 'get_catalog_data'));
        add_action('wp_ajax_menu_master_update_item', array($this, 'update_item'));
        add_action('wp_ajax_menu_master_delete_item', array($this, 'delete_item'));
        add_action('wp_ajax_menu_master_add_item', array($this, 'add_item'));
        add_action('wp_ajax_menu_master_get_column_mapping', array($this, 'get_column_mapping'));
        add_action('wp_ajax_menu_master_get_catalog_stats', array($this, 'get_catalog_stats'));
        add_action('wp_ajax_menu_master_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_menu_master_upload_image', array($this, 'upload_image'));
        add_action('wp_ajax_menu_master_cleanup_test_image', array($this, 'cleanup_test_image'));
        
        // Image review handlers
        add_action('wp_ajax_menu_master_next_review', array($this, 'get_next_review'));
        add_action('wp_ajax_menu_master_resolve_review', array($this, 'resolve_review'));
        
        // Column mapping handlers
        add_action('wp_ajax_menu_master_suggest_mapping', array($this, 'suggest_mapping'));
        
        // New handlers for Import Preview and Images
        add_action('wp_ajax_menu_master_preview_import', array($this, 'preview_import'));
        add_action('wp_ajax_menu_master_get_images', array($this, 'get_images'));
        add_action('wp_ajax_menu_master_get_image_stats', array($this, 'get_image_stats'));
        add_action('wp_ajax_menu_master_get_image_details', array($this, 'get_image_details'));
        add_action('wp_ajax_menu_master_delete_image', array($this, 'delete_image'));

        add_action('wp_ajax_menu_master_update_from_github', function() {
            check_ajax_referer('menu_master_nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            // Use master branch instead of main
            $github_zip = 'https://github.com/Calabalac/menu-master/archive/refs/heads/master.zip';
            $tmp = download_url($github_zip);
            
            if (is_wp_error($tmp)) {
                MenuMaster_Logger::error('GitHub update: download error', ['error' => $tmp->get_error_message()]);
                wp_send_json_error('Download error: ' . $tmp->get_error_message());
            }
            
            $unzip_dir = WP_CONTENT_DIR . '/uploads/menu-master-update';
            if (!wp_mkdir_p($unzip_dir)) {
                MenuMaster_Logger::error('GitHub update: cannot create temp dir', ['dir' => $unzip_dir]);
                wp_send_json_error('Cannot create temp dir');
            }
            
            $result = unzip_file($tmp, $unzip_dir);
            @unlink($tmp);
            
            if (is_wp_error($result)) {
                MenuMaster_Logger::error('GitHub update: unzip error', ['error' => $result->get_error_message()]);
                wp_send_json_error('Unzip error: ' . $result->get_error_message());
            }
            
            // Use master branch folder name
            $src = $unzip_dir . '/menu-master-master/';
            $dst = plugin_dir_path(__FILE__) . '../';
            
            if (!is_dir($src)) {
                MenuMaster_Logger::error('GitHub update: source dir not found', ['src' => $src]);
                wp_send_json_error('Source dir not found');
            }
            
            // Copy files over current plugin
            $errors = [];
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
            foreach ($rii as $file) {
                if ($file->isDir()) continue;
                $rel = str_replace($src, '', $file->getPathname());
                $target = $dst . $rel;
                if (!is_dir(dirname($target))) wp_mkdir_p(dirname($target));
                if (!copy($file->getPathname(), $target)) {
                    $errors[] = $rel;
                }
            }
            
            // Clean up temporary files
            $this->recursive_rmdir($unzip_dir);
            
            if ($errors) {
                MenuMaster_Logger::error('GitHub update: copy errors', ['files' => $errors]);
                wp_send_json_error('Copy errors: ' . implode(', ', $errors));
            }
            
            MenuMaster_Logger::info('GitHub update: success');
            wp_send_json_success('Plugin updated successfully from GitHub!');
        });
    }
    
    /**
     * Recursively remove directory
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Test Google Sheets connection
     */
    public function test_sheets_connection() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']);
        
        $result = MenuMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success(array(
                'message' => 'Connection successful',
                'headers' => $result['headers'],
                'row_count' => count($result['data'])
            ));
        }
    }
    
    /**
     * Get headers from Google Sheets
     */
    public function get_sheets_headers() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']);
        
        $result = MenuMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success($result['headers']); // Only send headers
        }
    }
    
    /**
     * Get headers from Google Sheets (alternative endpoint)
     */
    public function get_headers() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $google_sheet_url = sanitize_url($_POST['google_sheet_url']);
        
        if (empty($google_sheet_url)) {
            wp_send_json_error('Google Sheets URL is required');
        }

        try {
            $importer = new MenuMaster_Importer();
            $csv_data = $importer->fetch_csv_data($google_sheet_url);
            
            if (empty($csv_data)) {
                wp_send_json_error('No data found in the spreadsheet');
            }

            $headers = array_shift($csv_data); // First row as headers
            
            wp_send_json_success(array(
                'headers' => $headers,
                'total_rows' => count($csv_data)
            ));
            
        } catch (Exception $e) {
            MenuMaster_Logger::error('Failed to get headers: ' . $e->getMessage());
            wp_send_json_error('Failed to load headers: ' . $e->getMessage());
        }
    }
    
    /**
     * Save column mapping
     */
    public function save_column_mapping() {
        MenuMaster_Logger::info('🔄 Save column mapping AJAX called');
        
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            MenuMaster_Logger::error('❌ Insufficient permissions for save column mapping');
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $mappings = json_decode(stripslashes($_POST['mappings']), true); // Decode JSON string
        
        MenuMaster_Logger::info('📊 Received data', array(
            'catalog_id' => $catalog_id,
            'mappings_raw' => $mappings,
            'post_data' => $_POST
        ));
        
        if (!$catalog_id || !is_array($mappings)) {
            MenuMaster_Logger::error('❌ Invalid data', array(
                'catalog_id' => $catalog_id,
                'mappings_is_array' => is_array($mappings),
                'mappings_type' => gettype($mappings)
            ));
            wp_send_json_error('Invalid data provided.');
        }
        
        // Sanitize mappings
        $clean_mappings = array();
        foreach ($mappings as $google_column => $data) {
            $catalog_column = sanitize_text_field($data['catalog_column']);
            if (!empty($google_column) && !empty($catalog_column)) {
                $clean_mappings[] = array(
                    'google_column' => sanitize_text_field($google_column),
                    'catalog_column' => $catalog_column
                );
            }
        }
        
        MenuMaster_Logger::info('✅ Clean mappings prepared', array(
            'clean_mappings' => $clean_mappings,
            'count' => count($clean_mappings)
        ));
        
        try {
            MenuMaster_Database::save_column_mapping($catalog_id, $clean_mappings);
            MenuMaster_Logger::info('✅ Column mapping saved successfully');
            
            wp_send_json_success(array(
                'message' => 'Mapping saved successfully!',
                'saved_mappings' => $clean_mappings // Return saved mappings
            ));
        } catch (Exception $e) {
            MenuMaster_Logger::error('❌ Database error saving column mapping', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error('Database error saving mapping.');
        }
    }
    
    /**
     * Get existing column mapping for catalog
     */
    public function get_column_mapping() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        if (!$catalog_id) {
            wp_send_json_error('Invalid Catalog ID.');
        }
        
        $mappings = MenuMaster_Database::get_column_mapping($catalog_id);
        
        // Format mappings for frontend
        $formatted_mappings = array();
        foreach ($mappings as $mapping) {
            $formatted_mappings[] = array(
                'google_column' => $mapping->google_column,
                'catalog_column' => $mapping->catalog_column
            );
        }
        
        wp_send_json_success(array(
            'mappings' => $formatted_mappings
        ));
    }
    
    /**
     * Import data from Google Sheets
     */
    public function import_data() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : self::IMPORT_BATCH_SIZE;
        $is_first_batch = isset($_POST['is_first_batch']) ? boolval($_POST['is_first_batch']) : false;
        
        if (!$catalog_id) {
            wp_send_json_error('Invalid Catalog ID.');
        }
        
        $catalog = MenuMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_send_json_error('Menu not found.');
        }
        
        $mappings = MenuMaster_Database::get_column_mapping($catalog_id);
        if (empty($mappings)) {
            wp_send_json_error('Please configure column mapping first.');
        }

        $transient_data_key = 'mm_import_data_' . $catalog_id;
        $transient_total_key = 'mm_import_total_' . $catalog_id;
        $transient_img_cache_key = 'mm_import_img_cache_' . $catalog_id;

        $all_data_rows = null;
        $headers = null;
        $total_items_in_sheet = 0;
        $processed_category_image_urls_cache = array();

        MenuMaster_Logger::info('Import: import_data called', [
            'catalog_id' => $catalog_id,
            'offset' => $offset,
            'batch_size' => $batch_size,
            'is_first_batch' => $is_first_batch
        ]);

        if ($is_first_batch) {
            MenuMaster_Logger::info('Import: First batch, fetching data from Google Sheets', [
                'sheet_url' => $catalog->google_sheet_url,
                'sheet_name' => $catalog->sheet_name
            ]);
            $import_result = MenuMaster_GoogleSheets::import_from_url($catalog->google_sheet_url, $catalog->sheet_name);
            MenuMaster_Logger::info('Import: Result from import_from_url', $import_result);
            if (isset($import_result['error'])) {
                MenuMaster_Logger::error('Import: Error from import_from_url', $import_result);
                wp_send_json_error($import_result['error']);
                return;
            }
            $headers = $import_result['headers'];
            $all_data_rows = $import_result['data'];
            $total_items_in_sheet = count($all_data_rows);
            MenuMaster_Logger::info('Import: Parsed headers and data', [
                'headers' => $headers,
                'first_rows' => array_slice($all_data_rows, 0, 3),
                'total_items_in_sheet' => $total_items_in_sheet
            ]);

            set_transient($transient_data_key, array('headers' => $headers, 'rows' => $all_data_rows), HOUR_IN_SECONDS);
            set_transient($transient_total_key, $total_items_in_sheet, HOUR_IN_SECONDS);
            set_transient($transient_img_cache_key, array(), HOUR_IN_SECONDS); // Initialize image cache

            MenuMaster_Database::clear_catalog_items($catalog_id);
            MenuMaster_Logger::info("Import: Cleared items for catalog {$catalog_id}. Total items from sheet: {$total_items_in_sheet}");
        } else {
            $cached_data = get_transient($transient_data_key);
            $total_items_in_sheet = get_transient($transient_total_key);
            $processed_category_image_urls_cache = get_transient($transient_img_cache_key) ?: array();
            MenuMaster_Logger::info('Import: Loaded from cache', [
                'cached_data_type' => gettype($cached_data),
                'total_items_in_sheet' => $total_items_in_sheet
            ]);
            if ($cached_data === false || $total_items_in_sheet === false) {
                MenuMaster_Logger::error('Import: Import session error, cache missing', [
                    'cached_data' => $cached_data,
                    'total_items_in_sheet' => $total_items_in_sheet
                ]);
                wp_send_json_error('Import session error. Please try again.');
                return;
            }
            $headers = $cached_data['headers'];
            $all_data_rows = $cached_data['rows'];
        }

        $current_chunk_of_rows = array_slice($all_data_rows, $offset, $batch_size);
        MenuMaster_Logger::info('Import: Processing batch', [
            'offset' => $offset,
            'batch_size' => $batch_size,
            'chunk_count' => count($current_chunk_of_rows)
        ]);
        $chunk_result = MenuMaster_GoogleSheets::process_data_chunk_for_import(
            $current_chunk_of_rows,
            $headers,
            $mappings,
            $catalog_id,
            $processed_category_image_urls_cache
        );

        MenuMaster_Database::insert_catalog_items($catalog_id, $chunk_result['items_for_db']);
        update_transient($transient_img_cache_key, $chunk_result['updated_image_cache'], HOUR_IN_SECONDS);

        $next_offset = $offset + count($current_chunk_of_rows);
        $has_more_batches = ($next_offset < $total_items_in_sheet);

        if (!$has_more_batches) {
            delete_transient($transient_data_key);
            delete_transient($transient_total_key);
            delete_transient($transient_img_cache_key);
            MenuMaster_Logger::info("Import: All batches processed for catalog {$catalog_id}. Total items imported: {$next_offset}");
            wp_send_json_success(array(
                'message' => sprintf('Import completed successfully! Total %d items imported.', $next_offset),
                'total_items' => $total_items_in_sheet,
                'processed_items' => $next_offset,
                'has_more' => false
            ));
        } else {
            MenuMaster_Logger::info("Import: Processed batch for catalog {$catalog_id}. Next offset: {$next_offset}");
            wp_send_json_success(array(
                'message' => sprintf('Processed %d/%d items...', $next_offset, $total_items_in_sheet),
                'total_items' => $total_items_in_sheet,
                'processed_items' => $next_offset,
                'has_more' => true,
                'next_offset' => $next_offset
            ));
        }
    }
    
    /**
     * Get catalog data (items and stats)
     */
    public function get_catalog_data() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 25;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $sort_column = sanitize_key($_POST['sort_column'] ?? 'product_id');
        $sort_direction = sanitize_key($_POST['sort_direction'] ?? 'asc');
        $filters_raw = json_decode(stripslashes($_POST['filters'] ?? ''), true);
        $filters = MenuMaster_Utils::sanitize_filters($filters_raw);

        if (!$catalog_id) {
            wp_send_json_error('Invalid Catalog ID');
        }

        $catalog = MenuMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_send_json_error('Catalog not found');
        }

        $items = MenuMaster_Database::get_catalog_items_modern($catalog_id, $limit, $offset, $search, $sort_column, $sort_direction, $filters);
        $total_items = MenuMaster_Database::get_catalog_items_count($catalog_id, $search, $filters);
            
        wp_send_json_success(array(
            'catalog' => $catalog,
            'items' => $items,
            'total_items' => $total_items,
            'current_offset' => $offset,
            'has_more' => ($offset + $limit < $total_items)
        ));
    }

    /**
     * Update a single item in the catalog
     */
    public function update_item() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $item_data = json_decode(stripslashes($_POST['item_data']), true);
        $item_id = intval($item_data['id']);
        $catalog_id = intval($item_data['catalog_id']);
    
        if (!$item_id || !$catalog_id || !is_array($item_data)) {
            MenuMaster_Logger::error('Invalid item data for update.', ['item_data' => $_POST['item_data']]);
            wp_send_json_error('Invalid item data.');
        }

        // Sanitize and prepare data for update
        $clean_data = array();
        foreach ($item_data as $key => $value) {
            if (in_array($key, ['id', 'catalog_id', 'created_at'])) continue; // Don't update these
            if (strpos($key, 'image_url') !== false) {
                $clean_data[$key] = sanitize_url($value); // Sanitize URLs
            } elseif (strpos($key, 'description') !== false) {
                $clean_data[$key] = sanitize_textarea_field($value); // Sanitize descriptions
            } else {
                $clean_data[$key] = sanitize_text_field($value); // Default to text field sanitization
            }
        }

        $clean_data['updated_at'] = current_time('mysql', 1);

        // Handle product image separately if it's updated
        if (isset($clean_data['product_image_url']) && !empty($clean_data['product_image_url'])) {
            $sku = $item_data['product_id'] ?? MenuMaster_Utils::generate_uuid(); // Use existing SKU or generate temp
            $local_image_url = MenuMaster_Images::get_instance()->process_image($clean_data['product_image_url'], $sku, 'product');
            $clean_data['product_image_url'] = $local_image_url;
        }

        // Handle category images separately if they are updated
        for ($i = 1; $i <= 3; $i++) {
            $cat_image_field = "category_image_{$i}";
            $cat_id_field = "category_id_{$i}";
            if (isset($clean_data[$cat_image_field]) && !empty($clean_data[$cat_image_field])) {
                $category_id = $item_data[$cat_id_field] ?? MenuMaster_Utils::generate_uuid();
                $local_cat_image_url = MenuMaster_Images::get_instance()->process_image(
                    $clean_data[$cat_image_field],
                    $category_id,
                    'category'
                );
                $clean_data[$cat_image_field] = $local_cat_image_url;
            }
        }
    
        $result = MenuMaster_Database::update_catalog_item($item_id, $clean_data);
    
        if ($result) {
            MenuMaster_Logger::info('Item updated successfully', ['item_id' => $item_id, 'data' => $clean_data]);
            wp_send_json_success('Item updated successfully.');
        } else {
            MenuMaster_Logger::error('Failed to update item', ['item_id' => $item_id, 'error' => $wpdb->last_error ?? 'Unknown']);
            wp_send_json_error('Failed to update item.');
        }
    }
    
    /**
     * Delete an item from the catalog
     */
    public function delete_item() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $item_id = intval($_POST['item_id']);
        $catalog_id = intval($_POST['catalog_id']); // Ensure catalog_id is passed

        if (!$item_id || !$catalog_id) {
            wp_send_json_error('Invalid item ID or catalog ID.');
        }

        // Optional: Get item details to delete associated images if needed
        $item = MenuMaster_Database::get_catalog_item($item_id);
        if ($item) {
            // Delete product image
            if (!empty($item->product_image_url)) {
                MenuMaster_Images::get_instance()->delete_image(basename($item->product_image_url), true);
            }
            // Delete category images
            for ($i = 1; $i <= 3; $i++) {
                $cat_image_field = "category_image_{$i}";
                if (!empty($item->{$cat_image_field})) {
                    MenuMaster_Images::get_instance()->delete_image(basename($item->{$cat_image_field}), false);
                }
            }
        }

        $result = MenuMaster_Database::delete_catalog_item($item_id);
        
        if ($result) {
            wp_send_json_success('Item deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete item.');
        }
    }
    
    /**
     * Add a new item to the catalog
     */
    public function add_item() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $item_data = json_decode(stripslashes($_POST['item_data']), true);
        $catalog_id = intval($item_data['catalog_id']);

        if (!$catalog_id || !is_array($item_data)) {
            MenuMaster_Logger::error('Invalid item data for add.', ['item_data' => $_POST['item_data']]);
            wp_send_json_error('Invalid item data.');
        }

        // Sanitize and prepare data for insert
        $clean_data = array('catalog_id' => $catalog_id);
        foreach ($item_data as $key => $value) {
            if (in_array($key, ['id', 'catalog_id', 'created_at', 'updated_at'])) continue; // Auto-generated/set
            if (strpos($key, 'image_url') !== false) {
                $clean_data[$key] = sanitize_url($value); // Sanitize URLs
            } elseif (strpos($key, 'description') !== false) {
                $clean_data[$key] = sanitize_textarea_field($value); // Sanitize descriptions
            } else {
                $clean_data[$key] = sanitize_text_field($value); // Default to text field sanitization
            }
        }

        // Generate product_id if not provided
        if (empty($clean_data['product_id'])) {
            $clean_data['product_id'] = MenuMaster_Utils::generate_uuid();
            MenuMaster_Logger::warning('Generated UUID for new item product_id.', ['generated_id' => $clean_data['product_id']]);
        }

        // Handle product image separately
        if (isset($clean_data['product_image_url']) && !empty($clean_data['product_image_url'])) {
            $local_image_url = MenuMaster_Images::get_instance()->process_image($clean_data['product_image_url'], $clean_data['product_id'], 'product');
            $clean_data['product_image_url'] = $local_image_url;
        }

        // Handle category images separately
        for ($i = 1; $i <= 3; $i++) {
            $cat_image_field = "category_image_{$i}";
            $cat_id_field = "category_id_{$i}";
            if (isset($clean_data[$cat_image_field]) && !empty($clean_data[$cat_image_field])) {
                $category_id = $clean_data[$cat_id_field] ?? MenuMaster_Utils::generate_uuid();
                $local_cat_image_url = MenuMaster_Images::get_instance()->process_image(
                    $clean_data[$cat_image_field],
                    $category_id,
                    'category'
                );
                $clean_data[$cat_image_field] = $local_cat_image_url;
            }
        }

        $clean_data['created_at'] = current_time('mysql', 1);
        $clean_data['updated_at'] = current_time('mysql', 1);

        $item_id = MenuMaster_Database::insert_catalog_item($clean_data);

        if ($item_id) {
            MenuMaster_Logger::info('Item added successfully', ['item_id' => $item_id, 'data' => $clean_data]);
            wp_send_json_success(array(
                'message' => 'Item added successfully.',
                'item_id' => $item_id
            ));
        } else {
            MenuMaster_Logger::error('Failed to add item', ['error' => $wpdb->last_error ?? 'Unknown']);
            wp_send_json_error('Failed to add item.');
        }
    }

    /**
     * Get catalog statistics
     */
    public function get_catalog_stats() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        if (!$catalog_id) {
            wp_send_json_error('Invalid Catalog ID.');
        }
        
        $total_items = MenuMaster_Database::get_catalog_items_count($catalog_id);
        $image_review_count = MenuMaster_Images::get_instance()->get_pending_reviews_count();
        
        wp_send_json_success(array(
            'total_items' => $total_items,
            'image_review_count' => $image_review_count
        ));
    }

    /**
     * Clear transients (cache)
     */
    public function clear_cache() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear import-related transients
        global $wpdb;
        $transient_keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_mm_import_%'");
        foreach ($transient_keys as $key) {
            delete_transient(str_replace('_transient_', '', $key));
        }

        MenuMaster_Logger::info('Cache cleared.');
        wp_send_json_success('Cache cleared successfully.');
    }

    /**
     * Sanitize filters array.
     * Ensures that only allowed keys are present and values are sanitized.
     * @param array $filters Raw filters array.
     * @return array Cleaned and sanitized filters array.
     */
    private function sanitize_filters($filters) {
        $clean_filters = [];
        $allowed_filter_keys = [
            'product_id',
            'product_name',
            'category_id_1',
            'category_id_2',
            'category_id_3',
            'category_name_1',
            'category_name_2',
            'category_name_3',
        ];

        if (!is_array($filters)) {
            return $clean_filters;
        }

        foreach ($filters as $key => $value) {
            if (in_array($key, $allowed_filter_keys)) {
                $clean_filters[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        return $clean_filters;
    }
    
    /**
     * Handle image upload via AJAX
     */
    public function upload_image() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
    
        if (!isset($_FILES['image_file']) || empty($_FILES['image_file']['tmp_name'])) {
            wp_send_json_error('No file uploaded.');
        }
    
        $catalog_id = intval($_POST['catalog_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
    
        if (!$catalog_id || empty($field_name)) {
            wp_send_json_error('Invalid request parameters.');
        }
    
        // Determine image type (product or category) based on field_name
        $image_type = (strpos($field_name, 'product_image') !== false) ? 'product' : 'category';
        
        // Get a base filename for the image. If item_id is available, try to use its product_id.
        // Otherwise, generate a unique ID.
        $filename_base = MenuMaster_Utils::generate_uuid(); // Default to UUID
        if ($item_id > 0) {
            $item = MenuMaster_Database::get_catalog_item($item_id);
            if ($item && !empty($item->product_id)) {
                $filename_base = $item->product_id;
            }
        }
    
        $temp_file_path = $_FILES['image_file']['tmp_name'];
    
        // Process and save the image
        $result_url = MenuMaster_Images::get_instance()->process_uploaded_image($temp_file_path, $catalog_id, $filename_base, $image_type);
    
        if ($result_url) {
            wp_send_json_success(array('url' => $result_url));
        } else {
            MenuMaster_Logger::error('Image upload failed.', ['file' => $_FILES['image_file']['name'], 'catalog_id' => $catalog_id]);
            wp_send_json_error('Image upload failed.');
        }
    }
    
    /**
     * Delete temporary image created during testing.
     */
    public function cleanup_test_image() {
        check_ajax_referer('menu_master_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $image_url = sanitize_url($_POST['image_url'] ?? '');
        $catalog_id = intval($_POST['catalog_id'] ?? 0);

        if (empty($image_url) || !$catalog_id) {
            wp_send_json_error('Invalid image URL or catalog ID provided.');
        }

        // Assuming product images by default for test cleanup for simplicity
        $is_sku = (strpos($image_url, '/SKU/') !== false);
        $deleted = MenuMaster_Images::get_instance()->delete_image(basename($image_url), $is_sku);

        if ($deleted) {
            MenuMaster_Logger::info('Test image cleaned up successfully.', ['url' => $image_url]);
            wp_send_json_success('Test image cleaned up.');
        } else {
            MenuMaster_Logger::warning('Failed to clean up test image (might not exist or permissions issue). ', ['url' => $image_url]);
            wp_send_json_error('Failed to clean up test image.');
        }
    }
    
    /**
     * Get next image review item
     */
    public function get_next_review() {
        check_ajax_referer('menu_master_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $next_review = MenuMaster_Images::get_instance()->get_pending_reviews(1, 0);

        if (!empty($next_review)) {
            wp_send_json_success($next_review[0]);
        } else {
            wp_send_json_success(array('message' => 'No pending reviews.', 'finished' => true));
        }
    }
    
    /**
     * Resolve image review
     */
    public function resolve_review() {
        check_ajax_referer('menu_master_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $review_id = intval($_POST['review_id']);
        $keep_remote = isset($_POST['keep_remote']) ? filter_var($_POST['keep_remote'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (!$review_id) {
            wp_send_json_error('Invalid review ID.');
        }

        $result = MenuMaster_Images::get_instance()->resolve_review($review_id, $keep_remote);

        if (is_wp_error($result)) {
            MenuMaster_Logger::error('Error resolving review.', ['review_id' => $review_id, 'error' => $result->get_error_message()]);
            wp_send_json_error($result->get_error_message());
        } else if ($result) {
            wp_send_json_success('Review resolved.');
        } else {
            MenuMaster_Logger::error('Unknown error resolving review.', ['review_id' => $review_id]);
            wp_send_json_error('Failed to resolve review due to an unknown error.');
        }
    }

    /**
     * Suggest column mapping based on similarity
     */
    public function suggest_mapping() {
        check_ajax_referer('menu_master_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']);
        $catalog_id = intval($_POST['catalog_id']);

        if (empty($sheet_url) || !$catalog_id) {
            wp_send_json_error('Invalid sheet URL or catalog ID.');
        }

        $result = MenuMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        $sheet_headers = $result['headers'];

        $catalog_columns = MenuMaster_Database::get_all_catalog_item_fields(); // Assuming this function exists and returns all possible catalog fields
        
        $suggested_mappings = MenuMaster_Utils::suggest_mapping($sheet_headers, $catalog_columns);

        wp_send_json_success(array('suggested_mappings' => $suggested_mappings));
    }
    
    /**
     * Preview import from Google Sheets
     */
    public function preview_import() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']) ?: 'Sheet1';
        
        MenuMaster_Logger::info('Import preview requested', [
            'sheet_url' => $sheet_url,
            'sheet_name' => $sheet_name
        ]);
        
        $result = MenuMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        
        if (isset($result['error'])) {
            MenuMaster_Logger::error('Import preview failed', ['error' => $result['error']]);
            wp_send_json_error($result['error']);
        } else {
            // Limit to first 100 rows for preview
            $preview_data = array_slice($result['data'], 0, 100);
            
            wp_send_json_success([
                'headers' => $result['headers'],
                'data' => $preview_data,
                'total_rows' => count($result['data'])
            ]);
        }
    }
    
    /**
     * Get images for gallery
     */
    public function get_images() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $upload_dir = wp_upload_dir();
        $images = [];
        
        // Get images from WordPress media library
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit'
        ]);
        
        foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            $metadata = wp_get_attachment_metadata($attachment->ID);
            $file_path = get_attached_file($attachment->ID);
            $file_size = $file_path && file_exists($file_path) ? filesize($file_path) : 0;
            
            $images[] = [
                'id' => $attachment->ID,
                'name' => $attachment->post_title ?: basename($url),
                'url' => $url,
                'size' => $this->format_file_size($file_size),
                'dimensions' => isset($metadata['width']) ? $metadata['width'] . 'x' . $metadata['height'] : 'Unknown',
                'type' => $attachment->post_mime_type,
                'date' => date('M j, Y', strtotime($attachment->post_date))
            ];
        }
        
        wp_send_json_success($images);
    }
    
    /**
     * Get image statistics
     */
    public function get_image_stats() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit'
        ]);
        
        $total_size = 0;
        $recent_count = 0;
        $current_month = date('Y-m');
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if ($file_path && file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
            
            if (date('Y-m', strtotime($attachment->post_date)) === $current_month) {
                $recent_count++;
            }
        }
        
        // Check for unused images (simplified - would need more complex logic for real usage tracking)
        $unused_count = 0; // Placeholder
        
        wp_send_json_success([
            'total' => count($attachments),
            'size' => $this->format_file_size($total_size),
            'recent' => $recent_count,
            'unused' => $unused_count
        ]);
    }
    
    /**
     * Get image details
     */
    public function get_image_details() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $image_id = intval($_POST['image_id']);
        $attachment = get_post($image_id);
        
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error('Image not found');
        }
        
        $url = wp_get_attachment_url($image_id);
        $metadata = wp_get_attachment_metadata($image_id);
        $file_path = get_attached_file($image_id);
        $file_size = $file_path && file_exists($file_path) ? filesize($file_path) : 0;
        
        // Check usage in menus (simplified)
        global $wpdb;
        $items_table = $wpdb->prefix . 'menu_master_items';
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $items_table WHERE image_url LIKE %s",
            '%' . basename($url) . '%'
        ));
        
        $usage = $usage_count > 0 ? "Used in $usage_count menu items" : "Not used in any menus";
        
        wp_send_json_success([
            'id' => $image_id,
            'name' => $attachment->post_title ?: basename($url),
            'url' => $url,
            'dimensions' => isset($metadata['width']) ? $metadata['width'] . 'x' . $metadata['height'] : 'Unknown',
            'file_size' => $this->format_file_size($file_size),
            'type' => $attachment->post_mime_type,
            'date' => date('M j, Y', strtotime($attachment->post_date)),
            'usage' => $usage
        ]);
    }
    
    /**
     * Delete image
     */
    public function delete_image() {
        check_ajax_referer('menu_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $image_id = intval($_POST['image_id']);
        
        if (wp_delete_attachment($image_id, true)) {
            wp_send_json_success('Image deleted successfully');
        } else {
            wp_send_json_error('Failed to delete image');
        }
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}