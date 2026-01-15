# Hardening Guide (CSP / Headers / DB)

> الهدف: تقليل سطح الهجوم بدون كسر الواجهة أو الحاجة لإعادة هندسة كبيرة.

## 1) HTTP Security Headers (موصى به)

ضع هذه الإعدادات على مستوى الـ Web Server (Nginx/Apache) أو عبر Middleware إن وُجد.

### 1.1 CSP (Content-Security-Policy)
ابدأ بوضع CSP “متسامح” ثم شدّد تدريجيًا حتى لا تكسر الواجهة.

مثال (بداية عملية):

```
Content-Security-Policy: default-src 'self';
  base-uri 'self';
  object-src 'none';
  frame-ancestors 'self';
  img-src 'self' data:;
  font-src 'self' data:;
  style-src 'self' 'unsafe-inline';
  script-src 'self' 'nonce-{RANDOM_NONCE}';
  connect-src 'self';
  form-action 'self';
```

- استخدم Nonce للـ scripts التي تحتاج inline (ثم حاول تقليلها تدريجيًا).
- لو عندك مصادر خارجية (CDN/Analytics) أضفها بشكل صريح بدل `*`.

### 1.2 HSTS
```
Strict-Transport-Security: max-age=15552000; includeSubDomains; preload
```
> لا تفعل HSTS إلا إذا HTTPS ثابت ومطبق في كل الدومينات الفرعية.

### 1.3 X-Content-Type-Options / Referrer-Policy / Permissions-Policy
```
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

### 1.4 Clickjacking / Framing
إذا لم تستخدم IFrame:
```
X-Frame-Options: SAMEORIGIN
```
(أو اعتمد `frame-ancestors` داخل CSP)

## 2) Cookies (جلسة وتوثيق)

- `HttpOnly` = On
- `Secure` = On (على HTTPS)
- `SameSite=Lax` كافٍ غالبًا (أو Strict إن أمكن)

## 3) قاعدة البيانات (Least Privilege)

### 3.1 حساب DB للإنتاج
- لا تستخدم root.
- امنح أقل صلاحيات ممكنة (SELECT/INSERT/UPDATE/DELETE على schema التطبيق فقط).
- امنع `CREATE USER`, `GRANT`, `SUPER` … الخ.

### 3.2 أمثلة إعدادات (ENV)
ضعها في ملف إعدادات غير مُضمن بالـ repo (مثل `.env`):

MySQL/MariaDB:
```
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=godyar
DB_USER=godyar_app
DB_PASS=********
```

PostgreSQL:
```
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=godyar
DB_USER=godyar_app
DB_PASS=********
DB_SCHEMA=public
```

## 4) صلاحيات الملفات (Filesystem)
- اجعل مجلدات التطبيق **read-only** قدر الإمكان.
- اجعل فقط `storage/` و `cache/` قابلة للكتابة.
- امنع تنفيذ PHP داخل مجلدات الرفع (uploads) إن وجدت.

## 5) Logging & Monitoring
- سجل أخطاء DB وValidation وفشل الـ CSRF.
- أضف تنبيهات على: 5xx spikes, auth failures, unexpected outbound requests.

