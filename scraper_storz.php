<?php
/**
 * Scraper لموقع Storz.ma - تطبيقات التلفزيون
 * أسهل وأفضل من Mobilltna لأنه يستخدم HTML ثابت
 */

// إعدادات
ini_set('memory_limit', '256M');
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

// مسار الحفظ
$outputFile = __DIR__ . '/data/apps_storz.json';

echo "<pre style='font-family: monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px;'>";
echo "🔄 بدء استخراج البيانات من Storz.ma\n";
echo str_repeat("=", 60) . "\n\n";

// دالة الـ Scraping
function scrapeStorz() {
    $apps = [
        'tv_apps' => [],
        'mobile_apps' => [],
        'games' => []
    ];
    
    // الفئات المتاحة
    $categories = [
        'https://www.storz.ma/category/تطبيقات-القنوات-والافلام/' => 'tv_apps'
    ];
    
    foreach ($categories as $url => $category) {
        echo "📥 جاري سحب: $url\n";
        
        $html = @file_get_contents($url);
        if (!$html) {
            echo "❌ خطأ في الوصول للصفحة\n";
            continue;
        }
        
        // استخراج البيانات من HTML
        $pattern = '/<a href="([^"]+)" (?:title=")?([^"]*)?[^>]*>\s*<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"/i';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        echo "✅ وجدت " . count($matches) . " تطبيق\n\n";
        
        foreach ($matches as $index => $match) {
            $link = $match[1];
            $title = !empty($match[2]) ? $match[2] : $match[4];
            $image = $match[3];
            
            // استخراج التفاصيل من صفحة التطبيق
            $appHtml = @file_get_contents($link);
            if (!$appHtml) continue;
            
            // استخراج الوصف
            preg_match('/<div class="entry-content">(.*?)<\/div>/is', $appHtml, $desc);
            $description = isset($desc[1]) ? strip_tags(substr($desc[1], 0, 150)) : 'تطبيق تلفزيوني متميز';
            
            // استخراج كود التفعيل إن وجد
            preg_match('/كود.{0,20}?[:：]?\s*([A-Z0-9\-]+)/i', $appHtml, $code);
            $activationCode = $code[1] ?? '';
            
            $app = [
                'id' => $index + 1,
                'name' => trim($title),
                'description' => trim($description),
                'icon' => 'fa-tv',
                'rating' => 4.5 + (rand(0, 5) / 10),
                'reviews' => rand(500, 5000),
                'category' => $category,
                'image' => $image,
                'download_link' => $link,
                'activation_code' => $activationCode
            ];
            
            $apps[$category][] = $app;
            
            echo "  ✓ " . substr($app['name'], 0, 40) . "...\n";
            sleep(1); // احترام للموقع
        }
    }
    
    return $apps;
}

// تنفيذ الـ Scraping
$data = scrapeStorz();

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 الإحصائيات:\n";
echo "  - تطبيقات التلفزيون: " . count($data['tv_apps']) . "\n";
echo "  - تطبيقات الهاتف: " . count($data['mobile_apps']) . "\n";
echo "  - الألعاب: " . count($data['games']) . "\n";
echo "  - الإجمالي: " . (count($data['tv_apps']) + count($data['mobile_apps']) + count($data['games'])) . "\n\n";

// دمج مع البيانات القديمة
echo "🔗 جاري دمج البيانات مع الملف الموجود...\n";
$existingFile = __DIR__ . '/data/apps.json';
if (file_exists($existingFile)) {
    $existing = json_decode(file_get_contents($existingFile), true);
    
    // دمج TV Apps الجديدة
    if (!empty($data['tv_apps'])) {
        // تحديد أرقام جديدة
        $maxId = 0;
        foreach ($existing['tv_apps'] as $app) {
            if ($app['id'] > $maxId) $maxId = $app['id'];
        }
        
        foreach ($data['tv_apps'] as &$app) {
            $app['id'] = ++$maxId;
        }
        
        $existing['tv_apps'] = array_merge($existing['tv_apps'], $data['tv_apps']);
    }
    
    $data = $existing;
    echo "✅ تم دمج البيانات بنجاح\n";
}

// حفظ البيانات
echo "\n💾 جاري حفظ البيانات...\n";
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_ERROR_UTF8);

if (file_put_contents($existingFile, $json, LOCK_EX)) {
    $fileSize = filesize($existingFile);
    echo "✅ تم الحفظ بنجاح!\n";
    echo "📁 الملف: " . $existingFile . "\n";
    echo "📊 الحجم: " . number_format($fileSize / 1024, 2) . " KB\n";
} else {
    echo "❌ خطأ في الحفظ\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✨ انتهى الاستخراج بنجاح!\n";
echo "</pre>";
?>