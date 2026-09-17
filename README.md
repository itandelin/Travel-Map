# WordPress Travel Map

基于高德地图 JS API 的轻量级 WordPress 旅行地图插件。把去过、想去、计划中的地点标在一张交互地图上，「已去」的地点还能关联旅行文章，访客点击标记即可看到文章列表。

- 版本：1.1.0
- 作者：Mr. T
- 仓库：https://github.com/itandelin/Travel-Map
- 许可：GPL v2 or later

## 功能特性

> 1.1.0 起重写了前端渲染层：标记六形态、点聚合、弹窗、国家高亮、统计面板。

- 交互式世界地图，基于高德地图 JS API 2.0（light 底图 + 中文标注）
- 标记六形态：已去橙红渐变圆点 / 有图圆角方照片 / 无游记蓝灰点 / 想去黄橙小点 / 计划紫色小点 / 聚合数字气泡；悬停时小圆点膨胀为 64px 照片
- 点聚合（AMap.MarkerCluster）：数字气泡封顶可设，点击聚合逐级放大展开，缩放自动重算
- 弹窗：封面图 + 国旗 + 地点名 + 游记链接列表 + 到访年份胶囊；×/Esc/点地图三种关闭方式
- 已去国家整片填绿（AMap.DistrictLayer.World，需标记填写国别 ISO 代码）
- 南海十段线双层叠加
- 视野自适应（auto_zoom）：加载与筛选后自动包含全部标记；右上「返回」按钮一键回总览
- 前台筛选胶囊（全部/已去/想去/计划）：数量为 0 的按钮自动隐藏、仅一个状态有数据时整栏隐藏
- 双统计面板：右下状态统计（已去/想去/计划/总计）、左下年度统计（含国家数），随筛选联动
- 三种地点状态：已去（done）、想去（wish）、计划（plan）——1.0.x 的 visited/want_to_go/planned 自动迁移
- 地点支持国别（ISO 代码，多国逗号分隔）、多年份、手动封面图、分类（type）
- 文章编辑页 Meta Box：勾选已有地点、按名称搜索地点自动回填坐标、小地图新建
- 后台坐标管理：地图点选、状态筛选、关键词搜索、批量删除、CSV/JSON 导入与 CSV 导出（含新字段）
- 新增公开 REST 端点：`travel-map/v1/geojson`（全量 FeatureCollection）；旧端点保留兼容
- 短代码族：`[travel_map]`、`[travel_map_countries]`（去过国家国旗墙）、`[travel_map_markers]`（标记归档列表）
- 地图样式跟随站点深浅色模式自动切换
- 前端脚本无 jQuery 依赖，仅在页面出现短代码时才加载资源

## 系统要求

- WordPress 5.0+（已测试至 6.4）
- PHP 7.4+
- MySQL 5.7+
- 高德地图 Web 端（JS API）Key 与安全密钥

## 安装

1. 下载仓库压缩包，解压后将 `travel-map` 目录放到 `wp-content/plugins/`
2. 在「插件」页面启用 WordPress Travel Map
3. 启用时会自动创建 `wp_travel_map_markers` 和 `wp_travel_map_post_markers` 两张表

## 快速开始

### 1. 申请高德地图密钥

