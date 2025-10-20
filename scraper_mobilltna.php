<?php
/**
 * Scraper for mobilltna.org
 * استخراج أسماء التطبيقات من جميع الفئات
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

class MobiltnaaScraper {
    private $baseUrl = 'https://mobilltna.org';
    private $appNames = [];
    private $categories = [
        'mobile-apps' => 'mobile_apps',
        'games-mod' => 'games',
        'apps-mod' => 'apps_mod'
    ];

    public function scrape() {
        echo "🔍 بدء استخراج البيانات من mobilltna.org...\n\n";

        foreach ($this->categories as $urlPath => $categoryName) {
            echo "📂 استخراج من: $categoryName\n";
            $this->scrapeCategory($urlPath, $categoryName);
        }

        return $this->appNames;
    }

    private function scrapeCategory($urlPath, $categoryName) {
        $page = 1;
        $maxPages = 3; // استخراج من أول 3 صفحات فقط لتجنب الوقت الطويل
        $appCount = 0;

        while ($page <= $maxPages) {
            if ($page === 1) {
                $url = $this->baseUrl . '/' . $urlPath . '/';
            } else {
                $url = $this->baseUrl . '/' . $urlPath . '/page/' . $page . '/';
            }

            echo "  صفحة $page: ";
            $html = $this->fetchPage($url);
            
            if (!$html) {
                echo "❌ فشل التحميل\n";
                break;
            }

            $names = $this->extractNames($html);
            
            if (empty($names)) {
                echo "❌ لم يتم العثور على تطبيقات\n";
                break;
            }

            echo "✅ وجد " . count($names) . " تطبيق\n";

            foreach ($names as $name) {
                if (!empty($name) && !in_array($name, $this->appNames)) {
                    $this->appNames[] = $name;
                    $appCount++;
                }
            }

            $page++;
        }

        echo "  المجموع: $appCount تطبيق جديد\n\n";
    }

    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo "خطأ CURL: $error\n";
            return false;
        }

        return $response;
    }

    private function extractNames($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);

        $names = [];

        // Method 1: البحث عن العناوين في h4
        $h4Elements = $xpath->query('//h4/a');
        foreach ($h4Elements as $element) {
            $name = trim($element->textContent);
            $this->addNameIfValid($names, $name);
        }

        // Method 2: البحث عن الـ h3 و h2
        $headings = $xpath->query('//h3/a | //h2/a');
        foreach ($headings as $element) {
            $name = trim($element->textContent);
            $this->addNameIfValid($names, $name);
        }

        // Method 3: البحث عن أي رابط قد يكون بعنوان
        $allLinks = $xpath->query('//a[@title]');
        foreach ($allLinks as $element) {
            $name = trim($element->getAttribute('title'));
            if (strlen($name) > 5 && !preg_match('/\d{4}/', $name)) {
                $this->addNameIfValid($names, $name);
            }
        }

        // Method 4: استخراج من النصوص باستخدام regex
        if (preg_match_all('/تحميل\s+(?:برنامج|تطبيق|لعبة|app)?\s+([^Apk]+?)\s+(?:Apk|مهكر|بدون)/ui', $html, $matches)) {
            foreach ($matches[1] as $name) {
                $this->addNameIfValid($names, trim($name));
            }
        }

        return array_unique($names);
    }

    private function addNameIfValid(&$names, $name) {
        if (empty($name)) return;

        // تنظيف الاسم
        $name = preg_replace('/\s*Apk\s*$/i', '', $name);
        $name = preg_replace('/\s*مهكر\s*$/i', '', $name);
        $name = preg_replace('/\s*تحميل\s+(?:برنامج|تطبيق|لعبة)?\s*/i', '', $name);
        $name = preg_replace('/\s*تنزيل\s+/i', '', $name);
        $name = preg_replace('/\s*اخر\s+(?:اصدار|version).*$/i', '', $name);
        $name = preg_replace('/\s*مجانا?\s*$/i', '', $name);
        $name = preg_replace('/\s*للاندرويد.*$/i', '', $name);
        $name = trim($name);

        if (strlen($name) > 3 && !in_array($name, $names) && !preg_match('/^\d+/', $name)) {
            $names[] = $name;
        }
    }

    public function saveToJson($filename) {
        $json = json_encode($this->appNames, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filename, $json);
        echo "✅ تم حفظ " . count($this->appNames) . " اسم في $filename\n";
    }

    public function getNames() {
        return $this->appNames;
    }

    public function getCount() {
        return count($this->appNames);
    }
}

// تنفيذ السكريبت
if (php_sapi_name() === 'cli') {
    $scraper = new MobiltnaaScraper();
    $names = $scraper->scrape();
    
    echo "=" . str_repeat("=", 40) . "\n";
    echo "📊 النتيجة النهائية\n";
    echo "=" . str_repeat("=", 40) . "\n";
    echo "عدد الأسماء المستخرجة: " . $scraper->getCount() . "\n\n";
    
    // حفظ النتائج
    $outputFile = __DIR__ . '/data/mobilltna_apps.json';
    $scraper->saveToJson($outputFile);
    
    // عرض أول 10 أسماء
    echo "\n📱 أول 10 أسماء:\n";
    $names = $scraper->getNames();
    for ($i = 0; $i < min(10, count($names)); $i++) {
        echo ($i + 1) . ". " . $names[$i] . "\n";
    }
} else {
    // Web Interface
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Scraper - Mobilltna.org</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Cairo', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                padding: 30px;
            }
            h1 { color: #333; margin-bottom: 20px; text-align: center; }
            .button-group { display: flex; gap: 10px; margin: 20px 0; }
            button {
                flex: 1;
                padding: 12px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                transition: all 0.3s;
            }
            .btn-primary {
                background: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background: #5568d3;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }
            .output {
                background: #f5f5f5;
                border-left: 4px solid #667eea;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
                max-height: 400px;
                overflow-y: auto;
                font-family: monospace;
                white-space: pre-wrap;
            }
            .success { color: #27ae60; }
            .error { color: #e74c3c; }
            .info { color: #3498db; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 استخراج التطبيقات من Mobilltna.org</h1>
            
            <div class="button-group">
                <button class="btn-primary" onclick="startScraping()">🚀 ابدأ الاستخراج</button>
            </div>
            
            <div id="output" class="output" style="display:none;"></div>
        </div>

        <script>
            function startScraping() {
                const output = document.getElementById('output');
                output.style.display = 'block';
                output.innerHTML = '<span class="info">⏳ جاري الاستخراج... يرجى الانتظار...</span>';
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=scrape', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    let html = '<span class="success">✅ تم الانتهاء!</span>\n\n';
                    html += '<span class="info">📊 النتائج:</span>\n';
                    html += 'عدد التطبيقات: ' + data.count + '\n\n';
                    html += '<span class="info">📱 أول 20 تطبيق:</span>\n';
                    
                    data.names.slice(0, 20).forEach((name, i) => {
                        html += (i + 1) + '. ' + name + '\n';
                    });
                    
                    output.innerHTML = html;
                })
                .catch(error => {
                    output.innerHTML = '<span class="error">❌ خطأ: ' + error.message + '</span>';
                });
            }
        </script>
    </body>
    </html>
    <?php
}
?>