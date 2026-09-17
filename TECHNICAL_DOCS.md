# WordPress Travel Map 技术文档

适用版本：1.1.0

本文档描述插件的实际实现结构，包含已知的技术债与待改进项。

## 技术栈

| 层 | 实现 |
|----|------|
| 后端 | PHP 7.4+，WordPress 5.0+，单文件主类 + 迁移器 |
| 数据 | MySQL 5.7+，两张自建表，`$wpdb` 直接操作 |
| 前台 | 原生 JavaScript（无 jQuery），高德地图 JS API 2.0 |
| 后台 | jQuery（WordPress 内置） |
| 缓存 | Transient，5 分钟 |

没有 Composer、没有构建流程、没有自动化测试。

## 文件结构

```
travel-map/
├── travel-map.php                          2343 行，TravelMapPlugin 单例 + 全部后端逻辑
├── includes/
│   └── class-travel-map-migrator.php        139 行，数据库版本化迁移
├── templates/
│   ├── admin-settings.php                   494 行，设置页
│   ├── coordinates-list.php                 369 行，坐标管理页（实际使用）
│   ├── meta-box-coordinates.php             285 行，文章 Meta Box
│   ├── map-shortcode.php                     87 行，前台地图容器
│   ├── shortcode-countries.php               26 行，[travel_map_countries] 模板
│   └── shortcode-markers.php                 63 行，[travel_map_markers] 模板
├── assets/
│   ├── js/
│   │   ├── travel-map.js                   1528 行，前台 TravelMap 类
│   │   ├── travel-map-admin.js              654 行，后台脚本，逻辑多指向已删除的旧页面
│   │   ├── travel-map-coordinates.js        770 行，坐标管理页实际逻辑
│   │   ├── travel-map-meta-box.js           392 行，Meta Box 逻辑
│   │   ├── travel-map-shortcode-init.js     352 行，短代码初始化与资源兜底
│   │   └── travel-map-settings.js            74 行，设置页复制按钮
│   ├── css/
│   │   ├── travel-map.css                   946 行，前台样式
│   │   ├── travel-map-admin.css             970 行，后台通用样式
│   │   ├── travel-map-coordinates.css       583 行，坐标管理页样式
│   │   └── travel-map-meta-box.css          395 行，Meta Box 样式
│   └── flags/*.svg                          270 个国旗，按 ISO2 小写命名（flag-icons v7.2.3，MIT）
└── languages/travel-map.pot                 735 行
```

## 架构

单例类 `TravelMapPlugin`，构造函数私有，`get_instance()` 获取实例，文件末尾直接调用初始化。所有钩子在 `init_hooks()` 里集中注册。数据库迁移被抽到独立的 `TravelMapMigrator`（`includes/class-travel-map-migrator.php:18-138`），是唯一的例外；除此之外没有子类、没有依赖注入、没有独立的 Manager 类——数据库操作、AJAX、短代码、后台页面渲染都是这一个类的方法，视图逻辑抽到 `templates/`。

两个静态标志位控制幂等：

- `$frontend_scripts_loaded` — 前台资源只加载一次
- `$markers_cache_cleared` — 一次请求内缓存只清一次

## 数据库

建表在 `create_database_tables()`（`travel-map.php:1590-1639`），由激活钩子 `activate()`（:1549-1553）调用，用 `dbDelta()`；激活时同时写入 `travel_map_db_version`（`set_default_options()`，:1644-1653）。已安装站点不会再跑激活钩子，结构变更由迁移器在 `init` 时补齐，见「升级路径」。

### `{prefix}travel_map_markers`

```sql
CREATE TABLE {prefix}travel_map_markers (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NULL,
    title varchar(255) NOT NULL,
    latitude decimal(10,8) NOT NULL,
    longitude decimal(11,8) NOT NULL,
    status enum('done', 'wish', 'plan') DEFAULT 'done',
    visit_date date NULL,
    visit_count int DEFAULT 1,
    description text NULL,
    marker_color varchar(7) DEFAULT NULL,
    planned_date date NULL,
    wish_reason text NULL,
    priority_level tinyint DEFAULT 3,
    is_featured boolean DEFAULT FALSE,
    country varchar(20) NULL,
    years text NULL,
    cover_image text NULL,
    type varchar(30) NULL DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
);
```

`country` / `years` / `cover_image` / `type` 是 1.1.0 新增列（`travel-map.php:1612-1615`，老站点由 `add_missing_columns()` 补上）。

字段按状态分工：`visit_date`/`visit_count` 属于 `done`，`planned_date` 属于 `plan`，`wish_reason`/`priority_level` 属于 `wish`。`country` 存 ISO2 大写、多国逗号分隔（`sanitize_country()` 会把 ISO3 归一为 ISO2，无法映射的 3 位码直接丢弃，:149-213）；`years` 存年份逗号分隔；`cover_image` 存手动封面 URL；`type` 是自定义分类。表里没有 `user_id`，插件不做数据归属隔离。

