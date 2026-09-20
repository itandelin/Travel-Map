<?php
/**
 * Travel Map 数据库版本化迁移
 *
 * 1.0.1 → 1.1.0：
 *  - status 枚举 visited/want_to_go/planned → done/wish/plan
 *  - 新增列 country / years / cover_image / type
 *  - 清理废弃选项（三色设置）
 *
 * @package TravelMap
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class TravelMapMigrator {

    const DB_VERSION = '1.1.0';

    /**
     * 入口：版本号低于目标或表结构缺列时执行，幂等
     */
    public static function maybe_migrate() {
        $current = get_option('travel_map_db_version', '1.0.0');
        if (version_compare($current, self::DB_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'travel_map_markers';

        // 表可能尚未创建（插件激活前调用了 admin_init）
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }

        // 1. 状态枚举三步迁移：先扩宽（容纳新旧值）→ 改数据 → 再收窄
        self::migrate_status_enum($table);

        // 2. 新增列（幂等：逐列检查）
        self::add_missing_columns($table);

        // 3. 清理废弃选项、补默认设置
        self::cleanup_options();

        update_option('travel_map_db_version', self::DB_VERSION);
    }

    /**
     * status 枚举迁移。
     *
     * 直接 MODIFY 成窄枚举时，存量 'visited' 等值在严格模式下会让 ALTER 失败，
     * 所以先扩成包含全部六值的宽枚举，UPDATE 完成后再收窄。
     */
    private static function migrate_status_enum($table) {
        global $wpdb;

        $column = $wpdb->get_row(
            $wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' WHERE Field = %s', 'status')
        );
        if (!$column) {
            return;
        }

        // 已是目标枚举则跳过（幂等）
        if (strpos($column->Type, "'done'") !== false && strpos($column->Type, "'visited'") === false) {
            return;
        }

        $wpdb->query(
            "ALTER TABLE $table MODIFY status enum('visited','want_to_go','planned','done','wish','plan') DEFAULT 'done'"
        );
        $wpdb->query("UPDATE $table SET status = 'done' WHERE status = 'visited'");
        $wpdb->query("UPDATE $table SET status = 'wish' WHERE status = 'want_to_go'");
        $wpdb->query("UPDATE $table SET status = 'plan' WHERE status = 'planned'");
        $wpdb->query(
            "ALTER TABLE $table MODIFY status enum('done','wish','plan') DEFAULT 'done'"
        );
    }

    /**
     * 补齐 1.1.0 新增列
     */
    private static function add_missing_columns($table) {
        global $wpdb;

        $columns = array(
            'country'     => "ADD COLUMN country varchar(20) NULL",
            'years'       => "ADD COLUMN years text NULL",
            'cover_image' => "ADD COLUMN cover_image text NULL",
            'type'        => "ADD COLUMN type varchar(30) NULL DEFAULT ''",
        );

        $existing = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $wpdb->query("ALTER TABLE $table $definition");
            }
        }
    }

    /**
     * 清理废弃选项并写入新默认设置
     */
    private static function cleanup_options() {
        // 颜色由状态硬编码渐变，三色设置废弃
        delete_option('travel_map_visited_color');
        delete_option('travel_map_want_to_go_color');
        delete_option('travel_map_planned_color');

        $defaults = self::default_settings();
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * 新设置项默认值（1.1.0 起，含后续新增项）
     *
     * 同时作为安装时播种与卸载时清理的键清单，新增设置项必须登记在此。
     */
    public static function default_settings() {
        return array(
            'travel_map_cluster_radius'     => 40,
            'travel_map_cluster_limit'      => 9,
            'travel_map_auto_zoom'          => 1,
            'travel_map_highlight_country'  => 1,
            'travel_map_show_yearly_stats'  => 1,
            'travel_map_hide_yearly_stats_mobile' => 0,
            'travel_map_show_type_stats'    => 1,
            'travel_map_default_filter_status' => 'all',
            'travel_map_default_cover'      => '',
            'travel_map_min_zoom'           => 1,
            'travel_map_max_zoom'           => 12,
        );
    }
}
