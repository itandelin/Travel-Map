<?php
/**
 * 地图短代码显示模板
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

// 短代码级 auto_zoom 覆盖：空串 = 跟随设置项
$auto_zoom_attr = $atts['auto_zoom'];
?>

<div class="<?php echo esc_attr($container_classes); ?>" style="width: <?php echo esc_attr($atts['width']); ?>; --travel-map-height: <?php echo esc_attr($atts['height']); ?>; --tm-h-desktop: <?php echo esc_attr($atts['height']); ?>; --tm-h-mobile-base: <?php echo esc_attr($mobile_height_base); ?>;<?php echo $mobile_height_override !== '' ? ' --tm-h-mobile-override: ' . esc_attr($mobile_height_override) . ';' : ''; ?>">
    
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
            data-filters="<?php echo esc_attr($atts['filters']); ?>"
            <?php if ($auto_zoom_attr !== '') : ?>
            data-auto-zoom="<?php echo esc_attr(filter_var($auto_zoom_attr, FILTER_VALIDATE_BOOLEAN) ? '1' : '0'); ?>"
            <?php endif; ?>
            data-api-key="<?php echo esc_attr($api_key); ?>"
        ></div>
        
        <div class="travel-map-controls">
            <button class="travel-map-control-btn" data-action="zoom-in" type="button" aria-label="<?php esc_attr_e('放大', TRAVEL_MAP_TEXT_DOMAIN); ?>" title="<?php _e('放大', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="zoom-out" type="button" aria-label="<?php esc_attr_e('缩小', TRAVEL_MAP_TEXT_DOMAIN); ?>" title="<?php _e('缩小', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M19 13H5v-2h14v2z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="back" type="button" aria-label="<?php esc_attr_e('返回', TRAVEL_MAP_TEXT_DOMAIN); ?>" title="<?php _e('返回', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </button>
            <button class="travel-map-control-btn" data-action="fullscreen" type="button" aria-label="<?php esc_attr_e('全屏', TRAVEL_MAP_TEXT_DOMAIN); ?>" aria-pressed="false" title="<?php _e('全屏', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<?php
/**
 * 无障碍等价内容
 *
 * 地图标记是高德地图生成的普通 div，无法 Tab 聚焦、无法键盘触发。
 * 给每个标记加 tabindex 会在地图上产生大量焦点目标，缩放平移时更混乱。
 * 这里提供一份视觉隐藏、但可键盘操作的等价地点列表，由 JS 按当前筛选状态
 * 同步渲染（renderAccessibleList）。
 */
?>
<div class="travel-map-accessibility" id="<?php echo esc_attr($map_id); ?>-a11y">
    <h3><?php _e('地点列表', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
    <p><?php _e('这是一个交互式地图。下方列表与地图标记内容等价，可用键盘操作。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    <ul class="travel-map-a11y-list" data-travel-map-a11y-list="1">
        <li><?php _e('正在加载地点列表...', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
    </ul>
</div>