`is_featured` 字段建了但代码从未读写。`marker_color` 只有已废弃的 `travel-map-admin.js:265-266` 还会回填表单项，前台按状态统一取色，单个标记的颜色不生效。

### `{prefix}travel_map_post_markers`

```sql
CREATE TABLE {prefix}travel_map_post_markers (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    marker_id bigint(20) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_marker_id (marker_id)
);
```

没有唯一约束，也没有外键。删除地点时由 `delete_marker()` 手动清理关联记录；删除文章时不会清理，会留下孤儿关联行（查询时因为 `post_status = 'publish'` 条件被过滤掉，不影响显示）。

### 双轨关联

文章与地点的关联同时存在两处：主表的 `post_id`（「主关联」）和关联表。`update_post_marker_associations()`（`travel-map.php:2286-2326`）的流程是：

1. 删除该文章在关联表里的全部记录
2. 把主表中 `post_id` 等于该文章的记录重置为 `NULL`
3. 按勾选顺序写入关联表
4. 把 `marker_ids[0]` 对应的地点的 `post_id` 设为该文章

读取时两边都查再去重（`get_markers_by_status()`、`find_location_posts()`、`get_post_markers()`）。这是历史遗留的冗余设计。

### 升级路径

1.1.0 已经实现版本化迁移，代码在 `includes/class-travel-map-migrator.php`：

- `DB_VERSION = '1.1.0'`（:20）；版本号存在 option `travel_map_db_version` 里，读取时缺省 `'1.0.0'`（:26），因此 1.0.x 站点首次运行一定会走一遍迁移。
- 入口 `maybe_migrate()`（:25-50）在 `init()` 里调用（`travel-map.php:119`）：版本号已达标直接返回；`SHOW TABLES` 发现目标表不存在（插件激活前触发了 `admin_init`/`init`）也直接返回；否则依次执行
  1. `migrate_status_enum()`（:58-82）——三步迁移 ENUM：先扩成 `'visited','want_to_go','planned','done','wish','plan'`，UPDATE 把旧值改写成 `done`/`wish`/`plan`，再收窄回 `enum('done','wish','plan')`。直接 MODIFY 成窄枚举会让存量旧值在严格模式下 ALTER 失败，所以必须分三步；已经是目标枚举时跳过。
  2. `add_missing_columns()`（:87-103）——逐列 `SHOW COLUMNS` 检查后补列，幂等。
  3. `cleanup_options()`（:108-120）——删除三个废弃颜色 option，并为缺失的新设置项写默认值。
  4. `update_option('travel_map_db_version', self::DB_VERSION)`。
- 触发时机：每次请求的 `init` 都会调用一次 `maybe_migrate()`，第一次比对失败时完成结构升级并写版本号，之后每次都是幂等返回。全新安装走激活钩子，`set_default_options()` 直接写入当前版本号（`travel-map.php:1652`），不会重复迁移。

## 配置项

`register_setting('travel_map_settings', ...)` 注册了 15 个键（`travel-map.php:342-395`）：

| Option | 默认值 | 说明 |
|--------|--------|------|
| `travel_map_api_key` | `''` | 高德 JS API Key |
| `travel_map_security_key` | `''` | 安全密钥 securityJsCode |
| `travel_map_default_zoom` | `4` | 默认缩放 |
| `travel_map_default_center` | `'35.0,105.0'` | 默认中心点，`纬度,经度` |
| `travel_map_show_filter_tabs` | `true` | 筛选标签开关 |
| `travel_map_cluster_radius` | `40` | 聚合半径 |
| `travel_map_cluster_limit` | `9` | 聚合点上限 |
| `travel_map_auto_zoom` | `true` | 初始自动缩放到全部标记 |
| `travel_map_highlight_country` | `true` | 已去国家高亮 |
| `travel_map_show_yearly_stats` | `true` | 年度统计面板 |
| `travel_map_show_type_stats` | `true` | 分类统计 |
| `travel_map_default_filter_status` | `'all'` | 前台默认筛选状态，取值 `all`/`done`/`wish`/`plan` |
| `travel_map_default_cover` | `''` | 默认封面图 URL |
| `travel_map_min_zoom` | `1` | 允许的最小缩放 |
| `travel_map_max_zoom` | `12` | 允许的最大缩放 |

**设置页没有任何颜色项**：1.0.x 的三个颜色 option（`travel_map_visited_color` / `travel_map_want_to_go_color` / `travel_map_planned_color`）已从设置项与代码中移除，`templates/admin-settings.php` 里只剩 `color:#dc2626` 这类形式样式。标记与筛选胶囊的配色改由状态决定，硬编码在前台样式里。

设置页用的是自己的 `save_settings()`（`travel-map.php:467-501`，校验 `travel_map_settings` nonce 后逐个 `update_option`，数值项做 min/max 夹取），`register_setting()` 的注册主要用于声明类型与默认值，并未走 Settings API 的表单流程。

