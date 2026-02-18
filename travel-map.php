<?php
/**
 * Plugin Name: WordPress Travel Map
 * Plugin URI: https://github.com/itandelin/Travel-Map
 * Description: 基于高德地图API的轻量级旅行博客地图插件，支持已去、想去、计划三种旅行状态标记。
 * Version: 1.0.1
 * Author: Mr. T
 * Author URI: https://www.74110.net/recommendation/wordpress-travel-map/
 * Text Domain: travel-map
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('TRAVEL_MAP_VERSION', '1.0.1');
define('TRAVEL_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRAVEL_MAP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TRAVEL_MAP_TEXT_DOMAIN', 'travel-map');

/**
 * WordPress Travel Map 主类
 */
class TravelMapPlugin {
    
    /**
     * 插件实例
     */
    private static $instance = null;
    private static $frontend_scripts_loaded = false;
    
    /**
     * 获取插件实例（单例模式）
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 插件激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('TravelMapPlugin', 'uninstall'));
        
        // WordPress 初始化钩子
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // 短代码注册（早期加载）
        add_shortcode('travel_map', array($this, 'render_map_shortcode'));
        
        // 在页面内容输出前检查是否有短代码
        add_filter('the_content', array($this, 'check_shortcode_and_enqueue_scripts'), 5);
        
        // 文章编辑增强
        add_action('add_meta_boxes', array($this, 'add_travel_map_meta_box'));
        add_action('save_post', array($this, 'save_travel_map_meta'));
        
        // AJAX 钩子
        add_action('wp_ajax_travel_map_get_markers', array($this, 'ajax_get_markers'));
        add_action('wp_ajax_nopriv_travel_map_get_markers', array($this, 'ajax_get_markers'));
        add_action('wp_ajax_travel_map_get_marker', array($this, 'ajax_get_marker'));
        add_action('wp_ajax_travel_map_save_marker', array($this, 'ajax_save_marker'));
        add_action('wp_ajax_travel_map_delete_marker', array($this, 'ajax_delete_marker'));
        add_action('wp_ajax_travel_map_get_post_info', array($this, 'ajax_get_post_info'));
        add_action('wp_ajax_nopriv_travel_map_get_post_info', array($this, 'ajax_get_post_info'));
        add_action('wp_ajax_travel_map_get_location_posts', array($this, 'ajax_get_location_posts'));
        add_action('wp_ajax_nopriv_travel_map_get_location_posts', array($this, 'ajax_get_location_posts'));
        add_action('wp_ajax_travel_map_bulk_delete', array($this, 'ajax_bulk_delete'));
        add_action('wp_ajax_travel_map_bulk_status', array($this, 'ajax_bulk_status'));
        add_action('wp_ajax_travel_map_export', array($this, 'ajax_export'));
        add_action('wp_ajax_travel_map_import', array($this, 'ajax_import'));
    }
    
    /**
     * 插件初始化
     */
    public function init() {
        // 加载文本域
        load_plugin_textdomain(TRAVEL_MAP_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 检查依赖
        $this->check_dependencies();
    }
    
    /**
     * 检查内容中是否有短代码并提前加载脚本
     */
    public function check_shortcode_and_enqueue_scripts($content) {
        global $post;
        
        // 检查当前文章内容是否包含 travel_map 短代码
        if ($post && has_shortcode($post->post_content, 'travel_map')) {
            // 提前加载前端脚本
            $this->enqueue_frontend_scripts();
        }
        
        return $content;
    }
    
    /**
     * 管理员初始化
     */
    public function admin_init() {
        // 注册设置
        $this->register_settings();
    }
    
    /**
     * 注册设置选项
     */
    private function register_settings() {
        register_setting('travel_map_settings', 'travel_map_api_key');
        register_setting('travel_map_settings', 'travel_map_security_key');
        register_setting('travel_map_settings', 'travel_map_default_zoom', array(
            'type' => 'integer',
            'default' => 4
        ));
        register_setting('travel_map_settings', 'travel_map_default_center', array(
            'type' => 'string',
            'default' => '35.0,105.0'
        ));
        register_setting('travel_map_settings', 'travel_map_show_filter_tabs', array(
            'type' => 'boolean',
            'default' => true
        ));
    }
    
    /**
     * 添加管理员菜单
     */
    public function admin_menu() {
        // 主菜单页面
        add_menu_page(
            __('Travel Map', TRAVEL_MAP_TEXT_DOMAIN),
            __('Travel Map', TRAVEL_MAP_TEXT_DOMAIN),
            'manage_options',
            'travel-map',
            array($this, 'admin_page_settings'),
            'dashicons-location-alt',
            30
        );
        
        // 设置子页面
        add_submenu_page(
            'travel-map',
            __('地图设置', TRAVEL_MAP_TEXT_DOMAIN),
            __('地图设置', TRAVEL_MAP_TEXT_DOMAIN),
            'manage_options',
            'travel-map',
            array($this, 'admin_page_settings')
        );
        
        // 坐标管理子页面
        add_submenu_page(
            'travel-map',
            __('坐标管理', TRAVEL_MAP_TEXT_DOMAIN),
            __('坐标管理', TRAVEL_MAP_TEXT_DOMAIN),
            'edit_posts',
            'travel-map-markers',
            array($this, 'admin_page_markers')
        );
    }
    
    /**
     * 设置页面
     */
    public function admin_page_settings() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $api_key = get_option('travel_map_api_key', '');
        $default_zoom = get_option('travel_map_default_zoom', 4);
        $default_center = get_option('travel_map_default_center', '35.0,105.0');
        $show_filter_tabs = get_option('travel_map_show_filter_tabs', true);
        
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/admin-settings.php';
    }
    
