Godyar — Hotfix: Footer icon dots (list markers)

المشكلة:
- ظهور "نقطة" سوداء بجانب/حول بعض أيقونات الوصلات في الفوتر (خصوصاً على الجوال).
- السبب الشائع: القائمة (<ul>/<ol>) لها list-style أو pseudo-element (:before) يضيف marker/•.

الحل:
1) ارفع الملف:
   public_html/assets/css/godyar_hotfix_footer_icons.css

2) أضف استدعاء CSS في الفوتر أو في الهيدر العام بعد ملفات الـ CSS الأساسية.

   الخيار (A) داخل frontend/templates/footer.php قبل إغلاق </body>:
   <link rel="stylesheet" href="<?= base_url('assets/css/godyar_hotfix_footer_icons.css') ?>?v=20260114">

   الخيار (B) داخل frontend/templates/header.php أو ملف الـ layout الرئيسي بعد CSS الأساسي:
   <link rel="stylesheet" href="<?= base_url('assets/css/godyar_hotfix_footer_icons.css') ?>?v=20260114">

3) امسح الكاش (إذا كان هناك LiteSpeed Cache أو Cloudflare) ثم حدّث الصفحة.

ملاحظة:
- الملف Scoped على عناصر الفوتر (.gdy-footer / footer / .footer) لتقليل أثره على باقي الموقع.
