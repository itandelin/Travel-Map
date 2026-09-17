# WordPress Travel Map 项目总结

版本 1.1.0 · 基于代码实际状态整理

## 项目定位

面向旅行博主的 WordPress 地图插件。核心价值是把博客里散落的旅行文章按地理位置聚合到一张地图上：地点分「已去 / 想去 / 计划」三种状态，「已去」的地点关联文章后，标记会直接显示文章特色图，访客点击即可看到该地点的全部文章。

技术定位是「轻量」：不引入构建工具、不依赖前端框架、前台脚本无 jQuery 依赖，后端逻辑集中在一个主类中（1.1.0 起数据库迁移拆到独立的 `TravelMapMigrator`）。地图服务选用高德，对中国大陆访客的加载速度和地名覆盖更友好，代价是不适合面向海外访客的站点。

## 代码规模

| 类别 | 文件数 | 行数 |
|------|--------|------|
| PHP | 8 | 3806 |
| JavaScript | 6 | 3770 |
| CSS | 4 | 2894 |
| 文档 | 5 | — |
| 合计代码 | 18 | 10470 |

其中 `travel-map.php` 单文件 2343 行，占 PHP 总量的 62%。

## 功能实现情况

### 已实现

- 三状态地点管理，状态相关字段差异化（已去用访问日期/次数，想去用理由/优先级，计划用计划日期）
- 前台交互地图：状态筛选、缩放/全屏/重置控件、标记点击智能定位（先缩放再上移 25% 视野再弹窗）
- 弹窗跟随地图 move/zoom 实时重定位，边界自适应翻转
- 「已去」标记以文章特色图渲染为圆形头像，支持悬停预览
- 地图样式跟随站点深浅色模式，通过 MutationObserver 监听 `<html>` class 变化及系统 `prefers-color-scheme`
- 移动端适配：按容器宽度计算竖版高度（×1.3 / ×1.5）、禁用滚轮缩放、下滑关闭弹窗
- 后台坐标管理：地图点选、状态筛选、关键词搜索（覆盖标题/描述/关联文章标题）、批量删除、批量改状态
- CSV / JSON 导入，CSV 导出（后端接口亦支持 JSON 格式，界面未提供入口）
- 文章 Meta Box：勾选已有地点、搜索筛选、小地图新建地点
- 短代码 3 个：`[travel_map]`（8 个属性）、`[travel_map_countries]`、`[travel_map_markers]`；`[travel_map]` 输出 Schema.org `Map` 结构化数据（`travel-map.php:802-816`）
- 点聚合：`AMap.MarkerCluster`，点击逐级展开
- 双统计面板：右下角状态统计（已去/想去/计划/总计）+ 左下角年度统计（含国家数），随筛选联动
- 标记数据 5 分钟 transient 缓存，按状态分 key
- 前台数据双通道：AJAX 返回 401/403/405 时自动降级到 REST 只读接口
- 资源按需加载：仅在页面内容含短代码时加载，配合三层样式兜底应对主题不规范
- XSS 防护：前端统一 `escapeHtml` 转义 + `safeUrl` 协议白名单

### 未实现（文档曾声称但代码中不存在）

- 地理距离计算（`find_location_posts` 用的是 ±0.0001 硬阈值匹配坐标，不是距离计算）
- GPX / KML 导入导出；GeoJSON 导入不接受 `.geojson`，只能通过 `/geojson` REST 端点只读获取（文件导出仅 CSV 与 JSON）
- PDF 报告导出
- 多用户数据隔离（数据表无 `user_id` 字段，所有地点全站共享）
- 地点公开/私密开关
- 文章内容地名自动识别
- REST 写接口（仅 GET 只读）
- README 曾列出的 6 个扩展钩子（实际只有 `travel_map_post_types` 一个 filter）

## 架构

### 后端

单例 `TravelMapPlugin` 类，构造函数中集中注册全部钩子。方法按职责分组但未拆类：

