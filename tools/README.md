# أدوات صيانة (Tools)

## scan_bom
يفحص ملفات PHP داخل المشروع بحثًا عن:
- UTF-8 BOM
- مسافات/أسطر قبل `<?php` تسبب `headers already sent`
- مسافات بعد `?>`

### الاستخدام
```bash
php tools/scan_bom.php
php tools/scan_bom.php --fix
```
