<?php
/**
 * [travel_map_markers] 标记归档列表模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="travel-map-markers-archive">
    <?php if (empty($markers)) : ?>
        <p class="travel-map-markers-empty"><?php _e('暂无符合条件的地点。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    <?php else : ?>
        <?php foreach ($markers as $marker) :
            $posts = $marker->posts;
            $country = strtoupper((string) $marker->country);
            $first_code = $country ? explode(',', $country)[0] : '';
            $flag = $first_code
                ? TRAVEL_MAP_PLUGIN_URL . 'assets/flags/' . strtolower($first_code) . '.svg'
                : '';
            $years = array_filter(array_map('trim', explode(',', (string) $marker->years)));
        ?>
            <div class="marker-item">
                <div class="marker-name">
                    <?php if ($flag) : ?>
                        <img class="marker-flag" src="<?php echo esc_url($flag); ?>" alt="<?php echo esc_attr($first_code); ?>"
                            onerror="this.style.display='none'">
                    <?php endif; ?>
                    <?php echo esc_html($marker->title); ?>
                    <span class="marker-coords">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 21s-8-6.5-8-12a8 8 0 1 1 16 0c0 5.5-8 12-8 12z"/>
                            <circle cx="12" cy="9" r="3"/>
                        </svg>
                        <?php echo esc_html($marker->latitude . ', ' . $marker->longitude); ?>
                    </span>
                </div>

                <?php if ($years) : ?>
                    <div class="travel-map-popup-years">
                        <?php foreach ($years as $year) : ?>
                            <span class="travel-map-year-chip"><?php echo esc_html($year); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($posts) : ?>
                    <div class="marker-posts">
                        <?php foreach ($posts as $post) : ?>
                            <a class="marker-post" href="<?php echo esc_url($post['permalink']); ?>">
                                <?php if (!empty($post['cover'])) : ?>
                                    <img src="<?php echo esc_url($post['cover']); ?>" alt="<?php echo esc_attr($post['title']); ?>" loading="lazy">
                                <?php endif; ?>
                                <div class="marker-postTitle"><?php echo esc_html($post['title']); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
