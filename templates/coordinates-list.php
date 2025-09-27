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
<style>
.travel-map-admin-page {
    max-width: 1400px;
    margin: 0;
    padding: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.travel-map-admin-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.travel-map-admin-title {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 600;
    color: #1d2327;
}

.travel-map-admin-subtitle {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.travel-map-coordinates-container {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 30px;
    align-items: start;
}

/* 左侧列表区域 */
.travel-map-coordinates-left {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.travel-map-list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
    background: #f9f9f9;
    border-radius: 8px 8px 0 0;
}

.travel-map-list-title h2 {
    margin: 0 10px 0 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
    display: inline-block;
}

.travel-map-count {
    background: #f0f6ff;
    color: #0073aa;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.travel-map-list-actions {
    display: flex;
    gap: 8px;
}

.travel-map-filters {
    padding: 20px;
    background: #fafafa;
    border-bottom: 1px solid #e1e1e1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.travel-map-filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #1d2327;
    font-size: 13px;
}

.travel-map-coordinates-table-container {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

.travel-map-coordinates-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.travel-map-coordinates-table th {
    background: #f9f9f9;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #1d2327;
    border-bottom: 2px solid #e1e1e1;
    position: sticky;
    top: 0;
    z-index: 10;
}

.travel-map-coordinates-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.travel-map-coordinates-table tbody tr:hover {
    background: #f9f9f9;
}

.travel-map-th-checkbox {
    width: 40px;
}

.travel-map-th-name {
    min-width: 150px;
}

.travel-map-th-status {
    width: 80px;
}

.travel-map-th-coords {
    width: 140px;
    font-family: monospace;
    font-size: 12px;
}

.travel-map-th-post {
    min-width: 120px;
}

.travel-map-th-date {
    width: 100px;
}

.travel-map-th-actions {
    width: 80px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-visited {
    background: #d1ecf1;
    color: #0c5460;
}

.status-want_to_go {
    background: #fff3cd;
    color: #856404;
}

.status-planned {
    background: #d4edda;
    color: #155724;
}

.travel-map-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f9f9f9;
    border-radius: 0 0 8px 8px;
}

/* 右侧表单区域 */
.travel-map-coordinates-right {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: sticky;
    top: 32px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
    padding-bottom: 20px;
}

.travel-map-form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
    background: #f9f9f9;
    border-radius: 8px 8px 0 0;
}

.travel-map-form-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
}

.travel-map-map-section {
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.travel-map-map-header h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.travel-map-map-description {
    margin: 0 0 15px 0;
    color: #646970;
    font-size: 13px;
}

.travel-map-map-container {
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    overflow: hidden;
}

.travel-map-selector {
    width: 100%;
    height: 250px;
    background: #f5f5f5;
}

.travel-map-form-section {
    padding: 20px;
}

.travel-map-form-group {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.travel-map-form-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.travel-map-group-title {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e8e8e8;
}

.travel-map-form-row {
    margin-bottom: 15px;
}

.travel-map-form-row:last-child {
    margin-bottom: 0;
}

.travel-map-row-split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.travel-map-form-field {
    display: flex;
    flex-direction: column;
}

.travel-map-label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #1d2327;
    font-size: 13px;
}

.travel-map-label.required::after {
    content: ' *';
    color: #d63384;
}

.travel-map-input,
.travel-map-select,
.travel-map-textarea {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.travel-map-input:focus,
.travel-map-select:focus,
.travel-map-textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 1px #007cba;
}

.travel-map-color-input {
    width: 50px;
    height: 38px;
    padding: 2px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
}

.travel-map-textarea {
    resize: vertical;
    min-height: 80px;
}

.travel-map-form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #e1e1e1;
    margin-top: 20px;
}

/* 按钮样式 */
.travel-map-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.travel-map-btn-primary {
    background: #007cba;
    border-color: #007cba;
    color: #fff;
}

.travel-map-btn-primary:hover {
    background: #005a87;
    border-color: #005a87;
}

.travel-map-btn-outline {
    background: #fff;
    border-color: #ddd;
    color: #1d2327;
}

.travel-map-btn-outline:hover {
    background: #f6f7f7;
    border-color: #999;
}

.travel-map-btn-danger {
    background: #d63384;
    border-color: #d63384;
    color: #fff;
}

.travel-map-btn-danger:hover {
    background: #b02a5b;
    border-color: #b02a5b;
}

.travel-map-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #646970;
}

