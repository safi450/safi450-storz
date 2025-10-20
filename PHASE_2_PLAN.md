# 🗄️ المرحلة 2: قاعدة البيانات والـ API المتقدم

## 📋 ملخص المرحلة 2

**الهدف**: تحويل التطبيق من نظام JSON إلى نظام قاعدة بيانات احترافي مع API متقدم

**المدة**: 1 أسبوع
**الأولوية**: عالية جداً ⚠️

---

## 🔄 ما سيتغير

### قبل (الآن):
```
apps.json (JSON ثابت)
  ↓
advanced-search.js (بحث العميل)
  ↓
api/index.php (API بسيط)
```

### بعد (المرحلة 2):
```
MySQL Database (قاعدة بيانات)
  ↓
api/index.php (API متقدم)
  ↓
admin/ (لوحة تحكم)
  ↓
عمليات متقدمة (تقييمات، تحميلات، إلخ)
```

---

## 📊 الجداول المطلوبة

### 1. جدول التطبيقات `apps`
```sql
CREATE TABLE apps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    full_description TEXT,
    category VARCHAR(50),
    icon VARCHAR(50),
    rating FLOAT DEFAULT 0,
    reviews INT DEFAULT 0,
    download_link VARCHAR(255),
    downloads_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_rating (rating),
    FULLTEXT KEY ft_search (name, description)
);
```

### 2. جدول الفئات `categories`
```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    display_order INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. جدول المستخدمين `users`
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4. جدول التقييمات `ratings`
```sql
CREATE TABLE ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    user_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (app_id, user_id)
);
```

### 5. جدول التحميلات `downloads`
```sql
CREATE TABLE downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE
);
```

### 6. جدول السجلات `logs`
```sql
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(100),
    user_id INT,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 📁 الملفات المطلوب إنشاؤها

### `database/config.php`
```php
<?php
// إعدادات الاتصال بقاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'safi_storz');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
```

### `database/schema.sql`
- جميع جداول SQL

### `database/init.php`
- إنشاء الجداول تلقائياً

### `database/seed.php`
- إدراج البيانات من apps.json

---

## 🔌 API المتقدمة

### المسارات الجديدة

#### التطبيقات
```
GET /api/v2/apps                          → جميع مع Pagination
GET /api/v2/apps/{id}                    → تطبيق محدد مع الإحصائيات
POST /api/v2/apps                         → إضافة (Admin فقط)
PUT /api/v2/apps/{id}                    → تعديل (Admin فقط)
DELETE /api/v2/apps/{id}                 → حذف (Admin فقط)
```

#### الفئات
```
GET /api/v2/categories                   → جميع الفئات
GET /api/v2/categories/{id}/apps         → تطبيقات الفئة
```

#### البحث
```
GET /api/v2/search                       → بحث متقدم
GET /api/v2/search/suggestions           → اقتراحات
GET /api/v2/trending                     → الأكثر شهرة
GET /api/v2/top-rated                    → الأعلى تقييماً
```

#### التقييمات
```
GET /api/v2/apps/{id}/ratings            → تقييمات التطبيق
POST /api/v2/apps/{id}/ratings           → إضافة تقييم
GET /api/v2/my-ratings                   → تقييماتي (محمي)
```

#### الإحصائيات
```
GET /api/v2/stats/global                 → إحصائيات عامة
GET /api/v2/stats/apps/{id}              → إحصائيات تطبيق
GET /api/v2/stats/trending               → الترندات
```

---

## 🛡️ نظام المصادقة

### التوكن JWT (اختياري)
```
Header: Authorization: Bearer <token>

POST /api/v2/auth/login
POST /api/v2/auth/register
POST /api/v2/auth/logout
POST /api/v2/auth/refresh
```

---

## 🎛️ لوحة التحكم الإدارية

### الصفحات المطلوبة

#### `/admin/index.php` - الصفحة الرئيسية
- إحصائيات سريعة
- الأنشطة الأخيرة
- الرسوم البيانية

#### `/admin/apps.php` - إدارة التطبيقات
- قائمة التطبيقات
- إضافة/تعديل/حذف
- البحث والتصفية
- التحميل بكمية كبيرة

#### `/admin/categories.php` - إدارة الفئات
- قائمة الفئات
- إضافة/تعديل/حذف

#### `/admin/users.php` - إدارة المستخدمين
- قائمة المستخدمين
- تعديل الأدوار
- حذف حسابات

#### `/admin/ratings.php` - إدارة التقييمات
- عرض التقييمات
- حذف التقييمات السيئة
- الإحصائيات

#### `/admin/stats.php` - الإحصائيات
- رسوم بيانية
- تقارير
- تحليلات

#### `/admin/logs.php` - السجلات
- عرض جميع العمليات
- تتبع التغييرات

---

## 🔄 عملية الهجرة (Migration)

### الخطوة 1: إنشاء قاعدة البيانات
```bash
php database/init.php
```

