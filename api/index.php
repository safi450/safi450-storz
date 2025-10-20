<?php
/**
 * API رئيسي - SAFI STORZ API
 * نقطة الدخول الأساسية لجميع طلبات API
 * 
 * المسارات المتاحة:
 * - GET /api/apps - جميع التطبيقات
 * - GET /api/apps/{id} - تطبيق محدد
 * - GET /api/search?q={term} - بحث
 * - GET /api/stats - إحصائيات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// معالجة CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// قراءة المسار والمعاملات
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = str_replace('/app/api/', '', $request_uri);
$request_method = $_SERVER['REQUEST_METHOD'];

// التوجيه الأساسي
$parts = explode('/', trim($request_uri, '/'));
$endpoint = $parts[0] ?? '';

try {
    switch ($endpoint) {
        case 'apps':
            handleAppsEndpoint($parts, $request_method);
            break;
        
        case 'search':
            handleSearchEndpoint();
            break;
        
        case 'stats':
            handleStatsEndpoint();
            break;
        
        case 'health':
            respondSuccess(['status' => 'ok', 'message' => 'API is running']);
            break;
        
        default:
            respondError('Endpoint not found', 404);
    }
} catch (Exception $e) {
    respondError($e->getMessage(), 500);
}

/**
 * معالج نقطة البيانات الأساسية
 */
function handleAppsEndpoint($parts, $method) {
    if ($method !== 'GET') {
        respondError('Method not allowed', 405);
        return;
    }

    $id = $parts[1] ?? null;

    if ($id) {
        // تطبيق محدد
        $app = getAppById($id);
        if ($app) {
            respondSuccess($app);
        } else {
            respondError('App not found', 404);
        }
    } else {
        // جميع التطبيقات
        $apps = getAllApps();
        respondSuccess([
            'total' => count($apps),
            'apps' => $apps
        ]);
    }
}

/**
 * معالج البحث
 */
function handleSearchEndpoint() {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? 'all';
    $minRating = (float)($_GET['minRating'] ?? 0);
    $sortBy = $_GET['sortBy'] ?? 'popular';

    if (empty($query)) {
        respondError('Query parameter is required', 400);
        return;
    }

    $results = searchApps($query, $category, $minRating, $sortBy);
    respondSuccess([
        'query' => $query,
        'total' => count($results),
        'results' => $results
    ]);
}

/**
 * معالج الإحصائيات
 */
function handleStatsEndpoint() {
    $stats = getStats();
    respondSuccess($stats);
}

/**
 * جلب جميع التطبيقات من JSON
 */
function getAllApps() {
    $filePath = __DIR__ . '/../data/apps.json';
    if (!file_exists($filePath)) {
        return [];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    // دمج الفئات
    $apps = [];
    if (isset($data['mobile_apps'])) {
        foreach ($data['mobile_apps'] as $app) {
            $app['category_slug'] = 'mobile';
            $apps[] = $app;
        }
    }
    if (isset($data['games'])) {
        foreach ($data['games'] as $app) {
            $app['category_slug'] = 'games';
            $apps[] = $app;
        }
    }
    if (isset($data['tv_apps'])) {
        foreach ($data['tv_apps'] as $app) {
            $app['category_slug'] = 'tv';
            $apps[] = $app;
        }
    }

    return $apps;
}

/**
 * جلب تطبيق محدد بـ ID
 */
function getAppById($id) {
    $apps = getAllApps();
    foreach ($apps as $app) {
        if ((int)$app['id'] === (int)$id) {
            return $app;
        }
    }
    return null;
}

/**
 * البحث في التطبيقات
 */
function searchApps($query, $category = 'all', $minRating = 0, $sortBy = 'popular') {
    $apps = getAllApps();
    $query = strtolower($query);

    // تصفية
    $results = array_filter($apps, function ($app) use ($query, $category, $minRating) {
        // البحث النصي
        $nameMatch = strpos(strtolower($app['name']), $query) !== false;
        $descMatch = strpos(strtolower($app['description']), $query) !== false;
        
        // تصفية الفئة
        $categoryMatch = $category === 'all' || ($app['category_slug'] ?? '') === $category;
        
        // تصفية التقييم
        $ratingMatch = ($app['rating'] ?? 0) >= $minRating;

        return ($nameMatch || $descMatch) && $categoryMatch && $ratingMatch;
    });

    // ترتيب
    usort($results, function ($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'rating':
                return ($b['rating'] ?? 0) - ($a['rating'] ?? 0);
            
            case 'reviews':
                return ($b['reviews'] ?? 0) - ($a['reviews'] ?? 0);
            
            case 'name':
                return strcmp($a['name'], $b['name']);
            
            case 'new':
                return $b['id'] - $a['id'];
            
            default: // popular
                return (($b['reviews'] ?? 0) + ($b['rating'] ?? 0)) - 
                       (($a['reviews'] ?? 0) + ($a['rating'] ?? 0));
        }
    });

    return array_values($results);
}

/**
 * جلب الإحصائيات العامة
 */
function getStats() {
    $apps = getAllApps();
    $categories = [
        'mobile' => 0,
        'games' => 0,
        'tv' => 0
    ];
    $totalRating = 0;
    $totalReviews = 0;

    foreach ($apps as $app) {
        $slug = $app['category_slug'] ?? 'mobile';
        if (isset($categories[$slug])) {
            $categories[$slug]++;
        }
        $totalRating += $app['rating'] ?? 0;
        $totalReviews += $app['reviews'] ?? 0;
    }

    $total = count($apps);
    $avgRating = $total > 0 ? round($totalRating / $total, 2) : 0;

    return [
        'total_apps' => $total,
        'categories' => $categories,
        'total_reviews' => $totalReviews,
        'average_rating' => $avgRating,
        'last_updated' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/../data/apps.json'))
    ];
}

/**
 * الرد بنجاح
 */
function respondSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * الرد بخطأ
 */
function respondError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>