.travel-map-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.travel-map-no-permission {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.travel-map-no-permission-icon {
    font-size: 48px;
    margin-bottom: 15px;
    color: #c3c4c7;
}

/* 模态框样式 */
.travel-map-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.travel-map-modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.travel-map-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.travel-map-modal-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.travel-map-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.travel-map-modal-close:hover {
    background: #f0f0f0;
}

.travel-map-modal-body {
    padding: 20px;
}

/* 通知消息样式 */
.travel-map-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 6px;
    color: #fff;
    font-weight: 500;
    z-index: 100001;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.travel-map-notification.show {
    transform: translateX(0);
    opacity: 1;
}

.travel-map-notification-success {
    background: #28a745;
}

.travel-map-notification-error {
    background: #dc3545;
}

.travel-map-notification-warning {
    background: #ffc107;
    color: #212529;
}

.travel-map-notification-info {
    background: #17a2b8;
}

/* 响应式设计 */
@media (max-width: 1200px) {
    .travel-map-coordinates-container {
        grid-template-columns: 1fr;
    }
    
    .travel-map-coordinates-right {
        position: static;
        max-height: none;
    }
}

@media (max-width: 768px) {
    .travel-map-admin-page {
        padding: 10px;
    }
    
    .travel-map-filters {
        grid-template-columns: 1fr;
    }
    
    .travel-map-row-split {
        grid-template-columns: 1fr;
    }
    
    .travel-map-list-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .travel-map-list-actions {
        justify-content: center;
    }
    
    .travel-map-form-actions {
        flex-direction: column;
    }
}
</style>

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
                    <button type="button" class="button" onclick="document.getElementById('import-modal').style.display='none'">
                        <?php _e('取消', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 初始化地图选择器
    initMapPicker();
    
    // 加载坐标列表
    loadCoordinatesList();
    
    // 状态改变时显示/隐藏相关字段
    $('#marker-status').on('change', function() {
        const status = $(this).val();
        $('#visit-date-row').toggle(status === 'visited');
        $('#planned-date-row').toggle(status === 'planned');
        $('#wish-reason-row').toggle(status === 'want_to_go');
    });
    
    // 表单提交
    $('#coordinate-form').on('submit', function(e) {
        e.preventDefault();
        saveMarker();
    });
    
    // 筛选和搜索
    $('#status-filter, #search-input').on('change keyup', function() {
        loadCoordinatesList();
    });
    
    // 批量操作
    $('#select-all').on('change', function() {
        $('.marker-checkbox').prop('checked', $(this).prop('checked'));
        toggleBulkActions();
    });
    
    $(document).on('change', '.marker-checkbox', function() {
        toggleBulkActions();
    });
    
    $('#bulk-delete-btn').on('click', function() {
        bulkDeleteMarkers();
    });
    
    // 重置表单
    $('#reset-form-button').on('click', function() {
        resetForm();
    });
    
    // 导入导出
    $('#export-btn').on('click', function() {
        exportData();
    });
    
    $('#import-btn').on('click', function() {
        $('#import-modal').show();
    });
    
    $('#import-form').on('submit', function(e) {
        e.preventDefault();
        importData();
    });
});

// 初始化地图选择器
function initMapPicker() {
    const mapContainer = document.getElementById('admin-map');
    if (!mapContainer) return;
    
    // 检查API密钥
    if (typeof window.AMap === 'undefined') {
        mapContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">请先配置API密钥并刷新页面</div>';
        return;
    }
    
    // 创建地图
    const map = new AMap.Map(mapContainer, {
        zoom: 4,
        center: [116.4074, 39.9042],
        mapStyle: 'amap://styles/light'
    });
    
    // 创建标记
    const marker = new AMap.Marker({
        position: [116.4074, 39.9042],
        draggable: true
    });
    
    map.add(marker);
    
    // 存储在全局变量中，供其他函数使用
    window.adminMap = map;
    window.adminMapMarker = marker;
    
    // 地图点击事件
    map.on('click', function(e) {
        const lng = e.lnglat.lng;
        const lat = e.lnglat.lat;
        updateCoordinates(lng, lat);
        marker.setPosition([lng, lat]);
    });
    
    // 标记拖拽事件
    marker.on('dragend', function(e) {
        const position = e.target.getPosition();
        updateCoordinates(position.lng, position.lat);
    });
    
    // 坐标输入框变化事件
    jQuery('#marker-latitude, #marker-longitude').on('input', function() {
        const lat = parseFloat(jQuery('#marker-latitude').val());
        const lng = parseFloat(jQuery('#marker-longitude').val());
        
        if (!isNaN(lat) && !isNaN(lng)) {
            marker.setPosition([lng, lat]);
            map.setCenter([lng, lat]);
        }
    });
}

