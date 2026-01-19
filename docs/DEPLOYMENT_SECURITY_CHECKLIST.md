# Deployment & Security Checklist (Godyar CMS)

## قبل الإطلاق (Staging)
1) انقل `.env` خارج `public_html` (مثال: `/home/USER/godyar/.env`).
2) فعّل إخفاء الأخطاء:
   - `display_errors=0`
   - `log_errors=1`
3) اضبط مسار الجلسات:
   - أنشئ: `storage/sessions` خارج الويب إن أمكن
   - ضع في `.env`: `SESSION_SAVE_PATH=/home/USER/godyar/storage/sessions`
4) شغّل فحص BOM:
   - `php tools/scan_bom.php --fix`
5) اختبر الروابط الأساسية (Smoke Test):
   - `/`, `/ar`, `/en`, `/fr`
   - `/news/...`, `/archive`, `/trending`, `/saved`
   - `/admin/login`

## بعد نجاح الإطلاق
- احذف أو عطّل مجلد `install/` (أو امنع الوصول له عبر `.htaccess`).
- تأكد من منع تنفيذ PHP داخل `uploads/` وأي مجلد رفع آخر.
- تأكد من عدم وجود:
  - `old/`
  - ملفات `*.bak_*`, `*.sql` خارج مسارات التثبيت
  - سجلات `error_log` داخل webroot

## ENV_FILE (مسار .env خارج الويب)

- يوصى بتحديد مسار ملف .env خارج public_html عبر متغير بيئة:

```
ENV_FILE=/home/YOUR_USERNAME/godyar_private/.env
```

- على Apache/LiteSpeed أضف في public_html/.htaccess:

```
SetEnv ENV_FILE /home/YOUR_USERNAME/godyar_private/.env
```

- بديل إذا لم يعمل SetEnv: ضع في أعلى includes/bootstrap.php قبل تحميل includes/env.php:

```php
putenv('ENV_FILE=/home/YOUR_USERNAME/godyar_private/.env');
$_SERVER['ENV_FILE'] = '/home/YOUR_USERNAME/godyar_private/.env';
```
