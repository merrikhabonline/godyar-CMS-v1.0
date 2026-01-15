# Smoke Test

نفّذ هذا الاختبار بعد كل رفع نسخة واحدة (Release Candidate):

1) الصفحة الرئيسية:
- `/`
- `/ar`
- `/en`
- `/fr`

2) الأخبار:
- `/news/id/1` (أو ID موجود)
- `/archive`
- `/trending`

3) Saved:
- `/saved`

4) Admin:
- `/admin/login`
- `/admin/media`
- `/admin/ads`
- `/admin/sliders`
- `/admin/team`

5) PWA:
- `/manifest.webmanifest`
- `/{lang}/manifest.webmanifest`
