/**
 * Travel Map Admin JavaScript
 * 管理员界面交互脚本
 */

(function($) {
    'use strict';

    class TravelMapAdmin {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initMapPicker();
            this.initApiKeyValidator();
        }
        
        bindEvents() {
            // 表单提交
            $(document).on('submit', '.travel-map-marker-form', (e) => {
                this.handleMarkerSubmit(e);
            });
            
            // 删除标记
            $(document).on('click', '.travel-map-delete-btn', (e) => {
                this.handleMarkerDelete(e);
            });
            
            // 编辑标记
            $(document).on('click', '.travel-map-edit-btn', (e) => {
                this.handleMarkerEdit(e);
            });
            
            // API密钥验证
            $(document).on('blur', '#travel_map_api_key', () => {
                this.validateApiKey();
            });
            
            // 坐标输入更新地图
            $(document).on('input', '.travel-map-coord-input', () => {
                this.updateMapMarker();
            });
        }
        
        initMapPicker() {
            const $mapPicker = $('.travel-map-map-picker');
            if ($mapPicker.length === 0) return;
            
            const apiKey = $('#travel_map_api_key').val();
            if (!apiKey || !window.AMap) {
                $mapPicker.html('<div style="color: #6b7280; text-align: center;">请先配置API密钥</div>');
                return;
            }
            
            // 创建地图
            this.pickerMap = new AMap.Map($mapPicker[0], {
                zoom: 10,
                center: [116.4074, 39.9042],
                mapStyle: 'amap://styles/light'
            });
            
            // 添加点击事件
            this.pickerMap.on('click', (e) => {
                this.setCoordinates(e.lnglat.lng, e.lnglat.lat);
            });
            
            // 创建标记
            this.pickerMarker = new AMap.Marker({
                position: [116.4074, 39.9042],
                draggable: true
            });
            
            this.pickerMap.add(this.pickerMarker);
            
            // 标记拖拽事件
            this.pickerMarker.on('dragend', (e) => {
                const position = e.target.getPosition();
                this.setCoordinates(position.lng, position.lat);
            });
        }
        
        setCoordinates(lng, lat) {
            $('#marker_longitude').val(lng.toFixed(6));
            $('#marker_latitude').val(lat.toFixed(6));
            
            if (this.pickerMarker) {
                this.pickerMarker.setPosition([lng, lat]);
            }
        }
        
        updateMapMarker() {
            const lng = parseFloat($('#marker_longitude').val());
            const lat = parseFloat($('#marker_latitude').val());
            
            if (!isNaN(lng) && !isNaN(lat) && this.pickerMarker && this.pickerMap) {
                this.pickerMarker.setPosition([lng, lat]);
                this.pickerMap.setCenter([lng, lat]);
            }
        }
        
        initApiKeyValidator() {
            // 等待DOM完全加载后再初始化
            setTimeout(() => {
                this.validateApiKey();
            }, 100);
        }
        
        validateApiKey() {
            const $input = $('#travel_map_api_key');
            
            // 如果输入框不存在，直接返回
            if ($input.length === 0) {
                return;
            }
            
            const $status = $('.travel-map-api-status');
            const apiKey = ($input.val() || '').trim();
            
            if (!apiKey) {
                $status.remove();
                return;
            }
            
            // 显示验证中状态
            $status.remove();
            $input.after('<span class="travel-map-api-status checking">验证中...</span>');
            
            // 模拟API验证（实际应该发送请求到高德API）
            setTimeout(() => {
                $('.travel-map-api-status').remove();
                
                if (apiKey.length > 20) {
                    $input.after('<span class="travel-map-api-status valid">✓ API密钥有效</span>');
                } else {
                    $input.after('<span class="travel-map-api-status invalid">✗ API密钥格式不正确</span>');
                }
            }, 1000);
        }
        
        handleMarkerSubmit(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            // 添加AJAX参数
            formData.append('action', 'travel_map_save_marker');
            formData.append('nonce', window.travelMapAdmin.nonce);
            
            // 调试信息：显示表单数据

            
            // 禁用提交按钮
            const $submitBtn = $form.find('[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('保存中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message || '保存成功');
                        this.refreshMarkersList();
                        $form[0].reset();
                    } else {
                        this.showNotice('error', response.data || '保存失败');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('error', '网络请求失败: ' + xhr.status + ' ' + xhr.statusText);
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        }
        
        handleMarkerDelete(e) {
            e.preventDefault();
            
            const $btn = $(e.target);
            const markerId = $btn.data('marker-id');
            
            if (!confirm('确定要删除这个标记点吗？')) {
                return;
            }
            
            $btn.prop('disabled', true).text('删除中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'travel_map_delete_marker',
                    nonce: window.travelMapAdmin.nonce,
                    marker_id: markerId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message || '删除成功');
                        $btn.closest('tr').fadeOut(() => {
                            $btn.closest('tr').remove();
                        });
                    } else {
                        this.showNotice('error', response.data || '删除失败');
                        $btn.prop('disabled', false).text('删除');
                    }
                },
                error: () => {
                    this.showNotice('error', '网络请求失败');
                    $btn.prop('disabled', false).text('删除');
                }
            });
        }
        
        handleMarkerEdit(e) {
            e.preventDefault();
            
            const $btn = $(e.target);
            const markerId = $btn.data('marker-id');
            
            if (!markerId) {
                this.showNotice('error', '标记点ID不能为空');
                return;
            }
            
            // 获取完整的标记数据
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'travel_map_get_marker',
                    nonce: window.travelMapAdmin.nonce,
                    marker_id: markerId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.fillEditForm(response.data);
                        this.showNotice('success', '已加载编辑数据');
                    } else {
                        this.showNotice('error', response.data || '获取数据失败');
                    }
                },
                error: () => {
                    this.showNotice('error', '网络请求失败');
                }
            });
        }
        
        fillEditForm(markerData) {
            $('#marker-id').val(markerData.id);
            $('#marker-title').val(markerData.title);
            $('#marker-latitude').val(markerData.latitude);
            $('#marker-longitude').val(markerData.longitude);
            $('#marker-status').val(markerData.status).trigger('change'); // 触发改变事件
            $('#marker-description').val(markerData.description || '');
            
            // 填充其他字段
            if (markerData.marker_color) {
                $('#marker-color').val(markerData.marker_color);
            }
            if (markerData.post_id) {
                $('#marker-post').val(markerData.post_id);
            }
            if (markerData.visit_date) {
                $('#marker-visit-date').val(markerData.visit_date);
            }
            if (markerData.planned_date) {
                $('#marker-planned-date').val(markerData.planned_date);
            }
            if (markerData.wish_reason) {
                $('#marker-wish-reason').val(markerData.wish_reason);
            }
            
            // 更新界面文字
            $('#form-title').html('<span class="dashicons dashicons-edit"></span>编辑地点');
            $('#submit-text').text('更新坐标');
            $('#delete-marker').show();
            $('#reset-form').show();
            
            // 更新地图标记（如果有的话）
            if (typeof this.updateMapMarker === 'function') {
                this.updateMapMarker();
            }
            
            // 滚动到表单
            const $sidebar = $('.travel-map-sidebar');
            if ($sidebar.length > 0) {
                $('html, body').animate({
                    scrollTop: $sidebar.offset().top - 50
                }, 500);
            } else {
                // 如果找不到sidebar，尝试滚动到表单区域
                const $form = $('#coordinate-form');
                if ($form.length > 0) {
                    $('html, body').animate({
                        scrollTop: $form.offset().top - 50
                    }, 500);
                }
            }
        }
        
        refreshMarkersList() {
            // 重新加载标记列表
            location.reload();
        }
        
        showNotice(type, message) {
            // 移除现有通知
            $('.travel-map-notice').remove();
            
            // 创建新通知
            const $notice = $(`
                <div class="travel-map-notice ${type}">
                    <span>${message}</span>
                </div>
            `);
            
            // 插入到页面顶部
            $('.travel-map-admin-page').prepend($notice);
            
            // 自动移除
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 5000);
        }
        
        showModal(title, content, actions) {
            const actionsHtml = actions || `
                <button type="button" class="travel-map-btn travel-map-btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" class="travel-map-btn travel-map-btn-primary">确定</button>
            `;
            
            const modalHtml = `
                <div class="travel-map-modal">
                    <div class="travel-map-modal-content">
                        <div class="travel-map-modal-header">
                            <h3 class="travel-map-modal-title">${title}</h3>
                            <button type="button" class="travel-map-modal-close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="travel-map-modal-body">
                            ${content}
                        </div>
                        <div class="travel-map-modal-footer">
                            ${actionsHtml}
                        </div>
                    </div>
                </div>
            `;
            
            const $modal = $(modalHtml);
            $('body').append($modal);
            
            setTimeout(() => {
                $modal.addClass('show');
            }, 10);
            
            // 绑定关闭事件
            $modal.on('click', '[data-dismiss="modal"], .travel-map-modal', function(e) {
                if (e.target === this) {
                    $modal.removeClass('show');
                    setTimeout(() => {
                        $modal.remove();
                    }, 300);
                }
            });
            
            return $modal;
        }
    }

    // 批量操作类
    class BulkOperations {
        constructor() {
            this.selectedItems = [];
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            // 全选
            $(document).on('change', '#select-all-markers', (e) => {
                const checked = $(e.target).is(':checked');
                $('.marker-checkbox').prop('checked', checked);
                this.updateSelectedItems();
            });
            
            // 单选
            $(document).on('change', '.marker-checkbox', () => {
                this.updateSelectedItems();
            });
            
            // 批量操作
            $(document).on('click', '.bulk-action-btn', (e) => {
                this.handleBulkAction(e);
            });
        }
        
        updateSelectedItems() {
            this.selectedItems = $('.marker-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            // 更新批量操作按钮状态
            $('.bulk-action-btn').prop('disabled', this.selectedItems.length === 0);
            
            // 更新选择计数
            $('.selected-count').text(this.selectedItems.length);
        }
        
        handleBulkAction(e) {
            const action = $(e.target).data('action');
            
            if (this.selectedItems.length === 0) {
                alert('请先选择要操作的项目');
                return;
            }
            
            switch (action) {
                case 'delete':
                    this.bulkDelete();
                    break;
                case 'status':
                    this.bulkStatusChange();
                    break;
                case 'export':
                    this.bulkExport();
                    break;
            }
        }
        
        bulkDelete() {
            if (!confirm(`确定要删除选中的 ${this.selectedItems.length} 个标记点吗？`)) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'travel_map_bulk_delete',
                    nonce: window.travelMapAdmin.nonce,
                    marker_ids: this.selectedItems
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('批量删除失败：' + (response.data || '未知错误'));
                    }
                },
                error: () => {
                    alert('网络请求失败');
                }
            });
        }
        
        bulkStatusChange() {
            const newStatus = prompt('请输入新状态 (visited/want_to_go/planned):');
            if (!newStatus || !['visited', 'want_to_go', 'planned'].includes(newStatus)) {
                alert('状态格式不正确');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'travel_map_bulk_status',
                    nonce: window.travelMapAdmin.nonce,
                    marker_ids: this.selectedItems,
                    status: newStatus
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('批量更新失败：' + (response.data || '未知错误'));
                    }
                },
                error: () => {
                    alert('网络请求失败');
                }
            });
        }
        
        bulkExport() {
            const exportUrl = ajaxurl + '?' + $.param({
                action: 'travel_map_bulk_export',
                nonce: window.travelMapAdmin.nonce,
                marker_ids: this.selectedItems.join(','),
                format: 'csv'
            });
            
            window.open(exportUrl, '_blank');
        }
    }

    // 导入导出功能
    class ImportExport {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            // 文件导入
            $(document).on('change', '#import-file', (e) => {
                this.handleFileImport(e);
            });
            
            // 导出按钮
            $(document).on('click', '.export-btn', (e) => {
                this.handleExport(e);
            });
        }
        
        handleFileImport(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const allowedTypes = ['text/csv', 'application/json', 'text/plain'];
            if (!allowedTypes.includes(file.type)) {
                alert('不支持的文件格式，请上传 CSV 或 JSON 文件');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'travel_map_import');
            formData.append('nonce', window.travelMapAdmin.nonce);
            formData.append('import_file', file);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        alert(`导入成功！共导入 ${response.data.count} 个标记点`);
                        location.reload();
                    } else {
                        alert('导入失败：' + (response.data || '未知错误'));
                    }
                },
                error: () => {
                    alert('上传失败');
                }
            });
        }
        
        handleExport(e) {
            const format = $(e.target).data('format') || 'csv';
            const status = $(e.target).data('status') || 'all';
            
            const exportUrl = ajaxurl + '?' + $.param({
                action: 'travel_map_export',
                nonce: window.travelMapAdmin.nonce,
                format: format,
                status: status
            });
            
            window.open(exportUrl, '_blank');
        }
    }

    // 页面初始化
    $(document).ready(function() {
        // 确保全局变量存在
        if (typeof window.travelMapAdmin === 'undefined') {
            window.travelMapAdmin = {
                nonce: $('#_wpnonce').val() || '',
                ajaxurl: window.ajaxurl || '/wp-admin/admin-ajax.php',
                apiKey: ''
            };
        }
        
        // 确保 ajaxurl 全局变量存在
        if (typeof window.ajaxurl === 'undefined') {
            window.ajaxurl = window.travelMapAdmin.ajaxurl;
        }
        
        // 初始化管理员功能
        try {
            window.travelMapAdminInstance = new TravelMapAdmin();
            
            // 仅在相关页面初始化这些功能
            if (typeof BulkOperations !== 'undefined' && $('.travel-map-bulk-operations').length > 0) {
                new BulkOperations();
            }
            if (typeof ImportExport !== 'undefined' && $('.travel-map-import-export').length > 0) {
                new ImportExport();
            }
        } catch (error) {
            // 初始化失败静默处理
        }
    });

    // 将类暴露到全局作用域，供测试和外部使用
    window.TravelMapAdmin = TravelMapAdmin;
    window.BulkOperations = BulkOperations;
    window.ImportExport = ImportExport;

})(jQuery);