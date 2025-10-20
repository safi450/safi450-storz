# 📚 دليل استخدام GitHub + Dropbox

## 📍 الحالة الحالية

✅ **مشروعك موجود على GitHub:**
- **الرابط:** https://github.com/safi450/safi450-storz.git
- **الملفات المرفوعة:** جميع ملفات الموقع

---

## 🎯 المراحل القادمة

### 1️⃣ إعداد Dropbox للملفات الكبيرة

#### أولاً: إنشاء حساب Dropbox
```
1. اذهب إلى: https://www.dropbox.com/register
2. استخدم البريد: safifodh@gmail.com
3. أنشئ كلمة مرور قوية
4. تحقق من البريد الإلكتروني
```

#### ثانياً: إنشاء مجلد للتطبيقات
```
1. في Dropbox الرئيسية
2. اضغط "New folder"
3. اسم المجلد: safi-storz-apps
```

#### ثالثاً: رفع ملفات APK
```
1. انسخ جميع ملفات .apk من:
   c:\xampp\htdocs\app\downloads\
2. ألصقها في مجلد Dropbox
3. انتظر انتهاء الرفع
```

#### رابعاً: الحصول على روابط التحميل

لكل ملف APK:

```
1. اضغط كليك يمين على الملف
2. اختر "Share" أو "مشاركة الرابط"
3. اختر "Create link"
4. انسخ الرابط
5. ثم: غيّر آخر جزء من:
   ?dl=0  →  ?dl=1

مثال:
❌ https://www.dropbox.com/s/abc123xyz/netfly-tv.apk?dl=0
✅ https://www.dropbox.com/s/abc123xyz/netfly-tv.apk?dl=1
```

---

### 2️⃣ تحديث ملف البيانات JSON

افتح الملف: `data/apps.json`

**قبل:**
```json
{
    "id": 1,
    "name": "SAFI Player",
    "download_link": "downloads/netfly-tv.apk"
}
```

**بعد:**
```json
{
    "id": 1,
    "name": "SAFI Player",
    "download_link": "https://www.dropbox.com/s/YOUR_ID/netfly-tv.apk?dl=1"
}
```

---

### 3️⃣ استخدام نظام التحميل المتقدم

#### إضافة الملفات في HTML

في الصفحات (مثل `index.html` أو `mobile-apps.html`):

```html
<!-- في <head> -->
<link rel="stylesheet" href="css/download-manager.css">

<!-- في <body> قبل </body> -->
<script src="features/download-manager.js"></script>
```

#### استدعاء التحميل

```html
<!-- الطريقة 1: زر HTML مباشر -->
<button onclick="downloadApp('SAFI Player', 'https://www.dropbox.com/s/xxx/app.apk?dl=1', 1)">
    تحميل
</button>

<!-- الطريقة 2: من JavaScript -->
<script>
    downloadManager.startDownload('SAFI Player', 'https://www.dropbox.com/s/xxx/app.apk?dl=1', 1);
</script>
```

---

### 4️⃣ رفع التحديثات إلى GitHub

كلما أضفت تغييرات:

```bash
# 1. في PowerShell أو Terminal:
cd c:\xampp\htdocs\app

# 2. أضف التغييرات:
git add .

# 3. ارسل الرسالة:
git commit -m "تحديث روابط التحميل من Dropbox"

# 4. ادفع إلى GitHub:
git push origin main
```

---

## 🔗 روابط مهمة

| الخدمة | الرابط |
|-------|--------|
| **GitHub** | https://github.com/safi450/safi450-storz |
| **GitHub Raw** | https://raw.githubusercontent.com/safi450/safi450-storz/main/ |
| **Dropbox** | https://www.dropbox.com |
| **GitHub Desktop** | https://desktop.github.com/ |

---

## 📊 هيكل المشروع بعد التحديث

```
safi450-storz/
├── index.html                 ← الصفحة الرئيسية
├── mobile-apps.html
├── games.html
├── tv-apps.html
├── css/
│   ├── style.css
│   └── download-manager.css   ← نمط التحميل الجديد
├── data/
│   ├── apps.json              ← روابط Dropbox هنا
│   └── apps.js
├── features/
│   └── download-manager.js    ← نظام التحميل الجديد
└── downloads/                 ← فارغ (ملفات بـ Dropbox)
```

---

## ⚡ نصائح مهمة

### ✅ الأفضليات:
- ✅ استخدم Dropbox للملفات أكبر من 50MB
- ✅ استخدم GitHub لملفات التطبيق (CSS, JS, HTML)
- ✅ احفظ نسخة في GitHub للأمان
- ✅ استخدم روابط Dropbox المباشرة (اضف ?dl=1)

### ⚠️ تجنب:
- ❌ لا تضع ملفات APK في GitHub مباشرة
- ❌ لا تنسى تغيير ?dl=0 إلى ?dl=1
- ❌ لا تشارك Token محساسة في الـ Repository

---

## 🚀 الخطوات السريعة

```
1️⃣ أنشئ حساب Dropbox
2️⃣ ارفع ملفات APK
3️⃣ احصل على روابط مع ?dl=1
4️⃣ حدّث apps.json بالروابط
5️⃣ ادفع التحديثات لـ GitHub
6️⃣ تم! ✅
```

---

## 📞 الدعم

إذا واجهت مشكلة:

1. **خطأ في التحميل؟**
   - تأكد من إضافة `?dl=1` في نهاية رابط Dropbox

2. **ملف كبير جداً؟**
   - قسم الملف أو استخدم خدمة أخرى (Mega.nz, 4shared)

3. **لا يظهر الزر؟**
   - تأكد من تضمين `download-manager.js` و `download-manager.css`

---

**نجاح! 🎉 مشروعك جاهز للعمل!**