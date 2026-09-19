/**
 * Travel Map 视野自适应纯计算模块（无 DOM / 无地图依赖）
 * UMD：浏览器挂 window.TravelMapGeo，Node 下 module.exports。
 */
(function (root, factory) {
    var mod = factory();
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = mod;
    }
    if (root) {
        root.TravelMapGeo = mod;
    }
})(typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    var WORLD = 256; // zoom=0 世界像素边长（标准 slippy：worldSize = 256 · 2^zoom）
    var EPS = 1e-9;

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

    function lngLatToWorld(lng, lat) {
        var x = (lng + 180) / 360 * WORLD;
        var sinL = Math.sin(lat * Math.PI / 180);
        sinL = clamp(sinL, -0.9999, 0.9999);
        var y = (0.5 - Math.log((1 + sinL) / (1 - sinL)) / (4 * Math.PI)) * WORLD;
        return { x: x, y: y };
    }

    function worldToLngLat(x, y) {
        var lng = x / WORLD * 360 - 180;
        var n = Math.PI - 2 * Math.PI * (y / WORLD);
        var lat = 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
        return { lng: lng, lat: lat };
    }

    /**
     * 计算自适应视野：外接框定缩放（含固定像素留白 + 封顶，一步到位），
     * 质心定中心（带半跨 25% 钳制）。纯计算，不触碰地图对象。
     *
     * @param {Array<[number,number]>} coords 坐标数组 [[lng,lat], ...]
     * @param {Object} opts
     * @param {number} [opts.padding=60] 四边安全像素留白
     * @param {'centroid'|'bbox'} [opts.centering='centroid'] 居中策略
     * @param {number} [opts.minZoom=1] 缩放下限
     * @param {number} [opts.maxZoom=18] 缩放上限
     * @param {number} [opts.viewW=800] 视口宽（像素）
     * @param {number} [opts.viewH=550] 视口高（像素）
     * @returns {{zoom:number, center:[number,number]}|null}
     */
    function computeFitView(coords, opts) {
        opts = opts || {};
        if (!coords || !coords.length) return null;

        var padding = typeof opts.padding === 'number' ? opts.padding : 60;
        var minZoom = typeof opts.minZoom === 'number' ? opts.minZoom : 1;
        var maxZoom = typeof opts.maxZoom === 'number' ? opts.maxZoom : 18;
        var viewW = opts.viewW || 800;
        var viewH = opts.viewH || 550;
        var centering = opts.centering || 'centroid';

        var minLng = Infinity, minLat = Infinity, maxLng = -Infinity, maxLat = -Infinity;
        var sumLng = 0, sumLat = 0;
        for (var i = 0; i < coords.length; i++) {
            var lng = coords[i][0], lat = coords[i][1];
            if (lng < minLng) minLng = lng;
            if (lng > maxLng) maxLng = lng;
            if (lat < minLat) minLat = lat;
            if (lat > maxLat) maxLat = lat;
            sumLng += lng; sumLat += lat;
        }

        var bboxCenterLng = (minLng + maxLng) / 2;
        var bboxCenterLat = (minLat + maxLat) / 2;

        // 跨 180° 经线：含对蹠点，降级为 bbox 居中，避免畸形框
        if (maxLng - minLng > 180) centering = 'bbox';

        // 由外接框定缩放：E 为 zoom=0 世界像素跨度，视口有效区 V = view - 2*padding
        // 需 E·2^z <= V → z <= log2(V/E)，取最受限轴
        var wMin = lngLatToWorld(minLng, maxLat); // 左上（x 最小、y 最小，因纬度越高 y 越小）
        var wMax = lngLatToWorld(maxLng, minLat); // 右下
        var Ex = Math.max(Math.abs(wMax.x - wMin.x), EPS);
        var Ey = Math.max(Math.abs(wMax.y - wMin.y), EPS);
        var Vx = Math.max(viewW - 2 * padding, 64);
        var Vy = Math.max(viewH - 2 * padding, 64);
        var ratio = Math.min(Vx / Ex, Vy / Ey);
        var fitZoom = Math.log(ratio > 0 ? ratio : 1) / Math.LN2;
        var zoom = clamp(fitZoom, minZoom, maxZoom);

        // 由质心定中心（带半跨 25% 钳制）
        var center = [bboxCenterLng, bboxCenterLat];
        if (centering !== 'bbox') {
            var centroidLng = sumLng / coords.length;
            var centroidLat = sumLat / coords.length;
            var halfLng = Math.max((maxLng - minLng) / 2, EPS);
            var halfLat = Math.max((maxLat - minLat) / 2, EPS);
            var shiftLng = clamp(centroidLng - bboxCenterLng, -0.25 * halfLng, 0.25 * halfLng);
            var shiftLat = clamp(centroidLat - bboxCenterLat, -0.25 * halfLat, 0.25 * halfLat);
            center = [bboxCenterLng + shiftLng, bboxCenterLat + shiftLat];
        }

        return { zoom: zoom, center: center };
    }

    return {
        lngLatToWorld: lngLatToWorld,
        worldToLngLat: worldToLngLat,
        computeFitView: computeFitView
    };
});
