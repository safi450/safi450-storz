# 📦 إعداد Dropbox لملفات التطبيقات

## الخطوة 1️⃣: إنشاء مجلد Dropbox
1. اذهب إلى: https://www.dropbox.com
2. قم بتسجيل الدخول أو إنشاء حساب
3. أنشئ مجلد جديد باسم: `safi-storz-apps`

## الخطوة 2️⃣: رفع ملفات APK
1. انسخ جميع ملفات `.apk` إلى المجلد
2. تأكد من تسمية الملفات بشكل واضح

## الخطوة 3️⃣: الحصول على روابط التحميل
لكل ملف APK:
1. اضغط كليك يمين على الملف
2. اختر "Get link" أو "مشاركة الرابط"
3. انسخ الرابط
4. **غيّر آخر جزء من الرابط:**
   - من: `?dl=0`
   - إلى: `?dl=1`

**مثال:**
```
❌ https://www.dropbox.com/s/xxxxx/app.apk?dl=0
✅ https://www.dropbox.com/s/xxxxx/app.apk?dl=1
```

## الخطوة 4️⃣: تحديث ملف JSON
ضع الرابط Dropbox في `data/apps.json`:
```json
{
    "id": 1,
    "name": "تطبيق",
    "download_link": "https://www.dropbox.com/s/xxxxx/app.apk?dl=1"
}
```

## ✅ جاهز!
الآن عندما يضغط الزائر على "تحميل"، سيحمّل من Dropbox!