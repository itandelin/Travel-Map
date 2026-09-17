/**
 * Travel Map Meta Box Script
 * 文章编辑页面 Meta Box 交互脚本
 */

(function($) {
    'use strict';

    const config = window.travelMapConfig || {};

    // 地点选择界面的搜索和筛选功能
    $(document).ready(function() {
        const $searchInput = $('#travel-map-search');
        const $searchClear = $('#travel-map-search-clear');
        const $filterBtns = $('.travel-map-filter-btn');
        const $noResults = $('#travel-map-no-results');
        const $selectedCount = $('.travel-map-selected-count');

        let currentFilter = 'all';
        let currentSearch = '';
        let metaBoxMap = null;
        let metaBoxMapMarker = null;
        let isManualInputMode = false;

        initMetaBoxMapPicker();
        initPlaceSearch();
        updateStatistics();

        $searchInput.on('input', function() {
            currentSearch = $(this).val().toLowerCase().trim();
            if (currentSearch) {
                $searchClear.show();
            } else {
                $searchClear.hide();
            }
            filterMarkers();
        });

        $searchClear.on('click', function() {
            $searchInput.val('').trigger('input');
        });

        $filterBtns.on('click', function() {
            $filterBtns.removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            filterMarkers();
        });

        function filterMarkers() {
            let visibleCount = 0;

            $('.travel-map-marker-compact').each(function() {
                const $marker = $(this);
                const markerName = $marker.data('marker-name') || '';
                const markerStatus = $marker.data('marker-status');

                let showMarker = true;

                if (currentFilter !== 'all' && markerStatus !== currentFilter) {
                    showMarker = false;
                }

                if (currentSearch && markerName.indexOf(currentSearch) === -1) {
                    showMarker = false;
                }

                if (showMarker) {
                    $marker.show();
                    visibleCount++;
                } else {
                    $marker.hide();
                }
            });

            if (visibleCount === 0) {
                $noResults.show();
            } else {
                $noResults.hide();
            }

            updateStatistics();
        }

        $(document).on('click', '.travel-map-marker-compact', function(e) {
            if (e.target.type !== 'checkbox') {
                const $checkbox = $(this).find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });

        $(document).on('change', '.travel-map-marker-checkbox', function() {
            const $marker = $(this).closest('.travel-map-marker-compact');

            if ($(this).is(':checked')) {
                $marker.addClass('selected');
            } else {
                $marker.removeClass('selected');
            }

            updateStatistics();
        });

        function updateStatistics() {
            const selectedCount = $('.travel-map-marker-checkbox:checked').length;
            $selectedCount.text(selectedCount);
        }

        $('#toggle-manual-input').on('click', function() {
            isManualInputMode = !isManualInputMode;
            const $button = $(this);
            const $latInput = $('#meta-latitude');
            const $lngInput = $('#meta-longitude');

            if (isManualInputMode) {
                $button.addClass('active').text('🗺️ 地图选点');
                $latInput.prop('readonly', false);
                $lngInput.prop('readonly', false);
            } else {
                $button.removeClass('active').text('✏️ 手动输入坐标');
                $latInput.prop('readonly', true);
                $lngInput.prop('readonly', true);
            }
        });

        $('#meta-latitude, #meta-longitude').on('input', function() {
            if (isManualInputMode && metaBoxMap && metaBoxMapMarker) {
                const lat = parseFloat($('#meta-latitude').val());
                const lng = parseFloat($('#meta-longitude').val());

                if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    metaBoxMapMarker.setPosition([lng, lat]);
                    metaBoxMap.setCenter([lng, lat]);
                }
            }
        });

        function initMetaBoxMapPicker() {
            const mapContainer = document.getElementById('meta-box-map');

            if (!mapContainer) {
                return;
            }

            mapContainer.className = 'travel-map-mini-selector loading';
            mapContainer.innerHTML = '🔄 加载地图中...';

            setTimeout(function() {
                if (typeof window.AMap !== 'undefined') {
                    createMetaBoxMap(mapContainer);
                } else {
                    loadAMapAPI(function() {
                        createMetaBoxMap(mapContainer);
                    }, function() {
                        showMapError(mapContainer, '未配置API密钥或加载失败');
                    });
                }
            }, 500);
        }

        function loadAMapAPI(successCallback, errorCallback) {
            const apiKey = config.apiKey || '';
            const securityKey = config.securityKey || '';

            if (!apiKey) {
                if (errorCallback) errorCallback();
                return;
            }

            if (securityKey) {
                window._AMapSecurityConfig = {
                    securityJsCode: securityKey
                };
            }

            const script = document.createElement('script');
            script.src = 'https://webapi.amap.com/maps?v=2.0&key=' + apiKey;

            script.onload = function() {
                setTimeout(function() {
                    if (typeof window.AMap !== 'undefined') {
                        successCallback();
                    } else {
                        if (errorCallback) errorCallback();
                    }
                }, 100);
            };

            script.onerror = function() {
                if (errorCallback) errorCallback();
            };

            document.head.appendChild(script);
        }

        function createMetaBoxMap(mapContainer) {
            try {
                mapContainer.className = 'travel-map-mini-selector';
                mapContainer.innerHTML = '';

                metaBoxMap = new AMap.Map(mapContainer, {
                    zoom: 10,
                    center: [116.4074, 39.9042],
                    mapStyle: 'amap://styles/light',
                    dragEnable: true,
                    zoomEnable: true,
                    doubleClickZoom: true,
                    keyboardEnable: false,
                    scrollWheel: true
                });

                metaBoxMapMarker = new AMap.Marker({
                    position: [116.4074, 39.9042],
                    draggable: true,
                    cursor: 'move',
                    icon: new AMap.Icon({
                        image: 'data:image/svg+xml;base64,' + btoa('<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" fill="#ef4444" stroke="#fff" stroke-width="2"/></svg>'),
                        size: new AMap.Size(20, 20),
                        imageOffset: new AMap.Pixel(-10, -10)
                    })
                });

                metaBoxMap.add(metaBoxMapMarker);

                metaBoxMap.on('click', function(e) {
                    if (!isManualInputMode) {
                        const lng = e.lnglat.lng;
                        const lat = e.lnglat.lat;
                        updateMetaBoxCoordinates(lng, lat);
                        metaBoxMapMarker.setPosition([lng, lat]);
                    }
                });

                metaBoxMapMarker.on('dragend', function(e) {
                    if (!isManualInputMode) {
                        const position = e.target.getPosition();
                        updateMetaBoxCoordinates(position.lng, position.lat);
                    }
                });
            } catch (error) {
                showMapError(mapContainer, '地图初始化失败');
            }
        }

        function updateMetaBoxCoordinates(lng, lat) {
            $('#meta-longitude').val(lng.toFixed(6));
            $('#meta-latitude').val(lat.toFixed(6));
        }

        /**
         * 按名称搜索地点（高德 Autocomplete + PlaceSearch）
         * 选中后回填：名称、经纬度、地图定位；国别需人工确认（国内点自动填 CN）
         */
        function initPlaceSearch() {
            const $input = $('#travel-map-place-search');
            const $results = $('#travel-map-place-search-results');
            if (!$input.length || typeof window.AMap === 'undefined') {
                return;
            }

            let placeSearch = null;
            let autocomplete = null;
            let candidates = [];

            const hideResults = () => $results.hide().empty();
            const closeOnOutside = (e) => {
                if (!$(e.target).closest('#travel-map-place-search-row').length) {
                    hideResults();
                }
            };
            $(document).on('click', closeOnOutside);

            $input.on('input', function() {
                const keyword = $(this).val().trim();
                if (!keyword || keyword.length < 2) {
                    hideResults();
                    return;
                }
                AMap.plugin(['AMap.Autocomplete', 'AMap.PlaceSearch'], function() {
                    if (!autocomplete) {
                        autocomplete = new AMap.Autocomplete({ city: '' });
                    }
                    autocomplete.search(keyword, function(status, result) {
                        if (status !== 'complete' || !result.tips) {
                            hideResults();
                            return;
                        }
                        candidates = result.tips.filter(tip => tip.location);
                        if (!candidates.length) {
                            $results.html('<div class="travel-map-place-item empty">未找到匹配地点</div>').show();
                            return;
                        }
                        $results.html(candidates.slice(0, 6).map((tip, i) =>
                            '<div class="travel-map-place-item" data-idx="' + i + '">' +
                                '<span class="travel-map-place-name">' + escapeHtmlText(tip.name) + '</span>' +
                                '<span class="travel-map-place-district">' + escapeHtmlText(tip.district || '') + '</span>' +
                            '</div>'
                        ).join('')).show();
                    });
                });
            });

            $results.on('click', '.travel-map-place-item', function() {
                const tip = candidates[parseInt($(this).data('idx'), 10)];
                if (!tip || !tip.location) {
                    return;
                }
                const lng = tip.location.lng;
                const lat = tip.location.lat;

                // 回填名称与坐标
                $('input[name="new_marker_title"]').val(tip.name);
                $('#meta-longitude').val(lng.toFixed(6));
                $('#meta-latitude').val(lat.toFixed(6));

                // 地图定位
                if (metaBoxMap && metaBoxMapMarker) {
                    metaBoxMapMarker.setPosition([lng, lat]);
                    metaBoxMap.setCenter([lng, lat]);
                    metaBoxMap.setZoom(11);
                }

                // 国内点（高德坐标系覆盖范围）默认 CN，海外留空人工填写
                const $country = $('input[name="new_marker_country"]');
                if (!$country.val() && lng > 73 && lng < 136 && lat > 18 && lat < 54) {
                    $country.val('CN');
                }

                hideResults();
                $input.val(tip.name);
            });

            $input.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideResults();
                }
            });
        }

        function escapeHtmlText(value) {
            const map = {
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            };
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(c) {
                return map[c] || c;
            });
        }

        function showMapError(mapContainer, message) {
            mapContainer.className = 'travel-map-mini-selector error';
            mapContainer.innerHTML = '<div>⚠️ ' + message + '</div><div style="font-size: 11px; margin-top: 4px;">请检查API密钥配置</div>';
        }

        $('input[name="new_marker_latitude"], input[name="new_marker_longitude"]').on('blur', function() {
            const lat = parseFloat($('input[name="new_marker_latitude"]').val());
            const lng = parseFloat($('input[name="new_marker_longitude"]').val());

            if (!isNaN(lat) && !isNaN(lng)) {
                if (lat < -90 || lat > 90) {
                    alert('纬度必须在 -90 到 90 之间');
                    $('input[name="new_marker_latitude"]').focus();
                } else if (lng < -180 || lng > 180) {
                    alert('经度必须在 -180 到 180 之间');
                    $('input[name="new_marker_longitude"]').focus();
                }
            }
        });

        $('input[name="new_marker_title"]').on('blur', function() {
            if ($(this).val().trim() !== '') {
                const lat = $('#meta-latitude').val();
                const lng = $('#meta-longitude').val();

                if (!lat || !lng) {
                    $('.notice-warning').remove();
                    $(this).after('<div class="notice-warning" style="font-size: 12px; color: #b45309; margin-top: 4px;">请在地图上点击选择位置</div>');
                    setTimeout(() => {
                        $('.notice-warning').fadeOut();
                    }, 3000);
                }
            }
        });

        $searchInput.on('keydown', function(e) {
            if (e.key === 'Escape') {
                $(this).val('').trigger('input');
            }
        });

        filterMarkers();
    });
})(jQuery);
