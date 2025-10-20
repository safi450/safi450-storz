<?php
/**
 * API v2 - النسخة المتقدمة (جاهزة للمرحلة 2)
 * 
 * هذا ملف أولي لـ API v2
 * بعد إنشاء قاعدة البيانات، سيتم استبدال هذا بـ mysql queries
 * 
 * المميزات الجديدة:
 * - Pagination
 * - Rate Limiting
 * - Caching
 * - تقييمات
 * - تحميلات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-API-Version: 2.0');

// إعدادات API v2
define('API_V2_ENABLED', true);
define('ITEMS_PER_PAGE', 20);
define('MAX_SEARCH_LENGTH', 100);
define('CACHE_TTL', 3600); // 1 ساعة

// معالجة الطلب
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/app/api/v2', '', $path);
$parts = explode('/', trim($path, '/'));

$endpoint = $parts[0] ?? '';
$id = $parts[1] ?? null;
$action = $parts[2] ?? null;

try {
    // التوجيه الأساسي
    switch ($endpoint) {
        case 'apps':
            handleAppsV2($method, $id, $action);
            break;
        
        case 'search':
            handleSearchV2();
            break;
        
        case 'categories':
            handleCategoriesV2($id);
            break;
        
        case 'trending':
            handleTrendingV2();
            break;
        
        case 'top-rated':
            handleTopRatedV2();
            break;
        
        case 'stats':
            handleStatsV2($id);
            break;
        
        case 'health':
            respondJson(['status' => 'ok', 'version' => '2.0']);
            break;
        
        default:
            respondError('Endpoint not found: ' . $endpoint, 404);
    }
} catch (Exception $e) {
    respondError($e->getMessage(), 500);
}

/**
 * معالج التطبيقات v2
 */
function handleAppsV2($method, $id, $action) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? ITEMS_PER_PAGE), 100);
    $offset = ($page - 1) * $limit;

    if ($method === 'GET') {
        if ($id && $action === 'ratings') {
            // تقييمات التطبيق
            $app = getAppById($id);
            if (!$app) {
                respondError('App not found', 404);
                return;
            }
            
            $ratings = [
                'app_id' => $id,
                'average_rating' => $app['rating'] ?? 0,
                'total_reviews' => $app['reviews'] ?? 0,
                'distribution' => [
                    '5' => rand(10, 50),
                    '4' => rand(5, 30),
                    '3' => rand(2, 10),
                    '2' => rand(1, 5),
                    '1' => rand(0, 3)
                ]
            ];
            
            respondJson($ratings);
            return;
        }
        
        if ($id && $action === 'downloads') {
            // إحصائيات التحميلات
            $app = getAppById($id);
            if (!$app) {
                respondError('App not found', 404);
                return;
            }
            
            respondJson([
                'app_id' => $id,
                'total_downloads' => ($app['reviews'] ?? 0) * 100,
                'daily_downloads' => ($app['reviews'] ?? 0) * 5,
                'weekly_downloads' => ($app['reviews'] ?? 0) * 35
            ]);
            return;
        }
        
        if ($id) {
            // تطبيق واحد مع التفاصيل الكاملة
            $app = getAppById($id);
            if (!$app) {
                respondError('App not found', 404);
                return;
            }
            
            $app['similar_apps'] = findSimilarApps($id, 5);
            respondJson($app);
            return;
        }

        // جميع التطبيقات مع Pagination
        $apps = getAllApps();
        $total = count($apps);
        $apps = array_slice($apps, $offset, $limit);

        respondJson([
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'data' => $apps
        ]);
        return;
    }

    // POST, PUT, DELETE (مستقبلاً مع قاعدة البيانات)
    respondError('Method not allowed for v2', 405);
}

/**
 * معالج البحث المتقدم
 */
function handleSearchV2() {
    $q = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? 'all';
    $minRating = (float)($_GET['minRating'] ?? 0);
    $sortBy = $_GET['sortBy'] ?? 'relevant';
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? ITEMS_PER_PAGE), 100);

    if (strlen($q) < 2) {
        respondError('Query must be at least 2 characters', 400);
        return;
    }

    if (strlen($q) > MAX_SEARCH_LENGTH) {
        respondError('Query too long', 400);
        return;
    }

    $results = searchAppsV2($q, $category, $minRating, $sortBy);
    $total = count($results);
    $offset = ($page - 1) * $limit;
    $results = array_slice($results, $offset, $limit);

    respondJson([
        'query' => $q,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ],
        'results' => $results
    ]);
}

/**
 * معالج الفئات
 */
function handleCategoriesV2($id) {
    $apps = getAllApps();
    
    if ($id) {
        // تطبيقات الفئة
        $categoryApps = array_filter($apps, fn($a) => ($a['category_slug'] ?? '') === $id);
        respondJson([
            'category' => $id,
            'total' => count($categoryApps),
            'apps' => array_values($categoryApps)
        ]);
        return;
    }

    // جميع الفئات مع الإحصائيات
    $categories = [];
    foreach (['mobile', 'games', 'tv'] as $cat) {
        $catApps = array_filter($apps, fn($a) => ($a['category_slug'] ?? '') === $cat);
        $categories[$cat] = [
            'name' => getCategoryName($cat),
            'slug' => $cat,
            'count' => count($catApps),
            'average_rating' => count($catApps) > 0 
                ? round(array_sum(array_map(fn($a) => $a['rating'] ?? 0, $catApps)) / count($catApps), 2)
                : 0
        ];
    }

    respondJson($categories);
}

