/**
 * Travel Map Frontend JavaScript - 原生JavaScript版本
 * 前端地图交互脚本（无jQuery依赖）
 */

(function() {
    'use strict';
    class TravelMap {
        constructor(container, options) {
            this.container = typeof container === 'string' ? document.querySelector(container) : container;
            if (!this.container) {
                return;
            }
            
            // 获取容器ID，如果容器已有ID则使用，否则生成新ID
            this.mapId = this.container.id || this.generateMapId();
            
            this.options = Object.assign({
                zoom: 2,
                center: [116.4074, 39.9042],
                markers: [],
                showFilterTabs: true,
                apiKey: '',
                mapStyle: 'light', // 保持light样式
                defaultStatus: 'all' // 添加默认状态选项
            }, options);
            
            this.map = null;
            this.markers = [];
            this.currentFilter = this.options.defaultStatus || 'all'; // 使用传入的状态
            this.popup = null;
            this.themeObserver = null; // 主题变化监听器
            
            this.init();
        }
        
        init() {
            // 检查容器类型
            const existingMapDiv = this.container.querySelector('.travel-map');
            if (existingMapDiv && existingMapDiv.id) {
                // 如果是传入的外层容器，并且已经包含地图结构，直接使用
                this.mapId = existingMapDiv.id;
                this.mapContainer = this.container;
                this.addEmbeddedFiltersToExisting();
            } else if (this.container.classList.contains('travel-map')) {
                // 如果直接传入的是地图div元素，需要找到外层容器
                this.mapId = this.container.id;
                this.mapContainer = this.container.closest('.travel-map-container') || this.container.parentElement;
                this.addEmbeddedFiltersToExisting();
            } else {
                this.mapContainer = this.container;
                this.setupContainer();
            }
            
            this.setupFilterTabs();
            this.setupMap();
            this.bindEvents();
            // 防止主题图片浏览器的全局事件处理
            this.preventThemeConflicts();
        }
        
        setupContainer() {
            // 创建地图HTML结构
            const html = `
                <div class="travel-map-container">
                    <div class="travel-map-wrapper">
                        <div class="travel-map-loading">
                            <div class="travel-map-spinner"></div>
                            <div class="travel-map-loading-text">正在加载地图...</div>
                        </div>
                        <div class="travel-map" id="${this.mapId}"></div>
                        ${this.options.showFilterTabs ? this.createEmbeddedFilterTabs() : ''}
                        <div class="travel-map-controls">
                            <button class="travel-map-control-btn" data-action="zoom-in" title="放大">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="zoom-out" title="缩小">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 13H5v-2h14v2z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="fullscreen" title="全屏">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="reset" title="重置视图">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
        }
        
        createFilterTabs() {
            const activeStatus = this.options.defaultStatus || 'all';
            return `
                <div class="travel-map-filters">
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'all' ? 'active' : ''} all" data-status="all">全部</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'visited' ? 'active' : ''} visited" data-status="visited">已去</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'want_to_go' ? 'active' : ''} want_to_go" data-status="want_to_go">想去</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'planned' ? 'active' : ''} planned" data-status="planned">计划</a>
                </div>
            `;
        }
        
        createEmbeddedFilterTabs() {
            const activeStatus = this.options.defaultStatus || 'all';
            return `
                <div class="travel-map-embedded-filters">
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'all' ? 'active' : ''} all" data-status="all">全部</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'visited' ? 'active' : ''} visited" data-status="visited">已去</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'want_to_go' ? 'active' : ''} want_to_go" data-status="want_to_go">想去</a>
                    <a href="#" class="travel-map-filter-tab ${activeStatus === 'planned' ? 'active' : ''} planned" data-status="planned">计划</a>
                </div>
            `;
        }
        
        /**
         * 为现有地图结构添加嵌入式筛选标签
         */
        addEmbeddedFiltersToExisting() {
            if (!this.options.showFilterTabs) return;
            
            // 检查是否已经存在嵌入式筛选标签
            const existingFilters = this.mapContainer.querySelector('.travel-map-embedded-filters');
            if (existingFilters) {
                // 如果已存在，更新激活状态
                this.updateFilterTabsActiveState(existingFilters);
                return;
            }
            
            // 查找地图容器
            const mapWrapper = this.mapContainer.querySelector('.travel-map-wrapper');
            if (mapWrapper) {
                // 在地图容器中添加嵌入式筛选标签
                const filtersHtml = this.createEmbeddedFilterTabs();
                mapWrapper.insertAdjacentHTML('beforeend', filtersHtml);
            }
        }
        
        /**
         * 更新筛选标签的激活状态
         */
        updateFilterTabsActiveState(container) {
            const activeStatus = this.options.defaultStatus || 'all';
            const tabs = container.querySelectorAll('.travel-map-filter-tab');
            
            tabs.forEach(tab => {
                const tabStatus = tab.getAttribute('data-status');
                if (tabStatus === activeStatus) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
        
        setupFilterTabs() {
            if (!this.options.showFilterTabs) return;
            
            // 使用地图容器来查找筛选标签
            const container = this.mapContainer || this.container;
            
            // 使用事件委托绑定点击事件
            container.addEventListener('click', (e) => {
                if (e.target.classList.contains('travel-map-filter-tab')) {
                    e.preventDefault();
                    
                    const status = e.target.getAttribute('data-status');
                    
                    // 更新活动状态
                    const tabs = container.querySelectorAll('.travel-map-filter-tab');
                    tabs.forEach(tab => tab.classList.remove('active'));
                    e.target.classList.add('active');
                    
                    // 筛选标记
                    this.filterMarkers(status);
                }
            });
        }
        
        setupMap() {
            // 检查高德地图API是否已加载
            if (typeof window.AMap === 'undefined') {
                this.showError('高德地图API未加载，请检查网络连接或API密钥配置');
                return;
            }
            
            if (!this.options.apiKey) {
                this.showError('请先在插件设置中配置高德地图API密钥');
                return;
            }
            
            // 检查地图容器是否存在
            const mapContainer = document.getElementById(this.mapId);
            if (!mapContainer) {
                this.showError('地图容器元素未找到，ID: ' + this.mapId);
                return;
            }
            
            try {
                // 立即确保容器尺寸正确（在地图初始化之前）
                this.ensureMapSize();
                
                // 移动端优化设置
                const isMobile = window.innerWidth <= 768;
                // 获取地图样式
                const getMapStyle = (style) => {
                    const styleMap = {
                        'normal': 'amap://styles/normal',
                        'light': 'amap://styles/light',
                        'dark': 'amap://styles/dark',
                        'satellite': 'amap://styles/satellite'
                    };
                    return styleMap[style] || styleMap['light'];
                };
                
                const mapOptions = {
                    zoom: isMobile ? Math.max(this.options.zoom - 1, 1) : this.options.zoom,
                    center: this.options.center,
                    mapStyle: this.getMapStyleByTheme(), // 使用主题自适应样式
                    showLabel: true,
                    showBuildingBlock: false,
                    touchZoom: true,
                    doubleClickZoom: true,
                    scrollWheel: !isMobile, // 移动端禁用滚轮缩放
                    touchZoomCenter: 1
                };
                
                // 初始化地图
                this.map = new AMap.Map(this.mapId, mapOptions);
                
                // 再次确保容器尺寸正确
                this.ensureMapSize();
                
                // 地图加载完成
                this.map.on('complete', () => {
                    this.hideLoading();
                    this.loadMarkers();
                    // 再次确保尺寸正确
                    setTimeout(() => {
                        this.ensureMapSize();
                        // 在地图加载完成后确保筛选标签存在
                        this.ensureEmbeddedFiltersExist();
                    }, 100);
                    // 初始化主题监听器
                    this.initThemeObserver();
                });
                
                // 地图加载失败
                this.map.on('error', (error) => {
                    this.showError('地图加载失败，请检查网络连接');
                });
                
            } catch (error) {
                this.showError('地图初始化失败: ' + error.message);
            }
        }
        
        bindEvents() {
            const container = this.mapContainer || this.container;
            
            // 控制按钮事件
            container.addEventListener('click', (e) => {
                if (e.target.classList.contains('travel-map-control-btn') || e.target.closest('.travel-map-control-btn')) {
                    const btn = e.target.classList.contains('travel-map-control-btn') ? e.target : e.target.closest('.travel-map-control-btn');
                    const action = btn.getAttribute('data-action');
                    
                    if (action === 'zoom-in') {
                        this.zoomIn();
                    } else if (action === 'zoom-out') {
                        this.zoomOut();
                    } else if (action === 'fullscreen') {
                        this.toggleFullscreen();
                    } else if (action === 'reset') {
                        this.resetView();
                    }
                }
            });
            
            // 弹窗关闭事件
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('travel-map-popup')) {
                    this.closePopup();
                }
                if (e.target.classList.contains('travel-map-popup-close')) {
                    this.closePopup();
                }
            });
            
            // ESC键关闭弹窗
            document.addEventListener('keydown', (e) => {
                if (e.keyCode === 27 && this.popup) {
                    this.closePopup();
                }
            });
            
            // 移动端手势支持
            this.bindMobileGestures();
            
            // 屏幕旋转支持
            this.bindOrientationChange();
        }
        
        bindOrientationChange() {
            window.addEventListener('orientationchange', () => {
                setTimeout(() => {
                    if (this.map) {
                        this.map.getSize();
                        
                        // 移动端旋转后调整缩放级别
                        if (window.innerWidth <= 768) {
                            const currentZoom = this.map.getZoom();
                            if (currentZoom > this.options.zoom) {
                                this.map.setZoom(this.options.zoom);
                            }
                        }
                    }
                }, 300);
            });
            
            window.addEventListener('resize', () => {
                setTimeout(() => {
                    if (this.map) {
                        this.map.getSize();
                    }
                }, 300);
            });
        }
        
        bindMobileGestures() {
            if (!('ontouchstart' in window)) {
                return; // 不是移动设备
            }
            
            let startX = 0;
            let startY = 0;
            const self = this; // 保存实例引用
            
            // 弹窗滑动手势支持 - 使用原生 JavaScript
            document.addEventListener('touchstart', function(e) {
                if (e.target.closest('.travel-map-popup-content')) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                }
            }, true);
            
            document.addEventListener('touchmove', function(e) {
                if (e.target.closest('.travel-map-popup-content')) {
                    e.preventDefault(); // 防止页面滚动
                }
            }, { passive: false, capture: true });
            
            document.addEventListener('touchend', function(e) {
                if (e.target.closest('.travel-map-popup-content')) {
                    const endX = e.changedTouches[0].clientX;
                    const endY = e.changedTouches[0].clientY;
                    const diffX = endX - startX;
                    const diffY = endY - startY;
                    
                    // 向下滑动关闭弹窗
                    if (diffY > 100 && Math.abs(diffX) < 50) {
                        self.closePopup();
                    }
                }
            }, true);
        }
        
        loadMarkers() {
            // 使用原生XMLHttpRequest发送AJAX请求获取标记数据
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('action', 'travel_map_get_markers');
            formData.append('nonce', travelMapAjax.nonce);
            formData.append('status', this.currentFilter);
            
            xhr.open('POST', travelMapAjax.ajaxurl, true);
            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                this.addMarkers(response.data);
                            } else {
                                this.showError('加载标记数据失败');
                            }
                        } catch (e) {
                            this.showError('数据解析失败');
                        }
                    } else {
                        this.showError('网络请求失败');
                    }
                }
            };
            xhr.send(formData);
        }
        
        addMarkers(markersData) {
            this.clearMarkers();
            
            markersData.forEach(markerData => {
                this.addMarker(markerData);
            });
            
            // 自适应视图
            if (this.markers.length > 0) {
                this.fitView();
            }
        }
        
        addMarker(markerData) {
            if (!this.map) return;
            
            const marker = new AMap.Marker({
                position: [markerData.longitude, markerData.latitude],
                title: markerData.title,
                content: this.createMarkerContent(markerData),
                anchor: 'center'
            });
            
            // 存储原始数据
            marker.markerData = markerData;
            
            // 点击事件
            marker.on('click', (e) => {
                // 智能定位：将点击的坐标定位到地图中心偏下位置
                const markerPosition = [parseFloat(markerData.longitude), parseFloat(markerData.latitude)];
                
                // 设置合适的缩放级别
                const currentZoom = this.map.getZoom();
                let targetZoom = currentZoom;
                
                if (currentZoom < 6) {
                    targetZoom = 8;
                } else if (currentZoom >= 6 && currentZoom < 8) {
                    targetZoom = currentZoom + 1;
                } else {
                    targetZoom = Math.min(currentZoom, 8);
                }
                
                // 先设置缩放和基本定位
                this.map.setZoomAndCenter(targetZoom, markerPosition, false, 300);
                
                // 延迟进行偏移调整，确保地图已经完成初始定位
                setTimeout(() => {
                    // 计算偏移：将地图中心向上偏移，让标记点显示在中心偏下位置
                    const mapCenter = this.map.getCenter();
                    const mapBounds = this.map.getBounds();
                    
                    // 计算地图的纬度跨度
                    const latSpan = mapBounds.getNorthEast().lat - mapBounds.getSouthWest().lat;
                    
                    // 向上偏移25%的地图高度（增加偏移量）
                    const offsetLatitude = latSpan * 0.25;
                    
                    // 创建偏移后的坐标（确保使用数值计算）
                    const offsetPosition = [
                        parseFloat(markerData.longitude), 
                        parseFloat(markerData.latitude) + offsetLatitude
                    ];
                    
                    // 验证偏移后的坐标是否有效
                    if (offsetPosition[1] >= -90 && offsetPosition[1] <= 90 && 
                        offsetPosition[0] >= -180 && offsetPosition[0] <= 180) {
                        this.map.setCenter(offsetPosition);
                    }
                    
                    // 再延迟一下显示弹窗，确保地图动画完成
                    setTimeout(() => {
                        this.showMarkerPopup(markerData, marker);
                    }, 200);
                }, 350);
            });
            
            // 添加悬停效果（只对有图片的标记点）
            if (markerData.status === 'visited' && markerData.featured_image) {
                this.addMarkerHoverEffect(marker, markerData);
            }
            
            // 添加到地图
            this.map.add(marker);
            this.markers.push(marker);
        }
        
        addMarkerHoverEffect(marker, markerData) {
            // 获取标记点的DOM元素
            const markerEl = marker.getContent();
            if (markerEl && markerEl.querySelector) {
                const imageEl = markerEl.querySelector('img');
                
                if (imageEl) {
                    // 鼠标进入事件
                    imageEl.addEventListener('mouseenter', () => {
                        this.showHoverPreview(markerData, marker);
                    });
                    
                    // 鼠标离开事件
                    imageEl.addEventListener('mouseleave', () => {
                        this.hideHoverPreview();
                    });
                }
            }
        }
        
        showHoverPreview(markerData, marker) {
            // 移除现有预览
            this.hideHoverPreview();
            
            // 获取标记点在地图上的像素位置
            const pixel = this.map.lngLatToContainer([markerData.longitude, markerData.latitude]);
            
            const preview = document.createElement('div');
            preview.className = 'travel-map-hover-preview';
            preview.innerHTML = `
                <div class="hover-preview-content">
                    <img src="${markerData.featured_image}" alt="${markerData.title}">
                    <div class="hover-preview-title">${markerData.title}</div>
                </div>
            `;
            
            // 计算位置
            const container = this.container;
            const containerRect = container.getBoundingClientRect();
            
            preview.style.position = 'absolute';
            preview.style.left = (containerRect.left + pixel.x - 60) + 'px';
            preview.style.top = (containerRect.top + pixel.y - 120) + 'px';
            preview.style.zIndex = '9999';
            
            document.body.appendChild(preview);
            this.currentHoverPreview = preview;
            
            // 显示动画
            setTimeout(() => {
                preview.classList.add('show');
            }, 10);
        }
        
        hideHoverPreview() {
            if (this.currentHoverPreview) {
                this.currentHoverPreview.classList.remove('show');
                setTimeout(() => {
                    if (this.currentHoverPreview && this.currentHoverPreview.parentNode) {
                        this.currentHoverPreview.parentNode.removeChild(this.currentHoverPreview);
                    }
                    this.currentHoverPreview = null;
                }, 200);
            }
        }
        
        createMarkerContent(markerData) {
            const colors = travelMapAjax.colors || {
                visited: '#ff6b35',
                want_to_go: '#3b82f6',
                planned: '#10b981'
            };
            
            const color = colors[markerData.status] || '#6b7280';
            
            // 如果是"已去"状态且有文章图片，显示图片标记（不显示数字，更美观）
            if (markerData.status === 'visited' && markerData.featured_image) {
                return `
                    <div class="travel-marker travel-marker-with-image" style="
                        width: 28px;
                        height: 28px;
                        border-radius: 50%;
                        overflow: hidden;
                        border: 1px solid ${color};
                        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                        cursor: pointer;
                        position: relative;
                        background: #fff;
                    ">
                        <img src="${markerData.featured_image}" 
                             alt="${markerData.title}" 
                             style="
                                 width: 100%;
                                 height: 100%;
                                 object-fit: cover;
                             ">
                    </div>
                `;
            }
            
            // 默认显示圆形标记（只有没有图片时才显示数字）
            return `
                <div class="travel-marker travel-marker-default" style="
                    width: 24px;
                    height: 24px;
                    background: ${color};
                    border: 1px solid #fff;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: bold;
                    font-size: 10px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                    cursor: pointer;
                ">${markerData.visit_count || '1'}</div>
            `;
        }
        
        showMarkerPopup(markerData, marker) {
            // 保存当前弹窗关联的标记数据，用于地图移动时更新位置
            this.currentPopupMarkerData = markerData;
            
            // 直接显示弹窗（内部会关闭旧弹窗），不再做额外的缩放和定位处理（已在点击事件中处理）
            this.showPopupForMarker(markerData);
            
            // 绑定地图移动事件，使弹窗跟随标记移动
            this.bindPopupMapEvents();
        }
        
        showPopupForMarker(markerData) {
            // 获取标记点在地图上的像素位置
            const pixel = this.map.lngLatToContainer([markerData.longitude, markerData.latitude]);
            
            // 根据状态显示不同的弹窗
            if (markerData.status === 'visited') {
                this.showArticlePopup(markerData, pixel);
            } else {
                this.showSimplePopup(markerData, pixel);
            }
        }
        
        /**
         * 绑定地图事件，使弹窗跟随标记移动
         */
        bindPopupMapEvents() {
            // 移除之前绑定的事件（如果有）
            this.unbindPopupMapEvents();
            
            // 创建事件处理函数
            this._popupMapMoveHandler = () => {
                if (this.popup && this.currentPopupMarkerData) {
                    this.updatePopupPosition();
                }
            };
            
            // 监听地图移动和缩放事件
            this.map.on('move', this._popupMapMoveHandler);
            this.map.on('zoom', this._popupMapMoveHandler);
            this.map.on('zoomchange', this._popupMapMoveHandler);
            this.map.on('resize', this._popupMapMoveHandler);
        }
        
        /**
         * 解绑地图事件
         */
        unbindPopupMapEvents() {
            if (this._popupMapMoveHandler) {
                this.map.off('move', this._popupMapMoveHandler);
                this.map.off('zoom', this._popupMapMoveHandler);
                this.map.off('zoomchange', this._popupMapMoveHandler);
                this.map.off('resize', this._popupMapMoveHandler);
                this._popupMapMoveHandler = null;
            }
        }
        
        /**
         * 更新弹窗位置
         */
        updatePopupPosition() {
            if (!this.popup || !this.currentPopupMarkerData) return;
            
            const markerData = this.currentPopupMarkerData;
            const pixel = this.map.lngLatToContainer([markerData.longitude, markerData.latitude]);
            
            if (pixel && pixel.x !== undefined && pixel.y !== undefined) {
                const popupRect = this.popup.getBoundingClientRect();
                const containerRect = this.container.getBoundingClientRect();
                
                // 计算相对容器的位置
                let left = pixel.x - (popupRect.width / 2);
                let top = pixel.y - popupRect.height - 12;
                
                // 边界检查
                const containerWidth = containerRect.width;
                const containerHeight = containerRect.height;
                
                // 水平边界检查
                if (left < 10) left = 10;
                if (left + popupRect.width > containerWidth - 10) {
                    left = containerWidth - popupRect.width - 10;
                }
                
                // 垂直边界检查
                if (top < 10) {
                    top = pixel.y + 20;
                    this.popup.classList.add('popup-below');
                } else {
                    this.popup.classList.remove('popup-below');
                }
                
                if (top + popupRect.height > containerHeight - 10) {
                    top = Math.max(10, containerHeight - popupRect.height - 10);
                }
                
                this.popup.style.left = left + 'px';
                this.popup.style.top = top + 'px';
            }
        }
        
        showArticlePopup(markerData, pixel) {
            // 获取该地点的所有文章
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('action', 'travel_map_get_location_posts');
            formData.append('nonce', travelMapAjax.nonce);
            formData.append('latitude', markerData.latitude);
            formData.append('longitude', markerData.longitude);
            formData.append('location_name', markerData.title);
            
            xhr.open('POST', travelMapAjax.ajaxurl, true);
            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.data.length > 0) {
                                this.renderArticlePopup(markerData, response.data, pixel);
                            } else {
                                this.showSimplePopup(markerData, pixel);
                            }
                        } catch (e) {
                            this.showSimplePopup(markerData, pixel);
                        }
                    } else {
                        this.showSimplePopup(markerData, pixel);
                    }
                }
            };
            xhr.send(formData);
        }
        
        renderArticlePopup(markerData, articles, pixel) {
            // 按照参考图重新设计：顶部特色图 + 地点信息 + 文章标题列表
            const latestArticle = articles.length > 0 ? articles[0] : null;
            const featuredImage = latestArticle && latestArticle.featured_image ? latestArticle.featured_image : null;
            
            const popupHtml = `
                <div class="travel-map-enhanced-popup travel-map-custom-popup" 
                     data-travel-map-popup="true" 
                     data-no-lightbox="true" 
                     data-no-fancybox="true"
                     data-prevent-gallery="true">
                    <button class="travel-map-popup-close" type="button">&times;</button>
                    
                    ${featuredImage ? `
                    <div class="popup-header-image">
                        <img src="${featuredImage}" alt="${markerData.title}">
                    </div>
                    ` : ''}
                    
                    <div class="popup-location-header">
                        <span class="location-flag">📍</span>
                        <span class="location-name">${markerData.title}</span>
                    </div>
                    
                    <div class="popup-articles-list">
                        ${articles.map(article => `
                            <div class="popup-article-item" data-url="${article.permalink}" data-travel-map-article="true">
                                ${article.title}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            this.showCustomPopup(popupHtml, pixel);
        }
        
        showCustomPopup(html, pixel) {
            // 关闭现有弹窗（但不解绑事件，因为后面会重新绑定）
            this._closePopupOnly();
            
            const popupEl = document.createElement('div');
            popupEl.innerHTML = html;
            this.popup = popupEl.firstElementChild;
            
            // 添加到地图容器
            this.container.appendChild(this.popup);
            
            // 设置基本样式
            this.popup.style.position = 'absolute';
            this.popup.style.zIndex = '10001';
            this.popup.style.pointerEvents = 'auto';
            
            // 计算位置
            if (pixel && pixel.x !== undefined && pixel.y !== undefined) {
                // 获取弹窗尺寸
                this.popup.style.visibility = 'hidden';
                this.popup.style.display = 'block';
                const popupRect = this.popup.getBoundingClientRect();
                
                // 计算初始位置（默认显示在标记点上方，为箭头预留空间）
                // 考虑标记点的实际尺寸，确保箭头指向标记点中心
                // 标记点默认尺寸约为28px（有图片）或24px（默认），中心偏移约14px或12px
                let left = pixel.x - (popupRect.width / 2);
                let top = pixel.y - popupRect.height - 12; // 调整箭头与标记点的距离，考虑标记点半径
                
                // 边界检查
                const containerWidth = this.container.offsetWidth;
                const containerHeight = this.container.offsetHeight;
                
                // 水平边界检查
                if (left < 10) left = 10;
                if (left + popupRect.width > containerWidth - 10) {
                    left = containerWidth - popupRect.width - 10;
                }
                
                // 垂直边界检查：如果上方空间不足，显示在标记点下方
                if (top < 10) {
                    top = pixel.y + 20; // 显示在标记点下方，调整距离
                    this.popup.classList.add('popup-below'); // 添加样式标记
                } else {
                    this.popup.classList.remove('popup-below');
                }
                
                // 确保不超出底部边界
                if (top + popupRect.height > containerHeight - 10) {
                    top = Math.max(10, containerHeight - popupRect.height - 10);
                }
                
                this.popup.style.left = left + 'px';
                this.popup.style.top = top + 'px';
                this.popup.style.visibility = 'visible';
            } else {
                this.popup.style.left = '50%';
                this.popup.style.top = '50%';
                this.popup.style.transform = 'translate(-50%, -50%)';
            }
            
            // 显示动画
            setTimeout(() => {
                this.popup.classList.add('show');
            }, 10);
            
            // 绑定事件
            this.bindCustomPopupEvents();
        }
        
        bindCustomPopupEvents() {
            if (!this.popup) return;
            
            // 绑定关闭按钮
            const closeBtn = this.popup.querySelector('.travel-map-popup-close, .travel-map-simple-popup-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    // 不阻止事件传播，让关闭操作能完成
                    this.closePopup();
                }, false); // 使用冒泡阶段
            }
            
            // 绑定文章点击事件
            const articleItems = this.popup.querySelectorAll('.popup-article-item');
            articleItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    // 不阻止事件传播，让链接正常打开
                    const url = item.getAttribute('data-url');
                    if (url) {
                        window.open(url, '_blank');
                    }
                }, false); // 使用冒泡阶段
            });
            
            // 点击外部关闭弹窗（但不阻止弹窗内部点击）
            const outsideClickHandler = (e) => {
                // 检查弹窗是否存在，避免空指针错误
                if (!this.popup) {
                    document.removeEventListener('click', outsideClickHandler, true);
                    return;
                }
                
                if (!this.popup.contains(e.target)) {
                    this.closePopup();
                    document.removeEventListener('click', outsideClickHandler, true);
                }
            };
            
            setTimeout(() => {
                document.addEventListener('click', outsideClickHandler, true);
            }, 100);
        }
        
        switchToArticle(direction) {
            if (!this.currentArticles || this.currentArticles.length <= 1) return;
            
            const container = this.popup.querySelector('.current-article');
            if (!container) return;
            
            let currentIndex = parseInt(container.getAttribute('data-current-index')) || 0;
            
            if (direction === 'prev') {
                currentIndex = currentIndex > 0 ? currentIndex - 1 : this.currentArticles.length - 1;
            } else {
                currentIndex = currentIndex < this.currentArticles.length - 1 ? currentIndex + 1 : 0;
            }
            
            this.updateCurrentArticle(currentIndex);
        }
        
        switchToArticleByIndex(index) {
            if (!this.currentArticles || index >= this.currentArticles.length) return;
            this.updateCurrentArticle(index);
        }
        
        updateCurrentArticle(index) {
            const article = this.currentArticles[index];
            if (!article) return;
            
            // 更新主文章区域
            const container = this.popup.querySelector('.current-article');
            if (container) {
                container.setAttribute('data-current-index', index);
                
                const titleEl = container.querySelector('.article-title');
                const excerptEl = container.querySelector('.article-excerpt');
                const dateEl = container.querySelector('.article-date');
                const readMoreBtn = container.querySelector('.read-more-btn');
                
                if (titleEl) titleEl.textContent = article.title;
                if (excerptEl) excerptEl.textContent = article.excerpt || '';
                if (dateEl) dateEl.textContent = article.date;
                if (readMoreBtn) readMoreBtn.setAttribute('href', article.permalink);
            }
            
            // 更新头部图片
            const headerImg = this.popup.querySelector('.location-image img');
            if (headerImg && article.featured_image) {
                headerImg.src = article.featured_image;
                headerImg.alt = article.title;
            }
            
            // 更新导航指示器
            const indicator = this.popup.querySelector('.nav-indicator');
            if (indicator) {
                indicator.textContent = `${index + 1}/${this.currentArticles.length}`;
            }
        }
        
        switchArticle(direction) {
            if (!this.currentArticles || this.currentArticles.length <= 1) return;
            
            const container = this.popup.querySelector('.travel-map-article-container');
            if (!container) return;
            
            let currentIndex = parseInt(container.getAttribute('data-current-index')) || 0;
            
            if (direction === 'prev') {
                currentIndex = currentIndex > 0 ? currentIndex - 1 : this.currentArticles.length - 1;
            } else {
                currentIndex = currentIndex < this.currentArticles.length - 1 ? currentIndex + 1 : 0;
            }
            
            const article = this.currentArticles[currentIndex];
            if (article) {
                // 更新弹窗内容
                container.setAttribute('data-current-index', currentIndex);
                
                const titleEl = container.querySelector('.travel-map-popup-title');
                const excerptEl = container.querySelector('.travel-map-popup-excerpt');
                const indicatorEl = this.popup.querySelector('.travel-map-nav-indicator');
                const actionBtn = this.popup.querySelector('.travel-map-popup-btn');
                
                if (titleEl) titleEl.textContent = article.title;
                if (excerptEl) excerptEl.textContent = article.excerpt || '';
                if (indicatorEl) indicatorEl.textContent = `${currentIndex + 1} / ${this.currentArticles.length}`;
                if (actionBtn) actionBtn.setAttribute('onclick', `window.open('${article.permalink}', '_blank')`);
                
                // 更新头图
                const headerImg = this.popup.querySelector('.travel-map-popup-image');
                if (headerImg && article.featured_image) {
                    headerImg.src = article.featured_image;
                    headerImg.alt = article.title;
                } else if (headerImg && !article.featured_image) {
                    headerImg.style.display = 'none';
                }
            }
        }
        
        showSimplePopup(markerData) {
            const statusTexts = {
                visited: '已去',
                want_to_go: '想去',
                planned: '计划'
            };
            
            let contentHtml = '';
            
            if (markerData.status === 'planned') {
                const plannedDate = markerData.planned_date ? `计划日期：${markerData.planned_date}` : '计划日期：未定';
                contentHtml = `<div class="info-item">${plannedDate}</div>`;
                
                if (markerData.description) {
                    contentHtml += `<div class="info-item description">地点描述：${markerData.description}</div>`;
                }
            } else if (markerData.status === 'want_to_go') {
                // 想去状态：显示想去理由和地点描述
                if (markerData.wish_reason) {
                    contentHtml += `<div class="info-item wish-reason">想去理由：${markerData.wish_reason}</div>`;
                }
                
                if (markerData.description) {
                    contentHtml += `<div class="info-item description">地点描述：${markerData.description}</div>`;
                }
                
                // 如果两个字段都为空，显示默认状态
                if (!markerData.wish_reason && !markerData.description) {
                    contentHtml = `<div class="info-item">状态：想去</div>`;
                }
            } else {
                // 其他状态
                contentHtml = `<div class="info-item">状态：${statusTexts[markerData.status] || '未知'}</div>`;
                
                if (markerData.description) {
                    contentHtml += `<div class="info-item description">地点描述：${markerData.description}</div>`;
                }
            }
            
            const popupHtml = `
                <div class="travel-map-simple-popup travel-map-custom-popup" 
                     data-travel-map-popup="true" 
                     data-no-lightbox="true" 
                     data-no-fancybox="true"
                     data-prevent-gallery="true">
                    <button class="travel-map-simple-popup-close" type="button">&times;</button>
                    <div class="travel-map-simple-popup-content">
                        <div class="travel-map-simple-popup-place">${markerData.title}</div>
                        <div class="travel-map-simple-popup-info">
                            ${contentHtml}
                        </div>
                    </div>
                </div>
            `;
            
            // 获取标记点像素位置
            const pixel = this.map.lngLatToContainer([markerData.longitude, markerData.latitude]);
            this.showCustomPopup(popupHtml, pixel);
        }
        
        // 删除了旧的 showSimplePopupNearMarker 方法，现在统一使用 showCustomPopup
        
        preventThemeConflicts() {
            // 在地图容器上添加事件监听，阻止主题的图片浏览器
            const container = this.mapContainer || this.container;
            
            // 强化事件阻止机制 - 对整个地图区域的图片相关事件进行拦截
            const eventTypes = ['click', 'mousedown', 'mouseup', 'dblclick'];
            
            eventTypes.forEach(eventType => {
                container.addEventListener(eventType, (e) => {
                    const target = e.target;
                    
                    // 检查是否是地图标记点击（保留标记功能）
                    const isMarkerClick = target.closest('.amap-marker') || 
                                         target.classList.contains('amap-marker') ||
                                         target.tagName === 'CANVAS' && target.closest('.amap-maps');
                    
                    // 对弹窗内的所有元素进行事件阻止（但允许关闭按钮和文章链接）
                    if (target.closest('.travel-map-custom-popup')) {
                        // 允许关闭按钮的点击事件
                        if (target.classList.contains('travel-map-popup-close') || 
                            target.classList.contains('travel-map-simple-popup-close') ||
                            target.closest('.travel-map-popup-close') ||
                            target.closest('.travel-map-simple-popup-close')) {
                            return; // 不阻止关闭按钮的事件
                        }
                        
                        // 允许文章链接的点击事件
                        if (target.closest('.popup-article') || target.closest('.popup-article-item')) {
                            return; // 不阻止文章点击事件
                        }
                        
                        // 其他弹窗内元素的事件需要阻止
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                    
                    // 对地图区域内的图片元素进行特殊处理
                    if (target.tagName === 'IMG' && !isMarkerClick) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                    
                    // 检查是否是可能触发主题图片浏览器的元素
                    if (target.closest('[data-fancybox]') || 
                        target.closest('[data-lightbox]') ||
                        target.closest('.gallery') ||
                        target.closest('.wp-block-gallery') ||
                        target.classList.contains('attachment-thumbnail') ||
                        target.classList.contains('wp-post-image')) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }, true);
            });
            
            // 额外的全局事件拦截，防止事件冒泡到文档级别
            document.addEventListener('click', (e) => {
                const target = e.target;
                
                // 如果是关闭按钮或文章链接，不阻止
                if (target.classList.contains('travel-map-popup-close') || 
                    target.classList.contains('travel-map-simple-popup-close') ||
                    target.closest('.travel-map-popup-close') ||
                    target.closest('.travel-map-simple-popup-close') ||
                    target.closest('.popup-article') || 
                    target.closest('.popup-article-item')) {
                    return; // 允许这些元素的事件正常冒泡
                }
                
                // 只阻止图片和其他可能触发主题功能的元素
                if (target.closest('.travel-map-container') && 
                    (target.tagName === 'IMG' || 
                     (target.closest('.travel-map-custom-popup') && 
                      !target.closest('.travel-map-popup-close') && 
                      !target.closest('.travel-map-simple-popup-close') &&
                      !target.closest('.popup-article') && 
                      !target.closest('.popup-article-item')))) {
                    e.stopImmediatePropagation();
                }
            }, true);
            
            // 特别针对可能的主题图片浏览器库进行拦截
            const preventLibraries = ['fancybox', 'lightbox', 'photoswipe', 'swipebox', 'magnific'];
            preventLibraries.forEach(lib => {
                if (window[lib] || window[lib.charAt(0).toUpperCase() + lib.slice(1)]) {
                    // 覆盖可能的初始化函数
                    const originalInit = window[lib + 'Init'] || window['init' + lib.charAt(0).toUpperCase() + lib.slice(1)];
                    if (originalInit) {
                        window[lib + 'Init'] = function(...args) {
                            // 检查是否在地图容器内，如果是则不初始化
                            const elements = args[0];
                            if (elements && elements.closest && elements.closest('.travel-map-container')) {
                                return;
                            }
                            return originalInit.apply(this, args);
                        };
                    }
                }
            });
        }
        
        showPopup(html) {
            this.closePopup();
            
            const popupEl = document.createElement('div');
            popupEl.innerHTML = html;
            this.popup = popupEl.firstElementChild;
            document.body.appendChild(this.popup);
            
            setTimeout(() => {
                this.popup.classList.add('show');
            }, 10);
        }
        
        /**
         * 仅关闭弹窗 UI，不解绑事件（用于内部切换弹窗内容时）
         */
        _closePopupOnly() {
            if (this.popup) {
                this.popup.classList.remove('show');
                setTimeout(() => {
                    if (this.popup && this.popup.parentNode) {
                        this.popup.parentNode.removeChild(this.popup);
                    }
                    this.popup = null;
                }, 300);
            }
            
            // 同时关闭简洁弹窗
            const simplePopups = document.querySelectorAll('.travel-map-simple-popup');
            simplePopups.forEach(popup => {
                popup.classList.remove('show');
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 200);
            });
            
            // 关闭所有增强弹窗
            const enhancedPopups = document.querySelectorAll('.travel-map-enhanced-popup');
            enhancedPopups.forEach(popup => {
                popup.classList.remove('show');
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 200);
            });
            
            // 关闭所有自定义弹窗
            const customPopups = document.querySelectorAll('.travel-map-custom-popup');
            customPopups.forEach(popup => {
                popup.classList.remove('show');
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 200);
            });
        }
        
        closePopup() {
            // 解绑地图事件
            this.unbindPopupMapEvents();
            
            // 清除当前弹窗关联的标记数据
            this.currentPopupMarkerData = null;
            
            // 关闭弹窗 UI
            this._closePopupOnly();
        }
        
        clearMarkers() {
            if (this.markers.length > 0) {
                this.map.remove(this.markers);
                this.markers = [];
            }
        }
        
        filterMarkers(status) {
            this.currentFilter = status;
            this.loadMarkers();
        }
        
        fitView() {
            if (this.markers.length > 0) {
                this.map.setFitView(this.markers);
                
                // 限制最大缩放级别，避免过度放大
                setTimeout(() => {
                    const currentZoom = this.map.getZoom();
                    const maxZoom = 6; // 设置最大缩放级别为6，避免过度精细
                    if (currentZoom > maxZoom) {
                        this.map.setZoom(maxZoom);
                    }
                }, 100);
            }
        }
        
        resetView() {
            if (this.map) {
                this.map.setCenter(this.options.center);
                this.map.setZoom(this.options.zoom);
            }
        }
        
        zoomIn() {
            if (this.map) {
                const currentZoom = this.map.getZoom();
                this.map.setZoom(currentZoom + 1);
            }
        }
        
        zoomOut() {
            if (this.map) {
                const currentZoom = this.map.getZoom();
                this.map.setZoom(Math.max(currentZoom - 1, 1)); // 最小缩放级别为1
            }
        }
        
        toggleFullscreen() {
            const element = this.container;
            
            if (!document.fullscreenElement) {
                element.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }
        
        hideLoading() {
            // 先在地图容器中查找
            let loading = this.mapContainer ? this.mapContainer.querySelector('.travel-map-loading') : null;
            
            // 如果没有找到，在整个文档中查找
            if (!loading) {
                loading = document.querySelector('.travel-map-loading');
            }
            
            if (loading) {
                loading.style.display = 'none';
            }
        }
        
        showError(message) {
            
            // 在地图容器中查找 wrapper
            let wrapper = this.mapContainer ? this.mapContainer.querySelector('.travel-map-wrapper') : null;
            
            // 如果没有找到，在整个文档中查找
            if (!wrapper) {
                wrapper = document.querySelector('.travel-map-wrapper');
            }
            
            if (!wrapper) {
                return;
            }
            
            const errorHtml = `
                <div class="travel-map-error">
                    <div class="travel-map-error-icon">⚠️</div>
                    <div class="travel-map-error-message">${message}</div>
                    <div class="travel-map-error-details">
                        <p>可能的解决方案：</p>
                        <ul>
                            <li>检查网络连接是否正常</li>
                            <li>确认高德地图API密钥配置正确</li>
                            <li>刷新页面重试</li>
                        </ul>
                        <button class="travel-map-retry-btn" onclick="location.reload()">刷新页面</button>
                    </div>
                </div>
            `;
            
            wrapper.innerHTML = errorHtml;
        }
        
        ensureMapSize() {
            const mapElement = document.getElementById(this.mapId);
            const wrapperElement = mapElement?.closest('.travel-map-wrapper');
            const containerElement = mapElement?.closest('.travel-map-container');
            
            if (mapElement && wrapperElement && containerElement) {
                // 获取短代码设置的高度值
                const containerStyle = window.getComputedStyle(containerElement);
                let targetHeight = containerStyle.height;
                
                // 如果容器没有明确的高度，使用默认值
                if (targetHeight === 'auto' || targetHeight === '0px') {
                    targetHeight = '500px'; // 使用短代码的默认高度
                }
                
                // 移动端响应式调整
                const isMobile = window.innerWidth <= 768;
                const isSmallMobile = window.innerWidth <= 480;
                
                if (isSmallMobile) {
                    // 小屏幕限制最大高度为350px
                    const heightValue = parseInt(targetHeight);
                    if (heightValue > 350) {
                        targetHeight = '350px';
                    }
                } else if (isMobile) {
                    // 移动端限制最大高度为400px
                    const heightValue = parseInt(targetHeight);
                    if (heightValue > 400) {
                        targetHeight = '400px';
                    }
                }
                
                // 确保包装器和地图元素有正确的高度
                wrapperElement.style.height = targetHeight;
                mapElement.style.height = targetHeight;
                
                // 检查CSS计算结果
                const computedStyle = window.getComputedStyle(mapElement);
                const currentHeight = computedStyle.height;
                
                // 如果高度仍然不正确，强制设置
                if (currentHeight === '0px' || currentHeight === 'auto' || parseInt(currentHeight) < 300) {
                    const fallbackHeight = isMobile ? '400px' : '500px';
                    mapElement.style.height = fallbackHeight;
                    wrapperElement.style.height = fallbackHeight;
                }
                
                // 通知地图更新尺寸
                if (this.map) {
                    setTimeout(() => {
                        this.map.getSize();
                        // 最终检查
                        if (mapElement.offsetHeight < 300) {
                            const finalHeight = isMobile ? '400px' : '500px';
                            mapElement.style.height = finalHeight;
                            wrapperElement.style.height = finalHeight;
                            this.map.getSize();
                        }
                    }, 50);
                }
            }
        }
        
        /**
         * 检测当前主题模式
         */
        detectThemeMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                return 'dark';
            } else if (html.classList.contains('light')) {
                return 'light';
            } else if (html.classList.contains('auto')) {
                // auto模式跟随系统主题
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    return 'dark';
                } else {
                    return 'light';
                }
            }
            // 如果没有明确的class，尝试检测系统主题
            else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light'; // 默认浅色
        }
        
        /**
         * 根据主题模式获取地图样式
         */
        getMapStyleByTheme(themeMode = null) {
            const currentTheme = themeMode || this.detectThemeMode();
            
            // 根据主题自动选择地图样式
            if (currentTheme === 'dark') {
                return 'amap://styles/dark';
            } else {
                return 'amap://styles/light';
            }
        }
        
        /**
         * 初始化主题监听器
         */
        initThemeObserver() {
            const html = document.documentElement;
            
            // 使用MutationObserver监听主题变化
            this.themeObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const newTheme = this.detectThemeMode();
                        this.updateMapTheme(newTheme);
                    }
                });
            });
            
            // 开始监听
            this.themeObserver.observe(html, {
                attributes: true,
                attributeFilter: ['class']
            });
            
            // 也监听系统主题变化
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                const handleSystemThemeChange = () => {
                    // 只有在auto模式或没有明确主题class时才响应系统主题
                    if (html.classList.contains('auto') || 
                        (!html.classList.contains('dark') && !html.classList.contains('light'))) {
                        const newTheme = this.detectThemeMode();
                        this.updateMapTheme(newTheme);
                    }
                };
                
                // 绑定系统主题变化事件
                if (mediaQuery.addEventListener) {
                    mediaQuery.addEventListener('change', handleSystemThemeChange);
                } else {
                    // 兼容老版本浏览器
                    mediaQuery.addListener(handleSystemThemeChange);
                }
            }
        }
        
        /**
         * 确保嵌入式筛选标签存在
         */
        ensureEmbeddedFiltersExist() {
            if (!this.options.showFilterTabs) {
                return;
            }
            
            const existingFilters = this.mapContainer.querySelector('.travel-map-embedded-filters');
            if (!existingFilters) {
                this.addEmbeddedFiltersToExisting();
            } else {
                // 确保可见
                existingFilters.style.display = 'flex';
                existingFilters.style.visibility = 'visible';
                existingFilters.style.opacity = '1';
                
                // 更新激活状态
                this.updateFilterTabsActiveState(existingFilters.parentElement);
            }
        }
        
        /**
         * 更新地图主题
         */
        updateMapTheme(themeMode) {
            if (this.map) {
                const newMapStyle = this.getMapStyleByTheme(themeMode);
                this.map.setMapStyle(newMapStyle);
            }
        }
        
        generateMapId() {
            return `travel-map-${Math.random().toString(36).substr(2, 9)}`;
        }
        
        getMapId() {
            return this.mapId;
        }
        

    }

    // 全局函数，供短代码调用
    window.initTravelMap = function(container, options) {
        return new TravelMap(container, options);
    };
    
    // 暂时移除jQuery插件形式，直到确保没有jQuery依赖问题

})();