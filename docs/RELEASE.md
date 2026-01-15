# Release Candidate v1.0.x-security-pg

## 1) ما الذي يتضمنه هذا الإصدار؟
- تحسين توافق PostgreSQL في صفحات إدارة الأخبار (admin/news) عبر استخدام `information_schema` بدل أوامر MySQL-only مثل `information_schema queries`.
- جعل فحص وجود الجداول/الأعمدة في `includes/bootstrap.php` متوافقًا مع MySQL وPostgreSQL.
- إضافة CSRF token إلى نموذج التفعيل/الإيقاف في صفحة Plugins (نموذج الموبايل).
- إصلاح workflow الخاص بـ PHP في GitHub Actions + pinning بالـ SHA.

## 2) خطوات الترقية (Upgrade)
1. خذ نسخة احتياطية من قاعدة البيانات:
   - MySQL: `mysqldump ...`
   - PostgreSQL: `pg_dump ...`
2. انشر الكود الجديد إلى staging.
3. شغّل migrations الخاصة بقاعدة البيانات (حسب driver).
4. نفّذ Smoke Tests (login, browse news, search, admin create/edit).
5. راقب logs لمدة 15-30 دقيقة بعد النشر.

## 3) Checklist نشر سريع
- [ ] Backup DB + حفظ النسخة خارج السيرفر.
- [ ] نشر على staging.
- [ ] تشغيل installer/migrations على نفس DB_DRIVER.
- [ ] التحقق من صفحات: الأخبار، البحث، لوحة الإدارة، إضافة/تعديل خبر.
- [ ] فحص CSRF (أي POST حساس يرفض بدون token).
- [ ] تفعيل headers الأساسية (CSP/HSTS).
- [ ] نشر على production.
- [ ] مراقبة الأخطاء ووقت الاستجابة.

## 4) خطة Rollback
1. إيقاف النشر (إن كان تدريجي).
2. الرجوع إلى tag/commit السابق.
3. استرجاع قاعدة البيانات من backup إن تم تطبيق migrations غير متوافقة.
4. إن كانت المشكلة متعلقة بـ PG:
   - أعد `DB_DRIVER=mysql` مؤقتًا (مع التأكد أن البيانات متسقة).
5. إعادة تشغيل الخدمات (php-fpm/nginx) ومسح caches إن لزم.

## 5) ملاحظات تشغيلية
- أي صفحات/سكريبتات كانت تعتمد على `information_schema queries` تم تغطيتها في مسار إدارة الأخبار والـ helpers.
- ما زالت هناك أماكن أخرى في المشروع قد تحتوي على استعلامات MySQL-only؛ يفضّل فحصها تدريجيًا حسب أولويات التشغيل.

