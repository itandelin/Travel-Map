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
$show_filter_tabs = filter_var($atts['filter_tabs'], FILTER_VALIDATE_BOOLEAN);
$container_classes = 'travel-map-container' . ($show_filter_tabs ? '' : ' travel-map-no-tabs');
?>

<div class="<?php echo esc_attr($container_classes); ?>" style="width: <?php echo esc_attr($atts['width']); ?>; --travel-map-height: <?php echo esc_attr($atts['height']); ?>;">
    
    <div class="travel-map-wrapper">
        <div class="travel-map-loading">
            <div class="travel-map-spinner"></div>
            <div class="travel-map-loading-text"><?php _e('正在加载地图...', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
        </div>
        
        <div
            class="travel-map"
            id="<?php echo esc_attr($map_id); ?>"
            data-travel-map-init="1"
            data-zoom="<?php echo esc_attr(intval($atts['zoom'])); ?>"
            data-center-lat="<?php echo esc_attr($center_lat); ?>"
            data-center-lng="<?php echo esc_attr($center_lng); ?>"
            data-show-filter-tabs="<?php echo $show_filter_tabs ? '1' : '0'; ?>"
            data-status="<?php echo esc_attr($atts['status']); ?>"
            data-api-key="<?php echo esc_attr($api_key); ?>"
        ></div>
        
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

<!-- 无障碍访问支持 -->
<div class="travel-map-accessibility">
    <h3><?php _e('旅行地图', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
    <p><?php _e('这是一个交互式地图，显示了旅行目的地。您可以使用Tab键导航，使用Enter键激活控件。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    <ul>
        <li><?php _e('使用筛选标签查看不同类型的地点', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
        <li><?php _e('点击地图标记查看详细信息', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
        <li><?php _e('使用地图控制按钮调整视图', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
    </ul>
</div>