`uninstall()`（`travel-map.php:1565-1585`）删除 `api_key`、`security_key`、`default_zoom`、`default_center`、`show_filter_tabs`、`travel_map_db_version` 与 `TravelMapMigrator::default_settings()` 的全部 10 个键，并兼容清理三个旧颜色 option，最后 DROP 两张表。**仍然遗漏** transient：没有任何 `delete_transient()` 调用，`travel_map_markers_*` 与 `travel_map_markers_geojson` 会留在 options 表里直到自然过期。

## 短代码

三个短代码都在 `init_hooks()` 里注册（`travel-map.php:78-80`）。

### `[travel_map]` → `render_map_shortcode()`（`travel-map.php:763`）

| 属性 | 默认值 | 说明 |
|------|--------|------|
| `width` | `100%` | 容器宽度 |
| `height` | `550px` | 写入容器 CSS 变量 `--travel-map-height` |
| `zoom` | option `travel_map_default_zoom`，缺省 4 | |
| `center` | option `travel_map_default_center`，缺省 `'35.0,105.0'` | `纬度,经度` |
| `filter_tabs` | option `travel_map_show_filter_tabs`，缺省 true | |
| `status` | **空字符串**（:790） | 刻意留空而不是 `'all'`：只有空值才能与「用户显式写 `status="all"`」区分，从而决定是否让位给 option `travel_map_default_filter_status` |
| `filters` | `all,done,wish,plan` | 逗号分隔，可裁剪 |
| `auto_zoom` | 空字符串 | 非空时覆盖 option `travel_map_auto_zoom` |

未配置 API 密钥时直接返回错误块「请先配置高德地图API密钥」（:796-798）。首次调用时通过 `wp_footer` 输出 schema.org `Map` 类型 JSON-LD，`static $schema_added` 保证单页只输出一次（:802-816）。

### `[travel_map_countries]` → `render_countries_shortcode()`（`travel-map.php:828`）

**无属性**（:828）。取 `done` 状态标记的 `country` 字段（支持逗号分隔多国）分组计数，按地点数降序输出国旗 + ISO 码 + 地点数（:829-846）。模板 `templates/shortcode-countries.php`，国旗基址 `TRAVEL_MAP_PLUGIN_URL . 'assets/flags/'`。

### `[travel_map_markers]` → `render_markers_shortcode($atts)`（`travel-map.php:858`）

| 属性 | 默认值 | 说明 |
|------|--------|------|
| `status` | `done` | 取值 `done`/`wish`/`plan`/`all`，经 `normalize_status()` 归一（:864） |
| `type` | 空 | 按 `type` 字段精确过滤（:872） |

模板 `templates/shortcode-markers.php`：国旗 + 地名 + 坐标 + 年份胶囊 + 关联游记封面网格（复用 `get_marker_posts_payload()`）。

## 资源加载

前台资源采用三层兜底，用来对抗主题不规范调用 `wp_head()`/`wp_footer()` 的情况：

1. `maybe_enqueue_frontend_scripts()`（挂 `wp_enqueue_scripts`）遍历 `$posts` 与 `is_singular()` 的 `$post`，用 `has_shortcode()` 判断是否需要加载
2. `check_shortcode_and_enqueue_scripts()`（挂 `the_content`，优先级 5）再检测一次
3. `render_map_shortcode()` 渲染时若发现样式/脚本尚未输出，直接调用 `wp_print_styles()` / `wp_print_scripts()`

此外还有两处内联 CSS 兜底：`enqueue_frontend_scripts()` 里通过 `wp_add_inline_style()` 注入关键样式，`travel-map-shortcode-init.js` 的 `injectFallbackStyles()` 里还有同一份样式的第三个副本，通过检测 `.travel-map-accessibility` 元素的实际尺寸判断样式是否生效。

**技术债**：同一段关键 CSS 存在多份副本（`travel-map.php:564` 的 PHP 内联、`assets/js/travel-map-shortcode-init.js:64` 的 JS 内联、`assets/css/travel-map.css`），修改时需要同步（`travel-map.php:563` 的注释也写明了这一点）。

资源版本号由 `get_asset_version()` 返回文件 `filemtime()`，文件不存在时回退到 `TRAVEL_MAP_VERSION`（`travel-map.php:218-224`）。

传给前端的本地化对象：

- `travelMapAjax` — `ajaxurl`、`restUrl`、`geojsonUrl`、`nonce`、`apiKey`、`flagsBase`、`settings`（聚合半径/上限、autoZoom、highlightCountry、统计开关、默认筛选状态、min/max zoom）、`debug`（`travel-map.php:568-590`）
- `travelMapShortcode` — `apiScript`、`frontendScript`、`styleUrl`、`securityKey`、i18n 文案（:600）
- `travelMapAdmin`（:671）/ `travelMapSettings`（:686）/ `travelMapCoordinates`（:717，含 `flagsBase`）/ `travelMapConfig`（:1495）— 后台各页面配置