在[高德开放平台](https://lbs.amap.com/)创建应用，添加一个**服务平台为「Web端(JS API)」**的 Key，同时在 Key 详情里获取**安全密钥（securityJsCode）**。2021 年 12 月 2 日之后申请的 Key 必须配合安全密钥使用。

建议在高德控制台为 Key 绑定域名白名单：安全密钥会以内联脚本形式输出到前台页面源码，白名单是防止配额被盗用的主要手段。

### 2. 配置插件

进入 **Travel Map → 地图设置**，填写 API 密钥与安全密钥，按需调整默认缩放级别、默认中心点、筛选标签开关，以及聚合、国家高亮、统计面板等地图行为选项，保存。标记颜色由状态自动决定，设置页不再提供颜色配置（1.0.x 的三色设置已移除）。

### 3. 添加地点

进入 **Travel Map → 坐标管理**。右侧地图点选位置（或手动填经纬度），填写地点名称、选择状态，保存。不同状态会显示不同的补充字段：已去对应访问日期，计划对应计划日期，想去对应想去理由。

### 4. 在页面中显示地图

在文章或页面正文里插入短代码：

```
[travel_map]
```

### 5. 在主题模板中使用

短代码放在正文之外（主题模板、小工具）时，插件的按需加载检测不到它，需要先手动加载资源：

```php
<?php
if (function_exists('travel_map_enqueue_assets')) {
    travel_map_enqueue_assets();
}
echo do_shortcode('[travel_map]');
```

## 短代码参数

### [travel_map]

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `width` | `100%` | 地图容器宽度，直接写入 CSS |
| `height` | `550px` | 地图容器高度（桌面端；移动端按容器宽度自动换算竖版高度） |
| `zoom` | 设置页的默认缩放级别（默认 4） | 初始缩放级别 1–18 |
| `center` | 设置页的默认中心点（默认 `35.0,105.0`） | 格式为 `纬度,经度` |
| `filter_tabs` | 设置页的开关（默认 `true`） | 是否显示状态筛选胶囊 |
| `status` | `all` | 缺省筛选状态，取值 `all`/`done`/`wish`/`plan`（旧值 `visited` 等仍被接受并自动映射） |
| `filters` | `all,done,wish,plan` | 筛选按钮集，逗号分隔，可裁剪 |
| `auto_zoom` | 设置页的自适应缩放开关 | `true`/`false`，覆盖设置项 |

示例：

```
[travel_map height="700px" zoom="3"]
[travel_map status="done" filter_tabs="false"]
[travel_map filters="all,done" auto_zoom="false"]
```

### [travel_map_countries]

输出去过国家国旗墙（国旗 + ISO 码 + 地点数），数据来自「已去」且填写了国别字段的地点。无数据时输出提示文案。

### [travel_map_markers]

输出标记归档卡片列表：国旗 + 地名 + 坐标 + 到访年份 + 游记封面网格（3/2/1 列响应式）。

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `status` | `done` | `done`/`wish`/`plan`/`all` |
| `type` | 空 | 按标记分类过滤 |

## 状态说明

| 状态 | 标记形态 | 专属字段 | 弹窗内容 |
|------|----------|----------|--------------|
| 已去 `done` | 橙红渐变圆点；有图时为圆角方照片；无游记时为蓝灰点 | 访问日期、到访年份、国别、封面图 | 游记链接列表；无游记显示「该地点暂无游记。」 |
| 想去 `wish` | 12px 黄橙小点 | 想去理由 | 想去理由 |
| 计划 `plan` | 12px 紫色小点 | 计划日期 | 计划日期（未填显示「未定」） |

悬停有图标记会从小圆点膨胀为 64px 照片（0.2s/0.5s 过渡）。1.0.x 升级后 `visited/want_to_go/planned` 数据自动迁移为新键。

## 后台功能

**地图设置**（需要 `manage_options`）：API 密钥、安全密钥、默认缩放、默认中心点、筛选胶囊开关，以及地图行为设置：聚合半径、聚合数字上限、自适应缩放、国家高亮、年度/状态统计面板、缺省筛选状态、缺省封面图、最小/最大缩放级别。1.0.x 的三色设置已移除（标记颜色由状态决定）。

**坐标管理**（需要 `edit_posts`）：左侧地点列表支持按状态筛选和关键词搜索（搜索范围含地点名称、描述、关联文章标题），可勾选批量删除，列表含国别列（国旗 + ISO 码）；右侧是地图选点和地点表单（含国别、到访年份、封面图媒体库选择、分类字段）。列表顶部有导入、导出按钮。

**文章 Meta Box**：默认出现在文章和页面的编辑页，可通过 `travel_map_post_types` 过滤器调整。支持勾选已有地点建立关联、按名称搜索地点（高德 Autocomplete）自动回填坐标，或在小地图上点选坐标直接新建地点并自动关联到当前文章。

**导入导出**：导出按钮下载 CSV（含国别/年份/封面/分类新字段），后端 `ajax_export` 同样支持 `format=json`，但界面未提供入口；导入支持 CSV 与 JSON（列顺序需与导出一致），旧状态值自动映射。导入弹窗提供 **WGS-84 坐标纠偏** 选项（默认关闭）：数据来自 Google Maps 等国际地图时勾选，境内坐标（经 73-136 / 纬 18-54）会经 `AMap.convertFrom` 在浏览器端批量转换为高德 GCJ-02 坐标系，海外坐标保持不变。

## 权限

| 操作 | 所需能力 |
|------|----------|
| 查看前台地图 | 无（公开） |
| 打开地图设置页 | `manage_options` |
| 打开坐标管理页 | `edit_posts` |
| 新增 / 编辑地点、批量改状态、导入 | `edit_posts` |
| 删除地点、批量删除 | `delete_posts` |
| 编辑文章的地点关联 | `edit_post`（针对该文章） |

## 开发者接口

### 过滤器

```php
// 调整显示 Meta Box 的文章类型，默认 array('post', 'page')
add_filter('travel_map_post_types', function ($post_types) {
    $post_types[] = 'my_custom_type';
    return $post_types;
});
```

这是插件目前唯一提供的钩子。

### 模板函数

```php
travel_map_enqueue_assets(); // 主动加载前台样式与脚本
```

### 公开 REST 接口（只读）

```
GET /wp-json/travel-map/v1/geojson
GET /wp-json/travel-map/v1/markers?status=all&search=
GET /wp-json/travel-map/v1/location-posts?latitude=&longitude=&location_name=
```

`geojson` 是 1.1.0 新增的主数据端点，返回标准 FeatureCollection（properties 含 `title`/`status`/`country`/`type`/`image[]`/`posts[]`/`year[]`），前端一次拉全量后本地筛选。`markers` 与 `location-posts` 为 1.0.x 兼容端点，保留至 1.2.0。

## 数据表

**`{prefix}travel_map_markers`** — 地点主表：`id`、`post_id`、`title`、`latitude`、`longitude`、`status`（enum done/wish/plan）、`visit_date`、`visit_count`、`description`、`marker_color`（保留字段，前台不再消费）、`planned_date`、`wish_reason`、`priority_level`、`is_featured`、`country`、`years`、`cover_image`、`type`、`created_at`、`updated_at`。

**`{prefix}travel_map_post_markers`** — 文章与地点的多对多关联表：`id`、`post_id`、`marker_id`、`created_at`。

从 1.0.x 升级时自动执行数据库迁移（幂等）：状态枚举 `visited/want_to_go/planned` → `done/wish/plan`，新增国别/年份/封面/分类四列。REST/AJAX 入参的旧状态值在 1.1.x 仍被接受并自动映射。

卸载插件会删除这两张表及全部数据，请提前用导出功能备份。

## 常见问题

**地图不显示？** 依次确认：设置页填了 API 密钥和安全密钥；密钥的服务平台是「Web端(JS API)」而不是「Web服务」；高德控制台的域名白名单包含当前站点；浏览器控制台没有报错。

**境外区域底图粗略或放大后无细节？** 高德的境外详细路网/POI 属「世界地图」高级能力，需要在高德控制台提交工单为 Key 开通「世界地图」权限（个人认证可尝试申请，企业认证范围更广）。未开通不影响国家高亮、聚合、弹窗、统计等核心功能，仅境外深缩放精细度受限。

**国家没有变绿？** 国家高亮只统计「已去」（done）状态的标记，且需要地点填写了国别字段（ISO 代码，如 `CN`、`US`，多国逗号分隔）。改完数据后最多等 5 分钟缓存刷新。

**主题里地图区域样式错乱？** 插件会在短代码渲染时兜底输出样式，但如果主题没有正确调用 `wp_head()`/`wp_footer()`，最稳妥的做法是在模板里先调用 `travel_map_enqueue_assets()`。

**改了文章特色图，地图上没变？** 标记数据有 5 分钟缓存。通过 Meta Box 保存文章会立刻清缓存，单独换特色图则最多等 5 分钟。

**支持其他地图服务吗？** 目前只支持高德地图。

**怎么备份数据？** 坐标管理页的导出按钮可导出 CSV（接口亦支持 JSON 格式），或直接备份上面两张数据表。

## 更新日志

### 1.1.0
- 前端渲染层重写：标记六形态（含悬停 64px 照片揭示）、点聚合、弹窗、国家高亮、双统计面板
- 短代码新增 `[travel_map_countries]`（国旗墙）与 `[travel_map_markers]`（标记归档列表）
- 新增公开只读端点 `travel-map/v1/geojson`，返回标准 FeatureCollection 并缓存 5 分钟
- 数据库版本化迁移
- 内置 270 面 ISO 国旗/地区旗 SVG（flag-icons v7.2.3，MIT），支持国别字段与国旗墙
- 坐标管理支持 CSV/JSON 导入与 CSV 导出，导入弹窗提供 WGS-84 坐标纠偏选项
- 筛选按钮四色区分；后台设置新增聚合、国家高亮、统计面板、缩放上下限等选项，移除 1.0.x 的三色设置
- 修复弹窗标题继承主题浅色不可见、气泡压住标记与出界、点击标记视野异常等问题

### 1.0.1
- 新增公开只读 REST 接口，AJAX 被拦截时自动降级
- 加强标记管理与前端输出转义
- 优化移动端弹窗手势、弹窗动画与响应式样式
- 短代码用法说明补充，标记悬停效果调整

### 1.0.0
- 首次发布：地图展示、三种旅行状态、后台管理、短代码

## 致谢

- [WordPress](https://wordpress.org/)
- [高德开放平台](https://lbs.amap.com/)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=itandelin/Travel-Map&type=Date)](https://star-history.com/#itandelin/Travel-Map&Date)
