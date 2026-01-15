# دليل التثبيت — Godyar CMS v1.09 (استضافة مشتركة / cPanel)

هذا الدليل مخصص للاستضافة المشتركة بدون SSH.

## المتطلبات
- PHP: 8.1+ (مُختبر على 8.4.x)
- MariaDB/MySQL: 10.4+ (مُختبر على MariaDB 10.11)
- Apache/LiteSpeed مع mod_rewrite
- إضافات PHP: mysqli / pdo_mysql, mbstring, curl, json

## 1) رفع الملفات
1. افتح cPanel → **File Manager**
2. اذهب إلى `public_html/`
3. ارفع ملفات المشروع (المجلدات والملفات كما هي).

> لا ترفع ملف `.env` داخل `public_html`.

## 2) قاعدة البيانات
1. cPanel → **MySQL Databases**
2. أنشئ قاعدة بيانات + مستخدم
3. امنح المستخدم صلاحية **ALL PRIVILEGES** على القاعدة

### استيراد قاعدة البيانات
- استخدم phpMyAdmin → Import
- استورد ملف dump/CSV بحسب ما لديك.

## 3) إعداد ملف البيئة (.env) خارج مجلد الويب
أنشئ مجلد خاص خارج `public_html` مثل:
- `/home/YOUR_USERNAME/godyar_private/`

ثم أنشئ ملف:
- `/home/YOUR_USERNAME/godyar_private/.env`

مثال محتوى (استبدل القيم):
```env
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=YOUR_DB_NAME
DB_USER=YOUR_DB_USER
DB_PASS=YOUR_DB_PASS

SITE_URL=https://your-domain.com
```

### ربط المشروع بملف .env (مهم على PHP-FPM)
في هذا الريبو ستجد:
- `includes/env_path.php.example`

انسخه إلى:
- `includes/env_path.php`

ثم عدّل `ENV_FILE` داخله ليشير إلى:
- `/home/YOUR_USERNAME/godyar_private/.env`

> **مهم:** لا ترفع `includes/env_path.php` إلى GitHub (الموجود في هذا الريبو هو Example فقط).

## 4) .htaccess (Pretty URLs + لغات /ar /en /fr)
- تأكد أن `.htaccess` موجود داخل `public_html/`
- يجب أن يكون `RewriteEngine On` مفعّلًا.

## 5) جلسات PHP (Sessions) خارج الويب
أنشئ:
- `/home/YOUR_USERNAME/godyar_private/sessions`

ثم اضبط `session.save_path` عبر MultiPHP INI Editor أو `.user.ini` حسب الاستضافة.

## 6) الاختبار (Smoke Test)
اختبر بالترتيب:
- `/` و `/ar` و `/en` و `/fr`
- `/news/id/1` (أو أي ID موجود)
- `/archive`
- `/search?q=test`
- `/admin/login`

## 7) الأمان قبل الإطلاق
- أوقف `display_errors` في الإنتاج
- تأكد أن `.env` غير قابل للوصول عبر الويب
- احذف أي ملفات نسخ احتياطية `.bak / .sql / .log` داخل `public_html`

## حلول سريعة لمشاكل شائعة
### HTTP 500
- راجع `error_log` في cPanel أو log file الذي حددته.
### Loop للغات /ar
- راجع قواعد rewrite وتأكد من عدم وجود redirect مزدوج بين Apache وPHP.
