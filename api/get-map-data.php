<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Allow API key or session auth
if (!Auth::check()) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey) || $apiKey !== ENCRYPTION_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$layerType = get('layer', '');
$ward = get('ward', '');
$status = get('status', '');

try {
    $features = [];

    switch ($layerType) {
        case 'consumers':
            $where = "WHERE c.deleted_at IS NULL AND c.latitude IS NOT NULL AND c.longitude IS NOT NULL AND c.latitude != 0 AND c.longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND c.ward_no = :ward"; $params['ward'] = (int)$ward; }
            if ($status !== '') { $where .= " AND c.status = :status"; $params['status'] = $status; }

            $rows = db()->fetchAll(
                "SELECT c.id, c.consumer_no, c.full_name, c.mobile, c.ward_no,
                        c.latitude, c.longitude, c.status, c.connection_type,
                        cat.name as category_name
                 FROM consumers c
                 LEFT JOIN consumer_categories cat ON c.category_id = cat.id
                 {$where} ORDER BY c.full_name ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => 'consumer',
                        'consumer_no' => $r['consumer_no'],
                        'name' => $r['full_name'],
                        'mobile' => $r['mobile'],
                        'ward_no' => $r['ward_no'],
                        'status' => $r['status'],
                        'connection_type' => $r['connection_type'],
                        'category_name' => $r['category_name'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-user text-primary me-1"></i>' . escape($r['consumer_no']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Name:</span>' . escape($r['full_name']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Mobile:</span>' . escape($r['mobile']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Ward:</span>' . ($r['ward_no'] ?: '-') . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        case 'pipelines':
            $where = "WHERE p.start_latitude IS NOT NULL AND p.start_longitude IS NOT NULL
                       AND p.end_latitude IS NOT NULL AND p.end_longitude IS NOT NULL
                       AND p.start_latitude != 0 AND p.start_longitude != 0
                       AND p.end_latitude != 0 AND p.end_longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND p.ward_no = :ward"; $params['ward'] = (int)$ward; }

            $rows = db()->fetchAll(
                "SELECT p.*, a.name as asset_name, a.asset_code
                 FROM pipelines p
                 LEFT JOIN assets a ON p.asset_id = a.id
                 {$where} ORDER BY p.id ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [(float)$r['start_longitude'], (float)$r['start_latitude']],
                            [(float)$r['end_longitude'], (float)$r['end_latitude']]
                        ]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => 'pipeline',
                        'pipe_type' => $r['pipe_type'],
                        'material' => $r['material'],
                        'diameter_mm' => (float)$r['diameter_mm'],
                        'length_meters' => (float)$r['length_meters'],
                        'status' => $r['status'],
                        'name' => $r['asset_name'],
                        'asset_code' => $r['asset_code'],
                        'ward_no' => $r['ward_no'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-water text-info me-1"></i>Pipeline</div>' .
                            '<div class="popup-detail"><span class="label">Type:</span>' . ($r['pipe_type'] ?: 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Material:</span>' . ($r['material'] ?: 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Diameter:</span>' . ($r['diameter_mm'] ? $r['diameter_mm'] . 'mm' : 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Length:</span>' . ($r['length_meters'] ? $r['length_meters'] . 'm' : 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        case 'tanks':
            $where = "WHERE t.latitude IS NOT NULL AND t.longitude IS NOT NULL AND t.latitude != 0 AND t.longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND t.ward_no = :ward"; $params['ward'] = (int)$ward; }

            $rows = db()->fetchAll(
                "SELECT t.*, a.name as asset_name, a.asset_code
                 FROM water_tanks t
                 LEFT JOIN assets a ON t.asset_id = a.id
                 {$where} ORDER BY a.name ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => 'tank',
                        'name' => $r['asset_name'],
                        'asset_code' => $r['asset_code'],
                        'tank_type' => $r['tank_type'],
                        'capacity_liters' => (float)$r['capacity_liters'],
                        'status' => $r['status'],
                        'ward_no' => $r['ward_no'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-tint text-success me-1"></i>' . escape($r['asset_name'] ?: 'Water Tank') . '</div>' .
                            '<div class="popup-detail"><span class="label">Type:</span>' . ($r['tank_type'] ?: 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Capacity:</span>' . ($r['capacity_liters'] ? number_format($r['capacity_liters']) . ' L' : 'N/A') . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        case 'pumps':
            $where = "WHERE a.asset_type = 'pump' AND a.deleted_at IS NULL
                       AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
                       AND a.latitude != 0 AND a.longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND a.ward_no = :ward"; $params['ward'] = (int)$ward; }

            $rows = db()->fetchAll(
                "SELECT a.id, a.asset_code, a.name, a.latitude, a.longitude, a.status, a.ward_no
                 FROM assets a
                 {$where} ORDER BY a.name ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => 'pump',
                        'name' => $r['name'],
                        'asset_code' => $r['asset_code'],
                        'status' => $r['status'],
                        'ward_no' => $r['ward_no'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-cogs text-warning me-1"></i>' . escape($r['name']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Code:</span>' . escape($r['asset_code']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        case 'valves':
            $where = "WHERE a.asset_type = 'valve' AND a.deleted_at IS NULL
                       AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
                       AND a.latitude != 0 AND a.longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND a.ward_no = :ward"; $params['ward'] = (int)$ward; }

            $rows = db()->fetchAll(
                "SELECT a.id, a.asset_code, a.name, a.latitude, a.longitude, a.status, a.ward_no
                 FROM assets a
                 {$where} ORDER BY a.name ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => 'valve',
                        'name' => $r['name'],
                        'asset_code' => $r['asset_code'],
                        'status' => $r['status'],
                        'ward_no' => $r['ward_no'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-toggle-on text-danger me-1"></i>' . escape($r['name']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Code:</span>' . escape($r['asset_code']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        case 'assets':
            $where = "WHERE a.deleted_at IS NULL
                       AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
                       AND a.latitude != 0 AND a.longitude != 0";
            $params = [];
            if ($ward !== '') { $where .= " AND a.ward_no = :ward"; $params['ward'] = (int)$ward; }
            if ($status !== '') { $where .= " AND a.status = :status"; $params['status'] = $status; }

            $rows = db()->fetchAll(
                "SELECT a.*, c.name as category_name
                 FROM assets a
                 LEFT JOIN asset_categories c ON a.category_id = c.id
                 {$where} ORDER BY a.name ASC",
                $params
            );
            foreach ($rows as $r) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                    ],
                    'properties' => [
                        'id' => (int)$r['id'],
                        'type' => $r['asset_type'],
                        'name' => $r['name'],
                        'asset_code' => $r['asset_code'],
                        'asset_type' => $r['asset_type'],
                        'category_name' => $r['category_name'],
                        'status' => $r['status'],
                        'ward_no' => $r['ward_no'],
                        'popup_html' => '<div class="popup-title"><i class="fas fa-building me-1"></i>' . escape($r['name']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Code:</span>' . escape($r['asset_code']) . '</div>' .
                            '<div class="popup-detail"><span class="label">Type:</span>' . ucfirst(str_replace('_', ' ', $r['asset_type'])) . '</div>' .
                            '<div class="popup-detail"><span class="label">Category:</span>' . escape($r['category_name'] ?: '-') . '</div>' .
                            '<div class="popup-detail"><span class="label">Status:</span>' . get_status_badge($r['status']) . '</div>'
                    ]
                ];
            }
            break;

        default:
            json_error('Invalid layer type. Valid: consumers, pipelines, tanks, pumps, valves, assets');
    }

    echo json_encode([
        'success' => true,
        'type' => 'FeatureCollection',
        'layer' => $layerType,
        'features' => $features,
        'total' => count($features)
    ]);
} catch (Exception $e) {
    error_log("API get-map-data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
