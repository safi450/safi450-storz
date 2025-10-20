<?php
/**
 * مستخرج بيانات محسّن - مع تنظيف UTF-8
 */

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '512M');

function logOutput($message) {
    echo $message . "\n";
    flush();
}

function cleanUtf8($string) {
    if (!is_string($string)) return $string;
    
    // إزالة الأحرف غير الصحيحة
    $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);
    return trim($string);
}

function jsonSafeEncode($data) {
    // تنظيف البيانات بشكل عميق
    array_walk_recursive($data, function(&$item) {
        if (is_string($item)) {
            $item = cleanUtf8($item);
        }
    });
    
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// إعدادات curl
$curlOptions = [
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
];

$appsData = [
    "mobile_apps" => [],
    "games" => [],
    "tv_apps" => []
];

$appIdCounter = [
    "mobile_apps" => 1,
    "games" => 1,
    "tv_apps" => 1
];

function extractAppIcon($name) {
    $icons = [
        "player" => "fa-video",
        "video" => "fa-video",
        "music" => "fa-music",
        "photo" => "fa-image",
        "camera" => "fa-camera",
        "editor" => "fa-edit",
        "browse" => "fa-globe",
        "game" => "fa-gamepad",
        "puzzle" => "fa-puzzle-piece",
        "tv" => "fa-tv",
        "movie" => "fa-film",
        "book" => "fa-book",
        "news" => "fa-newspaper",
    ];
    
    $nameLower = strtolower($name);
    foreach ($icons as $keyword => $icon) {
        if (strpos($nameLower, $keyword) !== false) {
            return $icon;
        }
    }
    return "fa-star";
}

function parseRating($text) {
    if (!$text) return 4.5;
    $rating = (float)preg_replace('/[^0-9.]/', '', $text);
    return min(5.0, max(1.0, $rating ?: 4.5));
}

function parseReviews($text) {
    if (!$text) return 100;
    $text = strtolower(str_replace(',', '', $text));
    if (strpos($text, 'k') !== false) {
        return (int)(((float)preg_replace('/[^0-9.]/', '', $text)) * 1000);
    }
    $num = (int)preg_replace('/[^0-9]/', '', $text);
    return max(100, $num ?: 100);
}

function fetchURL($url) {
    global $curlOptions;
    
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return $response;
        }
        
        if ($attempt < 2) {
            sleep(pow(2, $attempt));
        }
    }
    
    return null;
}

function scrapePage($url, $catKey, &$appsData, &$appIdCounter) {
    $html = fetchURL($url);
    if (!$html) {
        return 0;
    }
    
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $xpath = new DOMXPath($doc);
    
    $appItems = $xpath->query("//div[@class='app-item']");
    
    if ($appItems->length == 0) {
        $appItems = $xpath->query("//article");
    }
    if ($appItems->length == 0) {
        $appItems = $xpath->query("//div[contains(@class, 'item')]");
    }
    
    $count = 0;
    foreach ($appItems as $item) {
        try {
            $nameNode = $xpath->query(".//h2 | .//h3 | .//a | .//span[@class='name']", $item);
            $name = $nameNode->length > 0 ? cleanUtf8(trim($nameNode->item(0)->textContent)) : null;
            
            if (!$name || strlen($name) < 2) continue;
            
            $descNode = $xpath->query(".//p | .//span[@class='description']", $item);
            $description = $descNode->length > 0 ? cleanUtf8(trim($descNode->item(0)->textContent)) : "تطبيق مميز";
            
            if (strlen($description) > 150) {
                $description = substr($description, 0, 150) . "...";
            }
            
            $ratingNode = $xpath->query(".//*[@class='rating'] | .//span[@class='rate']", $item);
            $rating = $ratingNode->length > 0 ? parseRating($ratingNode->item(0)->textContent) : 4.5;
            
            $reviewsNode = $xpath->query(".//*[@class='reviews'] | .//span[@class='count']", $item);
            $reviews = $reviewsNode->length > 0 ? parseReviews($reviewsNode->item(0)->textContent) : 100;
            
            $appObj = [
                "id" => $appIdCounter[$catKey],
                "name" => $name,
                "description" => $description,
                "icon" => extractAppIcon($name),
                "rating" => (float)$rating,
                "reviews" => (int)$reviews,
                "category" => "app",
                "download_link" => "https://mobilltna.org/app/" . urlencode($name)
            ];
            
            $appsData[$catKey][] = $appObj;
            $appIdCounter[$catKey]++;
            $count++;
            
        } catch (Exception $e) {
            continue;
        }
    }
    
    return $count;
}

