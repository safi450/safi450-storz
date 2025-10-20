<?php
/**
 * دمج البيانات المستخرجة من mobilltna_apps.json مع apps.json
 */

$dataDir = __DIR__ . '/data/';
$appsFile = $dataDir . 'apps.json';
$scrapedFile = $dataDir . 'mobilltna_apps.json';

// قراءة البيانات الأصلية
if (!file_exists($appsFile)) {
    die("❌ الملف apps.json غير موجود\n");
}

$originalData = json_decode(file_get_contents($appsFile), true);
if (!$originalData) {
    die("❌ خطأ في قراءة apps.json\n");
}

// قراءة الأسماء المستخرجة
if (!file_exists($scrapedFile)) {
    die("❌ الملف mobilltna_apps.json غير موجود. قم بتشغيل scraper_mobilltna.php أولاً\n");
}

$scrapedNames = json_decode(file_get_contents($scrapedFile), true);
if (!$scrapedNames) {
    die("❌ خطأ في قراءة mobilltna_apps.json\n");
}

echo "📊 دمج البيانات...\n";
echo "الأسماء المستخرجة: " . count($scrapedNames) . "\n";

// دالة للتحقق من وجود الاسم بالفعل
function appExists($name, $apps) {
    foreach ($apps as $app) {
        if (stripos($app['name'], $name) !== false || stripos($name, $app['name']) !== false) {
            return true;
        }
    }
    return false;
}

// دالة لتحديد الفئة بناءً على الاسم
function guessCategory($name) {
    $lowerName = strtolower($name);
    
    // الكلمات الدالة على الألعاب
    $gameKeywords = ['game', 'gaming', 'play', 'quest', 'battle', 'runner', 'puzzle', 'defense', 'racing', 'chess', 'sports', 'arena', 'dungeon', 'adventure', 'cosmic', 'fitness', 'bomb', 'لعبة', 'game'];
    
    // الكلمات الدالة على تطبيقات التلفاز
    $tvKeywords = ['tv', 'cinema', 'movies', 'iptv', 'cast', 'drama', 'streaming', 'flix', 'tflix', 'أفلام', 'مسلسلات', 'بث', 'cinema'];
    
    foreach ($gameKeywords as $keyword) {
        if (stripos($lowerName, $keyword) !== false) {
            return 'games';
        }
    }
    
    foreach ($tvKeywords as $keyword) {
        if (stripos($lowerName, $keyword) !== false) {
            return 'tv_apps';
        }
    }
    
    return 'mobile_apps';
}

// إضافة التطبيقات الجديدة
$newApps = [];
$duplicates = 0;
$addedCount = 0;

foreach ($scrapedNames as $name) {
    $name = trim($name);
    
    // التحقق من وجود التطبيق بالفعل
    $exists = appExists($name, $originalData['mobile_apps'] ?? []) ||
              appExists($name, $originalData['games'] ?? []) ||
              appExists($name, $originalData['tv_apps'] ?? []);
    
    if ($exists) {
        $duplicates++;
        continue;
    }
    
    $category = guessCategory($name);
    $nextId = 1;
    
    // حساب الـ ID التالي
    foreach ($originalData as $cat) {
        if (is_array($cat)) {
            foreach ($cat as $item) {
                if (isset($item['id']) && $item['id'] >= $nextId) {
                    $nextId = $item['id'] + 1;
                }
            }
        }
    }
    
    $newApp = [
        'id' => count($newApps) + 1000,
        'name' => $name,
        'description' => 'تطبيق ' . $name . ' - استخراج من Mobilltna.org',
        'full_description' => $name . ' هو تطبيق متميز يوفر ميزات عديدة. تم استخراجه تلقائياً من mobilltna.org',
        'icon' => 'fa-star',
        'rating' => 4.0 + (rand(0, 10) / 10),
        'reviews' => rand(100, 1000),
        'category' => $category,
        'download_link' => 'https://mobilltna.org/'
    ];
    
    if (!isset($originalData[$category === 'mobile_apps' ? 'mobile_apps' : ($category === 'games' ? 'games' : 'tv_apps')])) {
        $originalData[$category === 'mobile_apps' ? 'mobile_apps' : ($category === 'games' ? 'games' : 'tv_apps')] = [];
    }
    
    $originalData[$category === 'mobile_apps' ? 'mobile_apps' : ($category === 'games' ? 'games' : 'tv_apps')][] = $newApp;
    $newApps[] = $newApp;
    $addedCount++;
}

// حفظ البيانات المحدثة
$jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents($appsFile, json_encode($originalData, $jsonOptions));

echo "\n✅ تم الدمج بنجاح!\n";
echo "=" . str_repeat("=", 40) . "\n";
echo "📊 الإحصائيات:\n";
echo "  • تطبيقات جديدة مضافة: $addedCount\n";
echo "  • تطبيقات موجودة بالفعل: $duplicates\n";
echo "  • التطبيقات الكلية الآن: " . 
    (count($originalData['mobile_apps'] ?? []) + 
     count($originalData['games'] ?? []) + 
     count($originalData['tv_apps'] ?? [])) . "\n";
echo "=" . str_repeat("=", 40) . "\n";

if ($addedCount > 0) {
    echo "\n📱 أول 10 تطبيقات مضافة:\n";
    foreach (array_slice($newApps, 0, 10) as $index => $app) {
        echo ($index + 1) . ". " . $app['name'] . " (" . $app['category'] . ")\n";
    }
}

echo "\n✅ تم حفظ apps.json بنجاح!\n";
?>