安全密钥通过 `wp_add_inline_script(..., 'before')` 以 `window._AMapSecurityConfig` 的形式输出到页面，前台源码可见。这是高德 JS API 的既定用法，必须依赖控制台的域名白名单来限制滥用。

## 数据接口

### AJAX 端点

| Action | 游客可用 | 校验 |
|--------|----------|------|
| `travel_map_get_markers` | 是 | `verify_public_read_ajax_request()` |
| `travel_map_get_post_info` | 是 | `verify_public_read_ajax_request()` |
| `travel_map_get_location_posts` | 是 | `verify_public_read_ajax_request()` |
| `travel_map_get_marker` | 否 | `check_ajax_referer` |
| `travel_map_save_marker` | 否 | `check_ajax_referer` + `edit_posts` |
| `travel_map_delete_marker` | 否 | `check_ajax_referer` + `delete_posts` |
| `travel_map_bulk_delete` | 否 | `check_ajax_referer` + `delete_posts` |
| `travel_map_bulk_status` | 否 | `check_ajax_referer` + `edit_posts` |
| `travel_map_import` | 否 | `check_ajax_referer` + `edit_posts` |
| `travel_map_import_rows` | 否 | `check_ajax_referer` + `edit_posts` |
| `travel_map_export` | 否 | `check_ajax_referer` |

`verify_public_read_ajax_request()`（`travel-map.php:1082-1093`）的逻辑：nonce 有效直接放行；nonce 无效但用户未登录也放行（兼容页面缓存导致的 nonce 过期）；nonce 无效且已登录则返回 403。这三个端点只读公开数据，这样处理是有意的取舍。

当前脚本实际调用的 action：`travel_map_get_markers`、`travel_map_get_marker`、`travel_map_save_marker`、`travel_map_delete_marker`、`travel_map_bulk_delete`、`travel_map_export`、`travel_map_import`、`travel_map_import_rows`；`travel_map_bulk_status` 只剩已废弃的 `travel-map-admin.js:480` 会调用；`travel_map_get_post_info` 与 `travel_map_get_location_posts` 已无任何脚本调用（前台改走 REST `/geojson`），作为公开只读端点保留兼容。

**已知安全缺口**：`travel_map_export`（`travel-map.php:1330`）与 `travel_map_get_marker`（:1212）只校验了 nonce，没有 capability 检查。而 `travel_map_nonce` 会通过 `wp_localize_script` 输出到前台页面，任何登录用户（含订阅者）都能从源码取得有效 nonce 并调用 export 导出全部坐标数据。修复方式是给这两个端点补 `current_user_can('edit_posts')`。

**导入侧已经补过权限检查**：`ajax_import()`（:1351）与 `ajax_import_rows()`（:1388）都有 `current_user_can('edit_posts')`，不要笼统写成「导入导出都缺权限」。

### REST 路由

`register_rest_routes()`（`travel-map.php:887-927`）注册三个**公开只读**端点，`permission_callback` 都是 `__return_true`：

| 路由 | 方法 | 说明 |
|------|------|------|
| `GET /wp-json/travel-map/v1/markers?status=&search=` | GET | `status` 缺省 `all`，`search` 缺省空；响应体为 `{ success, data }`（`rest_get_markers()`，:1054-1065） |
| `GET /wp-json/travel-map/v1/geojson` | GET | 全量标记的标准 `FeatureCollection`（`rest_get_geojson()`，:935-1002） |
| `GET /wp-json/travel-map/v1/location-posts?latitude=&longitude=&location_name=` | GET | `latitude`/`longitude` 必填（:914-925） |

`/geojson` 用 transient `travel_map_markers_geojson` 缓存 5 分钟（:936-939 读、:999 写）。每个 Feature 的 `properties` 为 `title`、`status`、`country`、`type`、`image[]`（手动封面优先，其后拼关联文章特色图，去重且上限 10）、`posts[]`、`year[]`（`years` 字段优先，回退 `visit_date` 年份），另有 `plan_date` 与 `wish_reason`（:984-994）。

前台主地图直接 `GET /geojson`（`assets/js/travel-map.js:440-463`，用 `XMLHttpRequest`），`markers` 与 `location-posts` 是 1.0.x 兼容端点。

## 查询实现

### `get_markers_by_status($status, $search)`

主查询用 `$wpdb->prepare()` 拼装，`LEFT JOIN` posts 表取主关联文章标题。搜索会匹配地点名称、描述、主关联文章标题，以及通过 `EXISTS` 子查询匹配关联表里的文章标题。

拿到主结果后做两轮批量预加载避免 N+1：先用 `IN` 一次性查出全部 `marker_id` 的关联 `post_id`，再用 `IN` 一次性查出这些文章的标题，最后在 PHP 里组装 `post_titles` 数组。

`search` 为空时结果写入 transient，key 为 `travel_map_markers_{status}`，TTL 5 分钟（`travel-map.php:1796-1798`）。

