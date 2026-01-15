# رفع المشروع إلى GitHub (نسخة نظيفة)

## قبل الرفع
هذا المشروع مُجهز ليكون "Clean" على GitHub:
- لا ترفع `.env` أو أي أسرار.
- لا ترفع `.user.ini` النهائية (استخدم `.example` فقط).
- لا ترفع logs / cache / sessions / uploads.

تحقق من `.gitignore` قبل الرفع.

## طريقة رفع بدون Git (من المتصفح)
1. أنشئ Repository جديد على GitHub
2. **لا** تفعل: Add README / Add .gitignore / Add license (لأنها موجودة بالفعل)
3. بعد إنشاء الريبو، استخدم "Upload files" وارفع محتويات المجلد بالكامل

## طريقة رفع باستخدام Git
```bash
git init
git add .
git commit -m "Initial release v1.09"
git branch -M main
git remote add origin <REPO_URL>
git push -u origin main
```

## إصدار Release
- من GitHub → Releases → Create new release
- Tag: `v1.09`
- Title: `Godyar CMS v1.09 (Stable)`
