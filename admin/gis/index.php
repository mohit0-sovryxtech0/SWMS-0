<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'GIS Mapping';
$breadcrumbs = [['label' => 'GIS Mapping']];
RBAC::requirePermission('gis.view');

require_once __DIR__ . '/../includes/header.php';

$layers = db()->fetchAll("SELECT * FROM gis_layers ORDER BY id ASC");
$wards = range(1, 20);

$extraCss = <<<'CSS'
<style>
html, body { height: 100%; }
.main-content { padding: 0 !important; }
.page-content { padding: 0 !important; margin: 0 !important; }
.page-header { display: none; }
#map { height: calc(100vh - 60px); width: 100%; z-index: 1; }
.gis-container { display: flex; height: calc(100vh - 60px); position: relative; }
.gis-sidebar { width: 340px; min-width: 340px; background: #fff; border-right: 1px solid #dee2e6; display: flex; flex-direction: column; overflow: hidden; z-index: 2; }
.gis-sidebar-header { padding: 14px 16px; border-bottom: 1px solid #dee2e6; background: #f8f9fa; }
.gis-sidebar-header h6 { margin: 0; font-weight: 600; font-size: 14px; }
.gis-sidebar-body { flex: 1; overflow-y: auto; padding: 8px 0; }
.gis-map-area { flex: 1; position: relative; }
.layer-group { border-bottom: 1px solid #f0f0f0; }
.layer-group-header { display: flex; align-items: center; padding: 10px 16px; cursor: pointer; transition: background 0.15s; user-select: none; }
.layer-group-header:hover { background: #f8f9fa; }
.layer-group-header .form-check { margin: 0; padding: 0; min-height: auto; }
.layer-group-header .form-check-input { cursor: pointer; margin: 0; float: none; }
.layer-color { width: 14px; height: 14px; border-radius: 50%; margin: 0 10px; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.1); }
.layer-label { flex: 1; font-size: 13px; font-weight: 500; color: #333; }
.layer-badge { font-size: 10px; padding: 1px 7px; border-radius: 10px; background: #e9ecef; color: #666; }
.search-box-gis { position: relative; }
.search-box-gis input { height: 36px; font-size: 13px; padding-left: 34px; border-radius: 6px; border: 1.5px solid #dee2e6; }
.search-box-gis input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(24,28,184,0.1); }
.search-box-gis i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #999; font-size: 14px; }
.search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #dee2e6; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-height: 260px; overflow-y: auto; display: none; z-index: 1000; }
.search-result-item { padding: 8px 14px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 8px; }
.search-result-item:hover { background: #f0f4ff; }
.search-result-item:last-child { border-bottom: none; }
.search-result-item .sr-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; flex-shrink: 0; }
.search-result-item .sr-label { flex: 1; }
.search-result-item .sr-label .name { font-weight: 500; }
.search-result-item .sr-label .sub { font-size: 11px; color: #888; }
.legend-control { background: #fff; padding: 10px 14px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.15); font-size: 12px; min-width: 160px; }
.legend-control h6 { font-size: 12px; font-weight: 600; margin: 0 0 6px 0; padding-bottom: 4px; border-bottom: 1px solid #eee; }
.legend-item { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.legend-line { width: 20px; height: 3px; border-radius: 2px; flex-shrink: 0; }
.legend-polygon { width: 14px; height: 10px; border-radius: 2px; flex-shrink: 0; opacity: 0.5; }
.leaflet-popup-content-wrapper { border-radius: 8px; }
.leaflet-popup-content { margin: 10px 14px; font-size: 13px; min-width: 200px; }
.leaflet-popup-content .popup-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.leaflet-popup-content .popup-detail { display: flex; gap: 6px; padding: 1px 0; }
.leaflet-popup-content .popup-detail .label { color: #888; min-width: 70px; }
.gis-actions { padding: 10px 16px; border-top: 1px solid #dee2e6; display: flex; gap: 6px; flex-wrap: wrap; }
.gis-actions .btn { font-size: 12px; padding: 4px 10px; }
.coord-display { position: absolute; bottom: 8px; left: 8px; background: rgba(255,255,255,0.92); padding: 4px 10px; border-radius: 4px; font-size: 11px; color: #666; z-index: 1000; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
@media (max-width: 768px) {
    .gis-sidebar { position: absolute; left: -340px; top: 0; bottom: 0; transition: left 0.25s; z-index: 1001; }
    .gis-sidebar.open { left: 0; }
    .gis-toggle-btn { display: flex !important; }
    #map { height: calc(100vh - 60px); }
}
.gis-toggle-btn { display: none; position: absolute; top: 10px; left: 10px; z-index: 999; background: #fff; border: none; border-radius: 4px; padding: 6px 10px; box-shadow: 0 1px 5px rgba(0,0,0,0.2); cursor: pointer; }
</style>
CSS;

$extraJs = <<<'JS'
<script>
var map, layerGroups = {}, allMarkers = [], wardLayers = [], allPolylines = [];

var colors = {
    consumer: '#181CB8',
    pipeline: '#2196F3',
    tank: '#4CAF50',
    pump: '#FF9800',
    valve: '#F44336',
    service_area: '#9C27B0',
    ward_boundary: '#607D8B'
};

var icons = {
    consumer: L.divIcon({ className: 'custom-marker', html: '<div style="background:'+colors.consumer+';width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="fas fa-user"></i></div>', iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -14] }),
    tank: L.divIcon({ className: 'custom-marker', html: '<div style="background:'+colors.tank+';width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="fas fa-tint"></i></div>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16] }),
    pump: L.divIcon({ className: 'custom-marker', html: '<div style="background:'+colors.pump+';width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="fas fa-cogs"></i></div>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16] }),
    valve: L.divIcon({ className: 'custom-marker', html: '<div style="background:'+colors.valve+';width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="fas fa-toggle-on"></i></div>', iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -14] })
};

function initMap() {
    map = L.map('map', {
        center: [27.7172, 85.3240],
        zoom: 13,
        zoomControl: true,
        attributionControl: true
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
        minZoom: 5
    }).addTo(map);

    L.control.scale({ position: 'bottomright', metric: true, imperial: false }).addTo(map);

    var legend = L.control({ position: 'bottomleft' });
    legend.onAdd = function() {
        var div = L.DomUtil.create('div', 'legend-control');
        div.innerHTML = '<h6>Map Legend</h6>' +
            '<div class="legend-item"><span class="legend-dot" style="background:'+colors.consumer+'"></span> Consumers</div>' +
            '<div class="legend-item"><span class="legend-line" style="background:'+colors.pipeline+'"></span> Pipelines</div>' +
            '<div class="legend-item"><span class="legend-dot" style="background:'+colors.tank+'"></span> Water Tanks</div>' +
            '<div class="legend-item"><span class="legend-dot" style="background:'+colors.pump+'"></span> Pump Stations</div>' +
            '<div class="legend-item"><span class="legend-dot" style="background:'+colors.valve+'"></span> Valves</div>' +
            '<div class="legend-item"><span class="legend-polygon" style="background:'+colors.service_area+'"></span> Service Area</div>' +
            '<div class="legend-item"><span class="legend-polygon" style="background:'+colors.ward_boundary+'"></span> Ward Boundaries</div>';
        return div;
    };
    legend.addTo(map);

    map.on('mousemove', function(e) {
        document.getElementById('coordDisplay').textContent =
            'Lat: ' + e.latlng.lat.toFixed(6) + ' | Lng: ' + e.latlng.lng.toFixed(6);
    });

    loadMapData();
}

function loadMapData() {
    $.getJSON(ADMIN_URL + 'gis/map-data.php', function(res) {
        if (!res.success) return;
        var data = res.data;

        if (data.consumers) {
            layerGroups.consumer = L.layerGroup().addTo(map);
            data.consumers.forEach(function(c) {
                if (!c.latitude || !c.longitude) return;
                var m = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], { icon: icons.consumer });
                var statusBadge = c.status === 'active' ? '<span class="badge bg-success">Active</span>' :
                    c.status === 'suspended' ? '<span class="badge bg-warning">Suspended</span>' :
                    c.status === 'inactive' ? '<span class="badge bg-secondary">Inactive</span>' :
                    '<span class="badge bg-danger">Disconnected</span>';
                m.bindPopup(
                    '<div class="popup-title"><i class="fas fa-user text-primary me-1"></i>' + escapeHtml(c.consumer_no) + '</div>' +
                    '<div class="popup-detail"><span class="label">Name:</span>' + escapeHtml(c.full_name) + '</div>' +
                    '<div class="popup-detail"><span class="label">Mobile:</span>' + escapeHtml(c.mobile) + '</div>' +
                    '<div class="popup-detail"><span class="label">Ward:</span>' + (c.ward_no || '-') + '</div>' +
                    '<div class="popup-detail"><span class="label">Status:</span>' + statusBadge + '</div>' +
                    '<div class="mt-2"><a href="' + ADMIN_URL + 'consumers/view.php?id=' + c.id + '" class="btn btn-sm btn-primary" target="_blank"><i class="fas fa-external-link-alt"></i> View Profile</a></div>'
                );
                m._layerType = 'consumer';
                m._searchText = (c.consumer_no + ' ' + c.full_name + ' ' + c.mobile).toLowerCase();
                m._searchName = c.full_name;
                m._searchSub = c.consumer_no + ' | ' + c.mobile;
                allMarkers.push(m);
                layerGroups.consumer.addLayer(m);
            });
            document.getElementById('consumerCount').textContent = data.consumers.length;
        }

        if (data.tanks) {
            layerGroups.tank = L.layerGroup().addTo(map);
            data.tanks.forEach(function(t) {
                if (!t.latitude || !t.longitude) return;
                var m = L.marker([parseFloat(t.latitude), parseFloat(t.longitude)], { icon: icons.tank });
                m.bindPopup(
                    '<div class="popup-title"><i class="fas fa-tint text-success me-1"></i>' + escapeHtml(t.name) + '</div>' +
                    '<div class="popup-detail"><span class="label">Type:</span>' + (t.tank_type || 'N/A') + '</div>' +
                    '<div class="popup-detail"><span class="label">Capacity:</span>' + (t.capacity_liters ? t.capacity_liters.toLocaleString() + ' L' : 'N/A') + '</div>' +
                    '<div class="popup-detail"><span class="label">Status:</span>' + getBadge(t.status) + '</div>' +
                    (t.asset_code ? '<div class="popup-detail"><span class="label">Code:</span>' + escapeHtml(t.asset_code) + '</div>' : '')
                );
                allMarkers.push(m);
                layerGroups.tank.addLayer(m);
            });
            document.getElementById('tankCount').textContent = data.tanks.length;
        }

        if (data.pumps) {
            layerGroups.pump = L.layerGroup().addTo(map);
            data.pumps.forEach(function(p) {
                if (!p.latitude || !p.longitude) return;
                var m = L.marker([parseFloat(p.latitude), parseFloat(p.longitude)], { icon: icons.pump });
                m.bindPopup(
                    '<div class="popup-title"><i class="fas fa-cogs text-warning me-1"></i>' + escapeHtml(p.name) + '</div>' +
                    '<div class="popup-detail"><span class="label">Type:</span>' + (p.asset_type || 'Pump') + '</div>' +
                    '<div class="popup-detail"><span class="label">Status:</span>' + getBadge(p.status) + '</div>' +
                    (p.asset_code ? '<div class="popup-detail"><span class="label">Code:</span>' + escapeHtml(p.asset_code) + '</div>' : '')
                );
                allMarkers.push(m);
                layerGroups.pump.addLayer(m);
            });
            document.getElementById('pumpCount').textContent = data.pumps.length;
        }

        if (data.valves) {
            layerGroups.valve = L.layerGroup().addTo(map);
            data.valves.forEach(function(v) {
                if (!v.latitude || !v.longitude) return;
                var m = L.marker([parseFloat(v.latitude), parseFloat(v.longitude)], { icon: icons.valve });
                m.bindPopup(
                    '<div class="popup-title"><i class="fas fa-toggle-on text-danger me-1"></i>' + escapeHtml(v.name) + '</div>' +
                    '<div class="popup-detail"><span class="label">Type:</span>' + (v.asset_type || 'Valve') + '</div>' +
                    '<div class="popup-detail"><span class="label">Status:</span>' + getBadge(v.status) + '</div>' +
                    (v.asset_code ? '<div class="popup-detail"><span class="label">Code:</span>' + escapeHtml(v.asset_code) + '</div>' : '')
                );
                allMarkers.push(m);
                layerGroups.valve.addLayer(m);
            });
            document.getElementById('valveCount').textContent = data.valves.length;
        }

        if (data.pipelines) {
            layerGroups.pipeline = L.layerGroup().addTo(map);
            data.pipelines.forEach(function(pl) {
                if (!pl.start_lat || !pl.start_lng || !pl.end_lat || !pl.end_lng) return;
                var latlngs = [[parseFloat(pl.start_lat), parseFloat(pl.start_lng)], [parseFloat(pl.end_lat), parseFloat(pl.end_lng)]];
                var polyline = L.polyline(latlngs, {
                    color: colors.pipeline,
                    weight: 3,
                    opacity: 0.8
                });
                var diameterLabel = pl.diameter_mm ? pl.diameter_mm + 'mm' : 'N/A';
                var lengthLabel = pl.length_meters ? pl.length_meters + 'm' : 'N/A';
                polyline.bindPopup(
                    '<div class="popup-title"><i class="fas fa-water text-info me-1"></i>Pipeline</div>' +
                    '<div class="popup-detail"><span class="label">Type:</span>' + (pl.pipe_type || 'N/A') + '</div>' +
                    '<div class="popup-detail"><span class="label">Material:</span>' + (pl.material || 'N/A') + '</div>' +
                    '<div class="popup-detail"><span class="label">Diameter:</span>' + diameterLabel + '</div>' +
                    '<div class="popup-detail"><span class="label">Length:</span>' + lengthLabel + '</div>' +
                    '<div class="popup-detail"><span class="label">Status:</span>' + getBadge(pl.status) + '</div>'
                );
                allPolylines.push(polyline);
                layerGroups.pipeline.addLayer(polyline);
            });
            document.getElementById('pipelineCount').textContent = data.pipelines.length;
        }

        if (data.service_areas) {
            layerGroups.service_area = L.layerGroup().addTo(map);
            data.service_areas.forEach(function(sa) {
                if (!sa.coordinates || !sa.coordinates.length) return;
                var polygon = L.polygon(sa.coordinates, {
                    color: colors.service_area,
                    weight: 2,
                    opacity: 0.6,
                    fillOpacity: 0.1
                });
                polygon.bindPopup(
                    '<div class="popup-title"><i class="fas fa-draw-polygon text-purple me-1"></i>' + escapeHtml(sa.name || 'Service Area') + '</div>' +
                    (sa.description ? '<div class="popup-detail">' + escapeHtml(sa.description) + '</div>' : '')
                );
                layerGroups.service_area.addLayer(polygon);
            });
            document.getElementById('serviceAreaCount').textContent = data.service_areas.length;
        }

        if (data.ward_boundaries) {
            layerGroups.ward_boundary = L.layerGroup().addTo(map);
            data.ward_boundaries.forEach(function(wb) {
                if (!wb.coordinates || !wb.coordinates.length) return;
                var polygon = L.polygon(wb.coordinates, {
                    color: colors.ward_boundary,
                    weight: 1.5,
                    opacity: 0.5,
                    fillOpacity: 0.05,
                    dashArray: '5, 8'
                });
                polygon.bindPopup(
                    '<div class="popup-title"><i class="fas fa-vector-square text-secondary me-1"></i>' + escapeHtml(wb.name || 'Ward Boundary') + '</div>' +
                    (wb.ward_no ? '<div class="popup-detail"><span class="label">Ward:</span>' + wb.ward_no + '</div>' : '')
                );
                wardLayers.push(polygon);
                layerGroups.ward_boundary.addLayer(polygon);
            });
            document.getElementById('wardBoundaryCount').textContent = data.ward_boundaries.length;
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function getBadge(status) {
    var map = {
        'active': 'success', 'operational': 'success', 'inactive': 'secondary',
        'maintenance': 'warning', 'damaged': 'danger', 'decommissioned': 'secondary',
        'leak': 'danger', 'pending': 'warning', 'under_construction': 'info'
    };
    var cls = map[status] || 'primary';
    return '<span class="badge bg-' + cls + '">' + status + '</span>';
}

function toggleLayer(type, checked) {
    if (layerGroups[type]) {
        if (checked) {
            map.addLayer(layerGroups[type]);
        } else {
            map.removeLayer(layerGroups[type]);
        }
    }
}

function focusWard(wardNo) {
    if (wardLayers.length > 0) {
        var bounds = L.featureGroup(wardLayers.filter(function(w) { return w._wardNo == wardNo; })).getBounds();
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [30, 30] });
    }
}

function fitAllLayers() {
    var all = [];
    Object.keys(layerGroups).forEach(function(k) {
        layerGroups[k].eachLayer(function(l) { all.push(l); });
    });
    if (all.length) {
        var group = L.featureGroup(all);
        map.fitBounds(group.getBounds(), { padding: [30, 30] });
    }
}

function doSearch() {
    var q = document.getElementById('searchInput').value.toLowerCase().trim();
    var results = document.getElementById('searchResults');
    results.innerHTML = '';
    if (q.length < 2) { results.style.display = 'none'; return; }

    var matches = allMarkers.filter(function(m) {
        return m._searchText && m._searchText.indexOf(q) !== -1;
    }).slice(0, 20);

    if (matches.length === 0) {
        results.innerHTML = '<div class="search-result-item text-muted" style="justify-content:center;">No results found</div>';
        results.style.display = 'block';
        return;
    }

    matches.forEach(function(m) {
        var item = document.createElement('div');
        item.className = 'search-result-item';
        var color = colors[m._layerType] || '#181CB8';
        var icon = m._layerType === 'consumer' ? 'fa-user' : 'fa-map-marker-alt';
        item.innerHTML =
            '<div class="sr-icon" style="background:' + color + ';"><i class="fas ' + icon + '"></i></div>' +
            '<div class="sr-label"><div class="name">' + escapeHtml(m._searchName) + '</div><div class="sub">' + escapeHtml(m._searchSub) + '</div></div>';
        item.onclick = function() {
            map.setView(m.getLatLng(), 16);
            m.openPopup();
            results.style.display = 'none';
            document.getElementById('searchInput').value = '';
        };
        results.appendChild(item);
    });
    results.style.display = 'block';
}

$(document).ready(function() {
    initMap();

    $('.layer-toggle').on('change', function() {
        var type = $(this).data('layer');
        toggleLayer(type, this.checked);
    });

    $('#searchInput').on('input', doSearch);
    $('#searchInput').on('blur', function() { setTimeout(function() { document.getElementById('searchResults').style.display = 'none'; }, 200); });
    $('#searchInput').on('focus', function() { if (this.value.length >= 2) doSearch(); });

    $('.ward-select').on('change', function() {
        var w = parseInt(this.value);
        if (w) focusWard(w);
    });

    $('#sidebarToggleGis').on('click', function() {
        $('.gis-sidebar').toggleClass('open');
    });
});
</script>
JS;

$currentUser = Auth::user();
?>

<div class="gis-container">
    <div class="gis-sidebar" id="gisSidebar">
        <div class="gis-sidebar-header">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6><i class="fas fa-map-marked-alt me-2 text-primary"></i>GIS Layers</h6>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="fitAllLayers()" title="Fit all layers">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </button>
                    <?php if (RBAC::can('gis.edit')): ?>
                    <a href="<?= ADMIN_URL ?>gis/layers.php" class="btn btn-sm btn-outline-secondary ms-1" title="Manage Layers">
                        <i class="fas fa-cog"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="search-box-gis">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search consumers...">
                <div class="search-results" id="searchResults"></div>
            </div>
        </div>
        <div class="gis-sidebar-body">
            <?php foreach ($layers as $layer):
                $type = $layer['layer_type'];
                $color = $layer['color'] ?: '#181CB8';
                $countId = $type . 'Count';
                $layerIcons = [
                    'consumer' => 'fa-users',
                    'pipeline' => 'fa-water',
                    'tank' => 'fa-tint',
                    'pump' => 'fa-cogs',
                    'valve' => 'fa-toggle-on',
                    'service_area' => 'fa-draw-polygon',
                    'ward_boundary' => 'fa-vector-square'
                ];
                $icon = $layerIcons[$type] ?? 'fa-layer-group';
            ?>
            <div class="layer-group">
                <div class="layer-group-header">
                    <div class="form-check">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer_<?= $type ?>" data-layer="<?= $type ?>" <?= $layer['is_visible'] ? 'checked' : '' ?>>
                    </div>
                    <div class="layer-color" style="background:<?= $color ?>"></div>
                    <span class="layer-label"><i class="fas <?= $icon ?> me-1" style="color:<?= $color ?>"></i><?= escape($layer['name']) ?></span>
                    <span class="layer-badge" id="<?= $countId ?>">0</span>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($wards): ?>
            <div class="px-3 pt-3 pb-1">
                <label class="form-label small fw-semibold text-muted mb-1"><i class="fas fa-vector-square me-1"></i>Focus Ward</label>
                <select class="form-select form-select-sm ward-select">
                    <option value="">-- Select Ward --</option>
                    <?php foreach ($wards as $w): ?>
                    <option value="<?= $w ?>">Ward No. <?= $w ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="gis-actions">
            <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-users"></i> Consumers</a>
            <a href="<?= ADMIN_URL ?>assets-mgmt/index.php" class="btn btn-outline-info btn-sm"><i class="fas fa-building"></i> Assets</a>
            <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-headset"></i> Complaints</a>
        </div>
    </div>

    <div class="gis-map-area">
        <button class="gis-toggle-btn" id="sidebarToggleGis"><i class="fas fa-bars"></i></button>
        <div id="map"></div>
        <div class="coord-display" id="coordDisplay">Lat: 27.7172 | Lng: 85.3240</div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