**性能问题**：随后的特色图循环没有做批量优化。每个 `done` 状态的标记都会调用 `get_marker_featured_image()`（`travel-map.php:1789-1794`、:1806-1836），其中至少一次 `get_the_post_thumbnail_url()`，主关联没图时还会再查关联表（`LIMIT 5`）并逐个尝试。标记数量大时这里是主要开销。

### `find_location_posts($latitude, $longitude, $location_name)`

用 `title` 精确相等 + `ABS(latitude - %f) < 0.0001` + `ABS(longitude - %f) < 0.0001` 定位标记，再汇总主关联和关联表里的已发布文章，用 permalink 去重。这是硬阈值匹配，不是地理距离计算，也没有聚合算法。

### 缓存失效

`clear_markers_cache()`（`travel-map.php:300-313`）删除四个状态的 transient（`all`/`done`/`wish`/`plan`）、三个旧状态键的遗留缓存（`visited`/`want_to_go`/`planned`）以及 REST 用的 `travel_map_markers_geojson`。调用点为 `save_marker()`、`delete_marker()`、`update_marker()`、`update_post_marker_associations()`、`ajax_bulk_status()`、`ajax_import_rows()`、`import_csv()`、`import_json()`（:1917、:1932、:2260、:2325、:1318、:1403、:2060、:2123）。

**已知问题**：没有挂 `save_post` 或 `updated_post_meta`。单独更换文章特色图而不经过 Meta Box 保存时，前台最长 5 分钟仍显示旧图。

## 前台实现

`assets/js/travel-map.js`（1528 行）是一个 IIFE，导出 `window.initTravelMap(container, options)`，内部是 `TravelMap` 类。

初始化流程：`init()`（:146-166）判断容器形态（已有 `.travel-map` 元素 / 容器本身即地图 / 空容器需 `setupContainer()` 自建结构）→ `setupFilterTabs()` → `setupFullscreenControl()` → `setupMap()` → `bindEvents()` → `observeContainerSize()` → `syncPopupMaxWidth()` → `preventThemeConflicts()`。`setupMap()`（:385-439）创建 `AMap.Map`（`zooms: [minZoom, maxZoom]`），构造后立刻 `initThemeObserver()`，瓦片 `complete` 事件里才 `hideLoading()` + `loadData()` + 补一次 `syncMapTheme()`。

数据与渲染：`loadData()`（:440-463）XHR `GET` `options.geojsonUrl`，成功后 `onDataReady()`（:465-499）：算状态计数 → `buildFilterBar()` → 定初始筛选（含回退链，末位回退是 `all` 而不是 `done`）→ `applyFilter()` + `renderMarkers()` + `buildStatsPanels()` + `updateStatsPanels()` → `autoZoom` 打开时 `fitToMarkers()`，否则 `setZoomAndCenter()` → `highlightVisitedCountries()` → `renderTenDashLines()` → 容器加 `is-loaded`。

关键行为：

- **标记形态** `createMarkerElement(props)`（:580-606）：按 `status` 加 `marker--done`/`marker--plan`/`marker--wish`，有安全图片 URL 时加 `has-photo` 并把图片写成 `--photo` 变量，`done` 且没有关联文章再加 `no-post`。具体外观（照片缩略、状态色渐变、尺寸）由 `assets/css/travel-map.css:231-240` 决定。
- **聚合渲染** `initCluster()` / `renderClusterMarker()` / `renderSingleMarker()`（:512-579）：`AMap.MarkerCluster`，半径与上限取自 option `travel_map_cluster_radius` / `travel_map_cluster_limit`。
- **国家高亮与十段线**：`highlightVisitedCountries()`（:1023）用 `AMap.DistrictLayer.World`（填色 `#6abf69`、描边 `#2e7d32`）；`renderTenDashLines()`（:1057）绘制南海十段线。
- **弹窗定位**：`openMarkerPopup()`（:610）先 `syncPopupMaxWidth()`（:904）按容器宽度收敛卡片宽度，再用 `measurePopupSize()`（:729）+ `computePopupPlacement()`（:768）一次算准摆上方还是下方、水平偏移多少，`verifyPopupPlacement()`（:858）复核。不再用开窗后 `panBy` 补偿（那会改视野）。弹窗内容按状态分派：有关联文章列出链接，`plan` 显示计划日期，`wish` 显示想去理由，其余显示「该地点暂无游记。」。
- **筛选链**：`parseRequestedFilters()` / `computeStatusCounts()` / `computeRenderableFilters()` / `buildFilterBar()`（:207-303），空数据的筛选胶囊自动隐藏。
- **主题自适应**：`detectThemeMode()`（:1438）读 `<html>` 的 `dark`/`light`/`auto` class，`auto` 或无 class 时回落到 `prefers-color-scheme`；`initThemeObserver()`（:1458）用 `MutationObserver` + `matchMedia` 监听，`syncMapTheme()` / `updateMapTheme()`（:1503、:1509）只在检测结果与已生效值不同时才 `setMapStyle()` 在 `amap://styles/light` 与 `dark` 之间切换。
- **尺寸适配**：`refreshMapSize()`（:1414）+ `ResizeObserver`（`observeContainerSize()`，:1423）+ `bindOrientationChange()`（:1204，200ms 防抖，移动端横竖屏切换时顺带把 zoom 收敛回默认）。
- **无障碍列表**：`getAccessibleList()` / `renderAccessibleList()`（:1228、:1235）渲染地图容器旁的 `[data-travel-map-a11y-list]` 按钮列表，点击即打开对应弹窗。
- **XSS 防护**：`escapeHtml()`（:17）转义 `& < > " ' \``；`safeUrl()`（:31）只允许 `http://`、`https://`、`//` 开头或 `data:image/...;base64,` 的 URL。所有插入 `innerHTML` 的动态值都经过这两个函数。

