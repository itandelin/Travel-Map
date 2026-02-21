# WordPress Travel Map Plugin

一个基于高德地图API的轻量级WordPress旅行博客地图插件，支持已去、想去、计划三种旅行状态标记。

## ✨ 功能特性

- 🗺️ **交互式世界地图** - 基于高德地图API的流畅地图体验
- 📍 **三种旅行状态** - 支持"已去"、"想去"、"计划"三种状态标记
- 📝 **文章关联** - 地点标记可关联相关旅行文章
- 🎨 **简洁设计** - 现代化的界面设计，完美适配各种主题
- 📱 **响应式布局** - 完美支持桌面端和移动端
- ⚡ **轻量高效** - 最小化的技术栈，快速加载
- 🔧 **易于配置** - 简单的后台设置，即插即用

## 📋 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- 高德地图 Web 服务 API 密钥

## 🚀 安装方法

### 手动安装

1. 下载插件压缩包
2. 解压到 `wp-content/plugins/` 目录
3. 在 WordPress 后台激活插件

## 🎯 快速开始

### 1. 获取高德地图 API 密钥

1. 访问 [高德开放平台](https://lbs.amap.com/)
2. 注册账号并创建应用
3. 获取 Web 服务 API 密钥

### 2. 配置插件

1. 在 WordPress 后台访问 **Travel Map > 地图设置**
2. 输入高德地图 API 密钥
3. 配置默认地图中心点和缩放级别
4. 保存设置

### 3. 添加地点标记

1. 访问 **Travel Map > 坐标管理**
2. 在右侧表单中添加地点信息
3. 在地图上点击选择坐标位置
4. 选择旅行状态（已去/想去/计划）
5. 保存标记

### 4. 在页面中显示地图

在页面或文章中使用短代码：

```
[travel_map]
```

### 4.1 模板用法说明（主题/小工具）

如果短代码不是放在文章/页面正文里，而是放在主题模板或小工具中，建议在渲染短代码前显式加载资源，以确保样式与脚本可用：

```php
<?php
if (function_exists('travel_map_enqueue_assets')) {
    travel_map_enqueue_assets();
}
?>
```

然后输出短代码即可：

```php
<?php echo do_shortcode('[travel_map]'); ?>
```

## 📖 短代码参数

| 参数名 | 默认值 | 描述 |
|--------|--------|------|
| `width` | '100%' | 地图宽度 |
| `height` | '500px' | 地图高度 |
| `zoom` | 4 | 缩放级别 (1-18) |
| `center` | '35.0,105.0' | 地图中心点坐标 |
| `filter_tabs` | true | 是否显示筛选标签 |
| `status` | 'all' | 显示的状态 (all/visited/want_to_go/planned) |

### 示例用法

```
[travel_map width="100%" height="600px" zoom="5"]
```

只显示已去过的地点：
```
[travel_map status="visited"]
```

隐藏筛选标签：
```
[travel_map filter_tabs="false"]
```

## 🏷️ 标记状态说明

### 已去 (visited)
- 🟠 橙色标记
- 显示访问次数
- 可关联相关文章
- 点击显示文章详情弹窗

### 想去 (want_to_go)
- 🔵 蓝色标记
- 可添加想去理由
- 支持优先级设置
- 点击显示简洁信息弹窗

### 计划 (planned)
- 🟢 绿色标记
- 可设置计划日期
- 支持计划状态跟踪
- 点击显示计划信息弹窗

## 🛠️ 管理功能

### 坐标管理
- 添加、编辑、删除地点标记
- 批量操作支持
- 地图可视化选点
- 文章关联管理

### 数据导入导出
- 支持 CSV 格式导入导出
- 支持 JSON 格式导入导出
- 支持 GeoJSON 格式导出
- 批量数据处理

### 权限控制
- 基于 WordPress 用户角色
- 细粒度权限设置
- 多用户协作支持

## ❓ 常见问题

### Q: 地图不显示怎么办？
A: 请检查以下几点：
1. 确认 API 密钥配置正确
2. 检查网络连接是否正常
3. 确认浏览器支持 JavaScript

### Q: 如何更改标记颜色？
A: 在 **Travel Map > 地图设置** 中可以自定义各状态的默认颜色。

### Q: 支持其他地图服务吗？
A: 当前版本仅支持高德地图，后续版本将考虑支持更多地图服务。

### Q: 如何备份地图数据？
A: 可以通过 **坐标管理** 页面的导出功能备份数据。

## 👨‍💻 开发者信息

### 钩子支持

#### 动作钩子 (Actions)
- `travel_map_before_render` - 地图渲染前
- `travel_map_after_render` - 地图渲染后
- `travel_map_marker_added` - 标记添加后

#### 过滤器钩子 (Filters)
- `travel_map_config` - 修改地图配置
- `travel_map_marker_content` - 自定义标记内容
- `travel_map_shortcode_atts` - 短代码属性过滤

### 自定义开发

```php
// 自定义地图配置
add_filter('travel_map_config', function($config) {
    $config['theme'] = 'dark'; // 使用暗色主题
    return $config;
});

// 自定义标记内容
add_filter('travel_map_marker_content', function($content, $post_id) {
    // 添加自定义内容
    return $content;
}, 10, 2);
```

## 📝 更新日志

### 版本 1.0.0
- 首次发布
- 基础地图功能
- 三种旅行状态支持
- 管理后台界面
- 短代码支持

## 💬 支持和反馈

如果您在使用过程中遇到问题或有任何建议，请：

1. 查看 [常见问题](#-常见问题) 部分
2. 提交 [Issue](https://github.com/itandelin/Travel-Map/issues)

## 📄 许可证

本插件基于 GPL v2 或更高版本许可证发布。详情请查看 [LICENSE](LICENSE) 文件。

## 🙏 致谢

感谢以下开源项目和服务：

- [WordPress](https://wordpress.org/) - 强大的内容管理系统
- [高德地图](https://lbs.amap.com/) - 优秀的地图服务
- [GitHub](https://github.com/) - 代码托管平台

---

**开发者**: Mr. T
**版本**: 1.0.0  
**兼容性**: WordPress 5.0+, PHP 7.4+

## 🌟 Star History

如果这个项目对您有帮助，请给我们一个 ⭐ Star！

[![Star History Chart](https://api.star-history.com/svg?repos=itandelin/Travel-Map&type=Date)](https://star-history.com/#itandelin/Travel-Map&Date)
