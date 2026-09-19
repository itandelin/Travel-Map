'use strict';
// 视野自适应纯计算模块单元测试。运行：node tests/compute-fit-view.test.js
const assert = require('node:assert');
const geo = require('../assets/js/travel-map-geo.js');

let passed = 0;
function test(name, fn) {
  fn();
  passed++;
  console.log('  ok - ' + name);
}

// 1. 投影往返一致
test('lngLatToWorld / worldToLngLat 往返一致', () => {
  const p = geo.lngLatToWorld(116.4, 39.9);
  const back = geo.worldToLngLat(p.x, p.y);
  assert.ok(Math.abs(back.lng - 116.4) < 1e-6);
  assert.ok(Math.abs(back.lat - 39.9) < 1e-6);
});

// 2. 空集返回 null
test('空坐标返回 null', () => {
  assert.strictEqual(geo.computeFitView([], {}), null);
});

// 3. zoom 始终在 [minZoom,maxZoom]
test('全球散布点 zoom 不超过 maxZoom', () => {
  const coords = [[-170, -60], [10, 20], [120, 40], [179, -10]];
  const r = geo.computeFitView(coords, { padding: 60, centering: 'centroid', minZoom: 1, maxZoom: 12, viewW: 900, viewH: 550 });
  assert.ok(r.zoom >= 1 && r.zoom <= 12, 'zoom=' + r.zoom);
});

// 4. 对称点质心==bboxCenter，与 bbox 模式中心一致
test('对称点 centroid 与 bbox 中心一致', () => {
  const coords = [[100, 30], [120, 30], [110, 20], [110, 40]];
  const c = geo.computeFitView(coords, { padding: 60, centering: 'centroid', minZoom: 1, maxZoom: 18, viewW: 900, viewH: 550 });
  const b = geo.computeFitView(coords, { padding: 60, centering: 'bbox', minZoom: 1, maxZoom: 18, viewW: 900, viewH: 550 });
  assert.ok(Math.abs(c.center[0] - b.center[0]) < 1e-9);
  assert.ok(Math.abs(c.center[1] - b.center[1]) < 1e-9);
});

// 5. 离群点：质心偏移被钳制在半跨 25% 内
test('离群点质心偏移不超过半跨25%', () => {
  const coords = [[10, 50], [11, 51], [12, 50], [13, 51], [150, -30]]; // 欧洲簇 + 远端离群
  const r = geo.computeFitView(coords, { padding: 60, centering: 'centroid', minZoom: 1, maxZoom: 18, viewW: 900, viewH: 550 });
  const lngs = coords.map(c => c[0]);
  const halfLng = (Math.max(...lngs) - Math.min(...lngs)) / 2;
  const bboxCenterLng = (Math.max(...lngs) + Math.min(...lngs)) / 2;
  const centroidLng = lngs.reduce((a, b) => a + b, 0) / lngs.length;
  const rawShift = centroidLng - bboxCenterLng;
  const actualShift = r.center[0] - bboxCenterLng;
  assert.ok(Math.abs(actualShift) <= 0.25 * halfLng + 1e-9, 'shift=' + actualShift);
  if (Math.abs(rawShift) > 0.25 * halfLng) {
    assert.ok(Math.abs(actualShift) < Math.abs(rawShift));
  }
});

// 6. 跨 180° 降级为 bbox 居中
test('跨180度经线降级为bbox中心', () => {
  const coords = [[-179, 10], [179, 20], [178, 15]];
  const r = geo.computeFitView(coords, { padding: 60, centering: 'centroid', minZoom: 1, maxZoom: 18, viewW: 900, viewH: 550 });
  const lngs = coords.map(c => c[0]);
  const bboxCenterLng = (Math.max(...lngs) + Math.min(...lngs)) / 2;
  assert.ok(Math.abs(r.center[0] - bboxCenterLng) < 1e-9);
});

// 7. 极窄视口不产生 NaN/Infinity
test('极窄视口留白兜底不产生非法值', () => {
  const coords = [[100, 30], [120, 40]];
  const r = geo.computeFitView(coords, { padding: 60, centering: 'centroid', minZoom: 1, maxZoom: 18, viewW: 40, viewH: 30 });
  assert.ok(Number.isFinite(r.zoom));
  assert.ok(r.zoom >= 1 && r.zoom <= 18);
});

// 8. 留白有效性：视口越大允许越放大
test('视口越大允许越放大', () => {
  const coords = [[100, 30], [101, 31]];
  const small = geo.computeFitView(coords, { padding: 60, centering: 'bbox', minZoom: 1, maxZoom: 20, viewW: 400, viewH: 400 });
  const big = geo.computeFitView(coords, { padding: 60, centering: 'bbox', minZoom: 1, maxZoom: 20, viewW: 1200, viewH: 1200 });
  assert.ok(big.zoom > small.zoom, small.zoom + ' -> ' + big.zoom);
});

console.log('\n' + passed + ' tests passed');