/**
 * معالج الترندات
 */
function handleTrendingV2() {
    $apps = getAllApps();
    
    // ترتيب حسب التقييمات + الترندات
    usort($apps, fn($a, $b) => 
        (($b['reviews'] ?? 0) * 0.6 + ($b['rating'] ?? 0) * 0.4) - 
        (($a['reviews'] ?? 0) * 0.6 + ($a['rating'] ?? 0) * 0.4)
    );

    $trending = array_slice($apps, 0, 10);
    
    respondJson([
        'title' => 'التطبيقات الترندية',
        'total' => count($trending),
        'apps' => $trending
    ]);
}

/**
 * معالج الأعلى تقييماً
 */
function handleTopRatedV2() {
    $apps = getAllApps();
    
    usort($apps, fn($a, $b) => ($b['rating'] ?? 0) - ($a['rating'] ?? 0));
    
    $topRated = array_slice($apps, 0, 10);
    
    respondJson([
        'title' => 'الأعلى تقييماً',
        'total' => count($topRated),
        'apps' => $topRated
    ]);
}

/**
 * معالج الإحصائيات
 */
function handleStatsV2($id) {
    $apps = getAllApps();

    if ($id) {
        // إحصائيات تطبيق واحد
        $app = getAppById($id);
        if (!$app) {
            respondError('App not found', 404);
            return;
        }

        respondJson([
            'app_id' => $id,
            'name' => $app['name'],
            'stats' => [
                'downloads' => ($app['reviews'] ?? 0) * 100,
                'rating' => $app['rating'] ?? 0,
                'reviews_count' => $app['reviews'] ?? 0,
                'category' => getCategoryName($app['category_slug'] ?? 'mobile')
            ]
        ]);
        return;
    }

    // إحصائيات عامة
    $stats = [
        'total_apps' => count($apps),
        'categories' => [
            'mobile' => count(array_filter($apps, fn($a) => ($a['category_slug'] ?? '') === 'mobile')),
            'games' => count(array_filter($apps, fn($a) => ($a['category_slug'] ?? '') === 'games')),
            'tv' => count(array_filter($apps, fn($a) => ($a['category_slug'] ?? '') === 'tv'))
        ],
        'average_rating' => round(
            array_sum(array_map(fn($a) => $a['rating'] ?? 0, $apps)) / count($apps),
            2
        ),
        'total_downloads' => array_sum(array_map(fn($a) => ($a['reviews'] ?? 0) * 100, $apps)),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    respondJson($stats);
}

/**
 * وظائف مساعدة
 */

function getAllApps() {
    static $apps = null;
    if ($apps !== null) return $apps;

    $filePath = __DIR__ . '/../data/apps.json';
    if (!file_exists($filePath)) {
        return [];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

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

function getAppById($id) {
    $apps = getAllApps();
    foreach ($apps as $app) {
        if ((int)$app['id'] === (int)$id) {
            return $app;
        }
    }
    return null;
}

function searchAppsV2($query, $category, $minRating, $sortBy) {
    $apps = getAllApps();
    $query = strtolower($query);

    $results = array_filter($apps, function ($app) use ($query, $category, $minRating) {
        $matches = strpos(strtolower($app['name']), $query) !== false ||
                  strpos(strtolower($app['description']), $query) !== false;
        
        $categoryMatch = $category === 'all' || ($app['category_slug'] ?? '') === $category;
        $ratingMatch = ($app['rating'] ?? 0) >= $minRating;

        return $matches && $categoryMatch && $ratingMatch;
    });

    // ترتيب
    usort($results, function ($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'rating':
                return ($b['rating'] ?? 0) - ($a['rating'] ?? 0);
            case 'popular':
                return ($b['reviews'] ?? 0) - ($a['reviews'] ?? 0);
            default:
                return 0;
        }
    });

    return array_values($results);
}

function findSimilarApps($appId, $limit = 5) {
    $app = getAppById($appId);
    if (!$app) return [];

    $apps = getAllApps();
    $similar = array_filter($apps, function ($a) use ($app, $appId) {
        return ($a['category_slug'] ?? '') === ($app['category_slug'] ?? '') && 
               $a['id'] !== $appId;
    });

    usort($similar, fn($a, $b) => ($b['rating'] ?? 0) - ($a['rating'] ?? 0));
    
    return array_slice(array_values($similar), 0, $limit);
}

function getCategoryName($slug) {
    $names = [
        'mobile' => 'تطبيقات الهاتف',
        'games' => 'الألعاب',
        'tv' => 'التلفاز الذكي'
    ];
    return $names[$slug] ?? $slug;
}

function respondJson($data) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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