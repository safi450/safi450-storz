<?php
/**
 * مستخرج بيانات التطبيقات من موقع Mobilltna.org
 * استخراج البيانات من أول 30 صفحة مع تأخير آمن
 */

header('Content-Type: application/json; charset=utf-8');

class MobilltnaSScraper {
    private $baseUrl = "https://mobilltna.org";
    private $appsData = [
        "mobile_apps" => [],
        "games" => [],
        "tv_apps" => []
    ];
    private $appIdCounter = [
        "mobile_apps" => 1,
        "games" => 1,
        "tv_apps" => 1
    ];
    
    public function __construct() {
        set_time_limit(0); // لا تحديد زمني
        ini_set('memory_limit', '512M');
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
        flush();
    }
    
    private function safeRequest($url, $retries = 3) {
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 10,
                        'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n'
                    ]
                ]);
                
                $content = @file_get_contents($url, false, $context);
                if ($content !== false) {
                    return $content;
                }
                
                $this->log("⚠️  محاولة " . ($attempt + 1) . "/{$retries} فشلت للـ URL: {$url}");
                if ($attempt < $retries - 1) {
                    sleep(pow(2, $attempt));
                }
            } catch (Exception $e) {
                $this->log("❌ خطأ: " . $e->getMessage());
            }
        }
        
        $this->log("❌ فشل الحصول على: {$url}");
        return null;
    }
    
    private function extractAppIcon($name) {
        $icons = [
            "player" => "fa-video",
            "video" => "fa-video",
            "music" => "fa-music",
            "photo" => "fa-image",
            "camera" => "fa-camera",
            "editor" => "fa-edit",
            "browse" => "fa-globe",
            "browser" => "fa-globe",
            "game" => "fa-gamepad",
            "puzzle" => "fa-puzzle-piece",
            "chess" => "fa-chess",
            "sport" => "fa-trophy",
            "tv" => "fa-tv",
            "movie" => "fa-film",
            "series" => "fa-theater-masks",
            "book" => "fa-book",
            "read" => "fa-book",
            "news" => "fa-newspaper",
            "weather" => "fa-cloud",
            "map" => "fa-map",
            "navigate" => "fa-map",
            "tool" => "fa-tools",
            "util" => "fa-wrench",
        ];
        
        $nameLower = strtolower($name);
        foreach ($icons as $keyword => $icon) {
            if (strpos($nameLower, $keyword) !== false) {
                return $icon;
            }
        }
        return "fa-star";
    }
    
    private function parseRating($ratingText) {
        if (!$ratingText) {
            return 4.5;
        }
        try {
            $rating = (float)preg_replace('/[^0-9.]/', '', $ratingText);
            return min(5.0, max(1.0, $rating));
        } catch (Exception $e) {
            return 4.5;
        }
    }
    
    private function parseReviewsCount($reviewsText) {
        if (!$reviewsText) {
            return 100;
        }
        try {
            $text = strtolower(str_replace(',', '', $reviewsText));
            if (strpos($text, 'k') !== false) {
                return (int)(((float)preg_replace('/[^0-9.]/', '', $text)) * 1000);
            }
            return (int)preg_replace('/[^0-9]/', '', $text);
        } catch (Exception $e) {
            return 100;
        }
    }
    
    public function scrapeCategory($categoryName, $categoryUrl, $maxPages = 30) {
        $this->log("🔄 بدء استخراج البيانات من: {$categoryName}");
        
        // تحديد المفتاح
        if (strpos($categoryName, 'لعبة') !== false || strpos($categoryName, 'game') !== false) {
            $catKey = "games";
        } elseif (strpos($categoryName, 'tv') !== false || strpos($categoryName, 'تلفاز') !== false) {
            $catKey = "tv_apps";
        } else {
            $catKey = "mobile_apps";
        }
        
        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $url = $page == 1 ? $categoryUrl : "{$categoryUrl}?page={$page}";
                
                $this->log("  📄 الصفحة {$page}/{$maxPages}: {$url}");
                
                $html = $this->safeRequest($url);
                if (!$html) {
                    $this->log("  ⚠️  تخطي الصفحة {$page}");
                    continue;
                }
                
                // محاولة تحليل HTML
                $doc = new DOMDocument('1.0', 'UTF-8');
                libxml_use_internal_errors(true);
                @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                libxml_clear_errors();
                
                $xpath = new DOMXPath($doc);
                
                // البحث عن عناصر التطبيقات (محاولات متعددة)
                $appItems = $xpath->query("//div[@class='app-item']");
                if ($appItems->length == 0) {
                    $appItems = $xpath->query("//*[@data-app]");
                }
                if ($appItems->length == 0) {
                    $appItems = $xpath->query("//div[contains(@class, 'app')]");
                }
                
                if ($appItems->length == 0) {
                    $this->log("  ❌ لم يتم العثور على تطبيقات في الصفحة {$page}");
                    continue;
                }
                
                $appsFound = 0;
                foreach ($appItems as $item) {
                    try {
                        // استخراج البيانات
                        $nameNode = $xpath->query(".//h2 | .//h3 | .//a[@class='app-name'] | .//*[@class='title']", $item);
                        $name = $nameNode->length > 0 ? trim($nameNode->item(0)->textContent) : "Unknown App";
                        
                        $descNode = $xpath->query(".//p[@class='description'] | .//*[@class='desc']", $item);
                        $description = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : "تطبيق مميز";
                        
                        // قص الوصف إذا كان طويل جداً
                        if (strlen($description) > 150) {
                            $description = substr($description, 0, 150) . "...";
                        }
                        
                        $ratingNode = $xpath->query(".//*[@class='rating'] | .//*[@class='rate']", $item);
                        $rating = $ratingNode->length > 0 ? $this->parseRating($ratingNode->item(0)->textContent) : 4.5;
                        
                        $reviewsNode = $xpath->query(".//*[@class='reviews'] | .//*[@class='count']", $item);
                        $reviews = $reviewsNode->length > 0 ? $this->parseReviewsCount($reviewsNode->item(0)->textContent) : 100;
                        
                        $appObj = [
                            "id" => $this->appIdCounter[$catKey],
                            "name" => $name,
                            "description" => $description,
                            "icon" => $this->extractAppIcon($name),
                            "rating" => $rating,
                            "reviews" => $reviews,
                            "category" => "app",
                            "download_link" => $this->baseUrl . "/app/" . strtolower(str_replace(' ', '-', $name))
                        ];
                        
                        $this->appsData[$catKey][] = $appObj;
                        $this->appIdCounter[$catKey]++;
                        $appsFound++;
                        
                    } catch (Exception $e) {
                        // تجاهل التطبيقات التي تحتوي على أخطاء
                        continue;
                    }
                }
                
                $this->log("  ✅ تم استخراج {$appsFound} تطبيق من الصفحة {$page}");
                
                // تأخير آمن بين الطلبات
                if ($page < $maxPages) {
                    sleep(2);
                }
                
            } catch (Exception $e) {
                $this->log("  ❌ خطأ في معالجة الصفحة {$page}: " . $e->getMessage());
                continue;
            }
        }
        
        $count = count($this->appsData[$catKey]);
        $this->log("✅ انتهى استخراج {$categoryName}: {$count} تطبيق");
    }
    
    public function scrapeAll() {
        $this->log("============================================================");
        $this->log("🚀 بدء استخراج بيانات التطبيقات من Mobilltna.org");
        $this->log("============================================================");
        
        $categories = [
            ["تطبيقات موبايل", "{$this->baseUrl}/apps", "mobile_apps"],
            ["الألعاب", "{$this->baseUrl}/games", "games"],
            ["تطبيقات التلفاز", "{$this->baseUrl}/tv", "tv_apps"],
        ];
        
        foreach ($categories as $cat) {
            $this->scrapeCategory($cat[0], $cat[1], 30);
            sleep(3);
        }
        
        return $this->appsData;
    }
    
    public function saveToJson($filename = 'data/apps_new.json') {
        try {
            $json = json_encode($this->appsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            file_put_contents($filename, $json);
            
            $total = count($this->appsData["mobile_apps"]) + 
                     count($this->appsData["games"]) + 
                     count($this->appsData["tv_apps"]);
            
            $this->log("✅ تم حفظ البيانات في: {$filename}");
            $this->log("📊 الإجمالي: {$total} تطبيق");
            $this->log("   • تطبيقات موبايل: " . count($this->appsData["mobile_apps"]));
            $this->log("   • ألعاب: " . count($this->appsData["games"]));
            $this->log("   • تطبيقات TV: " . count($this->appsData["tv_apps"]));
            
        } catch (Exception $e) {
            $this->log("❌ خطأ في حفظ الملف: " . $e->getMessage());
        }
    }
}

