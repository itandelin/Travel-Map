<?php
/**
 * 地图短代码显示模板 - 原生JavaScript版本
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 处理中心点坐标
$center_parts = explode(',', $atts['center']);
$center_lat = isset($center_parts[0]) ? floatval(trim($center_parts[0])) : 35.0;
$center_lng = isset($center_parts[1]) ? floatval(trim($center_parts[1])) : 105.0;
?>

<div class="travel-map-container" style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
    
    <div class="travel-map-wrapper">
        <div class="travel-map-loading">
            <div class="travel-map-spinner"></div>
            <div class="travel-map-loading-text"><?php _e('正在加载地图...', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
        </div>
        
        <div class="travel-map" id="<?php echo esc_attr($map_id); ?>"></div>
        
        <div class="travel-map-controls">
            <button class="travel-map-control-btn" data-action="zoom-in" title="<?php _e('放大', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="zoom-out" title="<?php _e('缩小', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13H5v-2h14v2z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="fullscreen" title="<?php _e('全屏', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="reset" title="<?php _e('重置视图', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- 原生JavaScript版本 - 无jQuery依赖 -->
<script>
(function() {
    'use strict';
    
    console.log('Travel Map: 使用原生JavaScript初始化 - v<?php echo TRAVEL_MAP_VERSION; ?>');
    console.log('API Key:', '<?php echo esc_js($api_key ? '已配置' : '未配置'); ?>');
    
    // 直接初始化，不等待jQuery
    function initMap() {
        console.log('开始初始化地图');
        
        // 检查脚本是否已加载
        if (typeof window.initTravelMap !== 'function') {
            console.log('动态加载地图脚本...');
            loadScript('<?php echo TRAVEL_MAP_PLUGIN_URL . 'assets/js/travel-map.js?v=' . TRAVEL_MAP_VERSION . '.' . time(); ?>', function() {
                console.log('地图脚本加载完成');
                setTimeout(function() {
                    if (typeof window.initTravelMap === 'function') {
                        startMap();
                    } else {
                        showError('地图脚本加载失败');
                    }
                }, 100);
            });
        } else {
            startMap();
        }
    }
    
    function startMap() {
        try {
            console.log('开始启动地图');
            window.initTravelMap('#<?php echo esc_js($map_id); ?>', {
                zoom: <?php echo intval($atts['zoom']); ?>,
                center: [<?php echo floatval($center_lng); ?>, <?php echo floatval($center_lat); ?>],
                showFilterTabs: <?php echo filter_var($atts['filter_tabs'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'; ?>,
                defaultStatus: '<?php echo esc_js($atts['status']); ?>',
                apiKey: '<?php echo esc_js($api_key); ?>'
            });
            console.log('地图启动成功');
        } catch (error) {
            console.error('地图初始化失败:', error);
            showError('地图初始化失败: ' + error.message);
        }
    }
    
    function loadScript(src, callback) {
        var script = document.createElement('script');
        script.src = src;
        script.onload = callback;
        script.onerror = function() {
            console.error('脚本加载失败:', src);
            showError('脚本加载失败');
        };
        document.head.appendChild(script);
    }
    
    function showError(message) {
        var container = document.getElementById('<?php echo esc_js($map_id); ?>');
        if (container && container.parentElement) {
            container.parentElement.innerHTML = 
                '<div class="travel-map-error">' +
                    '<div class="travel-map-error-icon">⚠️</div>' +
                    '<div class="travel-map-error-message">' + message + '</div>' +
                    '<div class="travel-map-error-details">' +
                        '<p>可能的解决方案：</p>' +
                        '<ul>' +
                            '<li>检查网络连接是否正常</li>' +
                            '<li>确认高德地图API密钥配置正确</li>' +
                            '<li>刷新页面重试</li>' +
                        '</ul>' +
                        '<button class="travel-map-retry-btn" onclick="location.reload()">刷新页面</button>' +
                    '</div>' +
                '</div>';
        }
    }
    
    // 等待DOM加载完成
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM加载完成，开始初始化');
            setTimeout(initMap, 100);
        });
    } else {
        console.log('DOM已就绪，立即初始化');
        setTimeout(initMap, 100);
    }
})();
</script>

<style>
/* 当前地图实例的特定样式 */
#<?php echo esc_attr($map_id); ?> {
    width: 100%;
    height: <?php echo esc_attr($atts['height']); ?>;
    min-height: 400px;
    background: #f5f5f5;
    border-radius: 0 0 8px 8px;
}

/* 确保地图容器有明确的高度 */
.travel-map-container[style*="height: <?php echo esc_attr($atts['height']); ?>"] .travel-map-wrapper {
    height: <?php echo esc_attr($atts['height']); ?>;
    min-height: 400px;
}

<?php if (!filter_var($atts['filter_tabs'], FILTER_VALIDATE_BOOLEAN)): ?>
#<?php echo esc_attr($map_id); ?> {
    border-radius: 8px;
}
<?php endif; ?>

/* 响应式高度调整 */
@media (max-width: 768px) {
    .travel-map-container[style*="height: <?php echo esc_attr($atts['height']); ?>"] {
        height: <?php echo intval($atts['height']) > 400 ? '350px' : esc_attr($atts['height']); ?> !important;
    }
}

@media (max-width: 480px) {
    .travel-map-container[style*="height: <?php echo esc_attr($atts['height']); ?>"] {
        height: 300px !important;
    }
}
</style>

<?php
// 添加结构化数据
$schema_data = array(
    '@context' => 'https://schema.org',
    '@type' => 'Map',
    'name' => get_the_title() . ' - ' . __('旅行地图', TRAVEL_MAP_TEXT_DOMAIN),
    'description' => __('互动式旅行地图，展示已去、想去和计划的旅行目的地', TRAVEL_MAP_TEXT_DOMAIN),
    'url' => get_permalink(),
    'mapType' => 'InteractiveMap'
);
?>

<script type="application/ld+json">
<?php echo json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
</script>

<!-- 无障碍访问支持 -->
<div class="travel-map-accessibility" style="position: absolute; left: -9999px;">
    <h3><?php _e('旅行地图', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
    <p><?php _e('这是一个交互式地图，显示了旅行目的地。您可以使用Tab键导航，使用Enter键激活控件。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    <ul>
        <li><?php _e('使用筛选标签查看不同类型的地点', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
        <li><?php _e('点击地图标记查看详细信息', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
        <li><?php _e('使用地图控制按钮调整视图', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
    </ul>
</div>