| 分组 | 方法数 | 说明 |
|------|--------|------|
| 生命周期 | 4 | 激活建表、停用、卸载清理 |
| 资源加载 | 4 | 前台按需加载、后台分页面加载、版本号取 filemtime |
| 管理界面 | 4 | 菜单注册、两个页面渲染、设置保存 |
| 短代码 | 3 | 三个渲染函数 + 内容扫描 |
| AJAX | 11 | 见下表 |
| REST | 3 | 注册 + 三个只读回调 |
| Meta Box | 3 | 注册、渲染、保存 |
| 数据访问 | 约 15 | 查询、增删改、导入导出、缓存 |

### 数据模型

`wp_travel_map_markers`（主表）
- 基础：`id`、`title`、`latitude` decimal(10,8)、`longitude` decimal(11,8)、`status` enum('done','wish','plan')、`description`
- 状态相关：`visit_date`、`visit_count`、`planned_date`、`wish_reason`、`priority_level`
- 其他：`post_id`（主关联）、`marker_color`、`is_featured`、`created_at`、`updated_at`
- 1.1.0 新增列：`country` varchar(20)、`years` text、`cover_image` text、`type` varchar(30)（`add_missing_columns()`，`includes/class-travel-map-migrator.php:87-105`）
- 索引：`idx_post_id`、`idx_status`、`idx_created_at`

`wp_travel_map_post_markers`（关联表）
- `id`、`post_id`、`marker_id`、`created_at`
- 索引：`idx_post_id`、`idx_marker_id`

关联采用双轨设计：主表 `post_id` 存「主关联」，关联表存多对多关系。`update_post_marker_associations()` 先清空两侧，再把 `marker_ids[0]` 写回主表。查询时两边都读并去重。这个设计增加了一致性维护成本，是后续重构的重点候选。

1.1.0 起数据库迁移独立到 `TravelMapMigrator`（`includes/class-travel-map-migrator.php`）：`DB_VERSION = '1.1.0'`（`:20`）、option `travel_map_db_version`（`:26`，读取缺省 `'1.0.0'`）。`maybe_migrate()`（`:25-49`）比对版本后依次执行状态枚举迁移（`visited` → `done`，`migrate_status_enum()` `:58-85`）→ 补列（`:87-105`）→ 清理废弃选项并补写新设置项默认值（`cleanup_options()` `:108-120`）→ 写回版本号。

### 接口清单

| 类型 | 端点 | 权限 |
|------|------|------|
| AJAX | `travel_map_get_markers` | 公开（游客免 nonce） |
| AJAX | `travel_map_get_post_info` | 公开 |
| AJAX | `travel_map_get_location_posts` | 公开 |
| AJAX | `travel_map_save_marker` | nonce + `edit_posts` |
| AJAX | `travel_map_delete_marker` | nonce + `delete_posts` |
| AJAX | `travel_map_bulk_delete` | nonce + `delete_posts` |
| AJAX | `travel_map_bulk_status` | nonce + `edit_posts` |
| AJAX | `travel_map_import` | nonce + `edit_posts` |
| AJAX | `travel_map_import_rows` | nonce + `edit_posts`（前端 WGS-84 纠偏后按行导入，`travel-map.php:1385`） |
| AJAX | `travel_map_get_marker` | **仅 nonce，缺 capability 检查** |
| AJAX | `travel_map_export` | **仅 nonce，缺 capability 检查** |
| REST | `GET /travel-map/v1/markers` | 公开只读 |
| REST | `GET /travel-map/v1/geojson` | 公开只读（GeoJSON FeatureCollection，5 分钟 transient 缓存） |
| REST | `GET /travel-map/v1/location-posts` | 公开只读 |

### 前端

`travel-map.js` 定义单个 `TravelMap` 类（1528 行），暴露 `window.initTravelMap`。`travel-map-shortcode-init.js` 负责发现页面上的 `[data-travel-map-init="1"]` 容器并初始化，内含动态脚本注入、12 秒超时、MutationObserver 监听后插入的容器（兼容 AJAX 加载的页面）、pageshow/visibilitychange 重初始化。

