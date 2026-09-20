<?php
/**
 * 管理员设置页面模板
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="travel-map-admin-page">
    <div class="travel-map-admin-header">
        <h1 class="travel-map-admin-title"><?php _e('地图设置', TRAVEL_MAP_TEXT_DOMAIN); ?></h1>
        <p class="travel-map-admin-subtitle"><?php _e('配置高德地图API和基本显示选项', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
    </div>

    <form method="post" class="travel-map-settings-form">
        <?php wp_nonce_field('travel_map_settings'); ?>
        
        <!-- API 配置 -->
        <div class="travel-map-form-section">
            <h2 class="travel-map-section-title"><?php _e('API 配置', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            
            <div class="travel-map-form-row">
                <label for="api_key" class="travel-map-form-label">
                    <?php _e('高德地图 API 密钥', TRAVEL_MAP_TEXT_DOMAIN); ?> <span style="color: #dc2626;">*</span>
                </label>
                <input 
                    type="text" 
                    id="travel_map_api_key" 
                    name="api_key" 
                    value="<?php echo esc_attr($api_key); ?>" 
                    class="travel-map-form-input"
                    placeholder="<?php _e('请输入高德地图 Web 服务 API 密钥', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                    required
                >
                <p class="travel-map-form-help">
                    <?php _e('请在', TRAVEL_MAP_TEXT_DOMAIN); ?> 
                    <a href="https://lbs.amap.com/" target="_blank"><?php _e('高德开放平台', TRAVEL_MAP_TEXT_DOMAIN); ?></a> 
                    <?php _e('申请 Web 端 (JS API) 的 Key', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label for="security_key" class="travel-map-form-label">
                    <?php _e('高德地图安全密钥', TRAVEL_MAP_TEXT_DOMAIN); ?> <span style="color: #dc2626;">*</span>
                </label>
                <input 
                    type="text" 
                    id="travel_map_security_key" 
                    name="security_key" 
                    value="<?php echo esc_attr(get_option('travel_map_security_key', '')); ?>" 
                    class="travel-map-form-input"
                    placeholder="<?php _e('请输入高德地图安全密钥 (Security Key)', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                    required
                >
                <p class="travel-map-form-help">
                    <?php _e('2021年12月02日以后申请的 Key 必须配合安全密钥使用。请在', TRAVEL_MAP_TEXT_DOMAIN); ?> 
                    <a href="https://lbs.amap.com/" target="_blank"><?php _e('高德开放平台', TRAVEL_MAP_TEXT_DOMAIN); ?></a> 
                    <?php _e('获取安全密钥', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
        </div>

        <!-- 地图显示配置 -->
        <div class="travel-map-form-section">
            <h2 class="travel-map-section-title"><?php _e('地图显示配置', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            
            <div class="travel-map-form-row">
                <label for="default_zoom" class="travel-map-form-label">
                    <?php _e('默认缩放级别', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <select name="default_zoom" id="default_zoom" class="travel-map-form-select">
                    <?php for ($i = 1; $i <= 18; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($default_zoom, $i); ?>>
                            <?php printf(__('级别 %d', TRAVEL_MAP_TEXT_DOMAIN), $i); ?>
                            <?php if ($i <= 4): ?>
                                (<?php _e('世界地图', TRAVEL_MAP_TEXT_DOMAIN); ?>)
                            <?php elseif ($i <= 8): ?>
                                (<?php _e('国家级', TRAVEL_MAP_TEXT_DOMAIN); ?>)
                            <?php elseif ($i <= 12): ?>
                                (<?php _e('城市级', TRAVEL_MAP_TEXT_DOMAIN); ?>)
                            <?php else: ?>
                                (<?php _e('街道级', TRAVEL_MAP_TEXT_DOMAIN); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <p class="travel-map-form-help">
                    <?php _e('推荐使用级别 4-6 以获得最佳的世界地图视图', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label for="default_center" class="travel-map-form-label">
                    <?php _e('默认地图中心点', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input 
                    type="text" 
                    id="default_center" 
                    name="default_center" 
                    value="<?php echo esc_attr($default_center); ?>" 
                    class="travel-map-form-input"
                    placeholder="<?php _e('纬度,经度 (例如: 35.0,105.0)', TRAVEL_MAP_TEXT_DOMAIN); ?>"
                >
                <p class="travel-map-form-help">
                    <?php _e('格式：纬度,经度。默认为中国地理中心。注意：地图会优先自适应到当前筛选下的全部标记，此处的中心与缩放仅在所选筛选无任何标记时作为兜底视图。', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>

            <div class="travel-map-form-row">
                <label for="desktop_height" class="travel-map-form-label">
                    <?php _e('桌面端地图高度 (px)', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input
                    type="number"
                    id="desktop_height"
                    name="desktop_height"
                    value="<?php echo esc_attr(get_option('travel_map_desktop_height', 550)); ?>"
                    class="travel-map-form-input"
                    min="200" max="2000" step="10"
                >
                <p class="travel-map-form-help">
                    <?php _e('宽度 >768px 时的地图高度，单位像素，取值 200–2000，默认 550。短代码显式 height 属性可覆盖本页设置。', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>

            <div class="travel-map-form-row">
                <label for="mobile_height_percent" class="travel-map-form-label">
                    <?php _e('移动端地图高度 (视口百分比)', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input
                    type="number"
                    id="mobile_height_percent"
                    name="mobile_height_percent"
                    value="<?php echo esc_attr(get_option('travel_map_mobile_height_percent', 75)); ?>"
                    class="travel-map-form-input"
                    min="40" max="95" step="1"
                >
                <p class="travel-map-form-help">
                    <?php _e('宽度 ≤768px 时地图占可视窗口高度的百分比，取值 40–95，默认 75。实际高度会被限制在 340–720px 之间，并随浏览器地址栏伸缩自适应。', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
        </div>

        <!-- 功能选项 -->
        <div class="travel-map-form-section">
            <h2 class="travel-map-section-title"><?php _e('功能选项', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-checkbox">
                    <input 
                        type="checkbox" 
                        name="show_filter_tabs" 
                        value="1" 
                        <?php checked($show_filter_tabs); ?>
                    >
                    <span><?php _e('显示筛选标签栏', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                </label>
                <p class="travel-map-form-help">
                    <?php _e('在地图上方显示"全部"、"已去"、"想去"、"计划"筛选标签（数量为 0 的状态自动隐藏）', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-checkbox">
                    <input type="checkbox" name="highlight_country" value="1" <?php checked(get_option('travel_map_highlight_country', true)); ?>>
                    <span><?php _e('高亮已去国家', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                </label>
                <p class="travel-map-form-help">
                    <?php _e('将"已去"标记涉及的国家在地图上整片填绿（需要标记已填写国别字段）', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-checkbox">
                    <input type="checkbox" name="show_yearly_stats" value="1" <?php checked(get_option('travel_map_show_yearly_stats', true)); ?>>
                    <span><?php _e('显示年度统计面板', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                </label>
            </div>
            
            <div class="travel-map-form-row">
                <label class="travel-map-form-checkbox">
                    <input type="checkbox" name="show_type_stats" value="1" <?php checked(get_option('travel_map_show_type_stats', true)); ?>>
                    <span><?php _e('显示状态统计面板', TRAVEL_MAP_TEXT_DOMAIN); ?></span>
                </label>
            </div>
        </div>

        <!-- 聚合与视野 -->
        <div class="travel-map-form-section">
            <h2 class="travel-map-section-title"><?php _e('聚合与视野', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            
            <div class="travel-map-form-row">
                <label for="cluster_radius" class="travel-map-form-label">
                    <?php _e('聚合半径', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input 
                    type="number" 
                    id="cluster_radius" 
                    name="cluster_radius" 
                    value="<?php echo esc_attr(get_option('travel_map_cluster_radius', 40)); ?>" 
                    class="travel-map-form-input"
                    min="20" max="200"
                >
                <p class="travel-map-form-help">
                    <?php _e('像素。数值越大越容易合并成聚合气泡（默认 40）', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label for="cluster_limit" class="travel-map-form-label">
                    <?php _e('聚合数字上限', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input 
                    type="number" 
                    id="cluster_limit" 
                    name="cluster_limit" 
                    value="<?php echo esc_attr(get_option('travel_map_cluster_limit', 9)); ?>" 
                    class="travel-map-form-input"
                    min="1" max="999"
                >
                <p class="travel-map-form-help">
                    <?php _e('聚合气泡显示的最大数字，超出仍显示该值（默认 9）', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row">
                <label for="default_filter_status" class="travel-map-form-label">
                    <?php _e('缺省筛选状态', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <?php $tm_filter_status = get_option('travel_map_default_filter_status', 'all'); ?>
                <select name="default_filter_status" id="default_filter_status" class="travel-map-form-select">
                    <option value="all" <?php selected($tm_filter_status, 'all'); ?>><?php _e('全部', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    <option value="done" <?php selected($tm_filter_status, 'done'); ?>><?php _e('已去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    <option value="wish" <?php selected($tm_filter_status, 'wish'); ?>><?php _e('想去', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                    <option value="plan" <?php selected($tm_filter_status, 'plan'); ?>><?php _e('计划', TRAVEL_MAP_TEXT_DOMAIN); ?></option>
                </select>
                <p class="travel-map-form-help">
                    <?php _e('前台地图首次加载时激活的筛选页签。「全部」是已去/想去/计划的并集，不是单个状态。短代码显式写 status="…" 时以短代码为准。', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="travel-map-form-row travel-map-row-split">
                <div>
                    <label for="min_zoom" class="travel-map-form-label"><?php _e('最小缩放级别', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <input type="number" id="min_zoom" name="min_zoom" value="<?php echo esc_attr(get_option('travel_map_min_zoom', 1)); ?>" class="travel-map-form-input" min="1" max="20">
                </div>
                <div>
                    <label for="max_zoom" class="travel-map-form-label"><?php _e('最大缩放级别', TRAVEL_MAP_TEXT_DOMAIN); ?></label>
                    <input type="number" id="max_zoom" name="max_zoom" value="<?php echo esc_attr(get_option('travel_map_max_zoom', 12)); ?>" class="travel-map-form-input" min="3" max="20">
                </div>
            </div>
        </div>

        <!-- 缺省封面 -->
        <div class="travel-map-form-section">
            <h2 class="travel-map-section-title"><?php _e('缺省封面图', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
            
            <div class="travel-map-form-row">
                <label for="default_cover" class="travel-map-form-label">
                    <?php _e('缺省缩略图地址', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </label>
                <input 
                    type="url" 
                    id="default_cover" 
                    name="default_cover" 
                    value="<?php echo esc_attr(get_option('travel_map_default_cover', '')); ?>" 
                    class="travel-map-form-input"
                    placeholder="https://example.com/default-cover.jpg"
                >
                <p class="travel-map-form-help">
                    <?php _e('地点无封面且无关联文章特色图时，标记使用的兜底图片地址', TRAVEL_MAP_TEXT_DOMAIN); ?>
                </p>
            </div>
        </div>

        <!-- 保存按钮 -->
        <div style="padding-top: 20px;">
            <input type="submit" name="submit" class="button-primary" value="<?php _e('保存设置', TRAVEL_MAP_TEXT_DOMAIN); ?>">
        </div>
    </form>

    <!-- 使用说明 -->
    <div class="travel-map-form-section">
        <h2 class="travel-map-section-title"><?php _e('使用说明', TRAVEL_MAP_TEXT_DOMAIN); ?></h2>
        
        <!-- 快速开始 -->
        <div class="travel-map-usage-card">
            <div class="travel-map-usage-header">
                <div class="travel-map-usage-icon">🚀</div>
                <h3><?php _e('快速开始', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            </div>
            <div class="travel-map-usage-content">
                <div class="travel-map-step">
                    <span class="travel-map-step-number">1</span>
                    <div class="travel-map-step-content">
                        <h4><?php _e('配置API密钥', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <p><?php _e('在上方输入您的高德地图API密钥并保存设置', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
                <div class="travel-map-step">
                    <span class="travel-map-step-number">2</span>
                    <div class="travel-map-step-content">
                        <h4><?php _e('添加地点标记', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <p><?php _e('前往', TRAVEL_MAP_TEXT_DOMAIN); ?> <a href="<?php echo admin_url('admin.php?page=travel-map-markers'); ?>"><?php _e('坐标管理', TRAVEL_MAP_TEXT_DOMAIN); ?></a> <?php _e('页面添加您的旅行地点', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
                <div class="travel-map-step">
                    <span class="travel-map-step-number">3</span>
                    <div class="travel-map-step-content">
                        <h4><?php _e('显示地图', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <p><?php _e('在页面或文章中使用短代码', TRAVEL_MAP_TEXT_DOMAIN); ?> <code>[travel_map]</code> <?php _e('显示地图', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 短代码参数 -->
        <div class="travel-map-usage-card">
            <div class="travel-map-usage-header">
                <div class="travel-map-usage-icon">⚙️</div>
                <h3><?php _e('短代码参数', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            </div>
            <div class="travel-map-usage-content">
                <div class="travel-map-params-grid">
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">width</div>
                        <div class="travel-map-param-desc"><?php _e('地图宽度', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: 100%', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">width="800px"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">height</div>
                        <div class="travel-map-param-desc"><?php _e('地图高度', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: 550px', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">height="600px"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">zoom</div>
                        <div class="travel-map-param-desc"><?php _e('缩放级别 (1-18)', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: 配置值', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">zoom="6"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">center</div>
                        <div class="travel-map-param-desc"><?php _e('地图中心点坐标', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: 配置值', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">center="39.9,116.4"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">status</div>
                        <div class="travel-map-param-desc"><?php _e('显示的地点状态', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: all', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">status="done"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">filter_tabs</div>
                        <div class="travel-map-param-desc"><?php _e('是否显示筛选标签', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: true', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">filter_tabs="false"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">filters</div>
                        <div class="travel-map-param-desc"><?php _e('筛选按钮集（逗号分隔）', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: all,done,wish,plan', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">filters="all,done"</div>
                    </div>
                    <div class="travel-map-param">
                        <div class="travel-map-param-name">auto_zoom</div>
                        <div class="travel-map-param-desc"><?php _e('覆盖自适应缩放设置', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-default"><?php _e('默认: 设置值', TRAVEL_MAP_TEXT_DOMAIN); ?></div>
                        <div class="travel-map-param-example">auto_zoom="false"</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 地点状态说明 -->
        <div class="travel-map-usage-card">
            <div class="travel-map-usage-header">
                <div class="travel-map-usage-icon">📍</div>
                <h3><?php _e('地点状态说明', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            </div>
            <div class="travel-map-usage-content">
                <div class="travel-map-status-grid">
                    <div class="travel-map-status-item">
                        <div class="travel-map-status-badge done">已去</div>
                        <div class="travel-map-status-info">
                            <h4><?php _e('已去 (done)', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('已访问的地方，可关联旅行文章，点击显示文章详情', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                    <div class="travel-map-status-item">
                        <div class="travel-map-status-badge wish">想去</div>
                        <div class="travel-map-status-info">
                            <h4><?php _e('想去 (wish)', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('旅行愿望清单，可设置想去理由，点击显示简洁信息', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                    <div class="travel-map-status-item">
                        <div class="travel-map-status-badge plan">计划</div>
                        <div class="travel-map-status-info">
                            <h4><?php _e('计划 (plan)', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('已制定计划的旅行，可设置计划日期，点击显示计划详情', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 使用示例 -->
        <div class="travel-map-usage-card">
            <div class="travel-map-usage-header">
                <div class="travel-map-usage-icon">💡</div>
                <h3><?php _e('使用示例', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            </div>
            <div class="travel-map-usage-content">
                <div class="travel-map-examples">
                    <div class="travel-map-example">
                        <h4><?php _e('基础地图', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map]</code>
                            <button class="travel-map-copy-btn" data-copy="[travel_map]" title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('显示所有地点的基础地图', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="travel-map-example">
                        <h4><?php _e('大尺寸地图', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map height="700px" zoom="3"]</code>
                            <button class="travel-map-copy-btn" data-copy='[travel_map height=&quot;700px&quot; zoom=&quot;3&quot;]' title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('高度700px的大尺寸地图，缩放级别3', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="travel-map-example">
                        <h4><?php _e('只显示已去地点', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map status="done" filter_tabs="false"]</code>
                            <button class="travel-map-copy-btn" data-copy='[travel_map status=&quot;done&quot; filter_tabs=&quot;false&quot;]' title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('只显示已去的地点，隐藏筛选标签', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="travel-map-example">
                        <h4><?php _e('聚焦特定区域', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map center="39.9,116.4" zoom="6" height="600px"]</code>
                            <button class="travel-map-copy-btn" data-copy='[travel_map center=&quot;39.9,116.4&quot; zoom=&quot;6&quot; height=&quot;600px&quot;]' title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('聚焦北京地区（中国），适合展示特定区域的旅行足迹', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="travel-map-example">
                        <h4><?php _e('只显示想去地点', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map status="wish" height="500px"]</code>
                            <button class="travel-map-copy-btn" data-copy='[travel_map status=&quot;wish&quot; height=&quot;500px&quot;]' title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('仅显示想去的地点，展示旅行愿望清单', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="travel-map-example">
                        <h4><?php _e('计划中的旅行', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                        <div class="travel-map-code-block">
                            <code>[travel_map status="plan" filter_tabs="false"]</code>
                            <button class="travel-map-copy-btn" data-copy='[travel_map status=&quot;plan&quot; filter_tabs=&quot;false&quot;]' title="<?php _e('复制代码', TRAVEL_MAP_TEXT_DOMAIN); ?>">📋</button>
                        </div>
                        <p class="travel-map-example-desc"><?php _e('显示已制定计划的旅行地点，隐藏筛选标签', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 实用技巧 -->
        <div class="travel-map-usage-card">
            <div class="travel-map-usage-header">
                <div class="travel-map-usage-icon">🎯</div>
                <h3><?php _e('实用技巧', TRAVEL_MAP_TEXT_DOMAIN); ?></h3>
            </div>
            <div class="travel-map-usage-content">
                <div class="travel-map-tips">
                    <div class="travel-map-tip">
                        <div class="travel-map-tip-icon">✨</div>
                        <div class="travel-map-tip-content">
                            <h4><?php _e('地点命名建议', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('使用清晰明确的地点名称，如"北京天安门广场"、"法国巴黎埃菲尔铁塔"，避免使用模糊的描述', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                    <div class="travel-map-tip">
                        <div class="travel-map-tip-icon">🔗</div>
                        <div class="travel-map-tip-content">
                            <h4><?php _e('文章关联功能', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('发布旅行文章时，系统会自动检测地名并建议关联到地点标记。您也可以手动关联多篇文章到同一地点', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                    <div class="travel-map-tip">
                        <div class="travel-map-tip-icon">📱</div>
                        <div class="travel-map-tip-content">
                            <h4><?php _e('移动端适配', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('地图会自动适配移动设备，建议在移动端使用较小的高度值，如400px，以获得更好的显示效果', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                    <div class="travel-map-tip">
                        <div class="travel-map-tip-icon">🎨</div>
                        <div class="travel-map-tip-content">
                            <h4><?php _e('主题适配', TRAVEL_MAP_TEXT_DOMAIN); ?></h4>
                            <p><?php _e('地图会自动适配您的网站主题颜色模式（浅色/深色），无需手动配置', TRAVEL_MAP_TEXT_DOMAIN); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