// تشغيل المستخرج
try {
    echo "<!DOCTYPE html>\n";
    echo "<html dir='rtl'><head><meta charset='UTF-8'><style>\n";
    echo "body{font-family:Arial;background:#1a1a1a;color:#00ff00;padding:20px;line-height:1.6;}\n";
    echo "pre{background:#000;padding:15px;border:1px solid #00ff00;border-radius:5px;}\n";
    echo "</style></head><body>\n";
    echo "<pre>\n";
    
    $startTime = time();
    $this->log("⏰ بدء الاستخراج: " . date('Y-m-d H:i:s'));
    
    $scraper = new MobilltnaSScraper();
    $data = $scraper->scrapeAll();
    $scraper->saveToJson('data/apps_new.json');
    
    $endTime = time();
    $duration = $endTime - $startTime;
    
    echo "\n";
    echo "⏰ انتهى الاستخراج: " . date('Y-m-d H:i:s') . "\n";
    echo "⏳ الوقت المستغرق: {$duration} ثانية\n";
    echo "============================================================\n";
    
    echo "\n✅ تم بنجاح! يمكنك الآن:\n";
    echo "1. التحقق من البيانات في: data/apps_new.json\n";
    echo "2. استبدال apps.json بـ apps_new.json\n";
    echo "3. تحديث الموقع برؤية البيانات الجديدة\n";
    
    echo "</pre>\n</body></html>\n";
    
} catch (Exception $e) {
    echo "❌ خطأ غير متوقع: " . $e->getMessage();
}
?>