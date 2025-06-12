<?php
/**
 * Class MenuMaster_Images
 * Handles image processing and URL rewriting
 */
class MenuMaster_Images {
    
    private static $instance = null;
    private $upload_dir;
    
    const SKU_DIR = 'SKU';
    const COLLECTIONS_DIR = 'COLLECTIONS';
    
    private function __construct() {
        // Set up base directory
        $wp_upload_dir = wp_upload_dir();
        $this->upload_dir = $wp_upload_dir['basedir'] . '/img-master/';
        
        // Create directories if they don't exist
        $this->init_directories();
        
        // Add URL rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Handle image requests
        add_action('parse_request', array($this, 'handle_image_request'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create required directories
     */
    private function init_directories() {
        $dirs = array(
            $this->upload_dir,
            $this->upload_dir . self::SKU_DIR,
            $this->upload_dir . self::COLLECTIONS_DIR,
            $this->upload_dir . 'temp'
        );
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                $created = wp_mkdir_p($dir);
                if (!$created) {
                    MenuMaster_Logger::error('Failed to create directory', ['dir' => $dir]);
                    continue;
                }
                
                // Create .htaccess to prevent direct access
                file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all");
                
                // Create index.php for additional security
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
                
                // Create web.config for IIS servers
                $web_config = '<?xml version="1.0" encoding="UTF-8"?>
                <configuration>
                    <system.webServer>
                        <security>
                            <requestFiltering>
                                <hiddenSegments>
                                    <add segment="." />
                                </hiddenSegments>
                            </requestFiltering>
                        </security>
                    </system.webServer>
                </configuration>';
                file_put_contents($dir . '/web.config', $web_config);
                
                MenuMaster_Logger::info('Created and secured directory', ['dir' => $dir]);
            }
        }
    }
    
    /**
     * Add rewrite rules for images
     */
    public function add_rewrite_rules() {
        // For SKU images (numbers only)
        add_rewrite_rule(
            '^([0-9]+\.(?:jpe?g|png|gif))$',
            'index.php?menu_master_image=$1&type=sku',
            'top'
        );
        
        // For collection images (any valid filename)
        add_rewrite_rule(
            '^([a-zA-Z0-9-_]+\.(?:jpe?g|png|gif))$',
            'index.php?menu_master_image=$1&type=collection',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'menu_master_image';
            $vars[] = 'type';
            return $vars;
        });
    }
    
    /**
     * Handle image request and serve the file
     */
    public function handle_image_request($wp) {
        $image = get_query_var('menu_master_image');
        $type = get_query_var('type');
        
        if (!empty($image)) {
            $dir = ($type === 'sku') ? self::SKU_DIR : self::COLLECTIONS_DIR;
            $file_path = $this->upload_dir . $dir . '/' . basename($image);
            
            if (file_exists($file_path)) {
                $mime = wp_check_filetype($file_path)['type'];
                header('Content-Type: ' . $mime);
                readfile($file_path);
                exit;
            }
        }
    }
    
    /**
     * Save image from URL
     */
    public function save_image_from_url($url, $is_sku = true, $filename = null) {
        if (empty($filename)) {
            $filename = basename($url);
        }
        
        // Determine directory
        $dir = $is_sku ? self::SKU_DIR : self::COLLECTIONS_DIR;
        $upload_path = $this->upload_dir . $dir . '/' . $filename;
        
        // Download and save image
        $image_data = wp_remote_get($url);
        if (is_wp_error($image_data)) {
            return false;
        }
        
        $image_content = wp_remote_retrieve_body($image_data);
        if (empty($image_content)) {
            return false;
        }
        
        // Save file
        if (file_put_contents($upload_path, $image_content)) {
            // Generate and return public URL
            return home_url($filename);
        }
        
        return false;
    }
    