后台脚本：`travel-map-coordinates.js`（770 行）驱动坐标管理页，`travel-map-meta-box.js`（392 行）驱动文章 Meta Box，`travel-map-settings.js`（74 行）只负责短代码复制按钮；另有 `travel-map-admin.js`（654 行）仍随每个插件后台页面加载，问题见「已知问题」。

## 已知问题

以下均为 1.1.0 仍存在的问题，按优先级排列。1.0.1 文档记录过的 `templates/admin-markers.php` 死文件、设置页死链、空分页容器、删除/取消按钮未绑定、`travel-map.js` 死方法、缺少 `db_version` 版本化迁移、卸载未清理安全密钥与旧颜色选项等，都已在 1.1.0 修复或删除，不再列出。

### 安全

1. **`ajax_export` 缺权限检查**（1.1.0 仍存在）——`travel-map.php:1330` 只调用 `check_ajax_referer()`，没有 `current_user_can()`。nonce 经 `wp_localize_script` 输出到前台页面源码，任何登录用户（含订阅者）都能取得有效 nonce 并导出全部坐标数据。应补 `edit_posts`。
2. **`ajax_get_marker` 缺权限检查**（1.1.0 仍存在）——`travel-map.php:1212` 同样只校验 nonce，可逐条读取任意标记的完整记录。
3. 导入未走 `wp_handle_upload`（1.1.0 仍存在）——`travel-map.php:1359` 直接读取 `$_FILES['import_file']['tmp_name']`，只按扩展名判断类型，未调用 `is_uploaded_file()` / `wp_check_filetype()`。
4. 导入的经纬度不校验 -90/90、-180/180 范围（1.1.0 仍存在）——`import_row()`（`travel-map.php:2067`）直接写库，越界值同样入库。
5. 导入不去重（1.1.0 仍存在）——`import_row()` 没有任何重复判断，同一份 CSV / JSON 重复导入会产生重复地点。

### 死代码与断链

1. `travel-map-admin.js`（654 行）在 `travel-map.php:655-660` 被无条件入队，只按 `$hook` 是否含 `travel-map` 粗筛，不区分设置页与坐标页；其大部分逻辑面向已删除的 `admin-markers.php` 的 DOM（`#marker_status`、`.travel-map-marker-form`、`.travel-map-sidebar`、`.status-fields`），而实际页面由 `travel-map-coordinates.js` 驱动。其中 `bulkExport()`（`assets/js/travel-map-admin.js:500`）提交的 action `travel_map_bulk_export` 后端从未注册，点击导出无响应。（1.1.0 仍存在）
2. 表单提交路径 `handle_marker_action` / `handle_add_marker` / `handle_edit_marker` / `handle_delete_marker`（`travel-map.php:2130`、`2153`、`2190`、`2232`）仍然保留，约 100 行。`admin_page_markers()` 中确实有 `if (isset($_POST['action'])) { $this->handle_marker_action($_POST); }` 的分发（`travel-map.php:454-456`），但页面上的两个表单（`templates/coordinates-list.php:141`、`345`）全部由 AJAX 驱动、不带 `action` 属性，因此这条路径在实际使用中没有 UI 可以触发——问题不是"死分支"，而是"有分发、无入口"，可整体删除以减少维护面。（1.1.0 仍存在）
3. GeoJSON 前后端声明不一致（1.1.0 仍存在）——文件选择框 `accept=".csv,.json,.geojson"`（`templates/coordinates-list.php:349`）接受 `.geojson`，但后端 `ajax_import()` 只认 `csv` / `json`，其余后缀直接返回"不支持的文件格式"（`travel-map.php:1364-1370`），选 `.geojson` 必然失败。
4. 数据表字段 `is_featured` / `marker_color` 仍存在但不生效（1.1.0 仍存在）——两列只出现在建表语句里（`travel-map.php:1611`、`1607`），PHP 侧没有任何写入或读取路径；`marker_color` 仅在 `assets/js/travel-map-admin.js:265-266` 被读入 `#marker-color` 控件，而该控件只存在于已删除的旧版页面。前台一律按状态统一取色。

