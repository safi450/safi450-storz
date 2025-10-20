<?php
/**
 * API لمستخرج بيانات التطبيقات من موقع Mobilltna.org
 * يعمل مع streaming output
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('output_buffering', 'off');

// إذا كانت هناك buffer، فرغها
if (ob_get_level() > 0) {
    ob_end_clean();
}

$pages = isset($_POST['pages']) ? (int)$_POST['pages'] : 30;
$delay = isset($_POST['delay']) ? (int)$_POST['delay'] : 2;

// إعدادات curl محسّنة
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

function logOutput($message) {
    echo $message . "\n";
    flush();
}

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
    return min(5.0, max(1.0, $rating));
}

function parseReviews($text) {
    if (!$text) return 100;
    $text = strtolower(str_replace(',', '', $text));
    if (strpos($text, 'k') !== false) {
        return (int)(((float)preg_replace('/[^0-9.]/', '', $text)) * 1000);
    }
    return max(100, (int)preg_replace('/[^0-9]/', '', $text));
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

function scrapePage($url, $catKey) {
    global $appIdCounter, $appsData;
    
    $html = fetchURL($url);
    if (!$html) {
        return 0;
    }
    
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $xpath = new DOMXPath($doc);
    
    // محاولات متعددة للبحث عن التطبيقات
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
            // استخراج الاسم
            $nameNode = $xpath->query(".//h2 | .//h3 | .//a | .//span[@class='name']", $item);
            $name = $nameNode->length > 0 ? trim($nameNode->item(0)->textContent) : null;
            
            if (!$name || strlen($name) < 2) continue;
            
            // استخراج الوصف
            $descNode = $xpath->query(".//p | .//span[@class='description']", $item);
            $description = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : "تطبيق مميز";
            
            if (strlen($description) > 150) {
                $description = substr($description, 0, 150) . "...";
            }
            
            // استخراج التقييم
            $ratingNode = $xpath->query(".//*[@class='rating'] | .//span[@class='rate']", $item);
            $rating = $ratingNode->length > 0 ? parseRating($ratingNode->item(0)->textContent) : 4.5;
            
            // عدد التقييمات
            $reviewsNode = $xpath->query(".//*[@class='reviews'] | .//span[@class='count']", $item);
            $reviews = $reviewsNode->length > 0 ? parseReviews($reviewsNode->item(0)->textContent) : 100;
            
            $appObj = [
                "id" => $appIdCounter[$catKey],
                "name" => $name,
                "description" => $description,
                "icon" => extractAppIcon($name),
                "rating" => $rating,
                "reviews" => $reviews,
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

function scrapeCategory($categoryName, $baseUrl, $maxPages) {
    global $appsData;
    
    logOutput("🔄 بدء استخراج: {$categoryName}");
    
    // تحديد الفئة
    if (strpos($categoryName, 'لعبة') !== false || strpos($categoryName, 'game') !== false) {
        $catKey = "games";
    } elseif (strpos($categoryName, 'tv') !== false) {
        $catKey = "tv_apps";
    } else {
        $catKey = "mobile_apps";
    }
    
    $totalApps = 0;
    
    for ($page = 1; $page <= $maxPages; $page++) {
        $url = $page == 1 ? $baseUrl : "{$baseUrl}?page={$page}";
        
        logOutput("  📄 الصفحة {$page}/{$maxPages}");
        
        $appCount = scrapePage($url, $catKey);
        $totalApps += $appCount;
        
        if ($appCount > 0) {
            logOutput("    ✅ تم استخراج {$appCount} تطبيق");
        } else {
            logOutput("    ⚠️  لم يتم العثور على تطبيقات");
        }
        
        if ($page < $maxPages) {
            sleep(2);
            flush();
        }
    }
    
    logOutput("✅ انتهى: {$categoryName} ({$totalApps} تطبيق)\n");
}

// البدء
logOutput("============================================================");
logOutput("🚀 بدء استخراج بيانات التطبيقات");
logOutput("📊 الصفحات: {$pages} | التأخير: {$delay}ث");
logOutput("============================================================\n");

try {
    $startTime = time();
    
    // الفئات
    $categories = [
        ["تطبيقات موبايل", "https://mobilltna.org/apps", "mobile_apps"],
        ["الألعاب", "https://mobilltna.org/games", "games"],
        ["تطبيقات التلفاز", "https://mobilltna.org/tv", "tv_apps"],
    ];
    
    foreach ($categories as $cat) {
        scrapeCategory($cat[0], $cat[1], $pages);
        sleep(2);
    }
    
    // حفظ البيانات
    logOutput("\n💾 جاري حفظ البيانات...");
    
    $filename = __DIR__ . '/data/apps_new.json';
    $json = json_encode($appsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($filename, $json)) {
        $total = count($appsData["mobile_apps"]) + count($appsData["games"]) + count($appsData["tv_apps"]);
        
        logOutput("✅ تم حفظ البيانات بنجاح!");
        logOutput("📁 الملف: data/apps_new.json\n");
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
        logOutput("❌ خطأ في حفظ الملف");
    }
    
} catch (Exception $e) {
    logOutput("❌ خطأ: " . $e->getMessage());
}

logOutput("\n============================================================");
?>