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
        done: '已去',
        wish: '想去',
        plan: '计划'
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
        confirmDelete: i18n.confirmDelete || '确定要删除这个地点吗？此操作不可撤销。',
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
            $('#visit-date-row').toggle(status === 'done');
            $('#planned-date-row').toggle(status === 'plan');
            $('#wish-reason-row').toggle(status === 'wish');
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

        // 编辑态的删除按钮：模板里一直有这个按钮，但从未绑定过点击事件，
        // 导致点了没反应，只能靠勾选后「批量删除」删单条。
        $('#delete-marker').on('click', function() {
            deleteCurrentMarker();
        });

        // 取消编辑（表单标题旁的按钮）
        $('#reset-form').on('click', function() {
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

        // 媒体库选择封面图
        $('#select-cover-btn').on('click', function() {
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            const frame = wp.media({
                title: '选择封面图',
                button: { text: '使用此图片' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#marker-cover').val(attachment.url);
            });
            frame.open();
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
            const colspan = $('#select-all').length ? 8 : 7;
            tbody.append('<tr><td colspan="' + colspan + '" class="travel-map-loading-row"><div class="travel-map-loading" style="padding: 20px;">' + texts.noData + '</div></td></tr>');
            return;
        }

        markers.forEach(function(marker) {
            const row = $('<tr>');

            if ($('#select-all').length) {
                row.append('<td><input type="checkbox" class="marker-checkbox" value="' + marker.id + '"></td>');
            }

            row.append('<td><strong>' + escapeHtml(marker.title) + '</strong></td>');
            row.append('<td><span class="status-badge status-' + marker.status + '">' + getStatusText(marker.status) + '</span></td>');
            const country = String(marker.country || '').toUpperCase();
            const flagBase = cfg.flagsBase || ((window.travelMapAjax && window.travelMapAjax.flagsBase) || '');
            let countryHtml = '<em style="color: #999;">' + texts.none + '</em>';
            if (country) {
                const firstCode = country.split(',')[0];
                const flagUrl = flagBase ? flagBase + firstCode.toLowerCase() + '.svg' : '';
                countryHtml = (flagUrl ? '<img src="' + flagUrl + '" alt="' + escapeHtml(firstCode) + '" style="width:18px;height:18px;vertical-align:-4px;margin-right:6px;border-radius:2px;">' : '')
                    + escapeHtml(country);
            }
            row.append('<td style="font-size: 12px;">' + countryHtml + '</td>');
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
                    $('#marker-post').val(marker.post_id || '');
                    $('#marker-description').val(marker.description || '');
                    $('#marker-country').val(marker.country || '');
                    $('#marker-years').val(marker.years || '');
                    $('#marker-type').val(marker.type || '');
                    $('#marker-cover').val(marker.cover_image || '');

                    if (marker.status === 'done' && marker.visit_date) {
                        $('#marker-visit-date').val(marker.visit_date);
                    }
                    if (marker.status === 'plan' && marker.planned_date) {
                        $('#marker-planned-date').val(marker.planned_date);
                    }
                    if (marker.status === 'wish' && marker.wish_reason) {
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

    /**
     * 删除当前正在编辑的单个标记
     *
     * 表单里的删除按钮此前没有绑定任何事件，点击无反应，
     * 用户只能靠勾选后「批量删除」删单条。后端 travel_map_delete_marker 一直存在。
     */
    function deleteCurrentMarker() {
        const markerId = $('#marker-id').val();
        if (!markerId) {
            return;
        }

        if (!confirm(texts.confirmDelete)) {
            return;
        }

        const $btn = $('#delete-marker');
        $btn.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'travel_map_delete_marker',
                nonce: nonce,
                marker_id: markerId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(texts.deleteSuccess, 'success');
                    resetForm();
                    loadCoordinatesList();
                } else {
                    showNotification(texts.deleteFailed + (response.data || texts.unknownError), 'error');
                }
            },
            error: function() {
                showNotification(texts.networkError, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

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
        const wgs84 = $('#import-wgs84').is(':checked');
        const fileInput = document.getElementById('import-file');
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;

        if (wgs84 && file) {
            // 纠偏路径：本地解析文件 → 境内点 convertFrom 批量转换 → 行数据提交
            readImportFile(file).then(function(rows) {
                return convertDomesticRows(rows);
            }).then(function(rows) {
                submitImportRows(rows);
            }).catch(function(err) {
                alert(texts.importFailed + (err && err.message ? err.message : texts.unknownError));
            });
            return;
        }

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

    /**
     * 境内范围粗判（与高德 GCJ-02 覆盖区一致）：经 73-136 / 纬 18-54
     */
    function isDomesticLngLat(lng, lat) {
        return lng > 73 && lng < 136 && lat > 18 && lat < 54;
    }

    /**
     * 本地解析导入文件为行数组（CSV/JSON 两种布局，字段与导出格式一致）
     */
    function readImportFile(file) {
        return new Promise(function(resolve, reject) {
            const reader = new FileReader();
            reader.onerror = function() { reject(new Error(texts.networkError)); };
            reader.onload = function() {
                try {
                    const rows = parseImportContent(reader.result, file.name);
                    if (!rows.length) {
                        reject(new Error(texts.noData));
                        return;
                    }
                    resolve(rows);
                } catch (e) {
                    reject(e);
                }
            };
            reader.readAsText(file, 'UTF-8');
        });
    }

    function parseImportContent(content, filename) {
        const rows = [];
        if (/\.json$/i.test(filename)) {
            const data = JSON.parse(content);
            if (!Array.isArray(data)) {
                throw new Error(texts.noData);
            }
            data.forEach(function(item) {
                if (item && item.title != null && item.latitude != null && item.longitude != null) {
                    rows.push(sanitizeImportRow(item));
                }
            });
            return rows;
        }
        // CSV：与导出列顺序一致
        const lines = String(content).split(/\r?\n/).filter(function(l) { return l.trim() !== ''; });
        if (lines.length < 2) {
            throw new Error(texts.noData);
        }
        for (let i = 1; i < lines.length; i++) {
            // 简易 CSV 切分（字段含逗号的场景由双引号保护，导出端 fputcsv 已处理）
            const cells = parseCsvLine(lines[i]);
            if (cells.length >= 4) {
                rows.push(sanitizeImportRow({
                    title: cells[1],
                    latitude: cells[2],
                    longitude: cells[3],
                    status: cells[4],
                    description: cells[5],
                    visit_date: cells[6],
                    visit_count: cells[7],
                    planned_date: cells[8],
                    wish_reason: cells[9],
                    priority_level: cells[10],
                    country: cells[11],
                    years: cells[12],
                    cover_image: cells[13],
                    type: cells[14]
                }));
            }
        }
        return rows;
    }

    function parseCsvLine(line) {
        const cells = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (line[i + 1] === '"') { cur += '"'; i++; }
                    else { inQuotes = false; }
                } else {
                    cur += ch;
                }
            } else if (ch === '"') {
                inQuotes = true;
            } else if (ch === ',') {
                cells.push(cur);
                cur = '';
            } else {
                cur += ch;
            }
        }
        cells.push(cur);
        return cells;
    }

    function sanitizeImportRow(item) {
        const statusMap = { visited: 'done', want_to_go: 'wish', planned: 'plan' };
        let status = String(item.status == null ? 'done' : item.status).trim();
        if (statusMap[status]) { status = statusMap[status]; }
        if (['done', 'wish', 'plan'].indexOf(status) === -1) { status = 'done'; }
        const statusValid = status;
        const esc = function(v) { return String(v == null ? '' : v).replace(/[<>"']/g, function(c) {
            return { '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] || c;
        }); };
        return {
            title: esc(item.title),
            latitude: parseFloat(item.latitude),
            longitude: parseFloat(item.longitude),
            status: statusValid,
            description: esc(item.description),
            visit_date: item.visit_date || null,
            visit_count: parseInt(item.visit_count, 10) || 1,
            planned_date: item.planned_date || null,
            wish_reason: esc(item.wish_reason),
            priority_level: parseInt(item.priority_level, 10) || 3,
            country: String(item.country || '').toUpperCase(),
            years: String(item.years || ''),
            cover_image: esc(item.cover_image),
            type: esc(item.type)
        };
    }

    /**
     * 境内点经 AMap.convertFrom 批量转 GCJ-02（纯前端调用，服务端不发起外部请求）
     * 海外点原样返回；高德 API 不可用时整体降级为不转换并提示。
     */
    function convertDomesticRows(rows) {
        const domestic = [];
        rows.forEach(function(row) {
            if (isFinite(row.longitude) && isFinite(row.latitude)
                && isDomesticLngLat(row.longitude, row.latitude)) {
                domestic.push(row);
            }
        });

        if (!domestic.length) {
            return Promise.resolve(rows);
        }
        if (typeof window.AMap === 'undefined' || typeof window.AMap.convertFrom !== 'function') {
            return Promise.reject(new Error(texts.apiKeyMissing));
        }

        // convertFrom 单次上限 40 个坐标，分批串行
        const batches = [];
        for (let i = 0; i < domestic.length; i += 40) {
            batches.push(domestic.slice(i, i + 40));
        }

        return batches.reduce(function(chain, batch) {
            return chain.then(function() {
                return new Promise(function(resolve, reject) {
                    window.AMap.convertFrom(
                        batch.map(function(r) { return [r.longitude, r.latitude]; }),
                        'gps',
                        function(status, result) {
                            if (status === 'complete' && result.info === 'ok' && result.locations) {
                                batch.forEach(function(row, idx) {
                                    const loc = result.locations[idx];
                                    if (loc) {
                                        row.longitude = loc.lng;
                                        row.latitude = loc.lat;
                                    }
                                });
                                resolve();
                            } else {
                                reject(new Error(texts.networkError));
                            }
                        }
                    );
                });
            });
        }, Promise.resolve()).then(function() {
            return rows;
        });
    }

    /**
     * 纠偏后的行数据走独立端点提交
     */
    function submitImportRows(rows) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'travel_map_import_rows',
                nonce: nonce,
                import_data: JSON.stringify(rows)
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || texts.importSuccess);
                    $('#import-modal').hide();
                    $('#import-form')[0].reset();
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