### 数据与升级

1. `uninstall()` 未清理 transient（1.1.0 仍存在）——`travel-map.php:1565-1585` 已删除全部选项（含 `travel_map_security_key`、`travel_map_db_version`、三个旧颜色选项）并 DROP 两张表，但没有任何 `delete_transient()` 调用；`travel_map_markers_*`（写入于 `travel-map.php:1797`）与 `travel_map_markers_geojson`（`travel-map.php:999`）会残留。
2. 关联表无 `UNIQUE(post_id, marker_id)` 约束（1.1.0 仍存在）——建表语句见 `travel-map.php:1626-1635`，只有 `PRIMARY KEY (id)` 与普通索引 `idx_post_id`、`idx_marker_id`，重复插入不会被拦截。

### 性能

1. `get_marker_featured_image()`（`travel-map.php:1806`）对每个「已去」标记至少一次查询，存在 N+1（1.1.0 仍存在）——`get_markers_by_status()`（`travel-map.php:1668`）在循环中逐个调用。缓存失效只挂在插件自身的增删改上（`clear_markers_cache()`，`travel-map.php:300`），未监听 `save_post` / `updated_post_meta`；单独更换文章特色图后，前台最长 5 分钟仍显示旧图。
2. 无分页（1.1.0 仍存在）——`get_all_markers()`（`travel-map.php:1658`）与 `rest_get_geojson()`（`travel-map.php:935`）都一次性读取并返回全量标记；`rest_get_markers()`（`travel-map.php:1054`）也只支持 `status` / `search`，没有 `page` / `per_page` 之类的参数。

### 工程化

1. i18n 使用 `TRAVEL_MAP_TEXT_DOMAIN` 常量而非字面量 `'travel-map'`（1.1.0 仍存在）——常量定义于 `travel-map.php:27`，全仓 `__()` 都传常量，WordPress 官方 i18n 工具与 WP-CLI 无法扫描字符串。`languages/travel-map.pot` 现已更新为 735 行、`Project-Id-Version: Travel Map 1.1.0`（原文档写的"107 行、手写"已过时），但改为字面量后仍需重新生成。
2. 关键 CSS 仍有多份副本（1.1.0 仍存在）——`travel-map-popup` 出现于 `assets/css/travel-map.css`（29 处）、`assets/js/travel-map.js` 内联（19 处）、`templates/shortcode-markers.php`（1 处），修改需多处同步。
3. 缺 `readme.txt` 与 `LICENSE`（1.1.0 仍存在）——插件根目录只有 5 个 `.md` 文档，上架 WordPress.org 所需的这两份文件都不存在。
4. `preventThemeConflicts()` 手法较激进（1.1.0 仍存在）——`assets/js/travel-map.js:1266`，在捕获阶段拦截地图容器内的 `img` 与 `[data-fancybox]` / `[data-lightbox]` / 相册元素点击，调用 `stopImmediatePropagation()`（`:1284`、`:1291`、`:1301`、`:1313`），可能与部分主题或灯箱插件产生难以定位的冲突。
5. 无自动化测试（1.1.0 仍存在）——仓库内没有测试目录、`phpunit.xml` 或 CI 配置。

## 建议的改进顺序

**第一批（安全，应尽快）**
- 给 `ajax_export`（`travel-map.php:1330`）、`ajax_get_marker`（`travel-map.php:1212`）补 capability 检查
- 导入改用 `wp_handle_upload`，并补坐标范围校验与重复数据判断

**第二批（清理，低风险）**
- `travel-map-admin.js` 改为按页面条件加载，或删除面向已消失 DOM 的逻辑，并接上/移除 `travel_map_bulk_export`
- 移除主文件中没有 UI 入口的表单提交路径（`handle_marker_action` 与三个 `handle_*_marker`）
- 对齐 GeoJSON 的前后端声明：去掉 `accept` 里的 `.geojson`，或补上 GeoJSON 导入
- 明确 `is_featured` / `marker_color` 的取舍：接上前台渲染，或从表结构移除

