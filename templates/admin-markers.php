<?php
/**
 * 坐标管理页面模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="travel-map-admin-page">
    <div class="travel-map-admin-header">
        <h1 class="travel-map-admin-title"><?php _e('坐标管理', TRAVEL_MAP_TEXT_DOMAIN); ?></h1>
        <p class="travel-map-admin-subtitle"><?php _e('管理旅行地点标记和相关文章关联', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    </div>

    <div class="travel-map-markers-page">
        <!-- 标记列表 -->
        <div class="travel-map-markers-list">
            <div class="travel-map-markers-header">
                <h2 class="travel-map-markers-title"><?php _e('地点标记列表', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
                <div class="travel-map-markers-actions">
                    <button type="button" class="travel-map-add-marker-btn" data-focus-target="marker_title">
                        <?php _e('添加新标记', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>

            <?php if (!empty($markers)): ?>
                <table class="travel-map-markers-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-markers"></th>
                            <th><?php _e('地点名称', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th><?php _e('坐标', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th><?php _e('状态', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th><?php _e('关联文章', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th><?php _e('创建时间', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                            <th><?php _e('操作', TRAVEL_MAP_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($markers as $marker): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="marker-checkbox" value="<?php echo esc_attr($marker->id); ?>">
                                </td>
                                <td class="marker-title"><?php echo esc_html($marker->title); ?></td>
                                <td>
                                    <span class="marker-latitude"><?php echo esc_html($marker->latitude); ?></span>,
                                    <span class="marker-longitude"><?php echo esc_html($marker->longitude); ?></span>
                                </td>
                                <td>
                                    <span class="travel-map-status-badge <?php echo esc_attr($marker->status); ?>" data-status="<?php echo esc_attr($marker->status); ?>">
                                        <?php 
                                        $status_labels = array(
                                            'visited' => __('已去', TRAVEL_MAP_TEXT_DOMAIN),
                                            'want_to_go' => __('想去', TRAVEL_MAP_TEXT_DOMAIN),
                                            'planned' => __('计划', TRAVEL_MAP_TEXT_DOMAIN)
                                        );
                                        echo $status_labels[$marker->status] ?? __('未知', TRAVEL_MAP_TEXT_DOMAIN);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($marker->post_id): ?>
                                        <?php $post = get_post($marker->post_id); ?>
                                        <?php if ($post): ?>
                                            <a href="<?php echo get_edit_post_link($marker->post_id); ?>" target="_blank">
                                                <?php echo esc_html($post->post_title); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #dc2626;"><?php _e('文章不存在', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6b7280;"><?php _e('未关联', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($marker->created_at)); ?>
                                </td>
                                <td>
                                    <div class="travel-map-actions">
                                        <button type="button" class="travel-map-action-btn travel-map-edit-btn" data-marker-id="<?php echo esc_attr($marker->id); ?>">
                                            <?php _e('编辑', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                        </button>
                                        <button type="button" class="travel-map-action-btn travel-map-delete-btn" data-marker-id="<?php echo esc_attr($marker->id); ?>">
                                            <?php _e('删除', TRAVEL_MAP_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 批量操作 -->
                <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; background: #f9fafb;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span><?php _e('批量操作：', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                        <button type="button" class="button bulk-action-btn" data-action="delete" disabled>
                            <?php _e('删除选中', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="button bulk-action-btn" data-action="export" disabled>
                            <?php _e('导出选中', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </button>
                        <span class="description">
                            <?php _e('已选择', TRAVEL_MAP_TEXT_DOMAIN); ?> <strong class="selected-count">0</strong> <?php _e('项', TRAVEL_MAP_TEXT_DOMAIN); ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #6b7280;">
                    <p><?php _e('还没有添加任何地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    <p><?php _e('点击右侧表单开始添加您的第一个旅行地点', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 添加/编辑表单 -->
        <div class="travel-map-sidebar">
            <h3 class="travel-map-sidebar-title"><?php _e('添加/编辑地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            
            <form class="travel-map-marker-form" method="post">
                <?php wp_nonce_field('travel_map_marker', '_wpnonce'); ?>
                <input type="hidden" id="marker_id" name="marker_id" value="">
                
                <!-- 地图选择器 -->
                <div class="travel-map-map-picker" id="marker-map-picker">
                    <div style="color: #6b7280; text-align: center; line-height: 300px;">
                        <?php _e('点击地图选择坐标位置', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </div>
                </div>
                
                <!-- 坐标输入 -->
                <div class="travel-map-coord-inputs">
                    <div>
                        <label class="travel-map-form-label"><?php _e('纬度', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                        <input 
                            type="number" 
                            id="marker_latitude" 
                            name="latitude" 
                            step="0.000001" 
                            min="-90" 
                            max="90" 
                            class="travel-map-form-input travel-map-coord-input"
                            placeholder="39.9042"
                            required
                        >
                    </div>
                    <div>
                        <label class="travel-map-form-label"><?php _e('经度', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                        <input 
                            type="number" 
                            id="marker_longitude" 
                            name="longitude" 
                            step="0.000001" 
                            min="-180" 
                            max="180" 
                            class="travel-map-form-input travel-map-coord-input"
                            placeholder="116.4074"
                            required
                        >
                    </div>
                </div>
                
                <!-- 地点信息 -->
                <div class="travel-map-form-row">
                    <label class="travel-map-form-label"><?php _e('地点名称', TRAVEL_MAP_TEXT_DOMAIN); ?> <span style="color: #dc2626;">*</span></label>
                    <input 
                        type="text" 
                        id="marker_title" 
                        name="title" 
                        class="travel-map-form-input"
                        placeholder="<?php _e('例如：北京天安门', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                        required
                    >
                </div>
                
                <div class="travel-map-form-row">
                    <label class="travel-map-form-label"><?php _e('旅行状态', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <select id="marker_status" name="status" class="travel-map-form-select">
                        <option value="visited"><?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <option value="want_to_go"><?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <option value="planned"><?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
                
                <div class="travel-map-form-row">
                    <label class="travel-map-form-label"><?php _e('关联文章', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <select id="marker_post_id" name="post_id" class="travel-map-form-select">
                        <option value=""><?php _e('选择文章（可选）', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                        <?php
                        $posts = get_posts(array(
                            'numberposts' => 100,
                            'post_status' => 'publish',
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        foreach ($posts as $post): ?>
                            <option value="<?php echo esc_attr($post->ID); ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="travel-map-form-row">
                    <label class="travel-map-form-label"><?php _e('地点描述', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <textarea 
                        id="marker_description" 
                        name="description" 
                        class="travel-map-form-textarea"
                        placeholder="<?php _e('描述这个地点的特色或您的感受...', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                        rows="3"
                    ></textarea>
                </div>
                
                <!-- 额外字段（根据状态显示） -->
                <div id="status-specific-fields">
                    <!-- 已去状态字段 -->
                    <div class="status-fields visited-fields" style="display: none;">
                        <div class="travel-map-form-row">
                            <label class="travel-map-form-label"><?php _e('访问日期', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                            <input 
                                type="date" 
                                name="visit_date" 
                                class="travel-map-form-input"
                            >
                        </div>
                        <div class="travel-map-form-row">
                            <label class="travel-map-form-label"><?php _e('访问次数', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                            <input 
                                type="number" 
                                name="visit_count" 
                                min="1" 
                                value="1" 
                                class="travel-map-form-input"
                            >
                        </div>
                    </div>
                    
                    <!-- 想去状态字段 -->
                    <div class="status-fields want_to_go-fields" style="display: none;">
                        <div class="travel-map-form-row">
                            <label class="travel-map-form-label"><?php _e('想去理由', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                            <textarea 
                                name="wish_reason" 
                                class="travel-map-form-textarea"
                                placeholder="<?php _e('为什么想去这个地方？', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                                rows="2"
                            ></textarea>
                        </div>
                        <div class="travel-map-form-row">
                            <label class="travel-map-form-label"><?php _e('优先级', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                            <select name="priority_level" class="travel-map-form-select">
                                <option value="1"><?php _e('1 - 最想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                <option value="2"><?php _e('2 - 很想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                <option value="3" selected><?php _e('3 - 想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                <option value="4"><?php _e('4 - 有机会就去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                                <option value="5"><?php _e('5 - 一般', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- 计划状态字段 -->
                    <div class="status-fields planned-fields" style="display: none;">
                        <div class="travel-map-form-row">
                            <label class="travel-map-form-label"><?php _e('计划日期', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                            <input 
                                type="date" 
                                name="planned_date" 
                                class="travel-map-form-input"
                            >
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="travel-map-form-submit">
                    <?php _e('保存标记', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </button>
            </form>
            
            <!-- 批量导入 -->
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                <h4><?php _e('批量导入', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                <div class="travel-map-form-row">
                    <input type="file" id="import-file" accept=".csv,.json" style="margin-bottom: 8px;">
                    <p class="travel-map-form-help">
                        <?php _e('支持 CSV 和 JSON 格式文件', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </p>
                </div>
            </div>
            
            <!-- 导出功能 -->
            <div style="margin-top: 20px;">
                <h4><?php _e('导出数据', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="button export-btn" data-format="csv" data-status="all">
                        <?php _e('导出 CSV', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button export-btn" data-format="json" data-status="all">
                        <?php _e('导出 JSON', TRAVEL_MAP_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