// 更新坐标输入框
function updateCoordinates(lng, lat) {
    jQuery('#marker-longitude').val(lng.toFixed(6));
    jQuery('#marker-latitude').val(lat.toFixed(6));
}

// 加载坐标列表
function loadCoordinatesList() {
    const status = jQuery('#status-filter').val();
    const search = jQuery('#search-input').val();
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'travel_map_get_markers',
            nonce: '<?php echo wp_create_nonce('travel_map_nonce'); ?>',
            status: status,
            search: search
        },
        success: function(response) {
            if (response.success) {
                renderMarkersList(response.data);
            }
        }
    });
}

// 渲染标记列表
function renderMarkersList(markers) {
    const tbody = jQuery('#coordinates-tbody');
    tbody.empty();
    
    // 更新数量
    updateMarkersCount(markers.length);
    
    if (markers.length === 0) {
        tbody.append('<tr><td colspan="7" class="travel-map-loading-row"><div class="travel-map-loading" style="padding: 20px;"><?php _e('暂无数据', TRAVEL_MAP_TEXT_DOMAIN); ?></div></td></tr>');
        return;
    }
    
    markers.forEach(function(marker) {
        const row = jQuery('<tr>');
        
        <?php if ($can_delete): ?>
        row.append('<td><input type="checkbox" class="marker-checkbox" value="' + marker.id + '"></td>');
        <?php endif; ?>
        
        row.append('<td><strong>' + marker.title + '</strong></td>');
        row.append('<td><span class="status-badge status-' + marker.status + '">' + getStatusText(marker.status) + '</span></td>');
        row.append('<td style="font-family: monospace; font-size: 12px;">' + marker.latitude + ',<br>' + marker.longitude + '</td>');
        row.append('<td>' + (marker.post_title || '<em style="color: #999;"><?php _e('无', TRAVEL_MAP_TEXT_DOMAIN); ?></em>') + '</td>');
        row.append('<td>' + formatDate(marker.created_at) + '</td>');
        
        <?php if ($can_edit): ?>
        row.append('<td><button type="button" class="travel-map-btn travel-map-btn-outline" data-id="' + marker.id + '" onclick="editMarker(' + marker.id + ')" style="padding: 4px 8px; font-size: 12px;">编辑</button></td>');
        <?php endif; ?>
        
        tbody.append(row);
    });
}