`preventThemeConflicts()`（:1266-1316）在捕获阶段拦截地图容器内的 `click`/`mousedown`/`mouseup`/`dblclick`：弹窗（`.amap-info`）内只放行关闭按钮和链接，其余元素、容器内非标记的 `img`，以及 `[data-fancybox]`/`[data-lightbox]`/`.gallery`/`.wp-block-gallery`/`.attachment-thumbnail`/`.wp-post-image` 上的点击一律 `preventDefault()` + `stopImmediatePropagation()`。这是为了阻止主题的图片灯箱劫持弹窗里的图片，手法比较激进，改动前台交互时需要留意它。

### `travel-map-shortcode-init.js`

负责在多种时机初始化 `[data-travel-map-init="1"]` 元素：`DOMContentLoaded`、`load`、`pageshow`（`persisted` 时先重置容器）、`visibilitychange`，以及 `MutationObserver` 监听后续注入的 DOM（用于 AJAX 加载内容的主题）。

`ensureMapScripts()`（:152）在 `window.AMap` 或 `window.initTravelMap` 缺失时动态注入 script（12 秒超时，`scriptLoaders` Map 去重），`waitForMapApi()`（:182）每 200ms 轮询、最多 12 秒，超时显示错误面板。

## 后台实现

### 页面注册

```
add_menu_page    travel-map              manage_options   → admin_page_settings（:403-411）
add_submenu_page travel-map              manage_options   → admin_page_settings（地图设置，:414-421）
add_submenu_page travel-map-markers      edit_posts       → admin_page_markers（坐标管理，:424-431）
```

`enqueue_admin_scripts($hook)`（:623-）先用 `strpos($hook, 'travel-map')` 过滤，再按 hook 区分设置页与坐标管理页加载对应资源。`travel-map-admin.js`（:655-661）和 `travel-map-admin.css`（:663-668）在所有插件页面无条件加载，不在任何页面条件分支内。

### 坐标管理页

模板 `coordinates-list.php`，逻辑在 `travel-map-coordinates.js`：`initMapPicker()` 建可拖拽标记的选点地图，`loadCoordinatesList()` 通过 `travel_map_get_markers` 拉数据后 `renderMarkersList()` 渲染表格，状态切换时联动显示 `#visit-date-row` / `#planned-date-row` / `#wish-reason-row`，`window.editMarker(id)` 通过 `travel_map_get_marker` 回填表单，`#delete-marker` / `#reset-form` / `#reset-form-button` 均已绑定 click（`assets/js/travel-map-coordinates.js:97、103、108`）。1.1.0 的 `country` / `years` / `cover_image` / `type` 字段在该页可编辑，封面图选择器依赖 `wp_enqueue_media()`。

**技术债**：

- `travel_map_bulk_status`（批量改状态）有完整后端实现，但现用页面没有触发入口，只有已废弃的 `travel-map-admin.js` 里的 `bulkStatusChange()` 会调用它。

### 已废弃的后台代码

- `travel-map-admin.js`（654 行）中的 `BulkOperations`、`ImportExport` 类以及 `#marker_status`、`.travel-map-marker-form`、`.travel-map-add-marker-btn[data-focus-target]`、`.travel-map-sidebar`、`.status-fields` 等选择器全部指向已删除的 `templates/admin-markers.php` 的 DOM，在现用页面里匹配不到。其中 `bulkExport()` 请求的 `travel_map_bulk_export` action 后端根本没注册（`assets/js/travel-map-admin.js:500`）。
- PHP 侧的 `handle_marker_action()` / `handle_add_marker()` / `handle_edit_marker()` / `handle_delete_marker()`（`travel-map.php:2130`、:2153、:2190、:2232，走 POST 表单 + `travel_map_marker` nonce）仍然保留：`admin_page_markers()` 里确有 `if (isset($_POST['action'])) { $this->handle_marker_action($_POST); }`（:454-456），但现用页面（`templates/coordinates-list.php:141`、:345）的表单全部由 AJAX 驱动、没有 `action` 字段，所以这条路径没有 UI 可触发。

### 文章 Meta Box