    /**
     * Process image upload
     */
    public function handle_upload($file, $is_sku = true) {
        if (!empty($file['tmp_name'])) {
            $filename = sanitize_file_name($file['name']);
            $dir = $is_sku ? self::SKU_DIR : self::COLLECTIONS_DIR;
            $upload_path = $this->upload_dir . $dir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                return home_url($filename);
            }
        }
        return false;
    }
    
    /**
     * Delete image
     */
    public function delete_image($filename, $is_sku = true) {
        $dir = $is_sku ? self::SKU_DIR : self::COLLECTIONS_DIR;
        $file_path = $this->upload_dir . $dir . '/' . basename($filename);
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        return false;
    }
    
    /**
     * Compare remote image with local
     * @return array Image comparison result
     */
    public function compare_images($url, $filename, $is_sku = true) {
        $dir = $is_sku ? self::SKU_DIR : self::COLLECTIONS_DIR;
        $local_path = $this->upload_dir . $dir . '/' . basename($filename);
        
        // Check if local file exists
        if (!file_exists($local_path)) {
            return array(
                'exists' => false,
                'needs_review' => false
            );
        }
        
        // Get remote file info
        $remote_headers = get_headers($url, 1);
        if ($remote_headers === false) {
            return array(
                'error' => 'Cannot access remote file',
                'exists' => true,
                'needs_review' => false
            );
        }
        
        $remote_size = isset($remote_headers['Content-Length']) ? (int)$remote_headers['Content-Length'] : 0;
        $local_size = filesize($local_path);
        
        // Compare sizes
        if ($remote_size !== $local_size) {
            return array(
                'exists' => true,
                'needs_review' => true,
                'local_info' => $this->get_image_info($local_path),
                'remote_info' => array(
                    'size' => $remote_size,
                    'url' => $url
                )
            );
        }
        
        return array(
            'exists' => true,
            'needs_review' => false
        );
    }
    
    /**
     * Get image information
     */
    private function get_image_info($path) {
        $info = getimagesize($path);
        return array(
            'size' => filesize($path),
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'path' => $path
        );
    }
    
    /**
     * Save pending review
     */
    public function save_pending_review($sku, $local_path, $remote_url) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'menu_master_image_reviews',
            array(
                'sku' => $sku,
                'local_path' => $local_path,
                'remote_url' => $remote_url,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get pending reviews count
     */
    public function get_pending_reviews_count() {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}menu_master_image_reviews");
    }
    
    /**
     * Get pending reviews
     */
    public function get_pending_reviews($limit = 10, $offset = 0) {
        global $wpdb;
        $reviews = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}menu_master_image_reviews 
                ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        
        // Добавляем информацию о картинках
        foreach ($reviews as &$review) {
            $review['local_info'] = $this->get_image_info($review['local_path']);
            
            // Получаем информацию о удаленном файле
            $remote_headers = get_headers($review['remote_url'], 1);
            $review['remote_info'] = array(
                'size' => isset($remote_headers['Content-Length']) ? (int)$remote_headers['Content-Length'] : 0,
                'url' => $review['remote_url']
            );
        }
        
        return $reviews;
    }
    
    /**
     * Resolve review
     */
    public function resolve_review($review_id, $keep_remote = false) {
        global $wpdb;
        
        $review = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}menu_master_image_reviews WHERE id = %d",
                $review_id
            ),
            ARRAY_A
        );
        
        if (!$review) {
            return false;
        }
        
        if ($keep_remote) {
            // Загружаем удаленную картинку
            $this->save_image_from_url($review['remote_url'], true, basename($review['local_path']));
        }
        
        // Удаляем запись
        return $wpdb->delete(
            $wpdb->prefix . 'menu_master_image_reviews',
            array('id' => $review_id),
            array('%d')
        );
    }
    
    /**
     * Process image
     */
    public function process_image($url, $sku) {
        MenuMaster_Logger::info('Starting image processing', [
            'url' => $url,
            'sku' => $sku
        ]);
        
        try {
            // Image processing logic
            // ...existing code...
            
            MenuMaster_Logger::info('Image processing completed', [
                'sku' => $sku,
                'size' => $size,
                'dimensions' => $dimensions
            ]);
            return true;
        } catch (Exception $e) {
            MenuMaster_Logger::error('Image processing failed', [
                'error' => $e->getMessage(),
                'sku' => $sku,
                'url' => $url
            ]);
            return false;
        }
    }
    
    /**
     * Clean up temporary files older than 24 hours
     */
    public function cleanup_temp_files() {
        $temp_dir = $this->upload_dir . 'temp/';
        if (!is_dir($temp_dir)) {
            return;
        }

        MenuMaster_Logger::info('Starting temporary files cleanup');
        
        $files = glob($temp_dir . '*');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > 24 * 3600) {
                    if (unlink($file)) {
                        $deleted++;
                    } else {
                        MenuMaster_Logger::error('Failed to delete temp file', ['file' => $file]);
                    }
                }
            }
        }
        
        MenuMaster_Logger::info('Completed temp files cleanup', ['deleted' => $deleted]);
    }
}