**第三批（结构）**
- 关联表加唯一约束，简化 `post_id` 双轨关联
- 特色图查询改为批量预加载，消除 N+1；缓存失效补挂 `save_post` / `updated_post_meta`
- 后台与前台分页
- i18n 改为字面量 text domain，再重新生成 pot
- 关键 CSS 收敛到单一来源
- 补 `readme.txt` 与 `LICENSE`
- 补自动化测试
- 卸载时清理 transient

**1.1.0 已完成**：删除 `templates/admin-markers.php` 死文件、修正设置页死链 slug、移除空分页容器、绑定删除/取消编辑按钮、删除 `travel-map.js` 死方法、引入 `travel_map_db_version` 版本化迁移、补全卸载清理（安全密钥与三个旧颜色选项）、重新生成 pot。

## 技术规格

**运行环境**：WordPress 5.0+（测试至 6.4）、PHP 7.4+、MySQL 5.7+

**浏览器**：依赖 ES6 class、模板字符串、`MutationObserver`、`FormData`、可选链（`?.`），需现代浏览器，不支持 IE

**外部依赖**：高德地图 JS API 2.0（`webapi.amap.com`），需 Web 端 Key + 安全密钥，密钥会输出到前台源码，必须在高德控制台绑定域名白名单

**状态取值**：`done`（已去）/ `wish`（想去）/ `plan`（计划），筛选另用 `all`；旧值 `visited` / `planned` 由 `normalize_status()`（`travel-map.php:126`）自动映射到新值

**短代码**（3 个，注册于 `travel-map.php:78-80`）：

| 短代码 | 属性 | 默认值 / 说明 |
|--------|------|--------------|
| `[travel_map]` | `width` | `100%` |
| | `height` | `550px` |
| | `zoom` | 取 option `travel_map_default_zoom`，缺省 `4` |
| | `center` | 取 option `travel_map_default_center`，缺省 `35.0,105.0` |
| | `filter_tabs` | 取 option `travel_map_show_filter_tabs`，缺省 `true` |
| | `status` | **空字符串**。刻意留空以便与用户显式写 `status="all"` 区分：留空时让位给 option `travel_map_default_filter_status`（`travel-map.php:788-790` 有注释） |
| | `filters` | `all,done,wish,plan`，逗号分隔，可裁剪 |
| | `auto_zoom` | 空字符串 |
| `[travel_map_countries]` | — | 无属性（`travel-map.php:828`）。按 `done` 标记的 `country` 字段（支持逗号分隔多国）分组，输出国旗 + ISO 码 + 地点数，按地点数降序；模板 `templates/shortcode-countries.php` |
| `[travel_map_markers]` | `status` | `done`｜`wish`｜`plan`｜`all`，默认 `done`（`travel-map.php:858-862`） |
| | `type` | 空，按分类过滤 |

**REST 端点**（`travel-map.php:887-915`，全部为 GET、`permission_callback => '__return_true'` 公开只读）：

| 路由 | 参数 | 说明 |
|------|------|------|
| `GET /wp-json/travel-map/v1/markers` | `status`（默认 `all`）、`search` | 1.0.x 兼容端点，返回标记数组 |
| `GET /wp-json/travel-map/v1/geojson` | — | 标准 GeoJSON FeatureCollection，用 transient `travel_map_markers_geojson` 缓存 5 分钟（`travel-map.php:935-1001`）；properties 含 `title`/`status`/`country`/`type`/`image[]`/`posts[]`/`year[]` |
| `GET /wp-json/travel-map/v1/location-posts` | `latitude`、`longitude`（必填）、`location_name` | 1.0.x 兼容端点，返回坐标附近的关联文章 |

**许可**：GPL v2 or later

**作者**：Mr. T · https://github.com/itandelin/Travel-Map