`add_travel_map_meta_box()` 遍历 `apply_filters('travel_map_post_types', array('post', 'page'))` 注册，位置 `normal`/`default`。渲染时在页头加载高德 API（`$in_footer = false`，确保 Meta Box 脚本执行时 `AMap` 已可用）。

`save_travel_map_meta()`（`travel-map.php:1508-1544`）的顺序是：校验 `travel_map_meta_nonce` → 校验 `edit_post` 能力 → 跳过 `DOING_AUTOSAVE` → `update_post_marker_associations()` 处理勾选项 → 若填了新地点的名称与坐标则 `save_marker()` 并把 `post_id` 设为当前文章。

## 导入导出

导出 `ajax_export()`（`travel-map.php:1329`）：从 `$_GET` 读 `format`（csv/json）、`status`、`marker_ids`，`get_export_data()` 取数据后直接 `header()` + 输出 + `exit`。CSV 用 `fputcsv` 写 16 列固定表头（`ID`、`Title`、`Latitude`、`Longitude`、`Status`、`Description`、`Visit Date`、`Visit Count`、`Planned Date`、`Wish Reason`、`Priority Level`、`Country`、`Years`、`Cover Image`、`Type`、`Created At`，`export_csv()` :1958-2001），JSON 用 `json_encode` 带 `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`（`export_json()` :2006-2014）。坐标管理页的导出按钮写死 `format: 'csv'`（`assets/js/travel-map-coordinates.js:510-518`），JSON 只能手动构造带 `format=json` 的请求取得。

导入 `ajax_import()`（:1348）：按 `pathinfo()` 得到的扩展名分派到 `import_csv()` 或 `import_json()`。CSV 跳过首行，按固定列序读取（索引 1–14 对应 title 到 type，要求至少 4 列，`import_csv()` :2019-2062）；JSON 要求每项含 `title`、`latitude`、`longitude`（`import_json()` :2081-2125）。两者都用 `is_valid_status()` 校验并把 `status` 归一，非法值回退为 `done`。前端开启 WGS-84 纠偏时，行数据由 `AMap.convertFrom` 转换后走 `ajax_import_rows()`（:1385）按行入库，不再读文件。

**已知问题**：

- 只按文件扩展名判断类型，没有 `wp_handle_upload()`、没有 MIME 校验、没有 `is_uploaded_file()`，直接读取 `$_FILES['import_file']['tmp_name']`（:1359）。
- 导入的经纬度只做 `floatval()`，不校验 -90/90 与 -180/180 范围。
- 导入不做去重，重复导入同一文件会产生重复地点。
- 前台导入对话框的 `accept` 属性包含 `.geojson`（`templates/coordinates-list.php:349`），但后端只认 `csv`/`json`（:1364-1370），选择 GeoJSON 文件会得到「不支持的文件格式」。

## 安全实现

**输入清理**：`sanitize_text_field()`（标题、状态、日期、分类）、`sanitize_textarea_field()`（描述、想去理由）、`floatval()`（经纬度）、`intval()`（ID、次数、优先级）、`sanitize_country()` / `sanitize_years()`（国别、年份，`travel-map.php:149`、:201）、`esc_url_raw()`（封面图）。1.0.x 的 `sanitize_hex_color()` 已随三色设置一并移除，代码中不再出现。

**SQL**：所有含变量的查询都走 `$wpdb->prepare()`，`IN` 子句用 `array_fill` 生成占位符，`LIKE` 用 `$wpdb->esc_like()`。表名来自 `$wpdb->prefix` 拼接，不来自用户输入。

**输出转义**：PHP 模板用 `esc_attr()` / `esc_url()` / `esc_html()` / `_e()`；JS 用前述 `escapeHtml()` + `safeUrl()`。

**待修复项**（按优先级）：

1. **`ajax_export()` 缺 capability 检查**（1.1.0 仍存在）——`travel-map.php:1330` 只校验 nonce，没有 `current_user_can()`。任何登录用户（含订阅者）凭有效 nonce 即可导出全部地点数据。
2. **`ajax_get_marker()` 缺 capability 检查**（1.1.0 仍存在）——`travel-map.php:1212` 同样只有 nonce 校验，可逐条读取任意标记的完整记录。
3. **导入未走 `wp_handle_upload()`**（1.1.0 仍存在）——直接读 `$_FILES['import_file']['tmp_name']`（`travel-map.php:1359`），仅凭扩展名判断类型，没有 MIME 校验与 `is_uploaded_file()`。
4. **导入不校验经纬度范围、也不去重**（1.1.0 仍存在）——超出 ±90/±180 的值同样入库，重复导入产生重复地点。
5. **`uninstall()` 未清理 transient**（1.1.0 仍存在）——`travel-map.php:1565-1585` 中没有任何 `delete_transient()` 调用。
6. **关联表缺 `UNIQUE(post_id, marker_id)` 约束**（1.1.0 仍存在）——`travel-map.php:1626-1634`。