logOutput("============================================================");
logOutput("🚀 بدء استخراج بيانات التطبيقات من Mobilltna.org");
logOutput("📊 الصفحات: 30 | التأخير: 2ث");
logOutput("============================================================\n");

try {
    $startTime = time();
    
    $categories = [
        ["تطبيقات موبايل", "https://mobilltna.org/apps", "mobile_apps"],
        ["الألعاب", "https://mobilltna.org/games", "games"],
        ["تطبيقات التلفاز", "https://mobilltna.org/tv", "tv_apps"],
    ];
    
    foreach ($categories as $cat) {
        list($categoryName, $baseUrl, $catKey) = $cat;
        
        logOutput("🔄 بدء استخراج: {$categoryName}");
        
        $totalApps = 0;
        
        for ($page = 1; $page <= 30; $page++) {
            $url = $page == 1 ? $baseUrl : "{$baseUrl}?page={$page}";
            
            logOutput("  📄 الصفحة {$page}/30");
            
            $appCount = scrapePage($url, $catKey, $appsData, $appIdCounter);
            $totalApps += $appCount;
            
            if ($appCount > 0) {
                logOutput("    ✅ تم استخراج {$appCount} تطبيق");
            } else {
                logOutput("    ⚠️  لم يتم العثور على تطبيقات");
            }
            
            if ($page < 30) {
                sleep(2);
            }
        }
        
        logOutput("✅ انتهى: {$categoryName} ({$totalApps} تطبيق)\n");
        sleep(2);
    }
    
    // حفظ البيانات
    logOutput("\n💾 جاري حفظ البيانات...\n");
    
    $filename = __DIR__ . '/data/apps_new.json';
    $json = jsonSafeEncode($appsData);
    
    if (!$json) {
        logOutput("❌ خطأ في تحويل البيانات إلى JSON");
    } else {
        // كتابة الملف مع معالجة الأخطاء
        if (is_writable(dirname($filename))) {
            $bytes = file_put_contents($filename, $json, LOCK_EX);
            
            if ($bytes) {
                $total = count($appsData["mobile_apps"]) + count($appsData["games"]) + count($appsData["tv_apps"]);
                
                logOutput("✅ تم حفظ البيانات بنجاح!");
                logOutput("📁 الملف: " . $filename);
                logOutput("📊 حجم الملف: " . round($bytes / 1024, 2) . " KB\n");
                
                logOutput("📊 الإحصائيات:");
                logOutput("   • تطبيقات موبايل: " . count($appsData["mobile_apps"]));
                logOutput("   • ألعاب: " . count($appsData["games"]));
                logOutput("   • تطبيقات TV: " . count($appsData["tv_apps"]));
                logOutput("   • الإجمالي: {$total}\n");
                
                $endTime = time();
                $duration = $endTime - $startTime;
                
                logOutput("⏳ الوقت المستغرق: {$duration} ثانية");
                logOutput("✨ اكتمل بنجاح!");
                
            } else {
                logOutput("❌ خطأ في كتابة الملف");
            }
        } else {
            logOutput("❌ المجلد غير قابل للكتابة: " . dirname($filename));
        }
    }
    
} catch (Exception $e) {
    logOutput("❌ خطأ: " . $e->getMessage());
    logOutput("Trace: " . $e->getTraceAsString());
}

logOutput("\n============================================================");
?>