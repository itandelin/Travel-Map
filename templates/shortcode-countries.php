<?php
/**
 * [travel_map_countries] 去过国家国旗墙模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="travel-map-countries">
    <?php if (empty($countries)) : ?>
        <p class="travel-map-countries-empty"><?php _e('还没有已去国家的记录。为地点填写国别（ISO 代码）后，这里会展示你的足迹国家。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    <?php else : ?>
        <?php foreach ($countries as $code => $data) :
            $flag = TRAVEL_MAP_PLUGIN_URL . 'assets/flags/' . strtolower(substr($code, 0, 2)) . '.svg';
        ?>
            <div class="travel-map-country-item" title="<?php echo esc_attr($data['count']); ?> 个地点">
                <img class="travel-map-country-flag" src="<?php echo esc_url($flag); ?>" alt="<?php echo esc_attr($code); ?>"
                    onerror="this.style.display='none'">
                <span class="travel-map-country-code"><?php echo esc_html($code); ?></span>
                <span class="travel-map-country-count"><?php echo esc_html($data['count']); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
