/**
 * Travel Map Shortcode Initializer
 * 短代码地图初始化脚本
 */

(function() {
    'use strict';

    const cfg = window.travelMapShortcode || {};
    const i18n = cfg.i18n || {};

    const parseBool = (value) => {
        if (typeof value === 'boolean') return value;
        if (value === undefined || value === null) return false;
        const normalized = String(value).toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes';
    };

    const getNumber = (value, fallback) => {
        const num = Number(value);
        return Number.isFinite(num) ? num : fallback;
    };

    const showError = (mapEl, message) => {
        const wrapper = mapEl.closest('.travel-map-wrapper') || mapEl.parentElement;
        if (!wrapper) return;
        const safeMessage = String(message || i18n.mapInitFailed || '地图初始化失败');
        wrapper.innerHTML =
            '<div class="travel-map-error">' +
                '<div class="travel-map-error-icon">⚠️</div>' +
                '<div class="travel-map-error-message">' + safeMessage + '</div>' +
                '<div class="travel-map-error-details">' +
                    '<p>' + (i18n.possibleFix || '可能的解决方案：') + '</p>' +
                    '<ul>' +
                        '<li>' + (i18n.fixNetwork || '检查网络连接是否正常') + '</li>' +
                        '<li>' + (i18n.fixApiKey || '确认高德地图API密钥配置正确') + '</li>' +
                        '<li>' + (i18n.fixReload || '刷新页面重试') + '</li>' +
                    '</ul>' +
                    '<button class="travel-map-retry-btn" onclick="location.reload()">' + (i18n.reload || '刷新页面') + '</button>' +
                '</div>' +
            '</div>';
    };

    const initMapElement = (mapEl) => {
        if (!mapEl || mapEl.dataset.travelMapInitialized === '1') {
            return;
        }

        const id = mapEl.id;
        if (!id) {
            return;
        }

        if (typeof window.initTravelMap !== 'function') {
            showError(mapEl, i18n.mapScriptMissing || '地图脚本加载失败');
            return;
        }

        const zoom = getNumber(mapEl.dataset.zoom, 4);
        const centerLat = getNumber(mapEl.dataset.centerLat, 35.0);
        const centerLng = getNumber(mapEl.dataset.centerLng, 105.0);
        const showFilterTabs = parseBool(mapEl.dataset.showFilterTabs);
        const defaultStatus = mapEl.dataset.status || 'all';
        const apiKey = mapEl.dataset.apiKey || (window.travelMapAjax ? window.travelMapAjax.apiKey : '');

        try {
            window.initTravelMap('#' + id, {
                zoom: zoom,
                center: [centerLng, centerLat],
                showFilterTabs: showFilterTabs,
                defaultStatus: defaultStatus,
                apiKey: apiKey
            });
            mapEl.dataset.travelMapInitialized = '1';
        } catch (error) {
            showError(mapEl, error && error.message ? error.message : i18n.mapInitFailed || '地图初始化失败');
        }
    };

    const initAll = () => {
        const maps = document.querySelectorAll('[data-travel-map-init="1"]');
        maps.forEach(initMapElement);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
