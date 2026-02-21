/**
 * Travel Map Shortcode Initializer
 * 短代码地图初始化脚本
 */

(function() {
    'use strict';

    const cfg = window.travelMapShortcode || {};
    const i18n = cfg.i18n || {};
    const waitTimers = new WeakMap();
    const MAX_WAIT_MS = 12000;
    const WAIT_INTERVAL_MS = 200;
    const SCRIPT_TIMEOUT_MS = 12000;
    const scriptLoaders = new Map();
    let stylesEnsured = false;

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

    const isLoadingVisible = (mapEl) => {
        if (!mapEl) return false;
        const wrapper = mapEl.closest('.travel-map-wrapper') || mapEl.parentElement;
        if (!wrapper) return false;
        const loading = wrapper.querySelector('.travel-map-loading');
        if (!loading) return false;
        const style = window.getComputedStyle(loading);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    };

    const isMapApiReady = () => {
        return typeof window.initTravelMap === 'function' && typeof window.AMap !== 'undefined';
    };

    const injectFallbackStyles = () => {
        if (document.querySelector('style[data-travel-map-inline="1"]')) {
            return;
        }
        const style = document.createElement('style');
        style.dataset.travelMapInline = '1';
        style.textContent = '.travel-map-container{width:100%;height:var(--travel-map-height,500px);position:relative;overflow:hidden;background:#f5f5f5}.travel-map-wrapper,.travel-map{width:100%;height:100%;min-height:300px}.travel-map-loading{position:absolute;top:0;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:center;flex-direction:column;background:#f5f5f5;z-index:1000}.travel-map-controls{position:absolute;top:12px;right:12px;z-index:1000;display:flex;flex-direction:column;gap:8px}.travel-map-control-btn{width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:0}.travel-map-control-btn svg{width:20px;height:20px;display:block}.travel-map-accessibility{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}';
        document.head.appendChild(style);
    };

    const ensureStyles = () => {
        if (stylesEnsured) return;
        stylesEnsured = true;

        const styleUrl = cfg.styleUrl || '';
        if (styleUrl) {
            const existing = document.querySelector(`link[rel="stylesheet"][href*="travel-map.css"], link[data-travel-map-style="1"]`);
            if (!existing || existing.parentElement !== document.head) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = styleUrl;
                link.dataset.travelMapStyle = '1';
                document.head.appendChild(link);
            }
        }

        const checkVisibility = () => {
            const el = document.querySelector('.travel-map-accessibility');
            if (!el) return;
            const rect = el.getBoundingClientRect();
            if (rect.width > 4 || rect.height > 4) {
                injectFallbackStyles();
            }
        };

        setTimeout(checkVisibility, 300);
        setTimeout(checkVisibility, 1200);
    };

    const loadScript = (src, attrs = {}) => {
        if (!src) return Promise.reject(new Error('missing src'));

        if (scriptLoaders.has(src)) {
            return scriptLoaders.get(src);
        }

        const promise = new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[data-travel-map-src="${src}"], script[src="${src}"]`);
            if (existing) {
                if (existing.dataset.travelMapLoaded === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error('load failed')));
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.defer = true;
            script.dataset.travelMapSrc = src;
            Object.keys(attrs).forEach((key) => {
                script.setAttribute(key, attrs[key]);
            });

            const timeout = window.setTimeout(() => {
                reject(new Error('timeout'));
            }, SCRIPT_TIMEOUT_MS);

            script.onload = () => {
                window.clearTimeout(timeout);
                script.dataset.travelMapLoaded = '1';
                resolve();
            };
            script.onerror = () => {
                window.clearTimeout(timeout);
                reject(new Error('load failed'));
            };

            document.head.appendChild(script);
        });

        scriptLoaders.set(src, promise);
        return promise;
    };

    const ensureMapScripts = (mapEl) => {
        const apiKey = mapEl?.dataset?.apiKey || (window.travelMapAjax ? window.travelMapAjax.apiKey : '');
        const apiScriptUrl = cfg.apiScript || (apiKey ? `https://webapi.amap.com/maps?v=2.0&key=${encodeURIComponent(apiKey)}` : '');
        const frontendScriptUrl = cfg.frontendScript || '';

        if (cfg.securityKey && !window._AMapSecurityConfig) {
            window._AMapSecurityConfig = { securityJsCode: cfg.securityKey };
        }

        const tasks = [];
        if (!window.AMap && apiScriptUrl) {
            tasks.push(loadScript(apiScriptUrl, { 'data-travel-map': 'amap' }));
        }
        if (typeof window.initTravelMap !== 'function' && frontendScriptUrl) {
            tasks.push(loadScript(frontendScriptUrl, { 'data-travel-map': 'frontend' }));
        }

        if (tasks.length === 0) {
            return Promise.resolve();
        }

        return Promise.allSettled(tasks);
    };

    const waitForMapApi = (mapEl) => {
        if (!mapEl || waitTimers.has(mapEl)) {
            return;
        }

        const start = Date.now();
        const timer = window.setInterval(() => {
            if (isMapApiReady()) {
                window.clearInterval(timer);
                waitTimers.delete(mapEl);
                initMapElement(mapEl);
                return;
            }

            if (Date.now() - start >= MAX_WAIT_MS) {
                window.clearInterval(timer);
                waitTimers.delete(mapEl);
                showError(mapEl, i18n.mapScriptMissing || '地图脚本加载失败');
            }
        }, WAIT_INTERVAL_MS);

        waitTimers.set(mapEl, timer);
    };

    const hasMapContent = (mapEl) => {
        if (!mapEl) return false;
        if (mapEl.querySelector('.amap-container, .amap-maps, canvas')) {
            return true;
        }
        return mapEl.children.length > 0;
    };

    const resetMapElement = (mapEl) => {
        if (!mapEl) return;
        mapEl.removeAttribute('data-travel-map-initialized');
        mapEl.innerHTML = '';
    };

    const initMapElement = (mapEl) => {
        if (!mapEl) {
            return;
        }

        if (!isMapApiReady()) {
            ensureMapScripts(mapEl).finally(() => {
                waitForMapApi(mapEl);
            });
            return;
        }

        const alreadyInitialized = mapEl.dataset.travelMapInitialized === '1';
        const loadingVisible = isLoadingVisible(mapEl);
        const mapContentExists = hasMapContent(mapEl);

        if (alreadyInitialized && !loadingVisible && mapContentExists) {
            return;
        }

        const id = mapEl.id;
        if (!id) {
            return;
        }

        if (alreadyInitialized && (loadingVisible || !mapContentExists)) {
            resetMapElement(mapEl);
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
        ensureStyles();
        const maps = document.querySelectorAll('[data-travel-map-init="1"]');
        maps.forEach(initMapElement);
    };

    const setupMutationObserver = () => {
        if (!window.MutationObserver || !document.body) {
            return;
        }

        let scheduled = false;
        const scheduleInit = () => {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(() => {
                scheduled = false;
                initAll();
            });
        };

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (!mutation.addedNodes || mutation.addedNodes.length === 0) {
                    continue;
                }
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== 1) continue;
                    if ((node.matches && node.matches('[data-travel-map-init="1"]')) ||
                        (node.querySelector && node.querySelector('[data-travel-map-init="1"]'))) {
                        scheduleInit();
                        return;
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
        document.addEventListener('DOMContentLoaded', setupMutationObserver);
    } else {
        initAll();
        setupMutationObserver();
    }

    window.addEventListener('pageshow', function(event) {
        if (event && event.persisted) {
            const maps = document.querySelectorAll('[data-travel-map-init="1"]');
            maps.forEach((mapEl) => {
                if (isLoadingVisible(mapEl) || !hasMapContent(mapEl)) {
                    resetMapElement(mapEl);
                }
            });
        }
        setTimeout(initAll, 0);
    });

    window.addEventListener('load', function() {
        setTimeout(initAll, 0);
    });

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            setTimeout(initAll, 0);
        }
    });

    window.TravelMapShortcodeInit = {
        initAll: initAll
    };
})();
