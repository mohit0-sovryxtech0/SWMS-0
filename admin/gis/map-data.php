<?php
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $data = [];

    // 1. Consumer Locations
    $consumers = db()->fetchAll(
        "SELECT id, consumer_no, full_name, mobile, ward_no, latitude, longitude, status, connection_type
         FROM consumers
         WHERE deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
           AND latitude != 0 AND longitude != 0
         ORDER BY full_name ASC"
    );
    $data['consumers'] = $consumers;

    // 2. Water Tanks
    $tanks = db()->fetchAll(
        "SELECT t.id, t.asset_id, t.tank_type, t.capacity_liters, t.latitude, t.longitude, t.status,
                a.name, a.asset_code, a.ward_no
         FROM water_tanks t
         LEFT JOIN assets a ON t.asset_id = a.id
         WHERE t.latitude IS NOT NULL AND t.longitude IS NOT NULL
           AND t.latitude != 0 AND t.longitude != 0
         ORDER BY a.name ASC"
    );
    $data['tanks'] = $tanks;

    // 3. Pump Stations
    $pumps = db()->fetchAll(
        "SELECT a.id, a.asset_code, a.name, a.asset_type, a.latitude, a.longitude, a.status, a.ward_no
         FROM assets a
         WHERE a.asset_type = 'pump'
           AND a.deleted_at IS NULL
           AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
           AND a.latitude != 0 AND a.longitude != 0
         ORDER BY a.name ASC"
    );
    $data['pumps'] = $pumps;

    // 4. Valves
    $valves = db()->fetchAll(
        "SELECT a.id, a.asset_code, a.name, a.asset_type, a.latitude, a.longitude, a.status, a.ward_no
         FROM assets a
         WHERE a.asset_type = 'valve'
           AND a.deleted_at IS NULL
           AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
           AND a.latitude != 0 AND a.longitude != 0
         ORDER BY a.name ASC"
    );
    $data['valves'] = $valves;

    // 5. Pipeline Network
    $pipelines = db()->fetchAll(
        "SELECT p.id, p.pipe_type, p.material, p.diameter_mm, p.length_meters,
                p.start_latitude AS start_lat, p.start_longitude AS start_lng,
                p.end_latitude AS end_lat, p.end_longitude AS end_lng,
                p.status, p.ward_no, a.name, a.asset_code
         FROM pipelines p
         LEFT JOIN assets a ON p.asset_id = a.id
         WHERE p.start_latitude IS NOT NULL AND p.start_longitude IS NOT NULL
           AND p.end_latitude IS NOT NULL AND p.end_longitude IS NOT NULL
           AND p.start_latitude != 0 AND p.start_longitude != 0
           AND p.end_latitude != 0 AND p.end_longitude != 0
         ORDER BY p.id ASC"
    );
    $data['pipelines'] = $pipelines;

    // 6. Service Areas (from gis_shapes)
    $serviceLayers = db()->fetchAll("SELECT id FROM gis_layers WHERE layer_type = 'service_area'");
    $serviceAreaIds = array_column($serviceLayers, 'id');
    $serviceAreas = [];
    if (!empty($serviceAreaIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceAreaIds), '?'));
        $shapes = db()->fetchAll(
            "SELECT s.*, l.name as layer_name
             FROM gis_shapes s
             JOIN gis_layers l ON s.layer_id = l.id
             WHERE s.layer_id IN ($placeholders) AND s.shape_type IN ('polygon', 'rectangle')
             ORDER BY s.id ASC",
            $serviceAreaIds
        );
        foreach ($shapes as $s) {
            $coords = json_decode($s['coordinates'], true);
            if ($coords) {
                $serviceAreas[] = [
                    'id' => $s['id'],
                    'name' => $s['label'] ?: $s['layer_name'],
                    'description' => $s['description'],
                    'coordinates' => $coords,
                    'style' => json_decode($s['style'], true)
                ];
            }
        }
    }
    $data['service_areas'] = $serviceAreas;

    // 7. Ward Boundaries (from gis_shapes or generated)
    $wardLayerIds = db()->fetchAll("SELECT id FROM gis_layers WHERE layer_type = 'ward_boundary'");
    $wardBoundaries = [];

    if (!empty($wardLayerIds)) {
        $placeholders = implode(',', array_fill(0, count($wardLayerIds), '?'));
        $wardIds = array_column($wardLayerIds, 'id');
        $shapes = db()->fetchAll(
            "SELECT s.*, l.name as layer_name
             FROM gis_shapes s
             JOIN gis_layers l ON s.layer_id = l.id
             WHERE s.layer_id IN ($placeholders) AND s.shape_type IN ('polygon', 'rectangle')
             ORDER BY s.id ASC",
            $wardIds
        );
        foreach ($shapes as $s) {
            $coords = json_decode($s['coordinates'], true);
            if ($coords) {
                $wardBoundaries[] = [
                    'id' => $s['id'],
                    'name' => $s['label'] ?: $s['layer_name'],
                    'description' => $s['description'],
                    'ward_no' => $s['reference_id'],
                    'coordinates' => $coords
                ];
            }
        }
    }

    // Generate simplified ward boundaries if none exist (for demo)
    if (empty($wardBoundaries)) {
        $baseLat = MAP_CENTER_LAT;
        $baseLng = MAP_CENTER_LNG;
        for ($w = 1; $w <= 10; $w++) {
            $offset = ($w - 1) * 0.008;
            $coords = [
                [$baseLat - 0.004 + $offset * 0.1, $baseLng - 0.006 + $offset * 0.05],
                [$baseLat + 0.004 + $offset * 0.1, $baseLng - 0.006 + $offset * 0.05],
                [$baseLat + 0.004 + $offset * 0.1, $baseLng + 0.006 + $offset * 0.05],
                [$baseLat - 0.004 + $offset * 0.1, $baseLng + 0.006 + $offset * 0.05],
                [$baseLat - 0.004 + $offset * 0.1, $baseLng - 0.006 + $offset * 0.05]
            ];
            $wardBoundaries[] = [
                'id' => $w,
                'name' => 'Ward No. ' . $w,
                'ward_no' => $w,
                'coordinates' => $coords
            ];
        }
    }
    $data['ward_boundaries'] = $wardBoundaries;

    json_response(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log("GIS map-data error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to load map data'], 500);
}
