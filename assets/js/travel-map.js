/**
 * Travel Map Frontend JavaScript
 *
 * - 标记六形态 + hover 照片揭示
 * - InfoWindow 弹窗
 * - MarkerCluster 聚合 + 点击逐级展开
 * - auto_zoom / 返回按钮 / fitToMarkers
 * - 国家高亮 DistrictLayer.World
 * - 南海十段线
 * - 筛选栏智能显隐 + 行为链
 * - 年度/状态统计面板
 */

(function() {
    'use strict';

    const escapeHtml = (value) => {
        return String(value ?? '').replace(/[&<>"'`]/g, (char) => {
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

    const safeUrl = (value) => {
        const url = String(value ?? '').trim();
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) {
            return url;
        }
        if (/^data:image\/[a-zA-Z0-9.+-]+;base64,/.test(url)) {
            return url;
        }
        return '';
    };

    // ISO2 → SOC(ISO3) 映射（DistrictLayer.World 的 fill 回调入参为 SOC）
    const ISO2_TO_ISO3 = {
        ad:'AND',ae:'ARE',af:'AFG',ag:'ATG',ai:'AIA',al:'ALB',am:'ARM',ao:'AGO',aq:'ATA',ar:'ARG',as:'ASM',at:'AUT',au:'AUS',aw:'ABW',ax:'ALA',az:'AZE',
        ba:'BIH',bb:'BRB',bd:'BGD',be:'BEL',bf:'BFA',bg:'BGR',bh:'BHR',bi:'BDI',bj:'BEN',bl:'BLM',bm:'BMU',bn:'BRN',bo:'BOL',bq:'BES',br:'BRA',bs:'BHS',bt:'BTN',bv:'BVT',bw:'BWA',by:'BLR',bz:'BLZ',
        ca:'CAN',cc:'CCK',cd:'COD',cf:'CAF',cg:'COG',ch:'CHE',ci:'CIV',ck:'COK',cl:'CHL',cm:'CMR',cn:'CHN',co:'COL',cr:'CRI',cu:'CUB',cv:'CPV',cw:'CUW',cx:'CXR',cy:'CYP',cz:'CZE',
        de:'DEU',dj:'DJI',dk:'DNK',dm:'DMA',do:'DOM',dz:'DZA',ec:'ECU',ee:'EST',eg:'EGY',eh:'ESH',er:'ERI',es:'ESP',et:'ETH',
        fi:'FIN',fj:'FJI',fk:'FLK',fm:'FSM',fo:'FRO',fr:'FRA',ga:'GAB',gb:'GBR',gd:'GRD',ge:'GEO',gf:'GUF',gg:'GGY',gh:'GHA',gi:'GIB',gl:'GRL',gm:'GMB',gn:'GIN',gp:'GLP',gq:'GNQ',gr:'GRC',gs:'SGS',gt:'GTM',gu:'GUM',gw:'GNB',gy:'GUY',
        hk:'HKG',hm:'HMD',hn:'HND',hr:'HRV',ht:'HTI',hu:'HUN',id:'IDN',ie:'IRL',il:'ISR',im:'IMN',in:'IND',io:'IOT',iq:'IRQ',ir:'IRN',is:'ISL',it:'ITA',
        je:'JEY',jm:'JAM',jo:'JOR',jp:'JPN',ke:'KEN',kg:'KGZ',kh:'KHM',ki:'KIR',km:'COM',kn:'KNA',kp:'PRK',kr:'KOR',kw:'KWT',ky:'CYM',kz:'KAZ',
        la:'LAO',lb:'LBN',lc:'LCA',li:'LIE',lk:'LKA',lr:'LBR',ls:'LSO',lt:'LTU',lu:'LUX',lv:'LVA',ly:'LBY',
        ma:'MAR',mc:'MCO',md:'MDA',me:'MNE',mf:'MAF',mg:'MDG',mh:'MHL',mk:'MKD',ml:'MLI',mm:'MMR',mn:'MNG',mo:'MAC',mp:'MNP',mq:'MTQ',mr:'MRT',ms:'MSR',mt:'MLT',mu:'MUS',mv:'MDV',mw:'MWI',mx:'MEX',my:'MYS',mz:'MOZ',
        na:'NAM',nc:'NCL',ne:'NER',nf:'NFK',ng:'NGA',ni:'NIC',nl:'NLD',no:'NOR',np:'NPL',nr:'NRU',nu:'NIU',nz:'NZL',
        om:'OMN',pa:'PAN',pe:'PER',pf:'PYF',pg:'PNG',ph:'PHL',pk:'PAK',pl:'POL',pm:'SPM',pn:'PCN',pr:'PRI',ps:'PSE',pt:'PRT',pw:'PLW',py:'PRY',
        qa:'QAT',re:'REU',ro:'ROU',rs:'SRB',ru:'RUS',rw:'RWA',sa:'SAU',sb:'SLB',sc:'SYC',sd:'SDN',se:'SWE',sg:'SGP',sh:'SHN',si:'SVN',sj:'SJM',sk:'SVK',sl:'SLE',sm:'SMR',sn:'SEN',so:'SOM',sr:'SUR',ss:'SSD',st:'STP',sv:'SLV',sx:'SXM',sy:'SYR',sz:'SWZ',
        tc:'TCA',td:'TCD',tf:'ATF',tg:'TGO',th:'THA',tj:'TJK',tk:'TKL',tl:'TLS',tm:'TKM',tn:'TUN',to:'TON',tr:'TUR',tt:'TTO',tv:'TUV',tw:'TWN',tz:'TZA',
        ua:'UKR',ug:'UGA',um:'UMI',us:'USA',uy:'URY',uz:'UZB',va:'VAT',vc:'VCT',ve:'VEN',vg:'VGB',vi:'VIR',vn:'VNM',vu:'VUT',wf:'WLF',ws:'WSM',ye:'YEM',yt:'MYT',za:'ZAF',zm:'ZMB',zw:'ZWE'
    };

    // 南海十段线（MultiLineString，坐标来自分析文档 §2.9）
    const TEN_DASH_LINES = [
        [[109.51763678906526,16.360467782665847],[109.72339159230361,16.05587198177934],[109.8780414893003,15.766823920473868],[109.96506402665503,15.526031073258686],[109.98526818797363,15.335615618596712]],
        [[110.48331454715199,12.431407837351566],[110.48240767589328,12.085792287259398],[110.45136562643113,11.863835000833953],[110.25652028695671,11.393616070326182]],
        [[108.3388949586325,7.26656318024262],[108.30727608084116,6.727803403200289],[108.35631901989032,6.112648053307836]],
        [[111.94112275674237,3.553559321848772],[112.40151782268552,3.646409974664658],[112.92104341055976,3.845112027649191]],
        [[115.69079809651517,7.29016984601141],[116.4095482213759,8.137962397303875]],
        [[118.63503455703679,11.080904139262175],[118.85587024190139,11.457907321145406],[119.10128629647166,12.062751715859875],[119.12181771101825,12.135585760471585]],
        [[119.60808384544805,18.143451232827125],[119.91075760817219,18.77194701315816],[120.11918953031866,19.117669954512905]],
        [[121.40591812413318,20.8001943859176],[122.12216430894797,21.716094829922323]],
        [[122.80328441666389,23.665545127578547],[123.00481138309124,24.74934291726869]],
        [[119.16836075308866,15.107448879733406],[119.16981236678279,15.755038547478351],[119.17823197590195,16.265658015720753]]
    ];

    // 状态标签（done/wish/plan，旧键入参已在服务端归一）
    const STATUS_LABELS = { all: '全部', done: '已去', wish: '想去', plan: '计划' };
    const VALID_FILTERS = ['all', 'done', 'wish', 'plan'];

    // 标记 hover 会放大到 64px（见 CSS .marker:not(.no-hover):hover），
    // 半径 32px 加 10px 间隙，卡片边缘至少要离标记中心 42px 才不会压住放大后的图片。
    const POPUP_MARKER_GAP = 42;
    // 气泡与地图/视窗边界的安全距离
    const POPUP_VIEWPORT_MARGIN = 12;
    // 箭头贴边下限：箭头半宽 8px + 卡片圆角 10px
    const POPUP_ARROW_INSET = 18;

    const BREAKPOINT_MOBILE = 768;
    const MQ_MOBILE = window.matchMedia
        ? window.matchMedia('(max-width: ' + BREAKPOINT_MOBILE + 'px)')
        : null;
    const isMobileViewport = () => MQ_MOBILE ? MQ_MOBILE.matches : window.innerWidth <= BREAKPOINT_MOBILE;

    class TravelMap {
        constructor(container, options) {
            this.container = typeof container === 'string' ? document.querySelector(container) : container;
            if (!this.container) {
                return;
            }

            this.mapId = this.container.id || this.generateMapId();

            const ajaxConfig = (typeof window.travelMapAjax === 'object' && window.travelMapAjax) ? window.travelMapAjax : {};
            const s = ajaxConfig.settings || {};

            this.options = Object.assign({
                zoom: 2,
                center: [116.4074, 39.9042],
                markers: [],
                showFilterTabs: true,
                apiKey: '',
                // 空串而非 'all'：非空即代表调用方显式指定了状态，需压过后台设置项。
                defaultStatus: '',
                filters: null,          // 短代码裁剪的按钮集（数组或逗号分隔字符串）
                clusterRadius: s.clusterRadius || 40,
                clusterLimit: s.clusterLimit || 9,
                autoZoom: s.autoZoom !== false,
                highlightCountry: s.highlightCountry !== false,
                showYearlyStats: s.showYearlyStats !== false,
                showTypeStats: s.showTypeStats !== false,
                defaultFilterStatus: s.defaultFilterStatus || 'all',
                minZoom: s.minZoom || 1,
                maxZoom: s.maxZoom || 12,
                flagsBase: ajaxConfig.flagsBase || '',
                geojsonUrl: ajaxConfig.geojsonUrl || '',
                restUrl: ajaxConfig.restUrl || ''
            }, options);

            this.map = null;
            this.cluster = null;
            this.infoWindow = null;
            this.districtLayer = null;
            this.allFeatures = [];      // GeoJSON features 全量
            this.currentFeatures = [];  // 当前筛选后
            this.filterStatus = 'all';
            this._renderableFilters = null;
            this._defaultStatus = null;
            this.filterBar = null;
            this.statsEl = null;
            this.yearStatsEl = null;
            this.tenDashPolylines = [];
            this.themeObserver = null;

            this.init();
        }

        init() {
            const existingMapDiv = this.container.querySelector('.travel-map');
            if (existingMapDiv && existingMapDiv.id) {
                this.mapId = existingMapDiv.id;
                this.mapContainer = this.container;
            } else if (this.container.classList.contains('travel-map')) {
                this.mapId = this.container.id;
                this.mapContainer = this.container.closest('.travel-map-container') || this.container.parentElement;
            } else {
                this.mapContainer = this.container;
                this.setupContainer();
            }

            this.setupFilterTabs();
            this.setupFullscreenControl();
            this.setupMap();
            this.bindEvents();
            this.observeContainerSize();
            this.syncPopupMaxWidth();
            this.preventThemeConflicts();
        }

        setupContainer() {
            const html = `
                <div class="travel-map-container">
                    <div class="travel-map-wrapper">
                        <div class="travel-map-loading">
                            <div class="travel-map-spinner"></div>
                            <div class="travel-map-loading-text">正在加载地图...</div>
                        </div>
                        <div class="travel-map" id="${this.mapId}"></div>
                        <div class="travel-map-controls">
                            <button class="travel-map-control-btn" data-action="zoom-in" type="button" title="放大" aria-label="放大地图">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="zoom-out" type="button" title="缩小" aria-label="缩小地图">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M19 13H5v-2h14v2z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="back" type="button" title="返回" aria-label="返回总览视图">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                                </svg>
                            </button>
                            <button class="travel-map-control-btn" data-action="fullscreen" type="button" title="全屏" aria-label="全屏显示地图" aria-pressed="false">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            this.container.innerHTML = html;
        }

        // ============ 筛选（§2.6 智能显隐 + 行为链） ============

        parseRequestedFilters() {
            let requested = this.options.filters;
            if (requested == null || requested === '') {
                requested = ['all', 'done', 'wish', 'plan'];
            }
            if (typeof requested === 'string') {
                requested = requested.split(',');
            }
            return requested.map(f => String(f).trim()).filter(f => VALID_FILTERS.includes(f));
        }

        computeStatusCounts(features) {
            const counts = { done: 0, wish: 0, plan: 0 };
            for (const f of features) {
                const st = f.properties && f.properties.status;
                if (Object.prototype.hasOwnProperty.call(counts, st)) {
                    counts[st]++;
                }
            }
            return counts;
        }

        // 数量为 0 的按钮隐藏；状态按钮 ≤1 个有数据时整栏隐藏
        computeRenderableFilters() {
            const counts = this._counts || { done: 0, wish: 0, plan: 0 };
            const requested = this.parseRequestedFilters().length
                ? this.parseRequestedFilters()
                : ['all', 'done', 'wish', 'plan'];
            const statusKeys = ['done', 'wish', 'plan'];
            const requestedStatuses = requested.filter(f => statusKeys.includes(f));
            const nonZeroStatuses = requestedStatuses.filter(s => (counts[s] || 0) > 0);

            if (nonZeroStatuses.length <= 1) {
                this._renderableFilters = [];
                this._defaultStatus = nonZeroStatuses[0] || (requested.includes('all') ? 'all' : requested[0] || 'all');
                return;
            }

            const renderable = requested.filter(f => {
                if (f === 'all') return true;
                if (statusKeys.includes(f)) return (counts[f] || 0) > 0;
                return true;
            });

            this._renderableFilters = renderable;

            // 缺省状态优先级：短代码显式 status → 后台设置项 → 全部 → 第一个有数据状态。
            //
            // 设置项 travel_map_default_filter_status 的取值域含 'all'，语义是
            // 「首次加载激活哪个页签」，其中 'all' 表示 done/wish/plan 的并集。
            // 因此它必须排在硬编码的 'all' 之前，否则设置项永远拿不到控制权
            // —— 这正是此前后台设置在前台失效的原因。
            //
            // fromAttr 不再需要排除 'all'：短代码默认值已改为空串（见 PHP
            // render_map_shortcode），所以 defaultStatus 非空即代表用户显式指定，
            // 显式写 status="all" 也是一次真实选择，应当压过设置项。
            const fromAttr = this.options.defaultStatus || null;
            const fromSetting = this.options.defaultFilterStatus;

            if (fromAttr && renderable.includes(fromAttr)) {
                this._defaultStatus = fromAttr;
            } else if (fromSetting && renderable.includes(fromSetting)) {
                this._defaultStatus = fromSetting;
            } else if (renderable.includes('all')) {
                this._defaultStatus = 'all';
            } else {
                this._defaultStatus = nonZeroStatuses[0] || 'all';
            }
        }

        buildFilterBar() {
            const old = this.mapContainer.querySelector('.travel-map-embedded-filters');
            if (old) old.remove();
            this.filterBar = null;

            this.computeRenderableFilters();
            if (!this.options.showFilterTabs || !this._renderableFilters.length) {
                return;
            }

            const bar = document.createElement('div');
            bar.className = 'travel-map-embedded-filters';
            bar.setAttribute('role', 'tablist');
            bar.setAttribute('aria-label', '按状态筛选地点');
            bar.innerHTML = this._renderableFilters.map(f => {
                const isActive = this.filterStatus === f;
                return `<button type="button"
                        class="travel-map-filter-tab${isActive ? ' active' : ''}"
                        role="tab"
                        aria-selected="${isActive ? 'true' : 'false'}"
                        tabindex="${isActive ? '0' : '-1'}"
                        data-status="${f}">${STATUS_LABELS[f] || f}</button>`;
            }).join('');
            this.mapContainer.querySelector('.travel-map-wrapper').appendChild(bar);
            this.filterBar = bar;
        }

        setupFilterTabs() {
            if (!this.options.showFilterTabs) return;

            const container = this.mapContainer || this.container;

            container.addEventListener('click', (e) => {
                const tab = e.target.closest('.travel-map-filter-tab');
                if (tab && container.contains(tab)) {
                    e.preventDefault();
                    this.selectFilterTab(tab);
                }
            });

            container.addEventListener('keydown', (e) => {
                const tab = e.target.closest('.travel-map-filter-tab');
                if (!tab || !container.contains(tab)) return;

                const tabs = Array.from(container.querySelectorAll('.travel-map-filter-tab'));
                const index = tabs.indexOf(tab);
                if (index === -1) return;

                let nextIndex = -1;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') nextIndex = (index + 1) % tabs.length;
                else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') nextIndex = (index - 1 + tabs.length) % tabs.length;
                else if (e.key === 'Home') nextIndex = 0;
                else if (e.key === 'End') nextIndex = tabs.length - 1;

                if (nextIndex === -1) return;
                e.preventDefault();
                tabs[nextIndex].focus();
                this.selectFilterTab(tabs[nextIndex]);
            });
        }

        selectFilterTab(tab) {
            const status = tab.getAttribute('data-status');
            if (!status || status === this.filterStatus) return;
            this.filterStatus = status;
            this.updateFilterTabsActiveState();
            this.applyFilterChain();
        }

        updateFilterTabsActiveState() {
            if (!this.filterBar) return;
            this.filterBar.querySelectorAll('.travel-map-filter-tab').forEach(tab => {
                const isActive = tab.getAttribute('data-status') === this.filterStatus;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.setAttribute('tabindex', isActive ? '0' : '-1');
            });
        }

        // 筛选切换的完整行为链（§2.6）：过滤 → 重建聚合 → fitToMarkers → 统计 → 国家高亮
        applyFilterChain() {
            this.applyFilter();
            this.renderMarkers();
            if (this.currentFeatures.length) {
                this.fitToMarkers();
            } else {
                // 空结果：回初始中心（§2.6）
                this.map.setZoomAndCenter(this.options.zoom, this.options.center);
            }
            this.updateStatsPanels();
            if (this.options.highlightCountry) {
                this.highlightVisitedCountries();
            }
        }

        applyFilter() {
            if (this.filterStatus === 'all') {
                this.currentFeatures = this.allFeatures;
            } else {
                this.currentFeatures = this.allFeatures.filter(
                    f => f.properties && f.properties.status === this.filterStatus
                );
            }
            this.renderAccessibleList();
        }

        // ============ 地图与图层 ============

        setupMap() {
            if (typeof window.AMap === 'undefined') {
                this.showError('高德地图API未加载，请检查网络连接或API密钥配置');
                return;
            }
            if (!this.options.apiKey) {
                this.showError('请先在插件设置中配置高德地图API密钥');
                return;
            }

            const mapDiv = document.getElementById(this.mapId);
            if (!mapDiv) {
                this.showError('地图容器元素未找到，ID: ' + this.mapId);
                return;
            }

            try {
                // 记下构造时生效的主题，后续 syncMapTheme() 靠它判断是否真需要切样式。
                const initialThemeMode = this.detectThemeMode();
                this._appliedThemeMode = initialThemeMode;

                this.map = new AMap.Map(this.mapId, {
                    zoom: isMobileViewport() ? Math.max(this.options.zoom - 1, this.options.minZoom) : this.options.zoom,
                    center: this.options.center,
                    mapStyle: this.getMapStyleByTheme(initialThemeMode),
                    zooms: [this.options.minZoom, this.options.maxZoom],  // AMap 2.0 用 zooms 数组控制缩放范围
                    showOversea: true,   // 境外详细数据增强项，无权限时无副作用
                    showLabel: true,
                    touchZoom: true,
                    doubleClickZoom: true,
                    scrollWheel: !isMobileViewport(),
                    touchZoomCenter: 1
                });

                // 主题监听与瓦片加载完成无关，必须在构造后立刻绑定。
                // 原来它挂在 'complete' 回调里：complete 之前发生的主题切换会全部丢失，
                // 而 Safari 的加载路径更慢，这个窗口足以漏掉用户的一次切换。
                this.initThemeObserver();

                this.map.on('complete', () => {
                    this._mapReady = true;
                    this.hideLoading();
                    this.loadData();
                    // 构造到 complete 之间可能已经切过主题，这里补一次对齐。
                    this.syncMapTheme();
                });

                this.map.on('error', () => {
                    this.showError('地图加载失败，请检查网络连接');
                });
            } catch (error) {
                this.showError('地图初始化失败: ' + error.message);
            }
        }

        loadData() {
            const url = this.options.geojsonUrl;
            if (!url) {
                this.showError('数据接口未配置');
                return;
            }
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = () => {
                if (xhr.readyState !== 4) return;
                if (xhr.status === 200) {
                    try {
                        const geojson = JSON.parse(xhr.responseText);
                        this.allFeatures = Array.isArray(geojson.features) ? geojson.features : [];
                        this.onDataReady();
                    } catch (e) {
                        this.showError('标记数据解析失败');
                    }
                } else {
                    this.showError('标记数据加载失败');
                }
            };
            xhr.send();
        }

        onDataReady() {
            this._counts = this.computeStatusCounts(this.allFeatures);
            this.buildFilterBar();

            // 初始筛选状态（含回退链）。末位回退用 all 而非 done：
            // 「全部」是并集语义，缺省应展示全部地点。
            let initStatus = this._defaultStatus || 'all';
            if (this.filterBar && this._renderableFilters && this._renderableFilters.length
                && !this._renderableFilters.includes(initStatus)) {
                initStatus = this._renderableFilters[0];
            }
            this.filterStatus = initStatus;
            this.updateFilterTabsActiveState();

            this.applyFilter();
            this.renderMarkers();
            this.buildStatsPanels();
            this.updateStatsPanels();

            if (this.options.autoZoom) {
                this.fitToMarkers();
            } else {
                this.map.setZoomAndCenter(this.options.zoom, this.options.center);
            }

            if (this.options.highlightCountry) {
                this.highlightVisitedCountries();
            }
            this.renderTenDashLines();

            this.mapContainer.classList.add('is-loaded');
        }

        // ============ 聚合渲染（§2.3） ============

        renderMarkers() {
            if (!this.map || !this.cluster) {
                this.initCluster();
                return;
            }
            // MarkerCluster.setData 支持全量替换
            this.cluster.setData(this.currentFeatures.map(f => ({
                lnglat: f.geometry.coordinates,
                __feature: f
            })));
        }

        initCluster() {
            if (typeof AMap.MarkerCluster === 'undefined') {
                this.showError('点聚合插件未加载');
                return;
            }

            this.cluster = new AMap.MarkerCluster(this.map, [], {
                gridSize: this.options.clusterRadius,
                renderClusterMarker: (context) => this.renderClusterMarker(context),
                renderMarker: (context) => this.renderSingleMarker(context)
            });

            // 点击聚合：按子点范围展开视野。
            // setFitView(null) 会把全部覆盖物（十段线/国家图层）都算进视野，
            // 导致"越点越小"，所以用子点坐标显式算范围。
            this.cluster.on('click', (item) => {
                if (!item || item.cluster === undefined) return;
                const clusterData = item.clusterData || (item.cluster && item.cluster.clusterData) || [];
                const lnglats = clusterData
                    .map(d => (d.lnglat ? [d.lnglat.lng !== undefined ? d.lnglat.lng : d.lnglat[0], d.lnglat.lat !== undefined ? d.lnglat.lat : d.lnglat[1]] : null))
                    .filter(Boolean);
                if (!lnglats.length) return;

                // 单点聚合：不缩放，弹窗交给 renderSingleMarker 的 click
                if (lnglats.length === 1) return;

                this.fitToCoords(lnglats);
            });

            this.cluster.setData(this.currentFeatures.map(f => ({
                lnglat: f.geometry.coordinates,
                __feature: f
            })));
        }

        renderClusterMarker(context) {
            const count = Math.min(this.options.clusterLimit, context.count);
            const el = document.createElement('div');
            el.className = 'marker cluster';
            el.setAttribute('data-cardinality', count);
            el.setAttribute('role', 'img');
            el.setAttribute('aria-label', 'Map marker');
            context.marker.setContent(el);
            // MarkerCluster 默认以内容左上角为锚点；显式使用中心点，保证数字气泡
            // 和坐标位置在内容尺寸变化时仍保持一致。
            context.marker.setAnchor('center');
            context.marker.setOffset(new AMap.Pixel(0, 0));
        }

        renderSingleMarker(context) {
            const data = context.data && context.data[0];
            const feature = data && data.__feature;
            if (!feature) {
                context.marker.setContent(document.createElement('div'));
                return;
            }
            context.marker.setContent(this.createMarkerElement(feature.properties));
            // 自定义内容的默认锚点是左上角，不能用 CSS transform 代替地图锚点，
            // 否则 hover 放大和信息窗体箭头都会相对经纬度偏移。
            context.marker.setAnchor('center');
            context.marker.setOffset(new AMap.Pixel(0, 0));
            context.marker.on('click', () => {
                this.openMarkerPopup(feature);
            });
        }

        // ============ 标记六形态（§2.4） ============

        createMarkerElement(props) {
            const status = props.status;
            const images = Array.isArray(props.image) ? props.image : [];
            const photo = safeUrl(images[0]);
            const posts = Array.isArray(props.posts) ? props.posts : [];
            const hasPost = status !== 'done' || posts.length > 0;

            const marker = document.createElement('div');
            marker.className = 'marker travel-marker-hit';
            if (status === 'done') marker.classList.add('marker--done');
            if (status === 'plan') marker.classList.add('marker--plan');
            if (status === 'wish') marker.classList.add('marker--wish');
            if (photo) {
                marker.classList.add('has-photo');
                marker.style.setProperty('--photo', `url("${photo}")`);
            } else {
                marker.classList.add('no-hover');
            }
            if (status === 'done' && posts.length === 0) {
                marker.classList.add('no-post');
            }
            marker.setAttribute('role', 'button');
            marker.setAttribute('tabindex', '0');
            marker.setAttribute('aria-expanded', 'false');
            marker.setAttribute('aria-label', String(props.title || ''));
            return marker;
        }

        // ============ 弹窗（§2.5） ============

        openMarkerPopup(feature) {
            const p = feature.properties || {};
            const posts = Array.isArray(p.posts) ? p.posts : [];
            const images = Array.isArray(p.image) ? p.image : [];
            const cover = safeUrl(images[0]);
            const years = Array.isArray(p.year) ? p.year : [];
            const country = String(p.country || '').toUpperCase();
            const firstCode = country ? country.split(',')[0] : '';
            const flagUrl = firstCode && this.options.flagsBase
                ? safeUrl(this.options.flagsBase + firstCode.toLowerCase() + '.svg') : '';

            let contentHtml = '';
            if (posts.length > 0) {
                contentHtml = posts.map(post => {
                    const href = safeUrl(post.permalink);
                    if (!href) return '';
                    // 完整标题写进 title 属性：CSS 把超长标题省略成一行，hover 显示全文。
                    const title = escapeHtml(post.title);
                    return `<div class="travel-map-popup-link"><a target="_blank" href="${href}" title="${title}">${title}</a></div>`;
                }).join('');
            } else if (p.status === 'plan') {
                contentHtml = `<div class="travel-map-popup-note">计划日期：${escapeHtml(p.plan_date || '未定')}</div>`;
            } else if (p.status === 'wish') {
                contentHtml = `<div class="travel-map-popup-note">想去理由：${escapeHtml(p.wish_reason || '无')}</div>`;
            } else {
                contentHtml = '<div>该地点暂无游记。</div>';
            }

            let yearsHtml = '';
            if (years.length > 0) {
                yearsHtml = '<div class="travel-map-popup-years">'
                    + years.map(y => `<span class="travel-map-year-chip">${escapeHtml(y)}</span>`).join(' ')
                    + '</div>';
            }

            // isCustom 模式下 AMap 不生成 .amap-info-content，内容直接挂在
            // .amap-info-contentContainer 上。所以卡片样式必须挂在自己的根节点上，
            // 不能依赖高德的内部类名，否则整套弹窗 CSS 都不会命中。
            const html =
                '<div class="travel-map-popup">'
                + '<button type="button" class="travel-map-popup-close" aria-label="关闭">×</button>'
                + '<div class="travel-map-popup-header">'
                + (cover ? `<img src="${cover}" alt="${escapeHtml(p.title)}" class="travel-map-popup-cover">` : '')
                + '<div class="travel-map-popup-name">'
                + (flagUrl ? `<img src="${flagUrl}" class="travel-map-popup-flag" alt="${escapeHtml(firstCode)}">` : '')
                + escapeHtml(p.title)
                + '</div></div>'
                + `<div class="travel-map-popup-content">${contentHtml}${yearsHtml}</div>`
                + '</div>';

            const position = feature.geometry.coordinates;

            // 开窗前先按当前容器宽度收敛卡片宽度，避免用旧值测量
            this.syncPopupMaxWidth();

            // 位置在开窗前一次算准：先离屏量尺寸，再据此决定摆上方还是下方、
            // 水平推移多少。不再开完窗用 panBy 补偿（那会改视野，违反规格 §2.5，
            // 且动画期间可能把卡片甩出视窗）。
            const size = this.measurePopupSize(html);
            const placement = this.computePopupPlacement(position, size);

            // 把翻面标记与箭头位置注入卡片根节点。html 先按无状态构建是为了
            // 让上面的离屏测量拿到与最终一致的尺寸（class/style 不影响尺寸）。
            const finalHtml = html.replace(
                '<div class="travel-map-popup">',
                '<div class="travel-map-popup' + (placement.below ? ' is-below' : '') + '"'
                    + ' style="--tm-arrow-left:' + placement.arrowLeft + 'px">'
            );

            // 锚点变了就重建窗体：setAnchor 依赖已存在的 DOM，首次开窗时拿不到，
            // 重建比追时序稳，代价只是一次实例化。
            if (this.infoWindow && this._popupAnchor !== placement.anchor) {
                try { this.infoWindow.close(); } catch (e) { /* no-op */ }
                this.infoWindow = null;
            }
            if (!this.infoWindow) {
                this.infoWindow = new AMap.InfoWindow({
                    isCustom: true,
                    anchor: placement.anchor,
                    // AMap 的 autoMove 实现就是 panBy（见 SDK T.prototype.Oy），
                    // 会动视野，必须关掉
                    autoMove: false
                });
            }
            this._popupAnchor = placement.anchor;
            this._popupOffsetX = placement.offsetX;
            this._popupOffsetY = placement.offsetY;

            try {
                this.infoWindow.setOffset(new AMap.Pixel(placement.offsetX, placement.offsetY));
            } catch (e) { /* no-op */ }

            this.infoWindow.setContent(finalHtml);
            this.infoWindow.open(this.map, position);
            this.verifyPopupPlacement(position);

            // 自定义关闭按钮（每次重开都要重绑；isCustom 模式下 AMap 把内容
            // 包进 .amap-info-contentContainer，按钮的 click 走我们自己的关闭路径）
            const infoEl = this.infoWindow.getDOM
                ? this.infoWindow.getDOM()
                : document.querySelector('.amap-info-contentContainer');
            if (infoEl) {
                const btn = infoEl.querySelector('.travel-map-popup-close');
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.closePopup();
                    });
                }
            }
        }

        /**
         * 离屏量出卡片真实尺寸。
         *
         * 位置必须在开窗前一次算准，所以尺寸也得提前拿到。探针挂在地图容器内，
         * 才能继承 --tm-popup-max-width 与主题变量，量出的值与真实渲染一致。
         */
        measurePopupSize(html) {
            const mapEl = this.mapContainer || this.container;
            const fallback = { width: 240, height: 200 };
            if (!mapEl) return fallback;

            const probe = document.createElement('div');
            probe.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden;pointer-events:none';
            probe.innerHTML = html;
            mapEl.appendChild(probe);

            let size = fallback;
            const card = probe.querySelector('.travel-map-popup');
            if (card) {
                const box = card.getBoundingClientRect();
                if (box.width > 0 && box.height > 0) {
                    size = { width: box.width, height: box.height };
                }
            }
            probe.remove();
            return size;
        }

        /**
         * 算出卡片该摆哪儿：锚点 + 像素偏移 + 箭头横向位置。三件事一起解决，
         * 且全都不动视野（规格 §2.5：点击标记只开窗）。
         *
         *   1. 上方放不下就翻到标记下方。这是目标插件的做法——它给 Mapbox Popup
         *      不传 anchor，_getAnchor() 便按剩余空间在 top/bottom 间自选，
         *      从不移动地图。原来我们固定朝上、出界后 panBy 补偿，既会让刚点的
         *      点跳位置，动画期间还可能把卡片甩出视窗。
         *   2. 顶部预留筛选栏占位。筛选栏与地图 div 是兄弟节点，而卡片在地图 div
         *      内部；AMap 给内部容器加了 transform/z-index 形成层叠上下文，
         *      卡片的 z-index:200 出不了这个上下文，压不过筛选栏的 z-index:10。
         *      层叠改不动，只能靠几何避让。
         *   3. 左右溢出用水平偏移收回，箭头用 --tm-arrow-left 单独留在标记正上方。
         *
         * 可视区取「地图容器 ∩ 浏览器视窗」：页面滚动时地图可能只露出一条，
         * 只按容器判断会把卡片摆到屏幕外。
         */
        computePopupPlacement(position, size) {
            const m = POPUP_VIEWPORT_MARGIN;
            const fallback = {
                anchor: 'bottom-center',
                offsetX: 0,
                offsetY: -POPUP_MARKER_GAP,
                arrowLeft: size.width / 2,
                below: false
            };

            const mapEl = this.mapContainer || this.container;
            if (!mapEl || !this.map || typeof this.map.lngLatToContainer !== 'function') {
                return fallback;
            }

            let px = null;
            try {
                px = this.map.lngLatToContainer(position);
            } catch (e) {
                return fallback;
            }
            if (!px) return fallback;
            const pxX = typeof px.getX === 'function' ? px.getX() : px.x;
            const pxY = typeof px.getY === 'function' ? px.getY() : px.y;
            if (typeof pxX !== 'number' || typeof pxY !== 'number') return fallback;

            const mapBox = mapEl.getBoundingClientRect();

            // 可视上下边界，换算成相对地图容器的坐标
            const viewTop = Math.max(0, -mapBox.top) + m;
            const viewBottom = Math.min(mapBox.height, window.innerHeight - mapBox.top) - m;

            // 顶部再让出筛选栏
            let topLimit = viewTop;
            const bar = mapEl.querySelector('.travel-map-embedded-filters');
            if (bar) {
                const barBox = bar.getBoundingClientRect();
                if (barBox.height > 0) {
                    topLimit = Math.max(topLimit, (barBox.bottom - mapBox.top) + m);
                }
            }

            // 竖向：默认在标记上方，放不下才翻到下方
            const roomAbove = (pxY - POPUP_MARKER_GAP) - topLimit;
            const roomBelow = viewBottom - (pxY + POPUP_MARKER_GAP);
            let below;
            if (roomAbove >= size.height) {
                below = false;
            } else if (roomBelow >= size.height) {
                below = true;
            } else {
                // 两侧都放不下（容器太矮）：取空间大的一侧，卡片可能仍被裁一点，
                // 但不会整张飞出可视区
                below = roomBelow > roomAbove;
            }

            // 横向：卡片以标记为中心，溢出多少往回推多少
            const halfW = size.width / 2;
            let offsetX = 0;
            const leftEdge = pxX - halfW;
            const rightEdge = pxX + halfW;
            if (leftEdge < m) {
                offsetX = m - leftEdge;
            } else if (rightEdge > mapBox.width - m) {
                offsetX = (mapBox.width - m) - rightEdge;
            }

            // 箭头要留在标记正上/正下方：卡片被推了多少，箭头就往回退多少。
            // 夹在 [inset, width-inset] 内，避免箭头跑到圆角外面。
            const arrowLeft = Math.min(
                Math.max(halfW - offsetX, POPUP_ARROW_INSET),
                Math.max(size.width - POPUP_ARROW_INSET, POPUP_ARROW_INSET)
            );

            return {
                anchor: below ? 'top-center' : 'bottom-center',
                offsetX: offsetX,
                offsetY: below ? POPUP_MARKER_GAP : -POPUP_MARKER_GAP,
                arrowLeft: arrowLeft,
                below: below
            };
        }

        /**
         * 开窗后按真实渲染结果校一次横向位置。
         *
         * 离屏量的尺寸与真实渲染绝大多数时候一致，但字体晚加载等情况会差几像素。
         * 这里只用 setOffset 收回横向溢出，不平移地图；竖向翻面不在这里做——
         * 翻面会连带改锚点与箭头方向，与开窗流程互相触发，得不偿失。
         */
        verifyPopupPlacement(position) {
            if (!this.map || !this.infoWindow) return;

            const mapEl = this.mapContainer || this.container;
            if (!mapEl) return;

            // AMap 2.0 用自己的渲染循环异步定位 .amap-info，只等一帧往往量不到，
            // 因此按帧重试直到卡片挂上且尺寸非零。
            let tries = 0;
            const MAX_TRIES = 20;

            const attempt = () => {
                if (!this.map || !this.infoWindow) return;

                const popup = mapEl.querySelector('.travel-map-popup');
                if (!popup || popup.getBoundingClientRect().width === 0) {
                    if (++tries < MAX_TRIES) window.requestAnimationFrame(attempt);
                    return;
                }

                const box = popup.getBoundingClientRect();
                const placement = this.computePopupPlacement(position, {
                    width: box.width,
                    height: box.height
                });

                popup.style.setProperty('--tm-arrow-left', placement.arrowLeft + 'px');

                if (placement.offsetX !== this._popupOffsetX) {
                    this._popupOffsetX = placement.offsetX;
                    try {
                        this.infoWindow.setOffset(
                            new AMap.Pixel(placement.offsetX, this._popupOffsetY)
                        );
                    } catch (e) { /* 老版本无 setOffset，保持开窗时的偏移 */ }
                }
            };

            window.requestAnimationFrame(attempt);
        }

        /**
         * 把「容器宽度 - 2×安全边距」写进 --tm-popup-max-width，
         * 让 .travel-map-popup 在窄容器（如 320px 手机）下自动收缩，
         * 而不是保持 240px 固定宽被容器的 overflow:hidden 裁掉。
         */
        syncPopupMaxWidth() {
            const mapEl = this.mapContainer || this.container;
            if (!mapEl) return;
            const avail = mapEl.clientWidth - POPUP_VIEWPORT_MARGIN * 2;
            if (avail <= 0) return;
            mapEl.style.setProperty('--tm-popup-max-width', Math.min(240, avail) + 'px');
        }

        /**
         * 关闭弹窗。
         *
         * AMap 2.0 isCustom 模式下 infoWindow.close() 只隐藏默认窗体 DOM，
         * 自定义 content 的容器（.amap-info-contentContainer）不一定被清空，
         * 因此 close 后再显式移除残留内容，保证任何路径（×/Esc/换标记）都干净。
         */
        closePopup() {
            if (this.infoWindow) {
                try {
                    this.infoWindow.close();
                } catch (e) { /* no-op */ }

                const container = (this.mapContainer || this.container)
                    .querySelector('.amap-info-contentContainer');
                if (container && container.firstChild) {
                    container.innerHTML = '';
                }
            }
        }

        // ============ 视野 ============

        fitToMarkers() {
            const coords = this.currentFeatures
                .map(f => f.geometry && f.geometry.coordinates)
                .filter(c => Array.isArray(c) && c.length === 2);

            if (coords.length === 1) {
                this.map.setZoomAndCenter(Math.max(this.map.getZoom(), 10), coords[0]);
            } else if (coords.length > 1) {
                this.fitToCoords(coords);
            }
        }

        /**
         * 按给定坐标集合自适应视野（带内边距与缩放封顶）。
         *
         * 不用 setFitView(null)：null 表示"全部覆盖物"，会把十段线、
         * 国家图层都卷进视野导致地图缩小；这里只针对目标点算 bounds，
         * 再以缩放封顶 + 边距换算的方式落到 setBounds 上。
         */
        fitToCoords(lnglats) {
            if (!lnglats || !lnglats.length || !this.map) return;

            let minLng = Infinity, minLat = Infinity, maxLng = -Infinity, maxLat = -Infinity;
            for (const [lng, lat] of lnglats) {
                if (lng < minLng) minLng = lng;
                if (lat < minLat) minLat = lat;
                if (lng > maxLng) maxLng = lng;
                if (lat > maxLat) maxLat = lat;
            }

            // 全部点重合：以该点为中心放大一档
            if (minLng === maxLng && minLat === maxLat) {
                this.map.setZoomAndCenter(Math.min(this.map.getZoom() + 1, this.options.maxZoom), [minLng, minLat]);
                return;
            }

            // 边距换算：把像素 padding 折成经纬度扩展（近似，足够用于视野留白）
            const size = this.map.getSize();
            const w = (size && size.width) || 800;
            const h = (size && size.height) || 550;
            const padding = Math.round(Math.min(h, 550) * 0.12); // 与旧实现观感接近的留白
            const lngSpan = Math.max(maxLng - minLng, 0.01);
            const latSpan = Math.max(maxLat - minLat, 0.01);
            const padLng = lngSpan * (padding / Math.min(w, h)) * 2;
            const padLat = latSpan * (padding / Math.min(w, h)) * 2;

            const bounds = new AMap.Bounds(
                [minLng - padLng, minLat - padLat],
                [maxLng + padLng, maxLat + padLat]
            );
            this.map.setBounds(bounds);

            // setBounds 无 maxZoom 参数，超出封顶再压回
            if (this.map.getZoom() > this.options.maxZoom) {
                this.map.setZoom(this.options.maxZoom);
            }
        }

        backToOverview() {
            if (!this.options.autoZoom) {
                this.map.setZoomAndCenter(this.options.zoom, this.options.center);
            } else if (this.currentFeatures.length) {
                this.fitToMarkers();
            } else {
                this.map.setZoomAndCenter(this.options.zoom, this.options.center);
            }
        }

        // ============ 国家高亮（§2.8） ============

        collectVisitedSOC() {
            const set = new Set();
            for (const f of this.allFeatures) {
                const p = f.properties || {};
                if (p.status !== 'done') continue;
                const raw = String(p.country || '').toUpperCase();
                if (!raw) continue;
                raw.split(/[,，\s]+/).filter(Boolean).forEach(token => {
                    if (token.length === 3) {
                        set.add(token);
                    } else if (token.length === 2 && ISO2_TO_ISO3[token]) {
                        set.add(ISO2_TO_ISO3[token]);
                    }
                });
            }
            return set;
        }

        highlightVisitedCountries() {
            try {
                if (!this.map || typeof AMap.DistrictLayer === 'undefined') return;

                const visited = this.collectVisitedSOC();

                if (!this.districtLayer) {
                    this.districtLayer = new AMap.DistrictLayer.World({
                        zIndex: 1,
                        zooms: [this.options.minZoom, 20],
                        styles: {
                            'fill': (props) => visited.has(props.SOC) ? '#6abf69' : 'transparent',
                            'fill-opacity': 0.35,
                            'coastline-stroke': ['get', 'color'],
                            'nation-stroke': '#2e7d32'
                        }
                    });
                    this.map.add(this.districtLayer);
                } else {
                    // SOC 集合经闭包引用更新后重新应用样式
                    this.districtLayer.setStyles({
                        'fill': (props) => visited.has(props.SOC) ? '#6abf69' : 'transparent',
                        'fill-opacity': 0.35,
                        'coastline-stroke': ['get', 'color'],
                        'nation-stroke': '#2e7d32'
                    });
                }
            } catch (e) {
                console.warn('highlightVisitedCountries error', e);
            }
        }

        // ============ 十段线（§2.9） ============

        renderTenDashLines() {
            try {
                if (!this.map || this.tenDashPolylines.length) return;

                const widthFor = (zoom, stops) => {
                    for (let i = 0; i < stops.length - 1; i++) {
                        const [z1, w1] = stops[i];
                        const [z2, w2] = stops[i + 1];
                        if (zoom <= z1) return w1;
                        if (zoom < z2) return w1 + (w2 - w1) * (zoom - z1) / (z2 - z1);
                    }
                    return stops[stops.length - 1][1];
                };

                const glowStops = [[3,1.2],[5,2.0],[7,2.8],[10,3.6],[14,5.0]];
                const mainStops = [[3,0.5],[5,0.8],[7,1.0],[10,1.4],[14,1.8]];

                const build = () => {
                    const zoom = this.map.getZoom();
                    const glowW = widthFor(zoom, glowStops);
                    const mainW = widthFor(zoom, mainStops);
                    if (!this.tenDashPolylines.length) {
                        TEN_DASH_LINES.forEach(line => {
                            const glow = new AMap.Polyline({
                                path: line, strokeColor: '#a7adb3', strokeOpacity: 0.18,
                                strokeWeight: glowW, zIndex: 50, cursor: 'default'
                            });
                            const main = new AMap.Polyline({
                                path: line, strokeColor: '#9e9e9e', strokeOpacity: 0.7,
                                strokeWeight: mainW, zIndex: 51, cursor: 'default'
                            });
                            this.map.add([glow, main]);
                            this.tenDashPolylines.push({ glow, main });
                        });
                    } else {
                        this.tenDashPolylines.forEach(pair => {
                            pair.glow.setOptions({ strokeWeight: glowW });
                            pair.main.setOptions({ strokeWeight: mainW });
                        });
                    }
                };

                build();
                this.map.on('zoomchange', build);
            } catch (e) {
                console.warn('十段线加载失败', e);
            }
        }

        // ============ 统计面板（§2.7） ============

        buildStatsPanels() {
            const wrapper = this.mapContainer.querySelector('.travel-map-wrapper');
            if (!wrapper) return;

            if (this.options.showTypeStats && !this.statsEl) {
                this.statsEl = document.createElement('div');
                this.statsEl.className = 'travel-map-stats-panel';
                wrapper.appendChild(this.statsEl);
            }
            if (this.options.showYearlyStats && !this.yearStatsEl) {
                this.yearStatsEl = document.createElement('div');
                this.yearStatsEl.className = 'travel-map-year-panel';
                wrapper.appendChild(this.yearStatsEl);
            }
        }

        updateStatsPanels() {
            this.buildStatsPanels();

            if (this.statsEl) {
                const counts = this._counts || { done: 0, wish: 0, plan: 0 };
                const total = counts.done + counts.wish + counts.plan;
                const span = (label, key) =>
                    `<span class="${this.filterStatus === key ? 'active' : ''}">${label}<strong>${counts[key]}</strong></span>`;
                this.statsEl.innerHTML = span('已去', 'done') + span('想去', 'wish') + span('计划', 'plan')
                    + `<span class="total">总计<strong>${total}</strong></span>`;
            }

            if (this.yearStatsEl) {
                const yearCounts = {};
                const yearCountries = {};
                // 年份面板语义 = 「每年到访多少地点」，只统计有合法 4 位年份的标记；
                // wish/plan 状态或缺 years 且无 visit_date 的 done 标记，直接跳过，
                // 不再归入「未知」桶（否则默认「全部」筛选下总会出现一格「未知」，
                // 让用户误以为统计不准）。后端 properties.year 已是字符串数组，
                // 这里再做一次白名单过滤，防止 0000/空串等历史脏值穿透。
                for (const f of this.currentFeatures) {
                    const p = f.properties || {};
                    const rawYears = Array.isArray(p.year) ? p.year : [];
                    const validYears = rawYears
                        .map(y => (y === null || y === undefined) ? '' : String(y).trim())
                        .filter(y => /^\d{4}$/.test(y) && y !== '0000');
                    if (!validYears.length) continue;

                    const countryTokens = String(p.country || '').toUpperCase()
                        .split(/[,，\s]+/).filter(Boolean);

                    for (const y of validYears) {
                        yearCounts[y] = (yearCounts[y] || 0) + 1;
                        if (!yearCountries[y]) yearCountries[y] = new Set();
                        countryTokens.forEach(c => yearCountries[y].add(c));
                    }
                }

                const keys = Object.keys(yearCounts).sort(
                    (a, b) => parseInt(b, 10) - parseInt(a, 10)
                );

                this.yearStatsEl.innerHTML = keys.map(k => {
                    const countryCount = yearCountries[k] ? yearCountries[k].size : 0;
                    const suffix = countryCount > 0 ? ` (${countryCount})` : '';
                    return `<span class="y-item"><em>${escapeHtml(k)}</em><strong>${yearCounts[k]}${suffix}</strong></span>`;
                }).join('') || '<span class="y-item empty">无数据</span>';
            }
        }

        // ============ 通用（容器/事件/主题等，沿用既有实现） ============

        bindEvents() {
            const container = this.mapContainer || this.container;

            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.travel-map-control-btn');
                if (!btn) return;
                const action = btn.getAttribute('data-action');
                if (action === 'zoom-in') this.zoomIn();
                else if (action === 'zoom-out') this.zoomOut();
                else if (action === 'back') this.backToOverview();
                else if (action === 'fullscreen') this.toggleFullscreen();
            });

            document.addEventListener('keydown', (e) => {
                if (e.keyCode === 27 && this.infoWindow) {
                    this.closePopup();
                }
            });

            // 点击地图空白处关闭弹窗（对齐演示站 closeOnClick 体验）
            this.map.on('click', () => {
                if (this.infoWindow) {
                    this.closePopup();
                }
            });

            this.bindOrientationChange();
        }

        bindOrientationChange() {
            const scheduleResize = (adjustZoom) => {
                if (this._resizeTimer) window.clearTimeout(this._resizeTimer);
                this._resizeTimer = window.setTimeout(() => {
                    this._resizeTimer = null;
                    if (!this.map) return;
                    this.refreshMapSize();
                    if (adjustZoom && isMobileViewport()) {
                        const currentZoom = this.map.getZoom();
                        if (currentZoom > this.options.zoom) {
                            this.map.setZoom(this.options.zoom);
                        }
                    }
                }, 200);
            };

            if (window.screen && window.screen.orientation && window.screen.orientation.addEventListener) {
                window.screen.orientation.addEventListener('change', () => scheduleResize(true));
            } else {
                window.addEventListener('orientationchange', () => scheduleResize(true));
            }
            window.addEventListener('resize', () => scheduleResize(false));
        }

        getAccessibleList() {
            const inside = (this.mapContainer || this.container).querySelector('[data-travel-map-a11y-list]');
            if (inside) return inside;
            const parent = (this.mapContainer || this.container).parentElement;
            return parent ? parent.querySelector('[data-travel-map-a11y-list]') : null;
        }

        renderAccessibleList() {
            const list = this.getAccessibleList();
            if (!list) return;

            if (!this.currentFeatures.length) {
                list.innerHTML = '<li>当前筛选条件下没有地点</li>';
                return;
            }

            list.innerHTML = this.currentFeatures.map((f, index) => {
                const p = f.properties || {};
                const label = STATUS_LABELS[p.status] || '地点';
                return `<li><button type="button" class="travel-map-a11y-item" `
                    + `data-travel-map-a11y-index="${index}">`
                    + `${label}：${escapeHtml(p.title || '未命名地点')}</button></li>`;
            }).join('');

            if (!this._a11yListBound) {
                this._a11yListBound = true;
                list.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-travel-map-a11y-index]');
                    if (!btn) return;
                    const idx = parseInt(btn.getAttribute('data-travel-map-a11y-index'), 10);
                    const feature = this.currentFeatures[idx];
                    if (feature) {
                        this.openMarkerPopup(feature);
                    }
                });
            }
        }

        preventThemeConflicts() {
            const container = this.mapContainer || this.container;
            const eventTypes = ['click', 'mousedown', 'mouseup', 'dblclick'];

            eventTypes.forEach(eventType => {
                container.addEventListener(eventType, (e) => {
                    const target = e.target;
                    const isMarkerClick = target.closest('.amap-marker')
                        || target.classList.contains('amap-marker')
                        || (target.tagName === 'CANVAS' && target.closest('.amap-maps'));

                    // 弹窗内允许链接与关闭按钮
                    if (target.closest('.amap-info')) {
                        if (target.closest('.travel-map-popup-close') || target.closest('a')) {
                            return;
                        }
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }

                    if (target.tagName === 'IMG' && !isMarkerClick) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }

                    if (target.closest('[data-fancybox]') || target.closest('[data-lightbox]')
                        || target.closest('.gallery') || target.closest('.wp-block-gallery')
                        || target.classList.contains('attachment-thumbnail')
                        || target.classList.contains('wp-post-image')) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }, true);
            });

            document.addEventListener('click', (e) => {
                const target = e.target;
                if (target.closest('.travel-map-popup-close') || (target.closest('.amap-info') && target.closest('a'))) {
                    return;
                }
                if (target.closest('.travel-map-container') && target.tagName === 'IMG') {
                    e.stopImmediatePropagation();
                }
            }, true);
        }

        zoomIn() {
            if (this.map) this.map.setZoom(this.map.getZoom() + 1);
        }

        zoomOut() {
            if (this.map) this.map.setZoom(Math.max(this.map.getZoom() - 1, this.options.minZoom));
        }

        supportsFullscreen() {
            const el = this.container;
            if (!el) return false;
            const hasApi = !!(el.requestFullscreen || el.webkitRequestFullscreen);
            const enabled = document.fullscreenEnabled || document.webkitFullscreenEnabled;
            return hasApi && !!enabled;
        }

        setupFullscreenControl() {
            const container = this.mapContainer || this.container;
            const btn = container ? container.querySelector('.travel-map-control-btn[data-action="fullscreen"]') : null;
            if (!btn) return;

            if (!this.supportsFullscreen()) {
                btn.style.display = 'none';
                btn.setAttribute('hidden', 'hidden');
                return;
            }

            if (!this._fullscreenChangeHandler) {
                this._fullscreenChangeHandler = () => {
                    const active = document.fullscreenElement || document.webkitFullscreenElement;
                    btn.setAttribute('aria-pressed', active === this.container ? 'true' : 'false');
                    if (this.map) {
                        window.setTimeout(() => this.refreshMapSize(), 100);
                    }
                };
                document.addEventListener('fullscreenchange', this._fullscreenChangeHandler);
                document.addEventListener('webkitfullscreenchange', this._fullscreenChangeHandler);
            }
        }

        toggleFullscreen() {
            const el = this.container;
            if (!this.supportsFullscreen()) return;

            const current = document.fullscreenElement || document.webkitFullscreenElement;
            if (!current) {
                const request = el.requestFullscreen || el.webkitRequestFullscreen;
                if (request) {
                    const result = request.call(el);
                    if (result && typeof result.catch === 'function') result.catch(() => {});
                }
            } else {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (exit) {
                    const result = exit.call(document);
                    if (result && typeof result.catch === 'function') result.catch(() => {});
                }
            }
        }

        hideLoading() {
            let loading = this.mapContainer ? this.mapContainer.querySelector('.travel-map-loading') : null;
            if (!loading) loading = document.querySelector('.travel-map-loading');
            if (loading) loading.style.display = 'none';
        }

        showError(message) {
            let wrapper = this.mapContainer ? this.mapContainer.querySelector('.travel-map-wrapper') : null;
            if (!wrapper) wrapper = document.querySelector('.travel-map-wrapper');
            if (!wrapper) return;

            const safeMessage = escapeHtml(message);
            wrapper.innerHTML = `
                <div class="travel-map-error">
                    <div class="travel-map-error-icon">⚠️</div>
                    <div class="travel-map-error-message">${safeMessage}</div>
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
        }

        /**
         * 通知地图重算尺寸
         *
         * ResizeObserver 会在首次 observe 时立即回调，此时 AMap 内部视图尚未建立，
         * 直接调 getSize() 会抛 "Cannot read properties of undefined (reading 'getStatus')"。
         * 因此以 complete 事件置位的 _mapReady 为门槛，并兜住地图销毁等边界情况。
         */
        refreshMapSize() {
            // 容器宽度与地图就绪无关，先同步弹窗宽度上限
            this.syncPopupMaxWidth();
            if (!this.map || !this._mapReady) return;
            try {
                this.map.getSize();
            } catch (e) {}
        }

        observeContainerSize() {
            if (typeof ResizeObserver === 'undefined') return;
            const target = this.mapContainer || this.container;
            if (!target) return;

            this._resizeObserver = new ResizeObserver(() => {
                if (this._roTimer) window.clearTimeout(this._roTimer);
                this._roTimer = window.setTimeout(() => {
                    this._roTimer = null;
                    this.refreshMapSize();
                }, 120);
            });
            this._resizeObserver.observe(target);
        }

        detectThemeMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) return 'dark';
            if (html.classList.contains('light')) return 'light';
            if (html.classList.contains('auto')
                && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        }

        getMapStyleByTheme(themeMode = null) {
            return (themeMode || this.detectThemeMode()) === 'dark'
                ? 'amap://styles/dark'
                : 'amap://styles/light';
        }

        initThemeObserver() {
            // 'complete' 事件在某些路径下可能触发多次，重复绑定会让同一次
            // 主题切换重复调用 setMapStyle。
            if (this._themeObserverBound) return;
            this._themeObserverBound = true;

            const html = document.documentElement;
            this.themeObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        this.syncMapTheme();
                    }
                });
            });
            this.themeObserver.observe(html, { attributes: true, attributeFilter: ['class'] });

            if (window.matchMedia) {
                // 必须把 MediaQueryList 挂到实例上持久持有：WebKit 会回收没有强引用的
                // MediaQueryList，连带静默丢掉它的 change 监听（Chromium 不会）。
                // 原来它是局部 const，函数返回后即可被回收，Safari 下跟随系统深浅色因此失效。
                this._themeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                this._themeMediaHandler = () => {
                    if (html.classList.contains('auto')
                        || (!html.classList.contains('dark') && !html.classList.contains('light'))) {
                        this.syncMapTheme();
                    }
                };
                const mq = this._themeMediaQuery;
                if (mq.addEventListener) mq.addEventListener('change', this._themeMediaHandler);
                else mq.addListener(this._themeMediaHandler);
            }

            // 兜底对齐：Safari 在标签页不可见、窗口失焦或 bfcache 恢复期间可能不投递
            // prefers-color-scheme 的 change 事件，回到前台后主题会永久停在旧值。
            // 用户切换 macOS 外观必然经过「窗口失焦 → 回焦」，focus 是最可靠的补救点。
            this._themeResyncHandler = () => {
                if (document.visibilityState === 'hidden') return;
                this.syncMapTheme();
            };
            document.addEventListener('visibilitychange', this._themeResyncHandler);
            window.addEventListener('pageshow', this._themeResyncHandler);
            window.addEventListener('focus', this._themeResyncHandler);
        }

        // 只在检测结果与已生效值不同时才真正切样式，避免重复 setMapStyle。
        syncMapTheme() {
            const themeMode = this.detectThemeMode();
            if (themeMode === this._appliedThemeMode) return;
            this.updateMapTheme(themeMode);
        }

        updateMapTheme(themeMode) {
            if (!this.map) return;
            this._appliedThemeMode = themeMode;
            this.map.setMapStyle(this.getMapStyleByTheme(themeMode));
        }

        generateMapId() {
            return `travel-map-${Math.random().toString(36).substr(2, 9)}`;
        }

        getMapId() {
            return this.mapId;
        }
    }

    // 全局函数，供短代码调用（契约不变）
    window.initTravelMap = function(container, options) {
        return new TravelMap(container, options);
    };
})();
