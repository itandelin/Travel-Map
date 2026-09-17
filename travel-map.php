<?php
/**
 * Plugin Name: WordPress Travel Map
 * Plugin URI: https://github.com/itandelin/Travel-Map
 * Description: 基于高德地图API的轻量级旅行博客地图插件，支持已去、想去、计划三种旅行状态标记。
 * Version: 1.1.0
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
define('TRAVEL_MAP_VERSION', '1.1.0');
define('TRAVEL_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRAVEL_MAP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TRAVEL_MAP_TEXT_DOMAIN', 'travel-map');

require_once TRAVEL_MAP_PLUGIN_PATH . 'includes/class-travel-map-migrator.php';

/**
 * WordPress Travel Map 主类
 */
class TravelMapPlugin {
    
    /**
     * 插件实例
     */
    private static $instance = null;
    private static $frontend_scripts_loaded = false;
    private static $markers_cache_cleared = false;
    // 后台页面 hook 名由 WordPress 依据菜单标题生成，中文标题会被 URL 编码，
    // 不能写死字符串，需保存 add_menu_page/add_submenu_page 的返回值
    private $settings_page_hooks = array();
    private $markers_page_hook = '';
    
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
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_head', array($this, 'print_safari_retina_fix'), 1);
        
        // 短代码注册（早期加载）
        add_shortcode('travel_map', array($this, 'render_map_shortcode'));
        add_shortcode('travel_map_countries', array($this, 'render_countries_shortcode'));
        add_shortcode('travel_map_markers', array($this, 'render_markers_shortcode'));
        
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
        add_action('wp_ajax_travel_map_import_rows', array($this, 'ajax_import_rows'));

        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * 插件初始化
     */
    public function init() {
        // 加载文本域
        load_plugin_textdomain(TRAVEL_MAP_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');

        // 检查依赖
        $this->check_dependencies();

        // 版本化数据库迁移（幂等）
        TravelMapMigrator::maybe_migrate();
    }

    /**
     * 状态键兼容归一化：旧值 visited/want_to_go/planned → 新值 done/wish/plan
     * 入参兼容保留一个大版本（1.1.x），1.2.0 移除
     */
    private function normalize_status($status) {
        $map = array(
            'visited'   => 'done',
            'want_to_go' => 'wish',
            'planned'   => 'plan',
        );
        $status = sanitize_text_field((string) $status);
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 校验状态值是否合法（含旧值归一化）
     */
    private function is_valid_status($status) {
        return in_array($this->normalize_status($status), array('done', 'wish', 'plan'), true);
    }

    /**
     * 国别字段：仅保留 ISO 代码，3 位 ISO3 归一为 ISO2，统一大写逗号分隔
     *
     * assets/flags/ 下的国旗资源按 ISO2 命名（如 cn.svg），所以入库统一存 ISO2。
     * 无法映射的 3 位码直接丢弃：留着只会让前端去请求不存在的 svg 拿到 404。
     */
    public static function sanitize_country($raw) {
        static $iso3_to_iso2 = array(
            'AND' => 'AD', 'ARE' => 'AE', 'AFG' => 'AF', 'ATG' => 'AG', 'AIA' => 'AI', 'ALB' => 'AL', 'ARM' => 'AM', 'AGO' => 'AO',
            'ATA' => 'AQ', 'ARG' => 'AR', 'ASM' => 'AS', 'AUT' => 'AT', 'AUS' => 'AU', 'ABW' => 'AW', 'ALA' => 'AX', 'AZE' => 'AZ',
            'BIH' => 'BA', 'BRB' => 'BB', 'BGD' => 'BD', 'BEL' => 'BE', 'BFA' => 'BF', 'BGR' => 'BG', 'BHR' => 'BH', 'BDI' => 'BI',
            'BEN' => 'BJ', 'BLM' => 'BL', 'BMU' => 'BM', 'BRN' => 'BN', 'BOL' => 'BO', 'BES' => 'BQ', 'BRA' => 'BR', 'BHS' => 'BS',
            'BTN' => 'BT', 'BVT' => 'BV', 'BWA' => 'BW', 'BLR' => 'BY', 'BLZ' => 'BZ', 'CAN' => 'CA', 'CCK' => 'CC', 'COD' => 'CD',
            'CAF' => 'CF', 'COG' => 'CG', 'CHE' => 'CH', 'CIV' => 'CI', 'COK' => 'CK', 'CHL' => 'CL', 'CMR' => 'CM', 'CHN' => 'CN',
            'COL' => 'CO', 'CRI' => 'CR', 'CUB' => 'CU', 'CPV' => 'CV', 'CUW' => 'CW', 'CXR' => 'CX', 'CYP' => 'CY', 'CZE' => 'CZ',
            'DEU' => 'DE', 'DJI' => 'DJ', 'DNK' => 'DK', 'DMA' => 'DM', 'DOM' => 'DO', 'DZA' => 'DZ', 'ECU' => 'EC', 'EST' => 'EE',
            'EGY' => 'EG', 'ESH' => 'EH', 'ERI' => 'ER', 'ESP' => 'ES', 'ETH' => 'ET', 'FIN' => 'FI', 'FJI' => 'FJ', 'FLK' => 'FK',
            'FSM' => 'FM', 'FRO' => 'FO', 'FRA' => 'FR', 'GAB' => 'GA', 'GBR' => 'GB', 'GRD' => 'GD', 'GEO' => 'GE', 'GUF' => 'GF',
            'GGY' => 'GG', 'GHA' => 'GH', 'GIB' => 'GI', 'GRL' => 'GL', 'GMB' => 'GM', 'GIN' => 'GN', 'GLP' => 'GP', 'GNQ' => 'GQ',
            'GRC' => 'GR', 'SGS' => 'GS', 'GTM' => 'GT', 'GUM' => 'GU', 'GNB' => 'GW', 'GUY' => 'GY', 'HKG' => 'HK', 'HMD' => 'HM',
            'HND' => 'HN', 'HRV' => 'HR', 'HTI' => 'HT', 'HUN' => 'HU', 'IDN' => 'ID', 'IRL' => 'IE', 'ISR' => 'IL', 'IMN' => 'IM',
            'IND' => 'IN', 'IOT' => 'IO', 'IRQ' => 'IQ', 'IRN' => 'IR', 'ISL' => 'IS', 'ITA' => 'IT', 'JEY' => 'JE', 'JAM' => 'JM',
            'JOR' => 'JO', 'JPN' => 'JP', 'KEN' => 'KE', 'KGZ' => 'KG', 'KHM' => 'KH', 'KIR' => 'KI', 'COM' => 'KM', 'KNA' => 'KN',
            'PRK' => 'KP', 'KOR' => 'KR', 'KWT' => 'KW', 'CYM' => 'KY', 'KAZ' => 'KZ', 'LAO' => 'LA', 'LBN' => 'LB', 'LCA' => 'LC',
            'LIE' => 'LI', 'LKA' => 'LK', 'LBR' => 'LR', 'LSO' => 'LS', 'LTU' => 'LT', 'LUX' => 'LU', 'LVA' => 'LV', 'LBY' => 'LY',
            'MAR' => 'MA', 'MCO' => 'MC', 'MDA' => 'MD', 'MNE' => 'ME', 'MAF' => 'MF', 'MDG' => 'MG', 'MHL' => 'MH', 'MKD' => 'MK',
            'MLI' => 'ML', 'MMR' => 'MM', 'MNG' => 'MN', 'MAC' => 'MO', 'MNP' => 'MP', 'MTQ' => 'MQ', 'MRT' => 'MR', 'MSR' => 'MS',
            'MLT' => 'MT', 'MUS' => 'MU', 'MDV' => 'MV', 'MWI' => 'MW', 'MEX' => 'MX', 'MYS' => 'MY', 'MOZ' => 'MZ', 'NAM' => 'NA',
            'NCL' => 'NC', 'NER' => 'NE', 'NFK' => 'NF', 'NGA' => 'NG', 'NIC' => 'NI', 'NLD' => 'NL', 'NOR' => 'NO', 'NPL' => 'NP',
            'NRU' => 'NR', 'NIU' => 'NU', 'NZL' => 'NZ', 'OMN' => 'OM', 'PAN' => 'PA', 'PER' => 'PE', 'PYF' => 'PF', 'PNG' => 'PG',
            'PHL' => 'PH', 'PAK' => 'PK', 'POL' => 'PL', 'SPM' => 'PM', 'PCN' => 'PN', 'PRI' => 'PR', 'PSE' => 'PS', 'PRT' => 'PT',
            'PLW' => 'PW', 'PRY' => 'PY', 'QAT' => 'QA', 'REU' => 'RE', 'ROU' => 'RO', 'SRB' => 'RS', 'RUS' => 'RU', 'RWA' => 'RW',
            'SAU' => 'SA', 'SLB' => 'SB', 'SYC' => 'SC', 'SDN' => 'SD', 'SWE' => 'SE', 'SGP' => 'SG', 'SHN' => 'SH', 'SVN' => 'SI',
            'SJM' => 'SJ', 'SVK' => 'SK', 'SLE' => 'SL', 'SMR' => 'SM', 'SEN' => 'SN', 'SOM' => 'SO', 'SUR' => 'SR', 'SSD' => 'SS',
            'STP' => 'ST', 'SLV' => 'SV', 'SXM' => 'SX', 'SYR' => 'SY', 'SWZ' => 'SZ', 'TCA' => 'TC', 'TCD' => 'TD', 'ATF' => 'TF',
            'TGO' => 'TG', 'THA' => 'TH', 'TJK' => 'TJ', 'TKL' => 'TK', 'TLS' => 'TL', 'TKM' => 'TM', 'TUN' => 'TN', 'TON' => 'TO',
            'TUR' => 'TR', 'TTO' => 'TT', 'TUV' => 'TV', 'TWN' => 'TW', 'TZA' => 'TZ', 'UKR' => 'UA', 'UGA' => 'UG', 'UMI' => 'UM',
            'USA' => 'US', 'URY' => 'UY', 'UZB' => 'UZ', 'VAT' => 'VA', 'VCT' => 'VC', 'VEN' => 'VE', 'VGB' => 'VG', 'VIR' => 'VI',
            'VNM' => 'VN', 'VUT' => 'VU', 'WLF' => 'WF', 'WSM' => 'WS', 'YEM' => 'YE', 'MYT' => 'YT', 'ZAF' => 'ZA', 'ZMB' => 'ZM',
            'ZWE' => 'ZW',
        );

        $tokens = preg_split('/[,，\s]+/u', strtoupper((string) $raw));
        $valid = array();
        foreach ($tokens as $token) {
            $token = preg_replace('/[^A-Z]/', '', $token);
            if (strlen($token) === 2) {
                $valid[] = $token;
            } elseif (strlen($token) === 3 && isset($iso3_to_iso2[$token])) {
                $valid[] = $iso3_to_iso2[$token];
            }
        }
        return implode(',', array_slice(array_unique($valid), 0, 5));
    }

    /**
     * 年份字段：仅保留 4 位数字，去重倒序
     */
    public static function sanitize_years($raw) {
        $tokens = preg_split('/[,，\s]+/u', (string) $raw);
        $valid = array();
        foreach ($tokens as $token) {
            $token = trim($token);
            if (preg_match('/^\d{4}$/', $token)) {
                $valid[] = $token;
            }
        }
        $valid = array_unique($valid);
        rsort($valid);
        return implode(',', $valid);
    }

    /**
     * 资源版本号（基于文件修改时间）
     */
    private function get_asset_version($relative_path) {
        $path = TRAVEL_MAP_PLUGIN_PATH . ltrim($relative_path, '/');
        if (file_exists($path)) {
            return (string) filemtime($path);
        }
        return TRAVEL_MAP_VERSION;
    }

    /**
     * Safari(Mac/Retina) 下高德 2.0 会把 WebGL 渲染上限钳到 4096 再与
     * 屏幕"物理像素"比较，Retina 屏(如 3008×1692 @2x → 6016)必然过不了检，
     * SDK 静默回退 DOM 栅格渲染，深浅色自定义样式全部失效。
     * 这里在 SDK 加载前复刻同一段判定，仅当"必然回退"时设置
     * window.detectRetina = 0 关闭 Retina 倍乘，让 WebGL 判定通过。
     * 代价仅是 Safari 上按 1x 渲染；其他浏览器不受任何影响。
     */
    private function get_safari_retina_fix_script() {
        $script = "window.travelMapApplySafariRetinaFix=function(){if(window.travelMapSafariRetinaFixApplied)return;window.travelMapSafariRetinaFixApplied=!0;"
            . "var r=navigator.userAgent.toLowerCase();"
            . "if(-1===r.indexOf('macintosh'))return;"
            . "if(!((-1!==r.indexOf('safari'))&&(-1!==r.indexOf('version/'))))return;"
            . "var d=window.devicePixelRatio||1;if(!(1<d))return;"
            . "var c=document.createElement('canvas');if(!c.getContext)return;"
            . "var g=null;try{g=c.getContext('webgl')||c.getContext('experimental-webgl');}catch(e){}"
            . "if(!g)return;"
            . "var l=g.getParameter(g.MAX_RENDERBUFFER_SIZE),v=g.getParameter(g.MAX_VIEWPORT_DIMS);"
            . "if(!v)return;"
            . "l=Math.min(l,v[0],v[1],4096);"
            . "var s=Math.max(screen.width,screen.height);"
            . "if(d>1)s*=Math.min(2,d);"
            . "if(l<s){window.detectRetina=0;}};"
            . "window.travelMapApplySafariRetinaFix();";
        return $script;
    }

    /**
     * 前端 head 早期输出 Safari Retina 守卫，覆盖短代码动态注入 SDK 的场景
     */
    public function print_safari_retina_fix() {
        // 仅当本页可能用到地图短代码时输出，避免污染普通页面
        if (empty(get_option('travel_map_api_key', ''))) {
            return;
        }
        echo '<script>' . $this->get_safari_retina_fix_script() . '</script>' . "\n";
    }

    /**
     * 前端脚本按需加载
     */
    public function maybe_enqueue_frontend_scripts() {
        if (self::$frontend_scripts_loaded) {
            return;
        }
        
        global $posts;
        if (is_array($posts)) {
            foreach ($posts as $post) {
                if ($post && has_shortcode($post->post_content, 'travel_map')) {
                    $this->enqueue_frontend_scripts();
                    return;
                }
            }
        }
        
        if (is_singular()) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'travel_map')) {
                $this->enqueue_frontend_scripts();
            }
        }
    }

    /**
     * 标记点缓存 Key
     */
    private function get_markers_cache_key($status) {
        return 'travel_map_markers_' . $status;
    }

    /**
     * 清理标记点缓存
     */
    private function clear_markers_cache() {
        if (self::$markers_cache_cleared) {
            return;
        }
        self::$markers_cache_cleared = true;
        foreach (array('all', 'done', 'wish', 'plan') as $status) {
            delete_transient($this->get_markers_cache_key($status));
        }
        // 旧状态键的遗留缓存一并清理
        foreach (array('visited', 'want_to_go', 'planned') as $status) {
            delete_transient($this->get_markers_cache_key($status));
        }
        delete_transient('travel_map_markers_geojson');
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
        register_setting('travel_map_settings', 'travel_map_cluster_radius', array(
            'type' => 'integer',
            'default' => 40
        ));
        register_setting('travel_map_settings', 'travel_map_cluster_limit', array(
            'type' => 'integer',
            'default' => 9
        ));
        register_setting('travel_map_settings', 'travel_map_auto_zoom', array(
            'type' => 'boolean',
            'default' => true
        ));
        register_setting('travel_map_settings', 'travel_map_highlight_country', array(
            'type' => 'boolean',
            'default' => true
        ));
        register_setting('travel_map_settings', 'travel_map_show_yearly_stats', array(
            'type' => 'boolean',
            'default' => true
        ));
        register_setting('travel_map_settings', 'travel_map_show_type_stats', array(
            'type' => 'boolean',
            'default' => true
        ));
        register_setting('travel_map_settings', 'travel_map_default_filter_status', array(
            'type' => 'string',
            'default' => 'all'
        ));
        register_setting('travel_map_settings', 'travel_map_default_cover', array(
            'type' => 'string',
            'default' => ''
        ));
        register_setting('travel_map_settings', 'travel_map_min_zoom', array(
            'type' => 'integer',
            'default' => 1
        ));
        register_setting('travel_map_settings', 'travel_map_max_zoom', array(
            'type' => 'integer',
            'default' => 12
        ));
    }
    
    /**
     * 添加管理员菜单
     */
    public function admin_menu() {
        // 主菜单页面
        $this->settings_page_hooks[] = add_menu_page(
            __('地图', TRAVEL_MAP_TEXT_DOMAIN),
            __('地图', TRAVEL_MAP_TEXT_DOMAIN),
            'manage_options',
            'travel-map',
            array($this, 'admin_page_settings'),
            'dashicons-location-alt',
            30
        );
        
        // 设置子页面
        $this->settings_page_hooks[] = add_submenu_page(
            'travel-map',
            __('地图设置', TRAVEL_MAP_TEXT_DOMAIN),
            __('地图设置', TRAVEL_MAP_TEXT_DOMAIN),
            'manage_options',
            'travel-map',
            array($this, 'admin_page_settings')
        );
        
        // 坐标管理子页面
        $this->markers_page_hook = add_submenu_page(
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
        
        // 地图行为设置
        update_option('travel_map_cluster_radius', max(20, min(200, intval($_POST['cluster_radius'] ?? 40))));
        update_option('travel_map_cluster_limit', max(1, min(999, intval($_POST['cluster_limit'] ?? 9))));
        update_option('travel_map_auto_zoom', isset($_POST['auto_zoom']));
        update_option('travel_map_highlight_country', isset($_POST['highlight_country']));
        update_option('travel_map_show_yearly_stats', isset($_POST['show_yearly_stats']));
        update_option('travel_map_show_type_stats', isset($_POST['show_type_stats']));
        // 'all' 是合法取值：它表示 done/wish/plan 的并集（前端「全部」页签），
        // 不是单个状态值，所以不能用 is_valid_status() 校验。
        $filter_status = $this->normalize_status($_POST['default_filter_status'] ?? 'all');
        $allowed_filter_status = array('all', 'done', 'wish', 'plan');
        update_option('travel_map_default_filter_status', in_array($filter_status, $allowed_filter_status, true) ? $filter_status : 'all');
        update_option('travel_map_default_cover', esc_url_raw($_POST['default_cover'] ?? ''));
        update_option('travel_map_min_zoom', max(1, min(20, intval($_POST['min_zoom'] ?? 1))));
        update_option('travel_map_max_zoom', max(3, min(20, intval($_POST['max_zoom'] ?? 12))));
        
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
            // 加载高德地图 API（底部加载，支持按需加载）；
            // 一次带上聚合、行政区图层、搜索插件，避免后续二次注入
            wp_enqueue_script(
                'amap-api',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}&plugin=AMap.MarkerCluster,AMap.DistrictLayer,AMap.Autocomplete,AMap.PlaceSearch",
                $script_dependencies,
                TRAVEL_MAP_VERSION,
                true
            );
            
            wp_add_inline_script('amap-api', $this->get_safari_retina_fix_script(), 'before');

            if (!empty($security_key)) {
                $security_script = "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };";
                wp_add_inline_script('amap-api', $security_script, 'before');
            }
            
            // 添加高德地图API作为依赖
            $script_dependencies[] = 'amap-api';
        }
        
        // 加载插件脚本（无jQuery依赖）
        wp_enqueue_script(
            'travel-map-frontend',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map.js',
            $script_dependencies,
            $this->get_asset_version('assets/js/travel-map.js'),
            true
        );
        
        // 短代码初始化脚本
        wp_enqueue_script(
            'travel-map-shortcode-init',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-shortcode-init.js',
            array('travel-map-frontend'),
            $this->get_asset_version('assets/js/travel-map-shortcode-init.js'),
            true
        );
        
        // 加载插件样式
        wp_enqueue_style(
            'travel-map-frontend',
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map.css',
            array(),
            $this->get_asset_version('assets/css/travel-map.css')
        );

        // 关键样式兜底，避免主题未加载 head 时样式缺失（与 travel-map.css / shortcode-init.js 三处同步）
        $critical_css = '.travel-map-container{width:100%;height:var(--travel-map-height,550px);position:relative;overflow:hidden;background:#f5f5f5;border-radius:10px;margin-bottom:20px}.travel-map-wrapper,.travel-map{width:100%;height:100%;min-height:400px}@media (max-width:768px){.travel-map-container{height:auto;aspect-ratio:1/1.3;min-height:400px}}@media (max-width:480px){.travel-map-container{aspect-ratio:1/1.5;min-height:350px}.travel-map-wrapper,.travel-map{min-height:350px}}.travel-map-loading{position:absolute;top:0;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:center;flex-direction:column;background:#f5f5f5;z-index:1000}.travel-map-controls{position:absolute;top:10px;right:10px;z-index:1000;display:flex;flex-direction:column;gap:8px}.travel-map-control-btn{width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:#fff;border:0;border-radius:6px;padding:0;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.05)}.travel-map-control-btn svg{width:18px;height:18px;display:block;color:#333}.travel-map-embedded-filters{position:absolute;top:10px;left:50%;transform:translateX(-50%);z-index:10;background:#fff;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.05);padding:7px 9px;display:flex;gap:8px;max-width:calc(100% - 20px);flex-wrap:wrap;justify-content:center}.travel-map-filter-tab{border:0;font-size:12px !important;background-color:#f9f9f9;color:#555;padding:5px 12px;border-radius:10px;cursor:pointer;line-height:1.5}.travel-map-filter-tab[data-status="all"]{background-color:rgba(220,38,38,.12);color:#b91c1c}.travel-map-filter-tab[data-status="done"]{background-color:rgba(192,88,12,.12);color:#c0580c}.travel-map-filter-tab[data-status="wish"]{background-color:rgba(202,138,4,.14);color:#a16207}.travel-map-filter-tab[data-status="plan"]{background-color:rgba(5,150,105,.12);color:#047857}.travel-map-filter-tab[data-status="all"].active{background-color:#dc2626;color:#fff}.travel-map-filter-tab[data-status="done"].active{background-color:#c0580c;color:#fff}.travel-map-filter-tab[data-status="wish"].active{background-color:#ca8a04;color:#fff}.travel-map-filter-tab[data-status="plan"].active{background-color:#059669;color:#fff}.travel-map-filter-tab.active{cursor:not-allowed}.travel-map-accessibility{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}';
        wp_add_inline_style('travel-map-frontend', $critical_css);
        
        // 本地化脚本
        wp_localize_script('travel-map-frontend', 'travelMapAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('travel-map/v1/')),
            'geojsonUrl' => esc_url_raw(rest_url('travel-map/v1/geojson')),
            'nonce' => wp_create_nonce('travel_map_nonce'),
            'apiKey' => $api_key,
            'flagsBase' => TRAVEL_MAP_PLUGIN_URL . 'assets/flags/',
            'settings' => array(
                'clusterRadius' => (int) get_option('travel_map_cluster_radius', 40),
                'clusterLimit' => (int) get_option('travel_map_cluster_limit', 9),
                'autoZoom' => (bool) get_option('travel_map_auto_zoom', true),
                'highlightCountry' => (bool) get_option('travel_map_highlight_country', true),
                'showYearlyStats' => (bool) get_option('travel_map_show_yearly_stats', true),
                'showTypeStats' => (bool) get_option('travel_map_show_type_stats', true),
                'defaultFilterStatus' => get_option('travel_map_default_filter_status', 'all'),
                'minZoom' => (int) get_option('travel_map_min_zoom', 1),
                'maxZoom' => (int) get_option('travel_map_max_zoom', 12),
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
        
        $api_script_url = '';
        if (!empty($api_key)) {
            $api_script_url = "https://webapi.amap.com/maps?v=2.0&key={$api_key}&plugin=AMap.MarkerCluster,AMap.DistrictLayer,AMap.Autocomplete,AMap.PlaceSearch";
        }

        $style_url = add_query_arg(
            'ver',
            $this->get_asset_version('assets/css/travel-map.css'),
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map.css'
        );

        wp_localize_script('travel-map-shortcode-init', 'travelMapShortcode', array(
            'apiScript' => $api_script_url,
            'frontendScript' => TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map.js',
            'styleUrl' => $style_url,
            'securityKey' => $security_key,
            'i18n' => array(
                'mapInitFailed' => __('地图初始化失败', TRAVEL_MAP_TEXT_DOMAIN),
                'mapScriptMissing' => __('地图脚本加载失败', TRAVEL_MAP_TEXT_DOMAIN),
                'possibleFix' => __('可能的解决方案：', TRAVEL_MAP_TEXT_DOMAIN),
                'fixNetwork' => __('检查网络连接是否正常', TRAVEL_MAP_TEXT_DOMAIN),
                'fixApiKey' => __('确认高德地图API密钥配置正确', TRAVEL_MAP_TEXT_DOMAIN),
                'fixReload' => __('刷新页面重试', TRAVEL_MAP_TEXT_DOMAIN),
                'reload' => __('刷新页面', TRAVEL_MAP_TEXT_DOMAIN)
            )
        ));
        
        // 标记为已加载
        self::$frontend_scripts_loaded = true;
    }
    
    /**
     * 管理员脚本加载
     */
    public function enqueue_admin_scripts($hook) {
        // 只在插件页面加载
        $is_settings_page = in_array($hook, $this->settings_page_hooks, true);
        $is_markers_page = ($hook !== '' && $hook === $this->markers_page_hook);
        if (!$is_settings_page && !$is_markers_page) {
            return;
        }
        
        $api_key = get_option('travel_map_api_key', '');
        $security_key = get_option('travel_map_security_key', '');
        
        $dependencies = array('jquery');
        
        if (!empty($api_key)) {
            wp_enqueue_script(
                'amap-api',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}&plugin=AMap.Geocoder",
                $dependencies,
                TRAVEL_MAP_VERSION,
                true
            );
            
            wp_add_inline_script('amap-api', $this->get_safari_retina_fix_script(), 'before');

            if (!empty($security_key)) {
                $security_script = "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };";
                wp_add_inline_script('amap-api', $security_script, 'before');
            }
            $dependencies[] = 'amap-api';
        }
        
        wp_enqueue_script(
            'travel-map-admin',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-admin.js',
            $dependencies,
            $this->get_asset_version('assets/js/travel-map-admin.js'),
            true
        );
        
        wp_enqueue_style(
            'travel-map-admin',
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map-admin.css',
            array(),
            $this->get_asset_version('assets/css/travel-map-admin.css')
        );
        
        // 本地化管理员脚本
        wp_localize_script('travel-map-admin', 'travelMapAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('travel_map_nonce'),
            'apiKey' => $api_key
        ));
        
        if ($is_settings_page) {
            wp_enqueue_script(
                'travel-map-settings',
                TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-settings.js',
                array('jquery'),
                $this->get_asset_version('assets/js/travel-map-settings.js'),
                true
            );
            
            wp_localize_script('travel-map-settings', 'travelMapSettings', array(
                'i18n' => array(
                    'copySuccess' => __('✓ 代码已复制到剪贴板', TRAVEL_MAP_TEXT_DOMAIN),
                    'copyError' => __('✗ 复制失败，请手动复制', TRAVEL_MAP_TEXT_DOMAIN)
                )
            ));
        }
        
        if ($is_markers_page) {
            // 封面图选择器依赖媒体库
            wp_enqueue_media();

            wp_enqueue_style(
                'travel-map-coordinates',
                TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map-coordinates.css',
                array(),
                $this->get_asset_version('assets/css/travel-map-coordinates.css')
            );
            
            $coordinates_deps = array('jquery');
            if (!empty($api_key)) {
                $coordinates_deps[] = 'amap-api';
            }
            wp_enqueue_script(
                'travel-map-coordinates',
                TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-coordinates.js',
                $coordinates_deps,
                $this->get_asset_version('assets/js/travel-map-coordinates.js'),
                true
            );
            
            wp_localize_script('travel-map-coordinates', 'travelMapCoordinates', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('travel_map_nonce'),
                'flagsBase' => TRAVEL_MAP_PLUGIN_URL . 'assets/flags/',
                'defaults' => array(
                    'lng' => 116.4074,
                    'lat' => 39.9042
                ),
                'i18n' => array(
                    'noData' => __('暂无数据', TRAVEL_MAP_TEXT_DOMAIN),
                    'none' => __('无', TRAVEL_MAP_TEXT_DOMAIN),
                    'saving' => __('保存中...', TRAVEL_MAP_TEXT_DOMAIN),
                    'saveSuccess' => __('保存成功！', TRAVEL_MAP_TEXT_DOMAIN),
                    'saveFailed' => __('保存失败：', TRAVEL_MAP_TEXT_DOMAIN),
                    'unknownError' => __('未知错误', TRAVEL_MAP_TEXT_DOMAIN),
                    'networkError' => __('网络请求失败', TRAVEL_MAP_TEXT_DOMAIN),
                    'loadEditSuccess' => __('已加载编辑数据', TRAVEL_MAP_TEXT_DOMAIN),
                    'loadEditFailed' => __('获取数据失败', TRAVEL_MAP_TEXT_DOMAIN),
                    'fetchFailed' => __('网络请求失败', TRAVEL_MAP_TEXT_DOMAIN),
                    'selectToDelete' => __('请选择要删除的标记点', TRAVEL_MAP_TEXT_DOMAIN),
                    'confirmBulkDelete' => __('确定要删除选中的 %d 个标记点吗？', TRAVEL_MAP_TEXT_DOMAIN),
                    'confirmDelete' => __('确定要删除这个地点吗？此操作不可撤销。', TRAVEL_MAP_TEXT_DOMAIN),
                    'deleteSuccess' => __('删除成功', TRAVEL_MAP_TEXT_DOMAIN),
                    'deleteFailed' => __('删除失败：', TRAVEL_MAP_TEXT_DOMAIN),
                    'importSuccess' => __('导入成功', TRAVEL_MAP_TEXT_DOMAIN),
                    'importFailed' => __('导入失败：', TRAVEL_MAP_TEXT_DOMAIN),
                    'apiKeyMissing' => __('请先配置API密钥并刷新页面', TRAVEL_MAP_TEXT_DOMAIN),
                    'formTitleAdd' => __('添加新地点', TRAVEL_MAP_TEXT_DOMAIN),
                    'formTitleEdit' => __('编辑地点', TRAVEL_MAP_TEXT_DOMAIN),
                    'submitAdd' => __('添加坐标', TRAVEL_MAP_TEXT_DOMAIN),
                    'submitUpdate' => __('更新坐标', TRAVEL_MAP_TEXT_DOMAIN),
                    'countFormat' => __('%d 个地点', TRAVEL_MAP_TEXT_DOMAIN),
                    'edit' => __('编辑', TRAVEL_MAP_TEXT_DOMAIN),
                    'statusLabels' => array(
                        'done' => __('已去', TRAVEL_MAP_TEXT_DOMAIN),
                        'wish' => __('想去', TRAVEL_MAP_TEXT_DOMAIN),
                        'plan' => __('计划', TRAVEL_MAP_TEXT_DOMAIN)
                    )
                )
            ));
        }
    }
    
    /**
     * 渲染地图短代码
     */
    public function render_map_shortcode($atts) {
        // 确保前端脚本已加载
        $this->enqueue_frontend_scripts();

        // 兜底输出样式，避免主题未正确调用 wp_head 导致样式缺失
        if (!wp_style_is('travel-map-frontend', 'done')) {
            wp_print_styles('travel-map-frontend');
        }
        
        // 兜底输出脚本，避免主题未正确调用 wp_head/wp_footer 导致脚本缺失
        if (!wp_script_is('travel-map-shortcode-init', 'done')) {
            $handles = array();
            if (!empty(get_option('travel_map_api_key', ''))) {
                $handles[] = 'amap-api';
            }
            $handles[] = 'travel-map-frontend';
            $handles[] = 'travel-map-shortcode-init';
            wp_print_scripts($handles);
        }
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '550px',
            'zoom' => get_option('travel_map_default_zoom', 4),
            'center' => get_option('travel_map_default_center', '35.0,105.0'),
            'filter_tabs' => get_option('travel_map_show_filter_tabs', true),
            // 留空而非 'all'：短代码默认值必须与「用户显式写 status="all"」可区分，
            // 否则前端无法判断该不该让位给后台设置项 travel_map_default_filter_status。
            'status' => '',
            'filters' => 'all,done,wish,plan',
            'auto_zoom' => '',
        ), $atts, 'travel_map');
        
        $api_key = get_option('travel_map_api_key', '');
        if (empty($api_key)) {
            return '<div class="travel-map-error">' . __('请先配置高德地图API密钥', TRAVEL_MAP_TEXT_DOMAIN) . '</div>';
        }
        
        $map_id = 'travel-map-' . uniqid();
        
        static $schema_added = false;
        if (!$schema_added) {
            $schema_data = array(
                '@context' => 'https://schema.org',
                '@type' => 'Map',
                'name' => get_the_title(),
                'description' => __('互动式地图，展示已去、想去和计划的地点', TRAVEL_MAP_TEXT_DOMAIN),
                'url' => get_permalink(),
                'mapType' => 'InteractiveMap'
            );
            add_action('wp_footer', function() use ($schema_data) {
                echo '<script type="application/ld+json">' . wp_json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
            });
            $schema_added = true;
        }
        
        ob_start();
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/map-shortcode.php';
        return ob_get_clean();
    }
    
    /**
     * [travel_map_countries] 去过国家国旗墙
     *
     * 按已去（done）标记的 country 字段分组，输出国旗 + ISO 码 + 地点数。
     */
    public function render_countries_shortcode() {
        $markers = $this->get_markers_by_status('done');

        $countries = array();
        foreach ($markers as $marker) {
            $raw = strtoupper((string) $marker->country);
            if ($raw === '') {
                continue;
            }
            foreach (array_filter(explode(',', $raw)) as $code) {
                if (!isset($countries[$code])) {
                    $countries[$code] = array('count' => 0);
                }
                $countries[$code]['count']++;
            }
        }
        uasort($countries, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        ob_start();
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/shortcode-countries.php';
        return ob_get_clean();
    }

    /**
     * [travel_map_markers] 标记归档列表
     *
     * 属性：status（done|wish|plan|all，默认 done）、type（分类过滤）
     */
    public function render_markers_shortcode($atts) {
        $atts = shortcode_atts(array(
            'status' => 'done',
            'type' => '',
        ), $atts, 'travel_map_markers');

        $status = $this->normalize_status($atts['status']);
        $type = sanitize_text_field($atts['type']);

        $markers = $this->get_markers_by_status($status === 'all' ? 'all' : $status);

        // 组装 posts（复用 geojson 的预加载逻辑）
        $result = array();
        foreach ($markers as $marker) {
            if ($type !== '' && (string) $marker->type !== $type) {
                continue;
            }
            $marker->posts = $this->get_marker_posts_payload($marker);
            $result[] = $marker;
        }

        ob_start();
        include TRAVEL_MAP_PLUGIN_PATH . 'templates/shortcode-markers.php';
        return ob_get_clean();
    }

    /**
     * 注册 REST 路由（公开只读）
     */
    public function register_rest_routes() {
        register_rest_route('travel-map/v1', '/markers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_get_markers'),
            'permission_callback' => '__return_true',
            'args' => array(
                'status' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => 'all'
                ),
                'search' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => ''
                )
            )
        ));

        register_rest_route('travel-map/v1', '/geojson', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_get_geojson'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('travel-map/v1', '/location-posts', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_get_location_posts'),
            'permission_callback' => '__return_true',
            'args' => array(
                'latitude' => array(
                    'required' => true
                ),
                'longitude' => array(
                    'required' => true
                ),
                'location_name' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => ''
                )
            )
        ));
    }

    /**
     * REST: GeoJSON FeatureCollection（全量标记数据）
     *
     * properties: title / status(done|wish|plan) / country / type /
     *             image[] / posts[] / year[]
     */
    public function rest_get_geojson() {
        $cached = get_transient('travel_map_markers_geojson');
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $markers = $this->get_markers_by_status('all');
        $features = array();

        foreach ($markers as $marker) {
            $posts = $this->get_marker_posts_payload($marker);

            // 图片数组：手动封面优先，其后拼关联文章特色图（去重，上限 10）
            $images = array();
            if (!empty($marker->cover_image)) {
                $images[] = $marker->cover_image;
            }
            foreach ($posts as $post) {
                if (!empty($post['cover']) && !in_array($post['cover'], $images, true)) {
                    $images[] = $post['cover'];
                }
                if (count($images) >= 10) {
                    break;
                }
            }

            // 年份数组：years 字段优先，回退 visit_date 年份
            $years = array();
            if (!empty($marker->years)) {
                foreach (explode(',', $marker->years) as $y) {
                    $y = trim($y);
                    if (preg_match('/^\d{4}$/', $y)) {
                        $years[] = $y;
                    }
                }
            }
            if (empty($years) && !empty($marker->visit_date)) {
                $years[] = substr($marker->visit_date, 0, 4);
            }

            $features[] = array(
                'type' => 'Feature',
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        (float) $marker->longitude,
                        (float) $marker->latitude,
                    ),
                ),
                'properties' => array(
                    'title'   => (string) $marker->title,
                    'status'  => $marker->status,
                    'country' => isset($marker->country) ? (string) $marker->country : '',
                    'type'    => isset($marker->type) ? (string) $marker->type : '',
                    'image'   => $images,
                    'posts'   => $posts,
                    'year'    => $years,
                    'plan_date'   => isset($marker->planned_date) ? (string) $marker->planned_date : '',
                    'wish_reason' => isset($marker->wish_reason) ? (string) $marker->wish_reason : '',
                ),
            );
        }

        $geojson = array('type' => 'FeatureCollection', 'features' => $features);
        set_transient('travel_map_markers_geojson', $geojson, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($geojson);
    }

    /**
     * 组装某标记的关联游记 payload（一次 IN 查询，避免 N+1）
     */
    private function get_marker_posts_payload($marker) {
        global $wpdb;
        static $post_cache = array();

        $post_ids = array();

        if (!empty($marker->post_id)) {
            $post_ids[] = (int) $marker->post_id;
        }

        $related = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}travel_map_post_markers WHERE marker_id = %d",
            $marker->id
        ));
        foreach ($related as $pid) {
            $pid = (int) $pid;
            if (!in_array($pid, $post_ids, true)) {
                $post_ids[] = $pid;
            }
        }

        $posts = array();
        foreach ($post_ids as $pid) {
            if (!isset($post_cache[$pid])) {
                $post = get_post($pid);
                if ($post && $post->post_status === 'publish') {
                    $post_cache[$pid] = array(
                        'id' => (string) $pid,
                        'permalink' => get_permalink($pid),
                        'title' => $post->post_title,
                        'cover' => (string) get_the_post_thumbnail_url($pid, 'medium'),
                    );
                } else {
                    $post_cache[$pid] = null;
                }
            }
            if ($post_cache[$pid] !== null) {
                $posts[] = $post_cache[$pid];
            }
        }

        return $posts;
    }

    /**
     * REST: 获取标记点
     */
    public function rest_get_markers($request) {
        $status = sanitize_text_field((string) $request->get_param('status'));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $markers = $this->get_markers_by_status($status ?: 'all', $search);

        return rest_ensure_response(array(
            'success' => true,
            'data' => $markers
        ));
    }

    /**
     * REST: 获取地点相关文章
     */
    public function rest_get_location_posts($request) {
        $latitude = floatval($request->get_param('latitude'));
        $longitude = floatval($request->get_param('longitude'));
        $location_name = sanitize_text_field((string) $request->get_param('location_name'));

        return rest_ensure_response(array(
            'success' => true,
            'data' => $this->find_location_posts($latitude, $longitude, $location_name)
        ));
    }

    /**
     * 校验公开只读 AJAX 请求（对游客允许无 nonce，兼容缓存与服务器策略）
     */
    private function verify_public_read_ajax_request() {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        if ($nonce && wp_verify_nonce($nonce, 'travel_map_nonce')) {
            return;
        }

        if (is_user_logged_in()) {
            wp_send_json_error(__('安全验证失败', TRAVEL_MAP_TEXT_DOMAIN), 403);
        }
    }

    /**
     * AJAX: 获取标记点
     */
    public function ajax_get_markers() {
        // 清理输出缓冲区
        if (ob_get_level()) {
            ob_clean();
        }

        $this->verify_public_read_ajax_request();
        
        $status = $this->normalize_status($_POST['status'] ?? 'all');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $markers = $this->get_markers_by_status($status, $search);
        
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
            'status' => $this->normalize_status($_POST['status']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'post_id' => intval($_POST['post_id'] ?? 0) ?: null,
            'visit_date' => !empty($_POST['visit_date']) ? sanitize_text_field($_POST['visit_date']) : null,
            'visit_count' => intval($_POST['visit_count'] ?? 1),
            'planned_date' => !empty($_POST['planned_date']) ? sanitize_text_field($_POST['planned_date']) : null,
            'wish_reason' => sanitize_textarea_field($_POST['wish_reason'] ?? ''),
            'priority_level' => intval($_POST['priority_level'] ?? 3),
            'country' => self::sanitize_country($_POST['country'] ?? ''),
            'years' => self::sanitize_years($_POST['years'] ?? ''),
            'cover_image' => esc_url_raw($_POST['cover_image'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? ''),
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
        $this->verify_public_read_ajax_request();
        
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
        $this->verify_public_read_ajax_request();

        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        $location_name = sanitize_text_field($_POST['location_name'] ?? '');

        wp_send_json_success($this->find_location_posts($latitude, $longitude, $location_name));
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
        $new_status = $this->normalize_status($_POST['status']);
        
        if (!in_array($new_status, array('done', 'wish', 'plan'), true)) {
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
        
        $this->clear_markers_cache();
        
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
     * AJAX: 校正坐标后批量导入
     *
     * 前端开启 WGS-84 纠偏时，把经 AMap.convertFrom 转换好的行数据
     * 直接按行导入（import_data = JSON 数组，每项一条标记），
     * 不再走文件解析路径。
     */
    public function ajax_import_rows() {
        check_ajax_referer('travel_map_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('权限不足', TRAVEL_MAP_TEXT_DOMAIN));
        }

        $rows = isset($_POST['import_data']) ? json_decode(wp_unslash($_POST['import_data']), true) : null;
        if (!is_array($rows) || empty($rows)) {
            wp_send_json_error(__('没有可导入的数据', TRAVEL_MAP_TEXT_DOMAIN));
        }

        $imported = 0;
        foreach ($rows as $row) {
            if ($this->import_row($row)) {
                $imported++;
            }
        }
        $this->clear_markers_cache();

        wp_send_json_success(array(
            'message' => sprintf(__('已导入 %d 个标记点', TRAVEL_MAP_TEXT_DOMAIN), $imported),
            'count' => $imported
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
                     sprintf(__('本插件需要 PHP 7.4 或更高版本，当前版本：%s', TRAVEL_MAP_TEXT_DOMAIN), PHP_VERSION) . 
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
            
            // 加载高德地图API - 在头部加载以确保可用性
            wp_enqueue_script(
                'amap-api-meta-box',
                "https://webapi.amap.com/maps?v=2.0&key={$api_key}",
                array(),
                TRAVEL_MAP_VERSION,
                false  // 在头部加载
            );
            
            wp_add_inline_script('amap-api-meta-box', $this->get_safari_retina_fix_script(), 'before');

            if (!empty($security_key)) {
                $security_script = "window._AMapSecurityConfig = { securityJsCode: '{$security_key}' };";
                wp_add_inline_script('amap-api-meta-box', $security_script, 'before');
            }
        }
        
        // 确保jQuery已加载
        wp_enqueue_style(
            'travel-map-meta-box',
            TRAVEL_MAP_PLUGIN_URL . 'assets/css/travel-map-meta-box.css',
            array(),
            $this->get_asset_version('assets/css/travel-map-meta-box.css')
        );
        
        wp_enqueue_script(
            'travel-map-meta-box',
            TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map-meta-box.js',
            array('jquery'),
            $this->get_asset_version('assets/js/travel-map-meta-box.js'),
            true
        );
        
        wp_localize_script('travel-map-meta-box', 'travelMapConfig', array(
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
                'status' => $this->normalize_status($_POST['new_marker_status']) ?: 'done',
                'description' => sanitize_textarea_field($_POST['new_marker_description'] ?? ''),
                'country' => self::sanitize_country($_POST['new_marker_country'] ?? ''),
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
        delete_option('travel_map_security_key');
        delete_option('travel_map_default_zoom');
        delete_option('travel_map_default_center');
        delete_option('travel_map_show_filter_tabs');
        delete_option('travel_map_db_version');
        foreach (array_keys(TravelMapMigrator::default_settings()) as $key) {
            delete_option($key);
        }
        // 兼容清理：1.0.x 的三色选项
        delete_option('travel_map_visited_color');
        delete_option('travel_map_want_to_go_color');
        delete_option('travel_map_planned_color');
        
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
            status enum('done','wish','plan') DEFAULT 'done',
            visit_date date NULL,
            visit_count int DEFAULT 1,
            description text NULL,
            marker_color varchar(7) DEFAULT NULL,
            planned_date date NULL,
            wish_reason text NULL,
            priority_level tinyint DEFAULT 3,
            is_featured boolean DEFAULT FALSE,
            country varchar(20) NULL,
            years text NULL,
            cover_image text NULL,
            type varchar(30) NULL DEFAULT '',
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
        foreach (TravelMapMigrator::default_settings() as $key => $value) {
            add_option($key, $value);
        }
        update_option('travel_map_db_version', TravelMapMigrator::DB_VERSION);
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
    private function get_markers_by_status($status = 'all', $search = '') {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        $table_posts = $wpdb->posts;
        
        $search = trim($search);
        $use_cache = ($search === '');
        $cache_key = '';
        
        if ($use_cache) {
            $cache_key = $this->get_markers_cache_key($status);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $conditions = array();
        $params = array();
        
        if ($status !== 'all') {
            $conditions[] = 'm.status = %s';
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $conditions[] = "(m.title LIKE %s OR m.description LIKE %s OR p.post_title LIKE %s OR EXISTS (
                SELECT 1 FROM $table_post_markers pm2
                INNER JOIN $table_posts p2 ON pm2.post_id = p2.ID
                WHERE pm2.marker_id = m.id AND p2.post_status = 'publish' AND p2.post_title LIKE %s
            ))";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $sql = "SELECT m.*, p.post_title 
                FROM $table_markers m 
                LEFT JOIN $table_posts p ON m.post_id = p.ID AND p.post_status = 'publish'";
        
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= ' ORDER BY m.created_at DESC';
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $markers = $wpdb->get_results($sql);
        
        if (empty($markers)) {
            return $markers;
        }
        
        // 预加载关联文章标题（包含关联表）
        $marker_ids = array_map('intval', wp_list_pluck($markers, 'id'));
        $marker_to_post_ids = array();
        $all_post_ids = array();
        
        foreach ($markers as $marker) {
            $marker_to_post_ids[$marker->id] = array();
            if (!empty($marker->post_id)) {
                $marker_to_post_ids[$marker->id][$marker->post_id] = (int) $marker->post_id;
                $all_post_ids[$marker->post_id] = (int) $marker->post_id;
            }
        }
        
        if (!empty($marker_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($marker_ids), '%d'));
            $related_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT marker_id, post_id FROM $table_post_markers WHERE marker_id IN ($ids_placeholder)",
                    $marker_ids
                )
            );
            
            foreach ($related_rows as $row) {
                $mid = (int) $row->marker_id;
                $pid = (int) $row->post_id;
                if (!isset($marker_to_post_ids[$mid])) {
                    $marker_to_post_ids[$mid] = array();
                }
                $marker_to_post_ids[$mid][$pid] = $pid;
                $all_post_ids[$pid] = $pid;
            }
        }
        
        $post_titles_by_id = array();
        if (!empty($all_post_ids)) {
            $post_ids = array_values($all_post_ids);
            $post_ids_placeholder = implode(',', array_fill(0, count($post_ids), '%d'));
            $post_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title FROM $table_posts WHERE ID IN ($post_ids_placeholder) AND post_status = 'publish'",
                    $post_ids
                )
            );
            
            foreach ($post_rows as $post_row) {
                $post_titles_by_id[(int) $post_row->ID] = $post_row->post_title;
            }
        }
        
        foreach ($markers as $marker) {
            $post_titles = array();
            $post_ids = $marker_to_post_ids[$marker->id] ?? array();
            foreach ($post_ids as $pid) {
                if (isset($post_titles_by_id[$pid])) {
                    $post_titles[] = $post_titles_by_id[$pid];
                }
            }
            $marker->post_titles = array_values($post_titles);
            $marker->post_title = !empty($marker->post_titles) ? $marker->post_titles[0] : ($marker->post_title ?? null);
        }
        
        // 为每个标记点添加最新文章的特色图片
        foreach ($markers as $marker) {
            if ($marker->status === 'done') {
                $featured_image = $this->get_marker_featured_image($marker);
                $marker->featured_image = $featured_image;
            }
        }
        
        if ($use_cache) {
            set_transient($cache_key, $markers, 5 * MINUTE_IN_SECONDS);
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
     * 查询地点相关文章
     */
    private function find_location_posts($latitude, $longitude, $location_name) {
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

        if (!$marker) {
            return $articles;
        }

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

        return $articles;
    }
    
    /**
     * 保存标记点
     */
    private function save_marker($marker_data) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        $result = $wpdb->insert($table_markers, $marker_data);
        $this->clear_markers_cache();
        return $result;
    }
    
    /**
     * 删除标记点
     */
    private function delete_marker($marker_id) {
        global $wpdb;
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        $table_post_markers = $wpdb->prefix . 'travel_map_post_markers';
        
        // 先清理关联表，避免遗留孤儿记录
        $wpdb->delete($table_post_markers, array('marker_id' => $marker_id), array('%d'));
        $result = $wpdb->delete($table_markers, array('id' => $marker_id), array('%d'));
        $this->clear_markers_cache();
        return $result;
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

        // 先写入内存流再一次性输出，避免直接 fopen 输出流
        $output = fopen('php://temp', 'r+');

        // CSV 头部
        fputcsv($output, array(
            'ID', 'Title', 'Latitude', 'Longitude', 'Status', 'Description',
            'Visit Date', 'Visit Count', 'Planned Date', 'Wish Reason',
            'Priority Level', 'Country', 'Years', 'Cover Image', 'Type', 'Created At'
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
                isset($marker->country) ? $marker->country : '',
                isset($marker->years) ? $marker->years : '',
                isset($marker->cover_image) ? $marker->cover_image : '',
                isset($marker->type) ? $marker->type : '',
                $marker->created_at
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $csv;
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
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 4) {
                $marker_data = array(
                    'title' => sanitize_text_field($data[1]),
                    'latitude' => floatval($data[2]),
                    'longitude' => floatval($data[3]),
                    'status' => $this->is_valid_status($data[4] ?? '') ? $this->normalize_status($data[4]) : 'done',
                    'description' => sanitize_textarea_field($data[5] ?? ''),
                    'visit_date' => !empty($data[6]) ? $data[6] : null,
                    'visit_count' => intval($data[7] ?? 1),
                    'planned_date' => !empty($data[8]) ? $data[8] : null,
                    'wish_reason' => sanitize_textarea_field($data[9] ?? ''),
                    'priority_level' => intval($data[10] ?? 3),
                    'country' => self::sanitize_country($data[11] ?? ''),
                    'years' => self::sanitize_years($data[12] ?? ''),
                    'cover_image' => esc_url_raw($data[13] ?? ''),
                    'type' => sanitize_text_field($data[14] ?? ''),
                );
                
                if ($this->import_row($marker_data)) {
                    $imported_count++;
                }
            }
        }
        
        fclose($handle);
        $this->clear_markers_cache();
        return $imported_count;
    }

    /**
     * 写入单条标记（导入共用路径，字段已在上游 sanitize）
     */
    private function import_row($marker_data) {
        global $wpdb;
        $required = array('title', 'latitude', 'longitude', 'status');
        foreach ($required as $key) {
            if (!isset($marker_data[$key]) || $marker_data[$key] === '' || $marker_data[$key] === null) {
                return false;
            }
        }
        return (bool) $wpdb->insert($wpdb->prefix . 'travel_map_markers', $marker_data);
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
        
        foreach ($data as $item) {
            if (isset($item['title'], $item['latitude'], $item['longitude'])) {
                $status = isset($item['status']) ? $this->normalize_status($item['status']) : 'done';
                if (!in_array($status, array('done', 'wish', 'plan'), true)) {
                    $status = 'done';
                }
                $marker_data = array(
                    'title' => sanitize_text_field($item['title']),
                    'latitude' => floatval($item['latitude']),
                    'longitude' => floatval($item['longitude']),
                    'status' => $status,
                    'description' => sanitize_textarea_field($item['description'] ?? ''),
                    'visit_date' => !empty($item['visit_date']) ? $item['visit_date'] : null,
                    'visit_count' => intval($item['visit_count'] ?? 1),
                    'planned_date' => !empty($item['planned_date']) ? $item['planned_date'] : null,
                    'wish_reason' => sanitize_textarea_field($item['wish_reason'] ?? ''),
                    'priority_level' => intval($item['priority_level'] ?? 3),
                    'country' => self::sanitize_country($item['country'] ?? ''),
                    'years' => self::sanitize_years($item['years'] ?? ''),
                    'cover_image' => esc_url_raw($item['cover_image'] ?? ''),
                    'type' => sanitize_text_field($item['type'] ?? ''),
                );
                
                if ($this->import_row($marker_data)) {
                    $imported_count++;
                }
            }
        }
        $this->clear_markers_cache();
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
            'status' => $this->normalize_status($post_data['status']),
            'description' => sanitize_textarea_field($post_data['description']),
            'post_id' => intval($post_data['post_id']) ?: null,
            'visit_date' => !empty($post_data['visit_date']) ? $post_data['visit_date'] : null,
            'visit_count' => intval($post_data['visit_count']) ?: 1,
            'planned_date' => !empty($post_data['planned_date']) ? $post_data['planned_date'] : null,
            'wish_reason' => sanitize_textarea_field($post_data['wish_reason']),
            'priority_level' => intval($post_data['priority_level']) ?: 3,
            'country' => self::sanitize_country($post_data['country'] ?? ''),
            'years' => self::sanitize_years($post_data['years'] ?? ''),
            'cover_image' => esc_url_raw($post_data['cover_image'] ?? ''),
            'type' => sanitize_text_field($post_data['type'] ?? ''),
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
            'status' => $this->normalize_status($post_data['status']),
            'description' => sanitize_textarea_field($post_data['description']),
            'post_id' => intval($post_data['post_id']) ?: null,
            'visit_date' => !empty($post_data['visit_date']) ? $post_data['visit_date'] : null,
            'visit_count' => intval($post_data['visit_count']) ?: 1,
            'planned_date' => !empty($post_data['planned_date']) ? $post_data['planned_date'] : null,
            'wish_reason' => sanitize_textarea_field($post_data['wish_reason']),
            'priority_level' => intval($post_data['priority_level']) ?: 3,
            'country' => self::sanitize_country($post_data['country'] ?? ''),
            'years' => self::sanitize_years($post_data['years'] ?? ''),
            'cover_image' => esc_url_raw($post_data['cover_image'] ?? ''),
            'type' => sanitize_text_field($post_data['type'] ?? ''),
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
        $result = $wpdb->update($table_markers, $marker_data, array('id' => $marker_id));
        $this->clear_markers_cache();
        return $result;
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
        $table_markers = $wpdb->prefix . 'travel_map_markers';
        
        // 先删除现有关联
        $wpdb->delete($table_post_markers, array('post_id' => $post_id));
        
        // 清理之前设置在标记点上的主关联，避免遗留错误关联
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_markers SET post_id = NULL WHERE post_id = %d",
                $post_id
            )
        );
        
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
        
        $this->clear_markers_cache();
    }
}

// 初始化插件
TravelMapPlugin::get_instance();

/**
 * 供主题/模板主动调用的资源加载函数
 */
if (!function_exists('travel_map_enqueue_assets')) {
    function travel_map_enqueue_assets() {
        TravelMapPlugin::get_instance()->enqueue_frontend_scripts();
        
        if (did_action('wp_head') && !wp_style_is('travel-map-frontend', 'done')) {
            wp_print_styles('travel-map-frontend');
        }
    }
}
