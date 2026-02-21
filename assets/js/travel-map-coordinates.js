/**
 * Travel Map Coordinates Admin Script
 * 坐标管理页面交互脚本
 */

(function($) {
    'use strict';

    const cfg = window.travelMapCoordinates || {};
    const i18n = cfg.i18n || {};
    const ajaxurl = cfg.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';
    const nonce = cfg.nonce || '';
    const defaults = cfg.defaults || { lng: 116.4074, lat: 39.9042 };

    const statusLabels = i18n.statusLabels || {
        visited: '已去',
        want_to_go: '想去',
        planned: '计划'
    };

    const texts = {
        noData: i18n.noData || '暂无数据',
        none: i18n.none || '无',
        saving: i18n.saving || '保存中...',
        saveSuccess: i18n.saveSuccess || '保存成功！',
        saveFailed: i18n.saveFailed || '保存失败：',
        unknownError: i18n.unknownError || '未知错误',
        networkError: i18n.networkError || '网络请求失败',
        loadEditSuccess: i18n.loadEditSuccess || '已加载编辑数据',
        loadEditFailed: i18n.loadEditFailed || '获取数据失败',
        fetchFailed: i18n.fetchFailed || '网络请求失败',
        selectToDelete: i18n.selectToDelete || '请选择要删除的标记点',
        confirmBulkDelete: i18n.confirmBulkDelete || '确定要删除选中的 %d 个标记点吗？',
        deleteSuccess: i18n.deleteSuccess || '删除成功',
        deleteFailed: i18n.deleteFailed || '删除失败：',
        importSuccess: i18n.importSuccess || '导入成功',
        importFailed: i18n.importFailed || '导入失败：',
        apiKeyMissing: i18n.apiKeyMissing || '请先配置API密钥并刷新页面',
        formTitleAdd: i18n.formTitleAdd || '添加新地点',
        formTitleEdit: i18n.formTitleEdit || '编辑地点',
        submitAdd: i18n.submitAdd || '添加坐标',
        submitUpdate: i18n.submitUpdate || '更新坐标',
        countFormat: i18n.countFormat || '%d 个地点'
    };

    const escapeHtml = (value) => {
        const str = String(value ?? '');
        return str.replace(/[&<>"'`]/g, (char) => {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '`': '&#96;'
            };
            return map[char] || char;
        });
    };

    const formatCount = (template, value) => template.replace('%d', value);

    $(document).ready(function() {
        initMapPicker();
        loadCoordinatesList();

        $('#marker-status').on('change', function() {
            const status = $(this).val();
            $('#visit-date-row').toggle(status === 'visited');
            $('#planned-date-row').toggle(status === 'planned');
            $('#wish-reason-row').toggle(status === 'want_to_go');
        });

        $('#coordinate-form').on('submit', function(e) {
            e.preventDefault();
            saveMarker();
        });

        $('#status-filter, #search-input').on('change keyup', function() {
            loadCoordinatesList();
        });

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

        $('#reset-form-button').on('click', function() {
            resetForm();
        });

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

        $(document).on('click', '.travel-map-modal-close', function() {
            $('#import-modal').hide();
        });
        
        $(document).on('click', '.travel-map-modal-cancel', function() {
            $('#import-modal').hide();
        });
    });

    function initMapPicker() {
        const mapContainer = document.getElementById('admin-map');
        if (!mapContainer) return;

        if (typeof window.AMap === 'undefined') {
            mapContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">' + texts.apiKeyMissing + '</div>';
            return;
        }

        const map = new AMap.Map(mapContainer, {
            zoom: 4,
            center: [defaults.lng, defaults.lat],
            mapStyle: 'amap://styles/light'
        });

        const marker = new AMap.Marker({
            position: [defaults.lng, defaults.lat],
            draggable: true
        });

        map.add(marker);

        window.adminMap = map;
        window.adminMapMarker = marker;

        map.on('click', function(e) {
            const lng = e.lnglat.lng;
            const lat = e.lnglat.lat;
            updateCoordinates(lng, lat);
            marker.setPosition([lng, lat]);
        });

        marker.on('dragend', function(e) {
            const position = e.target.getPosition();
            updateCoordinates(position.lng, position.lat);
        });

        $('#marker-latitude, #marker-longitude').on('input', function() {
            const lat = parseFloat($('#marker-latitude').val());
            const lng = parseFloat($('#marker-longitude').val());

            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setPosition([lng, lat]);
                map.setCenter([lng, lat]);
            }
        });
    }

    function updateCoordinates(lng, lat) {
        $('#marker-longitude').val(lng.toFixed(6));
        $('#marker-latitude').val(lat.toFixed(6));
    }

    function loadCoordinatesList() {
        const status = $('#status-filter').val();
        const search = $('#search-input').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'travel_map_get_markers',
                nonce: nonce,
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

    function renderMarkersList(markers) {
        const tbody = $('#coordinates-tbody');
        tbody.empty();

        updateMarkersCount(markers.length);

        if (markers.length === 0) {
            tbody.append('<tr><td colspan="7" class="travel-map-loading-row"><div class="travel-map-loading" style="padding: 20px;">' + texts.noData + '</div></td></tr>');
            return;
        }

        markers.forEach(function(marker) {
            const row = $('<tr>');

            if ($('#select-all').length) {
                row.append('<td><input type="checkbox" class="marker-checkbox" value="' + marker.id + '"></td>');
            }

            row.append('<td><strong>' + escapeHtml(marker.title) + '</strong></td>');
            row.append('<td><span class="status-badge status-' + marker.status + '">' + getStatusText(marker.status) + '</span></td>');
            row.append('<td style="font-family: monospace; font-size: 12px;">' + marker.latitude + ',<br>' + marker.longitude + '</td>');

            const postTitles = Array.isArray(marker.post_titles) ? marker.post_titles : (marker.post_title ? [marker.post_title] : []);
            const postHtml = postTitles.length
                ? postTitles.map(title => '<div class="marker-post-title">' + escapeHtml(title) + '</div>').join('')
                : '<em style="color: #999;">' + texts.none + '</em>';
            row.append('<td>' + postHtml + '</td>');

            row.append('<td>' + formatDate(marker.created_at) + '</td>');

            if ($('#form-title').length) {
                row.append('<td><button type="button" class="travel-map-btn travel-map-btn-outline" data-id="' + marker.id + '" onclick="editMarker(' + marker.id + ')" style="padding: 4px 8px; font-size: 12px;">' + (i18n.edit || '编辑') + '</button></td>');
            }

            tbody.append(row);
        });
    }

    function getStatusText(status) {
        return statusLabels[status] || status;
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    function toggleBulkActions() {
        const checkedCount = $('.marker-checkbox:checked').length;
        $('#bulk-delete-btn').toggle(checkedCount > 0);
    }

    function resetForm() {
        $('#coordinate-form')[0].reset();
        $('#marker-id').val('');
        $('#form-title').html('<span class="dashicons dashicons-location-alt"></span>' + texts.formTitleAdd);
        $('#submit-text').text(texts.submitAdd);
        $('#delete-marker').hide();
        $('#reset-form').hide();

        $('#visit-date-row').show();
        $('#planned-date-row').hide();
        $('#wish-reason-row').hide();

        updateCoordinates(defaults.lng, defaults.lat);

        if (window.adminMap && window.adminMapMarker) {
            window.adminMapMarker.setPosition([defaults.lng, defaults.lat]);
            window.adminMap.setCenter([defaults.lng, defaults.lat]);
        }
    }

    function updateMarkersCount(count) {
        $('#markers-count').text(formatCount(texts.countFormat, count));
    }

    function saveMarker() {
        const formData = new FormData($('#coordinate-form')[0]);
        formData.append('action', 'travel_map_save_marker');
        formData.append('nonce', nonce);

        const submitBtn = $('#coordinate-form button[type="submit"]');
        const originalText = submitBtn.find('#submit-text').text();
        submitBtn.prop('disabled', true);
        submitBtn.find('#submit-text').text(texts.saving);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification(texts.saveSuccess, 'success');
                    resetForm();
                    loadCoordinatesList();
                } else {
                    showNotification(texts.saveFailed + (response.data || texts.unknownError), 'error');
                }
            },
            error: function(xhr) {
                showNotification(texts.networkError + ': ' + xhr.status + ' ' + xhr.statusText, 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.find('#submit-text').text(originalText);
            }
        });
    }

    function showNotification(message, type) {
        const notification = $('<div class="travel-map-notification travel-map-notification-' + type + '">' + message + '</div>');
        $('body').append(notification);

        setTimeout(function() {
            notification.addClass('show');
        }, 100);

        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }

    window.editMarker = function(markerId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'travel_map_get_marker',
                nonce: nonce,
                marker_id: markerId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const marker = response.data;

                    $('#marker-id').val(marker.id);
                    $('#marker-title').val(marker.title);
                    $('#marker-latitude').val(marker.latitude);
                    $('#marker-longitude').val(marker.longitude);
                    $('#marker-status').val(marker.status).trigger('change');
                    $('#marker-color').val(marker.marker_color || '#FF6B35');
                    $('#marker-post').val(marker.post_id || '');
                    $('#marker-description').val(marker.description || '');

                    if (marker.status === 'visited' && marker.visit_date) {
                        $('#marker-visit-date').val(marker.visit_date);
                    }
                    if (marker.status === 'planned' && marker.planned_date) {
                        $('#marker-planned-date').val(marker.planned_date);
                    }
                    if (marker.status === 'want_to_go' && marker.wish_reason) {
                        $('#marker-wish-reason').val(marker.wish_reason);
                    }

                    $('#form-title').html('<span class="dashicons dashicons-edit"></span>' + texts.formTitleEdit);
                    $('#submit-text').text(texts.submitUpdate);
                    $('#delete-marker').show();
                    $('#reset-form').show();

                    if (window.adminMap && window.adminMapMarker) {
                        const lng = parseFloat(marker.longitude);
                        const lat = parseFloat(marker.latitude);
                        window.adminMapMarker.setPosition([lng, lat]);
                        window.adminMap.setCenter([lng, lat]);
                    }

                    $('.travel-map-coordinates-right')[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    showNotification(texts.loadEditSuccess, 'info');
                } else {
                    showNotification(texts.loadEditFailed, 'error');
                }
            },
            error: function() {
                showNotification(texts.fetchFailed, 'error');
            }
        });
    };

    function bulkDeleteMarkers() {
        const checkedIds = $('.marker-checkbox:checked').map(function() {
            return this.value;
        }).get();

        if (checkedIds.length === 0) {
            alert(texts.selectToDelete);
            return;
        }

        const confirmText = formatCount(texts.confirmBulkDelete, checkedIds.length);
        if (!confirm(confirmText)) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'travel_map_bulk_delete',
                nonce: nonce,
                marker_ids: checkedIds
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || texts.deleteSuccess);
                    loadCoordinatesList();
                } else {
                    alert(texts.deleteFailed + (response.data || texts.unknownError));
                }
            },
            error: function() {
                alert(texts.networkError);
            }
        });
    }

    function exportData() {
        const exportUrl = ajaxurl + '?' + $.param({
            action: 'travel_map_export',
            nonce: nonce,
            format: 'csv'
        });

        window.open(exportUrl, '_blank');
    }

    function importData() {
        const formData = new FormData($('#import-form')[0]);
        formData.append('action', 'travel_map_import');
        formData.append('nonce', nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || texts.importSuccess);
                    $('#import-modal').hide();
                    loadCoordinatesList();
                } else {
                    alert(texts.importFailed + (response.data || texts.unknownError));
                }
            },
            error: function() {
                alert(texts.networkError);
            }
        });
    }
})(jQuery);
