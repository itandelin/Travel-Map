<?php
/**
 * 文章编辑页面的地图坐标 Meta Box 模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="travel-map-meta-box">
    

    <!-- 已关联的坐标点 -->
    <div class="travel-map-meta-section">
        <h4 class="travel-map-meta-title"><?php _e('选择已有地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
        
        <?php if (!empty($all_markers)): ?>
            <!-- 统计信息 -->
            <?php
            $stats = array(
                'total' => count($all_markers),
                'visited' => 0,
                'want_to_go' => 0,
                'planned' => 0,
                'selected' => count($associated_markers)
            );
            
            foreach ($all_markers as $marker) {
                if (isset($stats[$marker->status])) {
                    $stats[$marker->status]++;
                }
            }
            ?>
            
            <div class="travel-map-stats">
                <div class="travel-map-stat-item">
                    <span>共</span>
                    <span class="travel-map-stat-count"><?php echo $stats['total']; ?></span>
                    <span>个地点</span>
                </div>
                <div class="travel-map-stat-item">
                    <span>已选</span>
                    <span class="travel-map-stat-count travel-map-selected-count"><?php echo $stats['selected']; ?></span>
                    <span>个</span>
                </div>
                <div class="travel-map-stat-item">
                    <span style="color: #9a3412;">•</span>
                    <span><?php echo $stats['visited']; ?> 已去</span>
                </div>
                <div class="travel-map-stat-item">
                    <span style="color: #1e40af;">•</span>
                    <span><?php echo $stats['want_to_go']; ?> 想去</span>
                </div>
                <div class="travel-map-stat-item">
                    <span style="color: #065f46;">•</span>
                    <span><?php echo $stats['planned']; ?> 计划</span>
                </div>
            </div>
            
            <!-- 搜索框 -->
            <div class="travel-map-search-container">
                <span class="travel-map-search-icon">🔍</span>
                <input 
                    type="text" 
                    class="travel-map-search-input" 
                    placeholder="<?php _e('搜索地点名称...', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                    id="travel-map-search"
                >
                <button type="button" class="travel-map-search-clear" id="travel-map-search-clear">×</button>
            </div>
            
            <!-- 筛选按钮 -->
            <div class="travel-map-filters">
                <button type="button" class="travel-map-filter-btn active" data-filter="all">
                    <?php _e('全部', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="travel-map-filter-btn" data-filter="visited">
                    <?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="travel-map-filter-btn" data-filter="want_to_go">
                    <?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="travel-map-filter-btn" data-filter="planned">
                    <?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </button>
            </div>
            
            <!-- 紧凑的标记网格 -->
            <div class="travel-map-markers-grid" id="travel-map-markers-grid">
                <?php 
                $status_labels = array(
                    'visited' => __('已去', TRAVEL_MAP_TEXT_DOMAIN),
                    'want_to_go' => __('想去', TRAVEL_MAP_TEXT_DOMAIN),
                    'planned' => __('计划', TRAVEL_MAP_TEXT_DOMAIN)
                );
                
                foreach ($all_markers as $marker): 
                    $is_associated = false;
                    foreach ($associated_markers as $assoc_marker) {
                        if ($assoc_marker->id == $marker->id) {
                            $is_associated = true;
                            break;
                        }
                    }
                ?>
                    <div class="travel-map-marker-compact <?php echo $is_associated ? 'selected' : ''; ?>" 
                         data-marker-id="<?php echo esc_attr($marker->id); ?>"
                         data-marker-status="<?php echo esc_attr($marker->status); ?>"
                         data-marker-name="<?php echo esc_attr(strtolower($marker->title)); ?>">
                        <input 
                            type="checkbox" 
                            name="travel_map_markers[]" 
                            value="<?php echo esc_attr($marker->id); ?>"
                            class="travel-map-marker-checkbox"
                            <?php checked($is_associated); ?>
                        >
                        <div class="travel-map-marker-content">
                            <div class="travel-map-marker-name" title="<?php echo esc_attr($marker->title); ?>">
                                <?php echo esc_html($marker->title); ?>
                            </div>
                            <div class="travel-map-marker-status-compact <?php echo esc_attr($marker->status); ?>">
                                <?php echo $status_labels[$marker->status] ?? __('未知', TRAVEL_MAP_TEXT_DOMAIN); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 无结果提示 -->
            <div class="travel-map-no-results" id="travel-map-no-results" style="display: none;">
                <p><?php _e('未找到匹配的地点', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
            </div>
            
        <?php else: ?>
            <p style="color: #6b7280; font-style: italic;">
                <?php _e('还没有创建任何地点标记。您可以在下方创建新标记，或前往', TRAVEL_MAP_TEXT_DOMAIN); ?>
                <a href="<?php echo admin_url('admin.php?page=travel-map-markers'); ?>" target="_blank">
                    <?php _e('坐标管理页面', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </a>
                <?php _e('创建标记。', TRAVEL_MAP_TEXT_DOMAIN); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- 创建新标记点 -->
    <div class="travel-map-meta-section">
        <h4 class="travel-map-meta-title"><?php _e('或创建新地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
        
        <div class="travel-map-new-marker-form">
            <div class="travel-map-form-row">
                <label class="travel-map-form-label"><?php _e('地点名称', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                <input 
                    type="text" 
                    name="new_marker_title" 
                    class="travel-map-form-input"
                    placeholder="<?php _e('例如：巴黎埃菲尔铁塔', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                >
            </div>
            
            <!-- 地图选择器 -->
            <div class="travel-map-map-picker-section">
                <label class="travel-map-form-label"><?php _e('坐标位置', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                <div class="travel-map-map-picker-container">
                    <div class="travel-map-picker-instructions">
                        <span class="travel-map-picker-icon">📍</span>
                        <span><?php _e('点击地图选择位置', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                    </div>
                    <div id="meta-box-map" class="travel-map-mini-selector"></div>
                    <div class="travel-map-coords-display">
                        <div class="travel-map-coord-item">
                            <label><?php _e('纬度', TRAVEL_MAP_TEXT_DOMAIN); ?>:</label>
                            <input 
                                type="number" 
                                name="new_marker_latitude" 
                                id="meta-latitude"
                                class="travel-map-coord-input"
                                placeholder="39.9042"
                                step="0.000001"
                                min="-90"
                                max="90"
                                readonly
                            >
                        </div>
                        <div class="travel-map-coord-item">
                            <label><?php _e('经度', TRAVEL_MAP_TEXT_DOMAIN); ?>:</label>
                            <input 
                                type="number" 
                                name="new_marker_longitude" 
                                id="meta-longitude"
                                class="travel-map-coord-input"
                                placeholder="116.4074"
                                step="0.000001"
                                min="-180"
                                max="180"
                                readonly
                            >
                        </div>
                    </div>
                    <div class="travel-map-manual-input-toggle">
                        <button type="button" id="toggle-manual-input" class="travel-map-toggle-btn">
                            ✏️ <?php _e('手动输入坐标', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-label"><?php _e('旅行状态', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                <select name="new_marker_status" class="travel-map-form-input">
                    <option value="visited"><?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    <option value="want_to_go"><?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    <option value="planned"><?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                </select>
            </div>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-label"><?php _e('地点描述', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                <textarea 
                    name="new_marker_description" 
                    class="travel-map-form-input"
                    rows="3"
                    placeholder="<?php _e('描述这个地点...', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                ></textarea>
            </div>
        </div>
        
        <div class="travel-map-help-text">
            <?php _e('提示：您可以使用在线地图工具（如高德地图、百度地图等）查找准确的经纬度坐标。', TRAVEL_MAP_TEXT_DOMAIN); ?>
        </div>
    </div>

    <!-- 快速操作提示 -->
    <div class="travel-map-meta-section">
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px;">
            <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 13px;">
                <?php _e('💡 使用提示', TRAVEL_MAP_TEXT_DOMAIN); ?>
            </h4>
            <ul style="margin: 0; padding-left: 20px; font-size: 12px; color: #374151;">
                <li><?php _e('勾选已有标记点将该文章与地点关联', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
                <li><?php _e('创建新标记点会自动关联到当前文章', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
                <li><?php _e('文章发布后，访客可以在地图上点击查看相关文章', TRAVEL_MAP_TEXT_DOMAIN); ?></li>
                <li>
                    <?php _e('管理所有坐标请访问：', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    <a href="<?php echo admin_url('admin.php?page=travel-map-markers'); ?>" target="_blank">
                        <?php _e('坐标管理页面', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>