    /**
     * 坐标管理页面
     */
    public function admin_page_markers() {
        // 处理表单提交
        if (isset($_POST['action'])) {
            $this->handle_marker_action($_POST);
        }
        
        // 获取坐标列表
        $markers = $this->get_all_markers();
        
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/coordinates-list.php';
    }
    
    /**
     * 保存设置
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'travel_map_settings')) {
            wp_die(__('安全验证失败', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        // 基本设置
        update_option('travel_map_api_key', sanitize_text_field($_POST['api_key']));
        update_option('travel_map_security_key', sanitize_text_field($_POST['security_key'] ?? ''));
        update_option('travel_map_default_zoom', intval($_POST['default_zoom']));
        update_option('travel_map_default_center', sanitize_text_field($_POST['default_center']));
        update_option('travel_map_show_filter_tabs', isset($_POST['show_filter_tabs']));
        
        // 颜色设置
        if (isset($_POST['visited_color'])) {
            update_option('travel_map_visited_color', sanitize_hex_color($_POST['visited_color']));
        }
        if (isset($_POST['want_to_go_color'])) {
            update_option('travel_map_want_to_go_color', sanitize_hex_color($_POST['want_to_go_color']));
        }
        if (isset($_POST['planned_color'])) {
            update_option('travel_map_planned_color', sanitize_hex_color($_POST['planned_color']));
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('设置已保存', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
        });
    }
    
    /**
     * 前端脚本加载（原生JavaScript版本）
     */
    public function enqueue_frontend_scripts() {
        // 避免重复加载
        if (self::$frontend_scripts_loaded) {
            return;
        }
        
        $api_key = get_option('travel_map_api_key', '');
        $security_key = get_option('travel_map_security_key', '');
        
        // 设置脚本依赖关系（移除jQuery依赖）
        $script_dependencies = array();
        
        if (!empty($api_key)) {
            // 先加载安全密钥配置脚本
            if (!empty($security_key)) {
                wp_register_script(
                    'amap-security-config',
                    '',
                    array(),
                    TRAVEL_MAP_VERSION,
                    false
                );
                wp_enqueue_script('amap-security-config');
                wp_add_inline_script('amap-security-config', "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };");
                $script_dependencies[] = 'amap-security-config';
            }
            
            // 加载高德地图 API
            wp_enqueue_script(
                'amap-api',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}",
                $script_dependencies,
                TRAVEL_MAP_VERSION,
                false  // 在头部加载
            );
            // 添加高德地图API作为依赖
            $script_dependencies[] = 'amap-api';
        }
        
        // 加载插件脚本（无jQuery依赖）
        wp_enqueue_script(
            'travel-map-frontend',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map.js',
            $script_dependencies,
            TRAVEL_MAP_VERSION . '.' . time(),
            false  // 在头部加载
        );
        
        // 加载插件样式
        wp_enqueue_style(
            'travel-map-frontend',
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map.css',
            array(),
            TRAVEL_MAP_VERSION
        );
        