导入/导出要分开看待：导入侧的 `ajax_import()`（`travel-map.php:1351`）与 `ajax_import_rows()`（:1388）已经补上 `current_user_can('edit_posts')`，缺 capability 的只有导出侧的 `ajax_export()` 与只读单条的 `ajax_get_marker()`。

## 国际化

`load_plugin_textdomain()` 在 `init` 时加载，域名为 `travel-map`（`travel-map.php:113`）。

**已知问题**：代码里统一使用 `__('文本', TRAVEL_MAP_TEXT_DOMAIN)` 这种常量形式，而 WordPress 的 i18n 提取工具（`wp i18n make-pot`、GlotPress）要求 text domain 必须是字面量字符串，因此无法自动提取（1.1.0 仍存在）。`languages/travel-map.pot` 已经重新生成到 735 行、`Project-Id-Version: Travel Map 1.1.0`（`languages/travel-map.pot:9`），但仍是手工维护的成果，文案增删后需要同步更新。若要正式支持多语言，需要把全部常量替换为 `'travel-map'` 字面量后重新生成 POT。

另外前台 JS 里仍有硬编码中文（如 `assets/js/travel-map.js:174` 的「正在加载地图...」、`STATUS_LABELS` 里的状态标签、弹窗里的「计划日期：」「想去理由：」「该地点暂无游记。」），未纳入 i18n 体系。

## 扩展点

目前只有一个过滤器（`travel-map.php:1415`）：

```php
apply_filters('travel_map_post_types', array('post', 'page'))
```

以及一个模板函数（`travel-map.php:2336-2342`）：

```php
travel_map_enqueue_assets()
```

`README` 早期版本曾列出 `travel_map_before_render`、`travel_map_after_render`、`travel_map_marker_added`、`travel_map_config`、`travel_map_marker_content`、`travel_map_shortcode_atts` 等钩子，这些在代码中均不存在，文档已更正。若需要扩展点，建议优先在 `render_map_shortcode()` 的属性解析处和 `save_marker()` 之后补充。

## SEO

`render_map_shortcode()` 首次调用时通过 `wp_footer` 输出一段 `schema.org` 的 `Map` 类型 JSON-LD（`static $schema_added` 保证单页只输出一次）。`map-shortcode.php` 底部有一个 `.travel-map-accessibility` 区块，用视觉隐藏样式承载键盘操作说明，同时兼作前述样式生效检测的探针。

## 改进优先级建议

**安全**

1. 给 `ajax_export()` 补 `current_user_can('edit_posts')`（1.1.0 仍存在，`travel-map.php:1330`）、给 `ajax_get_marker()` 补 capability 检查（1.1.0 仍存在，:1212）；导入侧（:1351、:1388）已经补过。
2. 导入改用 `wp_handle_upload()` 并校验经纬度范围（1.1.0 仍存在，:1359），同时补去重。

**正确性**

3. 让 `is_featured` / `marker_color` 真正生效，或连同 `travel-map-admin.js:265-266` 的表单回填一起移除（1.1.0 仍存在，列定义 `travel-map.php:1607`、:1611）。
4. 实现 GeoJSON 导入，或移除 `templates/coordinates-list.php:349` 里 `.geojson` 的提示（1.1.0 仍存在）。
5. 给 `travel_map_post_markers` 补 `UNIQUE(post_id, marker_id)`（1.1.0 仍存在，`travel-map.php:1626-1634`）。

**清理**

6. 移除或改造 `travel-map-admin.js`（654 行）中的失效逻辑：它无条件加载（`travel-map.php:655-668`），但内部选择器指向已删除的 `admin-markers.php`，`bulkExport()` 请求的 `travel_map_bulk_export` 后端没有注册。
7. 删除 PHP 侧的 `handle_*_marker()` 表单路径（1.1.0 仍存在，`travel-map.php:2130-2255`）：现用页面表单全部走 AJAX 且没有 `action` 字段，`admin_page_markers()` 的 `isset($_POST['action'])` 分支无 UI 可触发。
8. 把三份关键 CSS 副本合并为单一来源（1.1.0 仍存在：`travel-map.php:564`、`assets/js/travel-map-shortcode-init.js:64`、`assets/css/travel-map.css`）。
9. 评估 `preventThemeConflicts()`（1.1.0 仍存在，`assets/js/travel-map.js:1266-1316`）的激进拦截是否还有必要。

**性能**

10. 批量预加载特色图，消除 `get_marker_featured_image()` 的 N+1（1.1.0 仍存在，`travel-map.php:1789-1794`、:1806-1836）。
11. 缓存失效挂上 `save_post` / `updated_post_meta`（1.1.0 仍存在，`clear_markers_cache()` 只在插件自身的写路径被调用）。

**其他**

12. i18n 常量改字面量并重新生成 POT（1.1.0 仍存在）。
13. 补 `readme.txt` 与 `LICENSE`（1.1.0 仍存在，仓库里目前只有 `README.md` 等 .md 文档）。
