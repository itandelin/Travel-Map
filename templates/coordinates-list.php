<?php
/**
 * 坐标管理页面模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取当前用户权限
$can_edit = current_user_can('edit_posts');
$can_delete = current_user_can('delete_posts');
?>

<div class="travel-map-admin-page">
    <div class="travel-map-admin-header">
        <h1 class="travel-map-admin-title"><?php _e('坐标管理', TRAVEL_MAP_TEXT_DOMAIN); ?></h1>
        <p class="travel-map-admin-subtitle"><?php _e('管理您的旅行地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    </div>

    <div class="travel-map-coordinates-container">
        <!-- 左侧地点列表区域 -->
        <div class="travel-map-coordinates-left">
            <!-- 筛选和操作区域 -->
            <div class="travel-map-list-header">
                <div class="travel-map-list-title">
                    <h2><?php _e('地点列表', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
                    <span class="travel-map-count" id="markers-count">0 个地点</span>
                </div>
                
                <div class="travel-map-list-actions">
                    <?php if ($can_edit): ?>
                        <button type="button" id="export-btn" class="travel-map-btn travel-map-btn-outline" title="导出数据">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('导出', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" id="import-btn" class="travel-map-btn travel-map-btn-outline" title="导入数据">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('导入', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" id="bulk-delete-btn" class="travel-map-btn travel-map-btn-danger" style="display: none;" title="批量删除">
                            <span class="dashicons dashicons-trash"></span>
                            批量删除
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 筛选区域 -->
            <div class="travel-map-filters">
                <div class="travel-map-filter-group">
                    <label for="status-filter"><?php _e('筛选状态', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <select id="status-filter" class="travel-map-select">
                        <option value="all"><?php _e('所有状态', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <option value="visited"><?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <option value="want_to_go"><?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <option value="planned"><?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
                
                <div class="travel-map-filter-group">
                    <label for="search-input"><?php _e('搜索地点', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="search-input" class="travel-map-input" placeholder="<?php _e('输入地点名称...', TRAVEL_MAP_TEXT_DOMAIN); ?>">
                </div>
            </div>

            <!-- 地点列表表格 -->
            <div class="travel-map-coordinates-table-container">
                <table class="travel-map-coordinates-table">
                    <thead>
                        <tr>
                            <?php if ($can_delete): ?>
                                <th class="travel-map-th-checkbox">
                                    <input type="checkbox" id="select-all" title="全选">
                                </th>
                            <?php endif; ?>
                            <th class="travel-map-th-name"><?php _e('地点名称', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th class="travel-map-th-status"><?php _e('状态', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th class="travel-map-th-coords"><?php _e('坐标', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th class="travel-map-th-post"><?php _e('关联文章', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th class="travel-map-th-date"><?php _e('创建时间', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <?php if ($can_edit): ?>
                                <th class="travel-map-th-actions"><?php _e('操作', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="coordinates-tbody">
                        <!-- 数据通过 AJAX 加载 -->
                        <tr>
                            <td colspan="<?php echo $can_edit ? '7' : '6'; ?>" class="travel-map-loading-row">
                                <div class="travel-map-loading">
                                    <span class="travel-map-spinner"></span>
                                    加载中...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 分页区域 -->
            <div class="travel-map-pagination">
                <div class="travel-map-pagination-info">
                    <span id="pagination-info"></span>
                </div>
                <div class="travel-map-pagination-controls" id="pagination-controls">
                    <!-- 分页按钮将在这里生成 -->
                </div>
            </div>
        </div>

        <!-- 右侧添加/编辑区域 -->
        <div class="travel-map-coordinates-right">
            <!-- 添加/编辑表单标题 -->
            <div class="travel-map-form-header">
                <h2 id="form-title" class="travel-map-form-title">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php _e('添加新地点', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </h2>
                <button type="button" id="reset-form" class="travel-map-btn travel-map-btn-outline" style="display: none;" title="取消编辑">
                    <span class="dashicons dashicons-no-alt"></span>
                    取消
                </button>
            </div>

            <!-- 地图选择器 -->
            <div class="travel-map-map-section">
                <div class="travel-map-map-header">
                    <h3><?php _e('地图选点', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
                    <p class="travel-map-map-description"><?php _e('点击地图选择坐标位置，或手动输入经纬度', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                </div>
                <div class="travel-map-map-container">
                    <div id="admin-map" class="travel-map-selector"></div>
                </div>
            </div>

            <!-- 表单区域 -->
            <?php if ($can_edit): ?>
                <div class="travel-map-form-section">
                    <form id="coordinate-form" class="travel-map-coordinate-form">
                        <?php wp_nonce_field('travel_map_save_marker', 'travel_map_nonce'); ?>
                        <input type="hidden" id="marker-id" name="marker_id" value="">

                        <!-- 基本信息 -->
                        <div class="travel-map-form-group">
                            <h4 class="travel-map-group-title">
                                <span class="dashicons dashicons-admin-generic"></span>
                                基本信息
                            </h4>
                            
                            <div class="travel-map-form-row">
                                <div class="travel-map-form-field">
                                    <label for="marker-title" class="travel-map-label required">
                                        <?php _e('地点名称', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="text" id="marker-title" name="title" class="travel-map-input" placeholder="例：北京天安门广场" required>
                                </div>
                            </div>

                            <div class="travel-map-form-row travel-map-row-split">
                                <div class="travel-map-form-field">
                                    <label for="marker-latitude" class="travel-map-label required">
                                        <?php _e('纬度', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="number" id="marker-latitude" name="latitude" class="travel-map-input" step="0.000001" placeholder="39.908822" required>
                                </div>
                                <div class="travel-map-form-field">
                                    <label for="marker-longitude" class="travel-map-label required">
                                        <?php _e('经度', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="number" id="marker-longitude" name="longitude" class="travel-map-input" step="0.000001" placeholder="116.397496" required>
                                </div>
                            </div>

                            <div class="travel-map-form-row travel-map-row-split">
                                <div class="travel-map-form-field">
                                    <label for="marker-status" class="travel-map-label">
                                        <?php _e('旅行状态', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <select id="marker-status" name="status" class="travel-map-select">
                                        <option value="visited"><?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                        <option value="want_to_go"><?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                        <option value="planned"><?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                    </select>
                                </div>
                                <div class="travel-map-form-field">
                                    <label for="marker-color" class="travel-map-label">
                                        <?php _e('标记颜色', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="color" id="marker-color" name="marker_color" class="travel-map-color-input" value="#FF6B35">
                                </div>
                            </div>
                        </div>

                        <!-- 高级选项 -->
                        <div class="travel-map-form-group">
                            <h4 class="travel-map-group-title">
                                <span class="dashicons dashicons-admin-settings"></span>
                                高级选项
                            </h4>
                            
                            <div class="travel-map-form-row">
                                <div class="travel-map-form-field">
                                    <label for="marker-post" class="travel-map-label">
                                        <?php _e('关联文章', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <select id="marker-post" name="post_id" class="travel-map-select">
                                        <option value=""><?php _e('无关联文章', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                        <?php
                                        $posts = get_posts(array(
                                            'numberposts' => -1,
                                            'post_status' => 'publish',
                                            'orderby' => 'date',
                                            'order' => 'DESC'
                                        ));
                                        foreach ($posts as $post): ?>
                                            <option value="<?php echo $post->ID; ?>">
                                                <?php echo esc_html($post->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="travel-map-form-row" id="visit-date-row">
                                <div class="travel-map-form-field">
                                    <label for="marker-visit-date" class="travel-map-label">
                                        <?php _e('访问日期', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="date" id="marker-visit-date" name="visit_date" class="travel-map-input">
                                </div>
                            </div>

                            <div class="travel-map-form-row" id="planned-date-row" style="display: none;">
                                <div class="travel-map-form-field">
                                    <label for="marker-planned-date" class="travel-map-label">
                                        <?php _e('计划日期', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <input type="date" id="marker-planned-date" name="planned_date" class="travel-map-input">
                                </div>
                            </div>

                            <div class="travel-map-form-row" id="wish-reason-row" style="display: none;">
                                <div class="travel-map-form-field">
                                    <label for="marker-wish-reason" class="travel-map-label">
                                        <?php _e('想去理由', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <textarea id="marker-wish-reason" name="wish_reason" class="travel-map-textarea" rows="3" placeholder="说说为什么想去这里..."></textarea>
                                </div>
                            </div>

                            <div class="travel-map-form-row">
                                <div class="travel-map-form-field">
                                    <label for="marker-description" class="travel-map-label">
                                        <?php _e('地点描述', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                    </label>
                                    <textarea id="marker-description" name="description" class="travel-map-textarea" rows="3" placeholder="记录一些关于这个地方的信息..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 表单操作按钮 -->
                        <div class="travel-map-form-actions">
                            <button type="submit" class="travel-map-btn travel-map-btn-primary">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <span id="submit-text"><?php _e('添加坐标', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                            </button>
                            <button type="button" id="delete-marker" class="travel-map-btn travel-map-btn-danger" style="display: none;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('删除', TRAVEL_MAP_TEXT_DOMAIN); ?>
                            </button>
                            <button type="button" id="reset-form-button" class="travel-map-btn travel-map-btn-outline">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('重置', TRAVEL_MAP_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                </form>
                <?php else: ?>
                    <div class="travel-map-no-permission">
                        <div class="travel-map-no-permission-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <p><?php _e('您没有编辑权限，只能查看地点信息。', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 添加必要的CSS样式 -->


<!-- 导入模态框 -->
<div id="import-modal" class="travel-map-modal" style="display: none;">
    <div class="travel-map-modal-content">
        <div class="travel-map-modal-header">
            <h2><?php _e('导入数据', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            <button type="button" class="travel-map-modal-close">&times;</button>
        </div>
        <div class="travel-map-modal-body">
            <form id="import-form" enctype="multipart/form-data">
                <?php wp_nonce_field('travel_map_import', 'import_nonce'); ?>
                <div class="travel-map-form-row">
                    <label for="import-file"><?php _e('选择文件', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <input type="file" id="import-file" name="import_file" accept=".csv,.json,.geojson" required>
                    <p class="description"><?php _e('支持 CSV, JSON, GeoJSON 格式', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                </div>
                <div class="travel-map-form-actions">
                    <button type="submit" class="button-primary"><?php _e('导入', TRAVEL_MAP_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button travel-map-modal-cancel">
                        <?php _e('取消', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