        // 本地化脚本
        wp_localize_script('travel-map-frontend', 'travelMapAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('travel_map_nonce'),
            'apiKey' => $api_key,
            'colors' => array(
                'visited' => get_option('travel_map_visited_color', '#FF6B35'),
                'want_to_go' => get_option('travel_map_want_to_go_color', '#3B82F6'),
                'planned' => get_option('travel_map_planned_color', '#10B981')
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
        
        // 标记为已加载
        self::$frontend_scripts_loaded = true;
    }
    
    /**
     * 管理员脚本加载
     */
    public function enqueue_admin_scripts($hook) {
        // 只在插件页面加载
        if (strpos($hook, 'travel-map') === false) {
            return;
        }
        
        $api_key = get_option('travel_map_api_key', '');
        $security_key = get_option('travel_map_security_key', '');
        
        $dependencies = array('jquery');
        
        if (!empty($api_key)) {
            // 先加载安全密钥配置脚本
            if (!empty($security_key)) {
                wp_register_script(
                    'amap-security-config-admin',
                    '',
                    array(),
                    TRAVEL_MAP_VERSION,
                    false
                );
                wp_enqueue_script('amap-security-config-admin');
                wp_add_inline_script('amap-security-config-admin', "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };");
                $dependencies[] = 'amap-security-config-admin';
            }
            
            wp_enqueue_script(
                'amap-api',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}",
                $dependencies,
                TRAVEL_MAP_VERSION,
                true
            );
            $dependencies[] = 'amap-api';
        }
        
        wp_enqueue_script(
            'travel-map-admin',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-admin.js',
            $dependencies,
            TRAVEL_MAP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'travel-map-admin',
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map-admin.css',
            array(),
            TRAVEL_MAP_VERSION
        );
        
        // 本地化管理员脚本
        wp_localize_script('travel-map-admin', 'travelMapAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('travel_map_nonce'),
            'apiKey' => $api_key
        ));
    }
    
    /**
     * 渲染地图短代码
     */
    public function render_map_shortcode($atts) {
        // 确保前端脚本已加载
        $this->enqueue_frontend_scripts();
        
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '500px',
            'zoom' => get_option('travel_map_default_zoom', 4),
            'center' => get_option('travel_map_default_center', '35.0,105.0'),
            'filter_tabs' => get_option('travel_map_show_filter_tabs', true),
            'status' => 'all'
        ), $atts, 'travel_map');
        
        $api_key = get_option('travel_map_api_key', '');
        if (empty($api_key)) {
            return '<div class="travel-map-error">' . __('请先配置高德地图API密钥', TRAVEL_MAP_TEXT_DOMAIN) . '</div>';
        }
        
        $map_id = 'travel-map-' . uniqid();
        
        ob_start();
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/map-shortcode.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: 获取标记点
     */
    public function ajax_get_markers() {
        // 清理输出缓冲区
        if (ob_get_level()) {
            ob_clean();
        }
        
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        $status = sanitize_text_field($_POST['status'] ?? 'all');
        $markers = $this->get_markers_by_status($status);
        
        wp_send_json_success($markers);
    }
    
    /**
     * AJAX: 保存标记点
     */
    public function ajax_save_marker() {
        // 清理输出缓冲区，避免调试信息干扰JSON响应
        if (ob_get_level()) {
            ob_clean();
        }
        
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $marker_data = array(
            'title' => sanitize_text_field($_POST['title']),
            'latitude' => floatval($_POST['latitude']),
            'longitude' => floatval($_POST['longitude']),
            'status' => sanitize_text_field($_POST['status']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'post_id' => intval($_POST['post_id'] ?? 0) ?: null,
            'visit_date' => !empty($_POST['visit_date']) ? sanitize_text_field($_POST['visit_date']) : null,
            'visit_count' => intval($_POST['visit_count'] ?? 1),
            'planned_date' => !empty($_POST['planned_date']) ? sanitize_text_field($_POST['planned_date']) : null,
            'wish_reason' => sanitize_textarea_field($_POST['wish_reason'] ?? ''),
            'priority_level' => intval($_POST['priority_level'] ?? 3),
            'marker_color' => sanitize_hex_color($_POST['marker_color'] ?? '') ?: '#FF6B35'
        );
        
        $marker_id = intval($_POST['marker_id'] ?? 0);
        
        if ($marker_id > 0) {
            // 更新现有标记点
            $result = $this->update_marker($marker_id, $marker_data);
            $message = __('标记点更新成功', TRAVEL_MAP_TEXT_DOMAIN);
            $error_message = __('标记点更新失败', TRAVEL_MAP_TEXT_DOMAIN);
        } else {
            // 新增标记点
            $result = $this->save_marker($marker_data);
            $message = __('标记点保存成功', TRAVEL_MAP_TEXT_DOMAIN);
            $error_message = __('标记点保存失败', TRAVEL_MAP_TEXT_DOMAIN);
        }
        
        if ($result !== false) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * AJAX: 获取文章信息
     */
    public function ajax_get_post_info() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error(__('文章不存在', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $post_data = array(
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
            'permalink' => get_permalink($post_id),
            'featured_image' => get_the_post_thumbnail_url($post_id, 'medium'),
            'date' => get_the_date('Y-m-d', $post_id)
        );
        
        wp_send_json_success($post_data);
    }
    
    /**
     * AJAX: 获取地点相关的文章列表
     */
    public function ajax_get_location_posts() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $location_name = sanitize_text_field($_POST['location_name']);
        
        // 根据坐标和地点名称找到对应的标记点
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        
        // 首先尝试精确匹配坐标和地点名称
        $marker = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_markers 
            WHERE title = %s 
            AND ABS(latitude - %f) < 0.0001 
            AND ABS(longitude - %f) < 0.0001
            LIMIT 1",
            $location_name, $latitude, $longitude
        ));
        
        $articles = array();
        
        if ($marker) {
            // 获取直接关联的文章（post_id字段）
            if ($marker->post_id) {
                $post = get_post($marker->post_id);
                if ($post && $post->post_status === 'publish') {
                    $articles[] = array(
                        'title' => $post->post_title,
                        'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
                        'permalink' => get_permalink($marker->post_id),
                        'featured_image' => get_the_post_thumbnail_url($marker->post_id, 'medium'),
                        'date' => get_the_date('Y-m-d', $marker->post_id)
                    );
                }
            }
            
            // 获取通过关联表关联的文章
            $related_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id FROM $table_post_markers WHERE marker_id = %d",
                $marker->id
            ));
            
            foreach ($related_posts as $related_post) {
                $post = get_post($related_post->post_id);
                if ($post && $post->post_status === 'publish') {
                    // 避免重复添加相同的文章
                    $exists = false;
                    foreach ($articles as $existing_article) {
                        if ($existing_article['permalink'] === get_permalink($post->ID)) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $articles[] = array(
                            'title' => $post->post_title,
                            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
                            'permalink' => get_permalink($post->ID),
                            'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium'),
                            'date' => get_the_date('Y-m-d', $post->ID)
                        );
                    }
                }
            }
        }
        
        wp_send_json_success($articles);
    }
    
    /**
     * AJAX: 获取单个标记点信息
     */
    public function ajax_get_marker() {
        // 清理输出缓冲区
        if (ob_get_level()) {
            ob_clean();
        }
        
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        $marker_id = intval($_POST['marker_id']);
        if (!$marker_id) {
            wp_send_json_error(__('标记点ID不能为空', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        $marker = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_markers WHERE id = %d",
            $marker_id
        ));
        
        if (!$marker) {
            wp_send_json_error(__('标记点不存在', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        wp_send_json_success($marker);
    }
    
    /**
     * AJAX: 删除标记点
     */
    public function ajax_delete_marker() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $marker_id = intval($_POST['marker_id']);
        if (!$marker_id) {
            wp_send_json_error(__('标记点ID不能为空', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $result = $this->delete_marker($marker_id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('标记点删除成功', TRAVEL_MAP_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(__('标记点删除失败', TRAVEL_MAP_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: 批量删除
     */
    public function ajax_bulk_delete() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $marker_ids = array_map('intval', $_POST['marker_ids']);
        $deleted_count = 0;
        
        foreach ($marker_ids as $marker_id) {
            if ($this->delete_marker($marker_id)) {
                $deleted_count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('已删除 %d 个标记点', TRAVEL_MAP_TEXT_DOMAIN), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }
    
    /**
     * AJAX: 批量状态修改
     */
    public function ajax_bulk_status() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $marker_ids = array_map('intval', $_POST['marker_ids']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if (!in_array($new_status, array('visited', 'want_to_go', 'planned'))) {
            wp_send_json_error(__('状态不正确', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $updated_count = 0;
        
        foreach ($marker_ids as $marker_id) {
            $result = $wpdb->update(
                $table_markers,
                array('status' => $new_status),
                array('id' => $marker_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('已更新 %d 个标记点状态', TRAVEL_MAP_TEXT_DOMAIN), $updated_count),
            'updated_count' => $updated_count
        ));
    }
    
    /**
     * AJAX: 导出数据
     */
    public function ajax_export() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $status = sanitize_text_field($_GET['status'] ?? 'all');
        $marker_ids = !empty($_GET['marker_ids']) ? explode(',', $_GET['marker_ids']) : array();
        
        $markers = $this->get_export_data($status, $marker_ids);
        
        if ($format === 'json') {
            $this->export_json($markers);
        } else {
            $this->export_csv($markers);
        }
    }
    
    /**
     * AJAX: 导入数据
     */
    public function ajax_import() {
        check_ajax_referer('travel_map_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('请选择文件', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        $file = $_FILES['import_file'];
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        $imported_count = 0;
        
        if ($file_ext === 'csv') {
            $imported_count = $this->import_csv($file['tmp_name']);
        } elseif ($file_ext === 'json') {
            $imported_count = $this->import_json($file['tmp_name']);
        } else {
            wp_send_json_error(__('不支持的文件格式', TRAVEL_MAP_TEXT_DOMAIN));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('已导入 %d 个标记点', TRAVEL_MAP_TEXT_DOMAIN), $imported_count),
            'count' => $imported_count
        ));
    }
    
    /**
     * 添加文章编辑 Meta Box
     */
    public function add_travel_map_meta_box() {
        $post_types = apply_filters('travel_map_post_types', array('post', 'page'));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'travel-map-coordinates',
                __('旅行地点坐标', TRAVEL_MAP_TEXT_DOMAIN),
                array($this, 'render_travel_map_meta_box'),
                $post_type,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * 检查依赖项
     */
    private function check_dependencies() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(__('Travel Map 需要 PHP 7.4 或更高版本，当前版本：%s', TRAVEL_MAP_TEXT_DOMAIN), PHP_VERSION) . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * 渲染 Meta Box 内容
     */
    public function render_travel_map_meta_box($post) {
        // 添加 nonce 字段
        wp_nonce_field('travel_map_meta_nonce', 'travel_map_meta_nonce');
        
        // 获取已关联的坐标
        $associated_markers = $this->get_post_markers($post->ID);
        
        // 获取所有坐标点
        $all_markers = $this->get_all_markers();
        
        // 获取API密钥配置
        $api_key = get_option('travel_map_api_key', '');
        
        // 在Meta Box中加载API和相关配置
        if (!empty($api_key)) {
            $security_key = get_option('travel_map_security_key', '');
            
            // 先加载安全密钥配置脚本
            if (!empty($security_key)) {
                wp_register_script(
                    'amap-security-config-metabox',
                    '',
                    array(),
                    TRAVEL_MAP_VERSION,
                    false
                );
                wp_enqueue_script('amap-security-config-metabox');
                wp_add_inline_script('amap-security-config-metabox', "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };");
            }
            
            // 加载高德地图API - 在头部加载以确保可用性
            wp_enqueue_script(
                'amap-api-meta-box',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}",
                !empty($security_key) ? array('amap-security-config-metabox') : array(),
                TRAVEL_MAP_VERSION,
                false  // 在头部加载
            );
        }
        
        // 确保jQuery已加载
        wp_enqueue_script('jquery');
        
        // 本地化配置给JavaScript使用
        wp_localize_script('jquery', 'travelMapConfig', array(
            'apiKey' => $api_key,
            'securityKey' => get_option('travel_map_security_key', ''),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('travel_map_nonce')
        ));
        
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/meta-box-coordinates.php';
    }
    
    /**
     * 保存 Meta Box 数据
     */
    public function save_travel_map_meta($post_id) {
        // 检查 nonce
        if (!isset($_POST['travel_map_meta_nonce']) || !wp_verify_nonce($_POST['travel_map_meta_nonce'], 'travel_map_meta_nonce')) {
            return;
        }
        
        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // 检查是否为自动保存
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // 处理坐标关联
        $selected_markers = isset($_POST['travel_map_markers']) ? array_map('intval', $_POST['travel_map_markers']) : array();
        
        // 更新关联关系
        $this->update_post_marker_associations($post_id, $selected_markers);
        
        // 处理新建坐标点
        if (!empty($_POST['new_marker_title']) && !empty($_POST['new_marker_latitude']) && !empty($_POST['new_marker_longitude'])) {
            $new_marker_data = array(
                'title' => sanitize_text_field($_POST['new_marker_title']),
                'latitude' => floatval($_POST['new_marker_latitude']),
                'longitude' => floatval($_POST['new_marker_longitude']),
                'status' => sanitize_text_field($_POST['new_marker_status']) ?: 'visited',
                'description' => sanitize_textarea_field($_POST['new_marker_description']),
                'post_id' => $post_id
            );
            
            $this->save_marker($new_marker_data);
        }
    }
    
    /**
     * 插件激活
     */
    public function activate() {
        $this->create_database_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * 插件停用
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * 插件卸载
     */
    public static function uninstall() {
        // 删除选项
        delete_option('travel_map_api_key');
        delete_option('travel_map_default_zoom');
        delete_option('travel_map_default_center');
        delete_option('travel_map_show_filter_tabs');
        
        // 删除数据库表
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}travel_map_markers");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}travel_map_post_markers");
    }
    
    /**
     * 创建数据库表
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 地图坐标表
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $sql_markers = "CREATE TABLE $table_markers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NULL,
            title varchar(255) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            status enum('visited', 'want_to_go', 'planned') DEFAULT 'visited',
            visit_date date NULL,
            visit_count int DEFAULT 1,
            description text NULL,
            marker_color varchar(7) DEFAULT '#FF6B35',
            planned_date date NULL,
            wish_reason text NULL,
            priority_level tinyint DEFAULT 3,
            is_featured boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // 文章坐标关联表
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        $sql_post_markers = "CREATE TABLE $table_post_markers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            marker_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_marker_id (marker_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_markers);
        dbDelta($sql_post_markers);
    }
    
    /**
     * 设置默认选项
     */
    private function set_default_options() {
        add_option('travel_map_api_key', '');
        add_option('travel_map_default_zoom', 4);
        add_option('travel_map_default_center', '35.0,105.0');
        add_option('travel_map_show_filter_tabs', true);
        add_option('travel_map_visited_color', '#FF6B35');
        add_option('travel_map_want_to_go_color', '#3B82F6');
        add_option('travel_map_planned_color', '#10B981');
    }
    
    /**
     * 获取所有标记点
     */
    private function get_all_markers() {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        return $wpdb->get_results("SELECT * FROM $table_markers ORDER BY created_at DESC");
    }
    
    /**
     * 根据状态获取标记点
     */
    private function get_markers_by_status($status = 'all') {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        if ($status === 'all') {
            $sql = "SELECT * FROM $table_markers ORDER BY created_at DESC";
            $markers = $wpdb->get_results($sql);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM $table_markers WHERE status = %s ORDER BY created_at DESC", $status);
            $markers = $wpdb->get_results($sql);
        }
        
        // 为每个标记点添加最新文章的特色图片
        foreach ($markers as $marker) {
            if ($marker->status === 'visited') {
                $featured_image = $this->get_marker_featured_image($marker);
                $marker->featured_image = $featured_image;
            }
        }
        
        return $markers;
    }
    
    /**
     * 获取标记点的特色图片（优先使用最新文章的图片）
     */
    private function get_marker_featured_image($marker) {
        global $wpdb;
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        
        // 首先检查直接关联的文章
        if ($marker->post_id) {
            $featured_image = get_the_post_thumbnail_url($marker->post_id, 'medium');
            if ($featured_image) {
                return $featured_image;
            }
        }
        
        // 如果没有直接关联的文章或者没有特色图片，查找关联表中最新的文章
        $related_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id FROM $table_post_markers 
            WHERE marker_id = %d 
            ORDER BY created_at DESC
            LIMIT 5",
            $marker->id
        ));
        
        foreach ($related_posts as $related_post) {
            $featured_image = get_the_post_thumbnail_url($related_post->post_id, 'medium');
            if ($featured_image) {
                return $featured_image;
            }
        }
        
        return null;
    }
    
    /**
     * 保存标记点
     */
    private function save_marker($marker_data) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        return $wpdb->insert($table_markers, $marker_data);
    }
    
    /**
     * 删除标记点
     */
    private function delete_marker($marker_id) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        return $wpdb->delete($table_markers, array('id' => $marker_id));
    }
    
    /**
     * 获取导出数据
     */
    private function get_export_data($status = 'all', $marker_ids = array()) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        if (!empty($marker_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($marker_ids), '%d'));
            $sql = $wpdb->prepare("SELECT * FROM $table_markers WHERE id IN ($ids_placeholder)", $marker_ids);
        } elseif ($status !== 'all') {
            $sql = $wpdb->prepare("SELECT * FROM $table_markers WHERE status = %s", $status);
        } else {
            $sql = "SELECT * FROM $table_markers";
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 导出 CSV 格式
     */
    private function export_csv($markers) {
        $filename = 'travel-map-export-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // CSV 头部
        fputcsv($output, array(
            'ID', 'Title', 'Latitude', 'Longitude', 'Status', 'Description',
            'Visit Date', 'Visit Count', 'Planned Date', 'Wish Reason',
            'Priority Level', 'Created At'
        ));
        
        // 数据行
        foreach ($markers as $marker) {
            fputcsv($output, array(
                $marker->id,
                $marker->title,
                $marker->latitude,
                $marker->longitude,
                $marker->status,
                $marker->description,
                $marker->visit_date,
                $marker->visit_count,
                $marker->planned_date,
                $marker->wish_reason,
                $marker->priority_level,
                $marker->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 导出 JSON 格式
     */
    private function export_json($markers) {
        $filename = 'travel-map-export-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo json_encode($markers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 导入 CSV 文件
     */
    private function import_csv($file_path) {
        if (!file_exists($file_path)) {
            return 0;
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return 0;
        }
        
        // 跳过头部行
        fgetcsv($handle);
        
        $imported_count = 0;
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 4) {
                $marker_data = array(
                    'title' => sanitize_text_field($data[1]),
                    'latitude' => floatval($data[2]),
                    'longitude' => floatval($data[3]),
                    'status' => in_array($data[4], array('visited', 'want_to_go', 'planned')) ? $data[4] : 'visited',
                    'description' => sanitize_textarea_field($data[5] ?? ''),
                    'visit_date' => !empty($data[6]) ? $data[6] : null,
                    'visit_count' => intval($data[7] ?? 1),
                    'planned_date' => !empty($data[8]) ? $data[8] : null,
                    'wish_reason' => sanitize_textarea_field($data[9] ?? ''),
                    'priority_level' => intval($data[10] ?? 3)
                );
                
                if ($wpdb->insert($table_markers, $marker_data)) {
                    $imported_count++;
                }
            }
        }
        
        fclose($handle);
        return $imported_count;
    }
    
    /**
     * 导入 JSON 文件
     */
    private function import_json($file_path) {
        if (!file_exists($file_path)) {
            return 0;
        }
        
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            return 0;
        }
        
        $imported_count = 0;
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        foreach ($data as $item) {
            if (isset($item['title'], $item['latitude'], $item['longitude'])) {
                $marker_data = array(
                    'title' => sanitize_text_field($item['title']),
                    'latitude' => floatval($item['latitude']),
                    'longitude' => floatval($item['longitude']),
                    'status' => in_array($item['status'] ?? 'visited', array('visited', 'want_to_go', 'planned')) ? $item['status'] : 'visited',
                    'description' => sanitize_textarea_field($item['description'] ?? ''),
                    'visit_date' => !empty($item['visit_date']) ? $item['visit_date'] : null,
                    'visit_count' => intval($item['visit_count'] ?? 1),
                    'planned_date' => !empty($item['planned_date']) ? $item['planned_date'] : null,
                    'wish_reason' => sanitize_textarea_field($item['wish_reason'] ?? ''),
                    'priority_level' => intval($item['priority_level'] ?? 3)
                );
                
                if ($wpdb->insert($table_markers, $marker_data)) {
                    $imported_count++;
                }
            }
        }
        
        return $imported_count;
    }
    
    /**
     * 处理标记点操作
     */
    private function handle_marker_action($post_data) {
        if (!wp_verify_nonce($post_data['_wpnonce'], 'travel_map_marker')) {
            return;
        }
        
        $action = $post_data['action'] ?? '';
        
        switch ($action) {
            case 'add_marker':
                $this->handle_add_marker($post_data);
                break;
            case 'edit_marker':
                $this->handle_edit_marker($post_data);
                break;
            case 'delete_marker':
                $this->handle_delete_marker($post_data);
                break;
        }
    }
    
    /**
     * 处理添加标记点
     */
    private function handle_add_marker($post_data) {
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        $marker_data = array(
            'title' => sanitize_text_field($post_data['title']),
            'latitude' => floatval($post_data['latitude']),
            'longitude' => floatval($post_data['longitude']),
            'status' => sanitize_text_field($post_data['status']),
            'description' => sanitize_textarea_field($post_data['description']),
            'post_id' => intval($post_data['post_id']) ?: null,
            'visit_date' => !empty($post_data['visit_date']) ? $post_data['visit_date'] : null,
            'visit_count' => intval($post_data['visit_count']) ?: 1,
            'planned_date' => !empty($post_data['planned_date']) ? $post_data['planned_date'] : null,
            'wish_reason' => sanitize_textarea_field($post_data['wish_reason']),
            'priority_level' => intval($post_data['priority_level']) ?: 3
        );
        
        if ($this->save_marker($marker_data)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('标记点添加成功', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('标记点添加失败', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        }
    }
    
    /**
     * 处理编辑标记点
     */
    private function handle_edit_marker($post_data) {
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        $marker_id = intval($post_data['marker_id']);
        if (!$marker_id) {
            return;
        }
        
        $marker_data = array(
            'title' => sanitize_text_field($post_data['title']),
            'latitude' => floatval($post_data['latitude']),
            'longitude' => floatval($post_data['longitude']),
            'status' => sanitize_text_field($post_data['status']),
            'description' => sanitize_textarea_field($post_data['description']),
            'post_id' => intval($post_data['post_id']) ?: null,
            'visit_date' => !empty($post_data['visit_date']) ? $post_data['visit_date'] : null,
            'visit_count' => intval($post_data['visit_count']) ?: 1,
            'planned_date' => !empty($post_data['planned_date']) ? $post_data['planned_date'] : null,
            'wish_reason' => sanitize_textarea_field($post_data['wish_reason']),
            'priority_level' => intval($post_data['priority_level']) ?: 3
        );
        
        if ($this->update_marker($marker_id, $marker_data)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('标记点更新成功', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('标记点更新失败', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        }
    }
    
    /**
     * 处理删除标记点
     */
    private function handle_delete_marker($post_data) {
        if (!current_user_can('delete_posts')) {
            return;
        }
        
        $marker_id = intval($post_data['marker_id']);
        if (!$marker_id) {
            return;
        }
        
        if ($this->delete_marker($marker_id)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('标记点删除成功', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('标记点删除失败', TRAVEL_MAP_TEXT_DOMAIN) . '</p></div>';
            });
        }
    }
    
    /**
     * 更新标记点
     */
    private function update_marker($marker_id, $marker_data) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        return $wpdb->update($table_markers, $marker_data, array('id' => $marker_id));
    }
    
    /**
     * 获取文章关联的坐标点
     */
    private function get_post_markers($post_id) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        
        $sql = $wpdb->prepare("
            SELECT m.* 
            FROM $table_markers m 
            LEFT JOIN $table_post_markers pm ON m.id = pm.marker_id 
            WHERE m.post_id = %d OR pm.post_id = %d
            ORDER BY m.created_at DESC
        ", $post_id, $post_id);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 更新文章坐标关联
     */
    private function update_post_marker_associations($post_id, $marker_ids) {
        global $wpdb;
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        
        // 先删除现有关联
        $wpdb->delete($table_post_markers, array('post_id' => $post_id));
        
        // 添加新关联
        foreach ($marker_ids as $marker_id) {
            if ($marker_id > 0) {
                $wpdb->insert(
                    $table_post_markers,
                    array(
                        'post_id' => $post_id,
                        'marker_id' => $marker_id
                    )
                );
            }
        }
        
        // 同时更新标记点表中的 post_id 字段为主要关联
        if (!empty($marker_ids)) {
            $main_marker_id = $marker_ids[0];
            $wpdb->update(
                $wpdb->prefix . 'travel_map_markers',
                array('post_id' => $post_id),
                array('id' => $main_marker_id)
            );
        }
    }
}

// 初始化插件
TravelMapPlugin::get_instance();