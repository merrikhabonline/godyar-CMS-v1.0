Godyar CMS - Hotfix ZIP (R3) - 2026-01-14

سبب R3:
- Media Picker: SELECT file_size (عمود غير موجود) => إضافة media.file_size
- Settings: Warnings بسبب $data غير معرّف في admin/settings/_settings_guard.php

المحتويات:
- apply_hotfix_r3.php : يطبّق تصحيحات كود (مع backup .bak_...) لملفات:
  - admin/news/_news_helpers.php (منع Cannot redeclare)
  - admin/settings/_settings_guard.php (منع Undefined $data / foreach null)
- upgrade_20260114_r3.php : يضيف أعمدة media المطلوبة + backfill + توحيد utf8mb4_unicode_ci
- godyar_upgrade_20260114_r3.sql : SQL اختياري
- includes/auth.php : ملف توافق (إن كان مشروعك يحتاجه)

طريقة التنفيذ:
1) خذ Backup.
2) ارفع الملفات إلى public_html مع الحفاظ على المسارات.
3) افتح:
   https://godyar.org/apply_hotfix_r3.php
   ثم احذف الملف.
4) افتح:
   https://godyar.org/upgrade_20260114_r3.php
   ثم احذف الملف.
5) اختبر:
   - /admin/media/picker.php
   - /admin/media/index.php
   - /admin/settings (أي صفحة)
   - /admin/news/create.php