### الخطوة 2: استيراد البيانات الحالية
```bash
php database/seed.php
```

### الخطوة 3: اختبار API الجديد
```bash
curl http://localhost/app/api/v2/apps
```

### الخطوة 4: تحديث الموقع (اختياري)
```javascript
// استخدام API الجديد بدلاً من JSON
const response = await fetch('/api/v2/apps');
```

---

## 📊 الميزات الجديدة

### ✨ للمستخدمين
- ✅ تقييم التطبيقات
- ✅ التعليقات على التطبيقات
- ✅ قائمة المفضلة (اختياري)
- ✅ إحصائيات الشخصية (اختياري)
- ✅ إشعارات (اختياري)

### 🛠️ للمديرين
- ✅ لوحة تحكم كاملة
- ✅ إدارة التطبيقات
- ✅ إدارة المستخدمين
- ✅ عرض التقارير
- ✅ تتبع الأنشطة

### 📈 للنظام
- ✅ أداء أفضل (Indexing)
- ✅ Caching متقدم
- ✅ نسخ احتياطي تلقائي
- ✅ معالجة أخطاء أفضل

---

## 🔒 الأمان المحسّن

### المصادقة
- ✅ كلمات مرور مشفرة (bcrypt)
- ✅ JWT Tokens
- ✅ Session Management

### التفويض
- ✅ نظام الأدوار (Roles)
- ✅ التحكم بالوصول (ACL)

### الحماية
- ✅ CSRF Protection
- ✅ SQL Injection Prevention
- ✅ XSS Protection
- ✅ Rate Limiting

---

## 📈 المعايير الجديدة

| المعيار | الحالي | المرحلة 2 |
|--------|--------|----------|
| **التخزين** | JSON | MySQL |
| **السرعة** | 100ms | 10ms |
| **الإمكانيات** | بحث فقط | كاملة |
| **الأمان** | أساسي | عالي |
| **التقاريح** | معدومة | متقدمة |
| **المستخدمين** | 0 | غير محدود |

---

## 🚀 جدول الأعمال

### اليوم 1️⃣
- إنشاء قاعدة البيانات
- إنشاء الجداول
- استيراد البيانات

### اليوم 2️⃣
- API v2 الأساسي
- المصادقة
- الاختبار

### اليوم 3️⃣
- لوحة التحكم الأساسية
- إدارة التطبيقات
- الاختبار

### اليوم 4️⃣
- المميزات المتقدمة (تقييمات، تعليقات)
- الإحصائيات
- التقارير

### اليوم 5️⃣ - 7️⃣
- الاختبار الشامل
- إصلاح الأخطاء
- التوثيق
- النشر

---

## 💡 نصائح مهمة

### ✅ أفضل الممارسات
1. **الـ Backups**: أخذ نسخة احتياطية قبل الهجرة
2. **الاختبار**: اختبر كل شيء قبل النشر
3. **التوثيق**: وثّق كل تغيير
4. **الأداء**: راقب سرعة الاستعلامات
5. **الأمان**: استخدم استعلامات معدة (Prepared Statements)

### ⚠️ تجنب
- ❌ حذف apps.json (احتياطي)
- ❌ تعديل API v1 (بعض المستخدمين قد يستخدمونه)
- ❌ تغيير أسماء الجداول بعد النشر
- ❌ إضافة عمود إجباري بدون قيمة افتراضية

---

## 🎯 النتيجة المتوقعة

### بعد اكتمال المرحلة 2:
- ✅ نظام قاعدة بيانات كامل
- ✅ API متقدمة وآمنة
- ✅ لوحة تحكم احترافية
- ✅ إمكانيات إدارية شاملة
- ✅ جاهزية 100% للإنتاج

### الإحصائيات
- **أداء**: 90% أسرع
- **أمان**: 99% محمي
- **توفرية**: 99.9%
- **معالجة**: 1000+ طلب/ثانية

---

## 📋 قائمة التحقق قبل البدء

```
☐ نسخة احتياطية من apps.json
☐ إنشاء قاعدة بيانات
☐ قراءة MySQL Docs
☐ إعداد بيئة التطوير
☐ إعداد XAMPP
☐ تعطيل الموقع أثناء الهجرة
```

---

## 🔗 المراجع المفيدة

- MySQL Documentation: https://dev.mysql.com/doc/
- PHP MySQLi: https://www.php.net/manual/en/book.mysqli.php
- API REST Best Practices: https://restfulapi.net/
- Security: https://owasp.org/

---

## 📞 الدعم

**متى تبدأ؟**
- اختر يوماً ووقتاً محدداً
- أخبر المستخدمين مسبقاً
- خذ نسخة احتياطية أولاً

**في حالة المشاكل:**
1. تحقق من السجلات (logs)
2. استعد من النسخة الاحتياطية
3. اختبر في بيئة تطوير أولاً

---

**الحالة**: جاهز للبدء
**الإصدار**: 2.0.0
**التاريخ**: 2025-01-15
