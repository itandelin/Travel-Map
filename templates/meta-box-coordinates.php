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
    <style>
        .travel-map-meta-box {
            padding: 10px 0;
        }
        
        .travel-map-meta-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .travel-map-meta-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .travel-map-meta-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #374151;
        }
        
        /* 搜索框样式 */
        .travel-map-search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .travel-map-search-input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            background: #fff;
        }
        
        .travel-map-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 16px;
        }
        
        .travel-map-search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
            padding: 2px;
            display: none;
        }
        
        .travel-map-search-clear:hover {
            color: #6b7280;
        }
        
        /* 紧凑的标记列表样式 */
        .travel-map-markers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 8px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            background: #fafafa;
        }
        
        .travel-map-marker-compact {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .travel-map-marker-compact:hover {
            border-color: #3b82f6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .travel-map-marker-compact.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .travel-map-marker-checkbox {
            margin-right: 8px;
            margin-top: 0;
        }
        
        .travel-map-marker-content {
            flex: 1;
            min-width: 0;
        }
        
        .travel-map-marker-name {
            font-size: 13px;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .travel-map-marker-status-compact {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 500;
            margin-top: 2px;
        }
        
        .travel-map-marker-status-compact.visited {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .travel-map-marker-status-compact.want_to_go {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .travel-map-marker-status-compact.planned {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* 统计信息 */
        .travel-map-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #6b7280;
        }
        
        .travel-map-stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .travel-map-stat-count {
            font-weight: 600;
            color: #374151;
        }
        
        /* 筛选按钮 */
        .travel-map-filters {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
        }
        
        .travel-map-filter-btn {
            padding: 4px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #fff;
            color: #6b7280;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .travel-map-filter-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .travel-map-filter-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
        }
        
        /* 地图选择器样式 */
        .travel-map-map-picker-section {
            margin-bottom: 15px;
        }
        
        .travel-map-map-picker-container {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        
        .travel-map-picker-instructions {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
        }
        
        .travel-map-picker-icon {
            font-size: 16px;
        }
        
        .travel-map-mini-selector {
            width: 100%;
            height: 200px;
            background: #f5f5f5;
            position: relative;
            cursor: crosshair;
        }
        
        .travel-map-coords-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 12px 15px;
            background: #fafafa;
            border-top: 1px solid #e5e7eb;
        }
        
        .travel-map-coord-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .travel-map-coord-item label {
            font-size: 12px;
            font-weight: 500;
            color: #374151;
        }
        
        .travel-map-coord-input {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
            background: #f9fafb;
            font-family: monospace;
        }
        
        .travel-map-coord-input:not([readonly]) {
            background: #fff;
        }
        
        .travel-map-coord-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .travel-map-manual-input-toggle {
            padding: 8px 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .travel-map-toggle-btn {
            background: none;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .travel-map-toggle-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .travel-map-toggle-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
        }
        
        /* 地图加载状态 */
        .travel-map-mini-selector.loading {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 13px;
        }
        
        .travel-map-mini-selector.error {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 13px;
            flex-direction: column;
            gap: 8px;
        }
        
        /* 地图标记样式 */
        .meta-map-marker {
            width: 20px;
            height: 20px;
            background: #ef4444;
            border: 2px solid #fff;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            cursor: move;
        }
        
        /* 原有样式保持不变 */
        .travel-map-new-marker-form {
            display: grid;
            gap: 10px;
        }
        
        .travel-map-form-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            align-items: center;
        }
        
        .travel-map-form-label {
            font-size: 13px;
            font-weight: 500;
            color: #374151;
        }
        
        .travel-map-form-input {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .travel-map-coords-row {
            display: grid;
            grid-template-columns: 120px 1fr 1fr;
            gap: 10px;
            align-items: center;
        }
        
        .travel-map-help-text {
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
            margin-top: 5px;
        }
    </style>

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

<script>
// 设置 Travel Map 配置（确保在 JavaScript 运行前定义）
window.travelMapConfig = {
    apiKey: '<?php echo esc_js($api_key); ?>',
    ajaxurl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('travel_map_nonce')); ?>'
};

// 地点选择界面的搜索和筛选功能
jQuery(document).ready(function($) {
    // 元素引用
    const $searchInput = $('#travel-map-search');
    const $searchClear = $('#travel-map-search-clear');
    const $filterBtns = $('.travel-map-filter-btn');
    const $markersGrid = $('#travel-map-markers-grid');
    const $noResults = $('#travel-map-no-results');
    const $selectedCount = $('.travel-map-selected-count');
    
    let currentFilter = 'all';
    let currentSearch = '';
    let metaBoxMap = null;
    let metaBoxMapMarker = null;
    let isManualInputMode = false;
    
    // 初始化地图选择器
    initMetaBoxMapPicker();
    
    // 初始化统计数据
    updateStatistics();
    
    // 搜索功能
    $searchInput.on('input', function() {
        currentSearch = $(this).val().toLowerCase().trim();
        
        // 显示/隐藏清除按钮
        if (currentSearch) {
            $searchClear.show();
        } else {
            $searchClear.hide();
        }
        
        filterMarkers();
    });
    
    // 清除搜索
    $searchClear.on('click', function() {
        $searchInput.val('').trigger('input');
    });
    
    // 筛选功能
    $filterBtns.on('click', function() {
        $filterBtns.removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        filterMarkers();
    });
    
    // 筛选和搜索逻辑
    function filterMarkers() {
        let visibleCount = 0;
        
        $('.travel-map-marker-compact').each(function() {
            const $marker = $(this);
            const markerName = $marker.data('marker-name') || '';
            const markerStatus = $marker.data('marker-status');
            
            let showMarker = true;
            
            // 状态筛选
            if (currentFilter !== 'all' && markerStatus !== currentFilter) {
                showMarker = false;
            }
            
            // 搜索筛选
            if (currentSearch && markerName.indexOf(currentSearch) === -1) {
                showMarker = false;
            }
            
            if (showMarker) {
                $marker.show();
                visibleCount++;
            } else {
                $marker.hide();
            }
        });
        
        // 显示/隐藏无结果提示
        if (visibleCount === 0) {
            $noResults.show();
        } else {
            $noResults.hide();
        }
        
        // 更新统计数据
        updateStatistics();
    }
    
    // 点击整个卡片来切换选择状态
    $(document).on('click', '.travel-map-marker-compact', function(e) {
        if (e.target.type !== 'checkbox') {
            const $checkbox = $(this).find('input[type="checkbox"]');
            $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        }
    });
    
    // 更新选择计数和视觉状态
    $(document).on('change', '.travel-map-marker-checkbox', function() {
        const $marker = $(this).closest('.travel-map-marker-compact');
        
        if ($(this).is(':checked')) {
            $marker.addClass('selected');
        } else {
            $marker.removeClass('selected');
        }
        
        // 更新统计数据
        updateStatistics();
    });
    
    // 统计数据更新函数
    function updateStatistics() {
        const selectedCount = $('.travel-map-marker-checkbox:checked').length;
        $('.travel-map-selected-count').text(selectedCount);
    }
    
    // 手动输入切换
    $('#toggle-manual-input').on('click', function() {
        isManualInputMode = !isManualInputMode;
        const $button = $(this);
        const $latInput = $('#meta-latitude');
        const $lngInput = $('#meta-longitude');
        
        if (isManualInputMode) {
            $button.addClass('active').text('🗺️ 地图选点');
            $latInput.prop('readonly', false);
            $lngInput.prop('readonly', false);
        } else {
            $button.removeClass('active').text('✏️ 手动输入坐标');
            $latInput.prop('readonly', true);
            $lngInput.prop('readonly', true);
        }
    });
    
    // 手动输入坐标时更新地图
    $('#meta-latitude, #meta-longitude').on('input', function() {
        if (isManualInputMode && metaBoxMap && metaBoxMapMarker) {
            const lat = parseFloat($('#meta-latitude').val());
            const lng = parseFloat($('#meta-longitude').val());
            
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                metaBoxMapMarker.setPosition([lng, lat]);
                metaBoxMap.setCenter([lng, lat]);
            }
        }
    });
    
    // 初始化Meta Box地图选择器
    function initMetaBoxMapPicker() {
        console.log('initMetaBoxMapPicker: 开始初始化');
        const mapContainer = document.getElementById('meta-box-map');
        
        if (!mapContainer) {
            console.error('initMetaBoxMapPicker: 找不到地图容器 #meta-box-map');
            return;
        }
        
        console.log('initMetaBoxMapPicker: 找到地图容器', mapContainer);
        console.log('initMetaBoxMapPicker: 配置对象', window.travelMapConfig);
        
        // 显示加载状态
        mapContainer.className = 'travel-map-mini-selector loading';
        mapContainer.innerHTML = '🔄 加载地图中...';
        
        // 等待一段时间确保API已加载，然后检查AMap对象
        setTimeout(function() {
            if (typeof window.AMap !== 'undefined') {
                console.log('initMetaBoxMapPicker: AMap已存在，直接创建地图');
                createMetaBoxMap(mapContainer);
            } else {
                console.log('initMetaBoxMapPicker: AMap未定义，需要动态加载API');
                // 动态加载API
                loadAMapAPI(function() {
                    console.log('initMetaBoxMapPicker: API加载成功，创建地图');
                    createMetaBoxMap(mapContainer);
                }, function() {
                    console.error('initMetaBoxMapPicker: API加载失败');
                    showMapError(mapContainer, '未配置API密钥或加载失败');
                });
            }
        }, 500); // 等待500毫秒确保API加载
    }
    
    // 加载高德地图API
    function loadAMapAPI(successCallback, errorCallback) {
        console.log('loadAMapAPI: 开始加载API');
        
        // 获取API密钥（可以从后台配置获取）
        const apiKey = window.travelMapConfig ? window.travelMapConfig.apiKey : '';
        const securityKey = window.travelMapConfig ? window.travelMapConfig.securityKey : '';
        
        console.log('loadAMapAPI: API密钥', apiKey ? '已配置' : '未配置');
        console.log('loadAMapAPI: 安全密钥', securityKey ? '已配置' : '未配置');
        
        if (!apiKey) {
            console.error('loadAMapAPI: API密钥为空');
            if (errorCallback) errorCallback();
            return;
        }
        
        // 设置安全密钥（2021年12月02日以后申请的 Key 必须配合安全密钥使用）
        if (securityKey) {
            window._AMapSecurityConfig = {
                securityJsCode: securityKey
            };
            console.log('loadAMapAPI: 安全密钥已设置');
        }
        
        const script = document.createElement('script');
        script.src = 'https://webapi.amap.com/maps?v=2.0&key=' + apiKey;
        console.log('loadAMapAPI: 加载脚本 URL', script.src);
        
        script.onload = function() {
            console.log('loadAMapAPI: 脚本加载成功');
            setTimeout(function() {
                if (typeof window.AMap !== 'undefined') {
                    console.log('loadAMapAPI: AMap 对象可用');
                    successCallback();
                } else {
                    console.error('loadAMapAPI: AMap 对象仍未定义');
                    if (errorCallback) errorCallback();
                }
            }, 100);
        };
        
        script.onerror = function() {
            console.error('loadAMapAPI: 脚本加载失败');
            if (errorCallback) errorCallback();
        };
        
        document.head.appendChild(script);
    }
    
    // 创建地图
    function createMetaBoxMap(mapContainer) {
        console.log('createMetaBoxMap: 开始创建地图');
        
        try {
            mapContainer.className = 'travel-map-mini-selector';
            mapContainer.innerHTML = '';
            
            console.log('createMetaBoxMap: AMap 对象', window.AMap);
            
            // 创建地图
            metaBoxMap = new AMap.Map(mapContainer, {
                zoom: 10,
                center: [116.4074, 39.9042], // 默认中心点（北京）
                mapStyle: 'amap://styles/light',
                dragEnable: true,
                zoomEnable: true,
                doubleClickZoom: true,
                keyboardEnable: false,
                scrollWheel: true
            });
            
            console.log('createMetaBoxMap: 地图对象创建成功', metaBoxMap);
            
            // 创建标记
            metaBoxMapMarker = new AMap.Marker({
                position: [116.4074, 39.9042],
                draggable: true,
                cursor: 'move',
                icon: new AMap.Icon({
                    image: 'data:image/svg+xml;base64,' + btoa('<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" fill="#ef4444" stroke="#fff" stroke-width="2"/></svg>'),
                    size: new AMap.Size(20, 20),
                    imageOffset: new AMap.Pixel(-10, -10)
                })
            });
            
            console.log('createMetaBoxMap: 标记对象创建成功', metaBoxMapMarker);
            
            metaBoxMap.add(metaBoxMapMarker);
            
            // 地图点击事件
            metaBoxMap.on('click', function(e) {
                console.log('createMetaBoxMap: 地图点击事件', e);
                if (!isManualInputMode) {
                    const lng = e.lnglat.lng;
                    const lat = e.lnglat.lat;
                    console.log('createMetaBoxMap: 点击坐标', lng, lat);
                    updateMetaBoxCoordinates(lng, lat);
                    metaBoxMapMarker.setPosition([lng, lat]);
                }
            });
            
            // 标记拖拽事件
            metaBoxMapMarker.on('dragend', function(e) {
                console.log('createMetaBoxMap: 标记拖拽事件', e);
                if (!isManualInputMode) {
                    const position = e.target.getPosition();
                    console.log('createMetaBoxMap: 拖拽坐标', position.lng, position.lat);
                    updateMetaBoxCoordinates(position.lng, position.lat);
                }
            });
            
            console.log('Meta Box 地图初始化成功');
            
        } catch (error) {
            console.error('Meta Box 地图初始化失败:', error);
            showMapError(mapContainer, '地图初始化失败');
        }
    }
    
    // 更新坐标输入框
    function updateMetaBoxCoordinates(lng, lat) {
        $('#meta-longitude').val(lng.toFixed(6));
        $('#meta-latitude').val(lat.toFixed(6));
    }
    
    // 显示地图错误
    function showMapError(mapContainer, message) {
        mapContainer.className = 'travel-map-mini-selector error';
        mapContainer.innerHTML = '<div>⚠️ ' + message + '</div><div style="font-size: 11px; margin-top: 4px;">请检查API密钥配置</div>';
    }
    
    // 坐标验证（保留原有功能）
    $('input[name="new_marker_latitude"], input[name="new_marker_longitude"]').on('blur', function() {
        const lat = parseFloat($('input[name="new_marker_latitude"]').val());
        const lng = parseFloat($('input[name="new_marker_longitude"]').val());
        
        if (!isNaN(lat) && !isNaN(lng)) {
            if (lat < -90 || lat > 90) {
                alert('纬度必须在 -90 到 90 之间');
                $('input[name="new_marker_latitude"]').focus();
            } else if (lng < -180 || lng > 180) {
                alert('经度必须在 -180 到 180 之间');
                $('input[name="new_marker_longitude"]').focus();
            }
        }
    });
    
    // 新标记点创建提示（保留原有功能）
    $('input[name="new_marker_title"]').on('blur', function() {
        if ($(this).val().trim() !== '') {
            const lat = $('#meta-latitude').val();
            const lng = $('#meta-longitude').val();
            
            if (!lat || !lng) {
                $('.notice-warning').remove(); // 移除之前的警告
                $(this).after('<div class="notice-warning" style="font-size: 12px; color: #b45309; margin-top: 4px;">请在地图上点击选择位置</div>');
                setTimeout(() => {
                    $('.notice-warning').fadeOut();
                }, 3000);
            }
        }
    });
    
    // 键盘快捷键
    $searchInput.on('keydown', function(e) {
        if (e.key === 'Escape') {
            $(this).val('').trigger('input');
        }
    });
    
    // 初始化
    filterMarkers();
});
</script>