// 其他 JavaScript 函数...
function getStatusText(status) {
    switch (status) {
        case 'visited': return '<?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?>';
        case 'want_to_go': return '<?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?>';
        case 'planned': return '<?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?>';
        default: return status;
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function toggleBulkActions() {
    const checkedCount = jQuery('.marker-checkbox:checked').length;
    jQuery('#bulk-delete-btn').toggle(checkedCount > 0);
}

// 重置表单
function resetForm() {
    jQuery('#coordinate-form')[0].reset();
    jQuery('#marker-id').val('');
    jQuery('#form-title').html('<span class="dashicons dashicons-location-alt"></span><?php _e('添加新地点', TRAVEL_MAP_TEXT_DOMAIN); ?>');
    jQuery('#submit-text').text('<?php _e('添加坐标', TRAVEL_MAP_TEXT_DOMAIN); ?>');
    jQuery('#delete-marker').hide();
    jQuery('#reset-form').hide();
    
    // 重置状态相关字段
    jQuery('#visit-date-row').show();
    jQuery('#planned-date-row').hide();
    jQuery('#wish-reason-row').hide();
    
    // 重置地图位置
    const defaultLng = 116.4074;
    const defaultLat = 39.9042;
    updateCoordinates(defaultLng, defaultLat);
    
    // 如果地图存在，重置地图位置
    if (window.adminMap && window.adminMapMarker) {
        window.adminMapMarker.setPosition([defaultLng, defaultLat]);
        window.adminMap.setCenter([defaultLng, defaultLat]);
    }
}

// 更新数量显示
function updateMarkersCount(count) {
    jQuery('#markers-count').text(count + ' 个地点');
}

// 保存标记点
function saveMarker() {
    const formData = new FormData(jQuery('#coordinate-form')[0]);
    formData.append('action', 'travel_map_save_marker');
    formData.append('nonce', '<?php echo wp_create_nonce('travel_map_nonce'); ?>');
    
    // 调试信息：显示表单数据
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // 显示加载状态
    const submitBtn = jQuery('#coordinate-form button[type="submit"]');
    const originalText = submitBtn.find('#submit-text').text();
    submitBtn.prop('disabled', true);
    submitBtn.find('#submit-text').text('保存中...');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('AJAX Response:', response);
            if (response.success) {
                // 显示成功消息
                showNotification('保存成功！', 'success');
                
                // 重置表单并刷新列表
                resetForm();
                loadCoordinatesList();
            } else {
                showNotification('保存失败：' + (response.data || '未知错误'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            showNotification('网络请求失败: ' + xhr.status + ' ' + xhr.statusText, 'error');
        },
        complete: function() {
            // 恢复按钮状态
            submitBtn.prop('disabled', false);
            submitBtn.find('#submit-text').text(originalText);
        }
    });
}

// 显示通知消息
function showNotification(message, type) {
    // 创建通知元素
    const notification = jQuery('<div class="travel-map-notification travel-map-notification-' + type + '">' + message + '</div>');
    
    // 添加到页面
    jQuery('body').append(notification);
    
    // 显示动画
    setTimeout(function() {
        notification.addClass('show');
    }, 100);
    
    // 3秒后自动消失
    setTimeout(function() {
        notification.removeClass('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}

// 编辑标记点
function editMarker(markerId) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'travel_map_get_marker',
            nonce: '<?php echo wp_create_nonce('travel_map_nonce'); ?>',
            marker_id: markerId
        },
        success: function(response) {
            if (response.success && response.data) {
                const marker = response.data;
                
                // 填充表单数据
                jQuery('#marker-id').val(marker.id);
                jQuery('#marker-title').val(marker.title);
                jQuery('#marker-latitude').val(marker.latitude);
                jQuery('#marker-longitude').val(marker.longitude);
                jQuery('#marker-status').val(marker.status).trigger('change');
                jQuery('#marker-color').val(marker.marker_color || '#FF6B35');
                jQuery('#marker-post').val(marker.post_id || '');
                jQuery('#marker-description').val(marker.description || '');
                
                // 根据状态填充相关字段
                if (marker.status === 'visited' && marker.visit_date) {
                    jQuery('#marker-visit-date').val(marker.visit_date);
                }
                if (marker.status === 'planned' && marker.planned_date) {
                    jQuery('#marker-planned-date').val(marker.planned_date);
                }
                if (marker.status === 'want_to_go' && marker.wish_reason) {
                    jQuery('#marker-wish-reason').val(marker.wish_reason);
                }
                
                // 更新界面文字
                jQuery('#form-title').html('<span class="dashicons dashicons-edit"></span>编辑地点');
                jQuery('#submit-text').text('更新坐标');
                jQuery('#delete-marker').show();
                jQuery('#reset-form').show();
                
                // 更新地图位置
                if (window.adminMap && window.adminMapMarker) {
                    const lng = parseFloat(marker.longitude);
                    const lat = parseFloat(marker.latitude);
                    window.adminMapMarker.setPosition([lng, lat]);
                    window.adminMap.setCenter([lng, lat]);
                }
                
                // 滚动到表单区域
                jQuery('.travel-map-coordinates-right')[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                showNotification('已加载编辑数据', 'info');
            } else {
                showNotification('获取数据失败', 'error');
            }
        },
        error: function() {
            showNotification('网络请求失败', 'error');
        }
    });
}

// 批量删除
function bulkDeleteMarkers() {
    const checkedIds = jQuery('.marker-checkbox:checked').map(function() {
        return this.value;
    }).get();
    
    if (checkedIds.length === 0) {
        alert('请选择要删除的标记点');
        return;
    }
    
    if (!confirm('确定要删除选中的 ' + checkedIds.length + ' 个标记点吗？')) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'travel_map_bulk_delete',
            nonce: '<?php echo wp_create_nonce('travel_map_nonce'); ?>',
            marker_ids: checkedIds
        },
        success: function(response) {
            if (response.success) {
                alert(response.data.message || '删除成功');
                loadCoordinatesList();
            } else {
                alert('删除失败：' + (response.data || '未知错误'));
            }
        },
        error: function() {
            alert('网络请求失败');
        }
    });
}

// 导出数据
function exportData() {
    const exportUrl = ajaxurl + '?' + jQuery.param({
        action: 'travel_map_export',
        nonce: '<?php echo wp_create_nonce('travel_map_nonce'); ?>',
        format: 'csv'
    });
    
    window.open(exportUrl, '_blank');
}

// 导入数据
function importData() {
    const formData = new FormData(jQuery('#import-form')[0]);
    formData.append('action', 'travel_map_import');
    formData.append('nonce', '<?php echo wp_create_nonce('travel_map_nonce'); ?>');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert(response.data.message || '导入成功');
                jQuery('#import-modal').hide();
                loadCoordinatesList();
            } else {
                alert('导入失败：' + (response.data || '未知错误'));
            }
        },
        error: function() {
            alert('网络请求失败');
        }
    });
}
</script>