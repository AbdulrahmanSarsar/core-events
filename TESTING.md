# دليل الاختبار — Phase 1 (1.1 → 1.5)

> هذا الملف هو الخطة العملية لاختبار كل اللي اشتغلنا عليه قبل البيع.
> آخر تحديث: 28 أبريل 2026

---

## 📋 ملخص اللي خلصناه

| Phase | الميزة | حجم التغيير | الحاجة لاختبار |
|-------|-------|-------------|---------------|
| **1.1** | QR محلي (phpqrcode bundled) | 6 ملفات + vendor | اختبار وظيفي + visual |
| **1.2** | License flow احترافي (3 modes + heartbeat) | 3 ملفات معاد كتابتها | اختبار UI + cron |
| **1.3** | Anti-spam (honeypot + rate limit + disposable) | 6 ملفات | اختبار سيء + إيجابي |
| **1.4** | Email Queue (DB-backed + worker) | 9 ملفات + DB table | اختبار async + cron |
| **1.5** | Event Schema (JSON-LD) | 2 ملف | اختبار Google validator |

**إحصائيات:**
- ملفات جديدة: 6 (Vendor/phpqrcode + 4 Helpers + this TESTING.md)
- ملفات معدلة: ~14
- DB tables جديدة: 1 (`wp_cep_email_queue`)
- Cron hooks جديدة: 2 (`cep_license_heartbeat`, `cep_email_queue_worker`)
- Dependencies خارجية: **0**

---

## 🛠️ Pre-flight Checklist (قبل أي اختبار)

### 1. البيئة المطلوبة
- [ ] WordPress 5.8+ مثبت (Local by Flywheel أو staging)
- [ ] PHP 7.4+
- [ ] PHP Extensions: **GD** (لتوليد QR), **JSON** (افتراضي)
- [ ] WooCommerce اختياري (لاختبار Paid Tickets فقط)
- [ ] خدمة إيميل: Mailtrap.io أو SMTP حقيقي أو حتى الـ default على Local

### 2. تثبيت الإضافة الجديدة
```bash
# نسخة احتياطية أولاً
wp db export backup-before-test.sql

# Deactivate القديمة (لو منصبة)
wp plugin deactivate core-events-pro

# انسخ الفولدر الجديد
cp -r core-events-pro/ /path/to/wordpress/wp-content/plugins/

# فعّل
wp plugin activate core-events-pro
```

### 3. التحقق من نجاح التثبيت
- [ ] Setup Wizard يفتح تلقائياً (إذا أول مرة) — اضغط Skip للاختبار
- [ ] في sidebar: قائمة "Events Pro" ظاهرة
- [ ] Sub-menus موجودة: Dashboard / All Main Events / Add Event / Sub Events / Attendees / Settings & Help / License

### 4. التحقق من DB Tables
```sql
SHOW TABLES LIKE 'wp_cep_%';
-- لازم تطلع:
-- wp_cep_attendees
-- wp_cep_email_queue   ← جديدة!
```

### 5. التحقق من Cron Hooks
ثبّت plugin **WP Crontrol** ثم افتح Tools → Cron Events وتأكد:
- [ ] `cep_hourly_check` (hourly)
- [ ] `cep_license_heartbeat` (daily) — جديد
- [ ] `cep_email_queue_worker` (every minute) — جديد

> **مهم:** على Local إذا الـ cron مش بيشتغل تلقائياً، شغّل:
> ```bash
> wp cron event run --due-now
> ```

---

## 🧪 Smoke Test السريع (10 دقايق)

اختبار أساسي للتأكد إنو كل شي شغال على المستوى الـ happy path.

### الخطوة 1 — أنشئ حدث (1 دقيقة)
1. Events Pro → Add Event
2. Title: "Test Event 2026"
3. Start Date: بعد ساعتين من الآن
4. End Date: بعد 4 ساعات من الآن
5. Capacity: **5**
6. ✅ Enable Registration / Tickets
7. Registration Type: Free RSVP & Waitlist
8. Location: "Main Hall, Damascus"
9. اضغط Publish

### الخطوة 2 — Phase 1.5 (Schema) (2 دقيقة)
1. اضغط View Event على frontend
2. View Source (Ctrl+U)
3. ابحث عن `application/ld+json`
4. لازم تشوف Event schema بـ:
   - `"@type": "Event"`
   - `"startDate"`, `"endDate"`, `"location"`, `"offers"`
5. **التحقق الرسمي:** انسخ URL الصفحة و افتح:
   - https://search.google.com/test/rich-results
   - اضغط Test URL
   - ✅ نتيجة: "Page is eligible for rich results"
   - ✅ Detected: 1 Event

### الخطوة 3 — Phase 1.3 + 1.1 + 1.4 (RSVP) (3 دقايق)
1. على نفس صفحة الحدث، املأ النموذج:
   - Name: "Test User"
   - Email: استخدم Mailtrap أو inbox حقيقي
   - Phone: 12345
2. اضغط Confirm Registration
3. ✅ رسالة نجاح "Registration successful! Check your email..."
4. **افحص الـ DB:**
   ```sql
   SELECT id, recipient, status, attempts, created_at
   FROM wp_cep_email_queue
   ORDER BY id DESC LIMIT 1;
   ```
   ✅ row جديد بـ status='pending'
5. استنى دقيقة (أو شغّل `wp cron event run --due-now`)
6. أعد الـ query — ✅ status='sent', sent_at مملوء
7. افحص الإيميل في Mailtrap:
   - ✅ Subject فيه اسم الحدث
   - ✅ صورة QR ظاهرة inline (مش link مكسور)
   - ✅ الصورة تبدأ بـ `data:image/png;base64,...` (View source للإيميل)

### الخطوة 4 — Phase 1.1 (QR Scan) (1 دقيقة)
1. كمشرف logged-in بـ admin، انسخ الـ QR من الإيميل
2. مرّره في QR scanner أو افتح الـ link اللي تحت الـ QR
3. ✅ صفحة "SUCCESS - Check-in confirmed!" تظهر
4. اضغط refresh أو امسح QR ثاني مرة
5. ✅ "ALREADY CHECKED IN" تظهر (anti-replay)

### الخطوة 5 — Phase 1.4 (Endpoint الجديد) (30 ثانية)
1. خد الـ qr_token من الإيميل (الجزء الأخير من الـ scan URL)
2. افتح: `https://yoursite.com/?cep_qr_image=<token>`
3. ✅ صورة PNG QR تظهر مباشرة في المتصفح

### الخطوة 6 — Phase 1.2 (License Page) (1 دقيقة)
1. Events Pro → License
2. ✅ Status: "Inactive"
3. لاحظ "Verification Mode: Format Only" بـ panel ثاني
4. ضع UUID صحيح: `d0a3c1a2-1234-5678-9abc-def012345678`
5. اضغط Activate License
6. ✅ Status صار "Active"
7. ✅ "Activated domain" ظاهر
8. اضغط "Re-check Now"
9. ✅ "Last verified" يحدّث

### الخطوة 7 — Phase 1.4 (Cron Worker) (1 دقيقة)
1. WP Crontrol → Cron Events
2. ابحث عن `cep_email_queue_worker`
3. اضغط Run Now
4. ✅ ما يطلع error
5. ارجع للـ DB:
   ```sql
   SELECT status, COUNT(*) FROM wp_cep_email_queue GROUP BY status;
   ```
   ✅ كل القديم بـ status='sent'

---

## 🔬 Deep Test (45 دقيقة)

اختبارات أعمق للحالات اللي ممكن تكسر الإضافة.

### 🛡️ Phase 1.3 — Anti-spam (10 دقايق)

#### A. Honeypot (يجب الفشل = نجاح)
1. افتح صفحة الحدث
2. F12 → Console، نفّذ:
   ```javascript
   document.querySelector('input[name="cep_website_url"]').value = 'http://bot.example';
   ```
3. املأ النموذج عادي + اضغط Submit
4. ✅ رسالة "Submission rejected." تظهر
5. ✅ ما في row جديد في `wp_cep_attendees`

#### B. Submission too fast
1. حمّل الصفحة
2. مباشرة (قبل 3 ثواني):
   ```javascript
   document.getElementById('cep-rsvp-form').submit();
   ```
   (أو نفّذ jQuery submit)
3. ✅ "Submission rejected."

#### C. Rate Limiting
1. حضّر 6 emails مختلفة
2. سجّل سجلاً ورا الآخر بسرعة (10 ثواني بين كل واحد)
3. ✅ بعد المحاولة الـ6 خلال 10 دقايق:
   - رسالة "Too many attempts from your network."
4. خلي الـ counter يـ reset (10 دقايق) أو امسحه:
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE '_transient_cep_rl_%';
   ```

#### D. Disposable Email Block
1. Settings & Help → Anti-spam Protection
2. ✅ فعّل "Block registrations from temporary..."
3. حاول تسجل بـ `test@mailinator.com`
4. ✅ "Please use a permanent email address."

### 🎟️ Phase 1.1 — QR Edge Cases (5 دقايق)

#### A. GD Extension مفقود (محاكاة)
هاد صعب نختبره بسهولة، لكن في الكود:
- لو `imagecreate()` ما موجودة → `QrGenerator::get_data_uri()` ترجع `''`
- الإيميل ينطبع بدون `<img>` بس بقي `Or keep this link: ...`
- ✅ الإيميل ما ينكسر

#### B. الإيميل بدون remote images (Outlook default)
1. افتح الإيميل في Outlook (أو Gmail بـ "Show images" مغلقة)
2. ✅ الـ QR ظاهر (لأنو data URI، مش remote)

### 📧 Phase 1.4 — Email Queue Failure (10 دقايق)

#### A. SMTP فاشل (محاكاة)
1. عطّل SMTP عن قصد (غلط في creds)
2. سجّل registration جديد
3. استنى دقيقة → افحص:
   ```sql
   SELECT id, status, attempts, last_error, scheduled_for
   FROM wp_cep_email_queue WHERE recipient = 'test@example.com';
   ```
4. ✅ status='pending', attempts=1, scheduled_for بعد 5 دقايق
5. صلّح SMTP
6. شغّل cron يدوياً
7. ✅ status='sent'

#### B. Queue Lock Recovery
1. ضيف lock يدوياً:
   ```sql
   -- في wp-cli أو phpmyadmin:
   ```
   ```php
   set_transient('cep_email_queue_lock', 1, 3600);
   ```
2. سجّل registration
3. شغّل cron — ✅ ما حصل أي شي (lock نشط)
4. امسح الـ lock:
   ```php
   delete_transient('cep_email_queue_lock');
   ```
5. شغّل cron — ✅ تم الـ send

#### C. Bulk Reminder Test
1. أنشئ حدث بعد 24 ساعة من الآن (تحديداً)
2. سجّل 10 attendees
3. شغّل `cep_hourly_check` cron
4. ✅ 10 rows جديدة في email_queue
5. ✅ الـ HTTP request للكرون رجع بسرعة (مش 10 ثواني)
6. الـ worker بيعالجهم بـ batches من 20 — كلهم في tick واحد

### 🔐 Phase 1.2 — License Edge Cases (5 دقايق)

#### A. UUID غلط
1. License page → ادخل: `wrong-format`
2. ✅ "Invalid format" error

#### B. Format Only Mode فقط (default)
1. ادخل أي UUID صحيح: `aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee`
2. ✅ ينقبل (هاد مقصود — هاد mode للـ initial launch)

#### C. Server Mode (إذا عندك license server)
في wp-config.php:
```php
define('CEP_LICENSE_SERVER', 'https://your-server.com/api/verify');
```
ادخل code → الـ plugin بيرسل POST لـ server
✅ بحسب الرد بنشط أو نرفض

#### D. Heartbeat
1. WP Crontrol → ابحث عن `cep_license_heartbeat`
2. اضغط Run Now
3. ✅ ما حصل error
4. License page → "Last verified" يحدّث

#### E. Grace Period (محاكاة عطل في الـ server)
1. غيّر الـ CEP_LICENSE_SERVER لـ URL مش شغال
2. شغّل heartbeat
3. ✅ License يضل Active لـ 30 يوم
4. (لاحقاً، بعد 30 يوم، يصير inactive)

### 🌐 Phase 1.5 — Schema Edge Cases (5 دقايق)

#### A. Cancelled Event
1. Edit حدث، فعّل "Cancel this event"
2. View source للـ frontend page
3. ✅ `"eventStatus": "https://schema.org/EventCancelled"`

#### B. Online Event (URL في location field)
1. Edit حدث، Location: `https://zoom.us/j/123456`
2. View source
3. ✅ `"@type": "VirtualLocation"`
4. ✅ `"eventAttendanceMode": "https://schema.org/OnlineEventAttendanceMode"`

#### C. Free RSVP — SoldOut
1. حدث capacity=2
2. سجّل 2 confirmed attendees
3. View source
4. ✅ `"availability": "https://schema.org/SoldOut"`

#### D. Paid Tickets (إذا WooCommerce نصبت)
1. حدث بـ Paid Tickets type
2. أنشئ tier "VIP" بـ $50
3. View source
4. ✅ Offers array فيه VIP بـ price=50, currency=USD

#### E. Google Validator
انسخ كل event URL وافحصه:
- https://search.google.com/test/rich-results
- https://validator.schema.org/

ولا ✅ "Valid" + "Eligible for rich result"

### 🗑️ Uninstall Test (5 دقايق)

⚠️ **اعمل backup أولاً!**

1. Plugins → Deactivate Core Events Pro
2. ✅ افحص Cron Events — `cep_*` كلها مختفية
3. Plugins → Delete (uninstall)
4. ✅ افحص:
   ```sql
   SHOW TABLES LIKE 'wp_cep_%';
   -- لازم ما يطلع شي
   
   SELECT * FROM wp_options WHERE option_name LIKE 'cep_%';
   -- لازم ما يطلع شي
   
   SELECT * FROM wp_options WHERE option_name LIKE 'core_events_pro_%';
   -- لازم ما يطلع شي
   ```

5. أعد التثبيت — ✅ الإضافة بترجع clean بدون residue

---

## 🚨 Troubleshooting الشائع

### "Cron events مش بتشتغل تلقائياً"
- WP-Cron بحاجة زائر ليشتغل. للموقع الـ low-traffic:
  - عطّل WP-Cron: `define('DISABLE_WP_CRON', true);` في wp-config
  - أضف real cron job في cPanel/Hostinger يستدعي `wp-cron.php` كل 5 دقايق:
    ```
    */5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
    ```

### "QR ما بيظهر في الإيميل"
- تأكد GD extension متاح: `<?php phpinfo(); ?>` → ابحث عن "gd"
- لو فعلاً مفقود: `apt install php-gd` على VPS، أو فعّله من الـ hosting panel
- لو الـ QR data URI طويل جداً وبيكسر: حدّد size 4 بدل 6 في `QrGenerator::DEFAULT_SIZE`

### "Email Queue stuck"
```sql
-- شوف الـ pending الـ مهترئ
SELECT id, recipient, attempts, scheduled_for, last_error
FROM wp_cep_email_queue
WHERE status = 'pending' AND attempts > 0
ORDER BY scheduled_for;

-- إعادة محاولة يدوية
UPDATE wp_cep_email_queue
SET attempts = 0, scheduled_for = NOW(), status = 'pending'
WHERE id = X;
```

### "License مش بيتفعّل"
- Format غلط: لازم UUID format بالضبط (8-4-4-4-12)
- Server Mode: تأكد `CEP_LICENSE_SERVER` defined و reachable
- Envato Mode: تأكد `CEP_ENVATO_TOKEN` defined

### "Schema مش بيظهر بـ Google"
- Permalink الصفحة ممكن متاح cached
- Flush rewrite rules: Settings → Permalinks → Save
- Google Search Console بياخد أيام لاسترجاع التحديث
- استخدم Live URL Test في Search Console للتحقق الفوري

### "Honeypot ما بيشتغل"
- DevTools → Elements → ابحث عن `cep_website_url`
- لازم يكون موجود لكن `position:absolute; left:-9999px;`
- لو مش موجود: تأكد `AntiSpam::render_fields()` متاح في الـ template

---

## ✅ Checklist للموافقة على Phase 1

قبل ما نمشي لـ Phase 1.6 (PDF Tickets)، لازم كل واحدة من هاي تعطي ✅:

### Functional Tests
- [ ] Smoke Test الكامل خلص بدون errors
- [ ] الـ 5 features الأساسية شغالة (QR, License, Anti-spam, Queue, Schema)
- [ ] Google Rich Results Test: Valid Event detected

### Stability Tests
- [ ] أنشأت 10 events، سجّلت 30 attendees → ما في errors في PHP error log
- [ ] الـ Email Queue بيعالج 10+ rows في tick واحد
- [ ] الـ Cron الثلاثة كلها شغالة (hourly + daily + every-minute)

### Cleanup Tests
- [ ] Deactivation بنظف الـ cron hooks
- [ ] Uninstall بنظف الـ tables + options بالكامل
- [ ] Re-install ما بيكسر شي (clean state)

### Performance Tests
- [ ] صفحة الحدث للزائر تحمّل بأقل من 2 ثانية (مع schema)
- [ ] AJAX submission للـ RSVP يرد بأقل من 500ms
- [ ] الـ admin dashboard يفتح بأقل من 3 ثواني

---

## 📝 Bug Report Template

لو لقيت bug، سجّله بهذا الفورمت:

```
**Phase:** (1.1 / 1.2 / 1.3 / 1.4 / 1.5)
**الميزة:** (مثلاً: Email Queue Worker)
**الخطوات:**
1. ...
2. ...
**النتيجة المتوقعة:** ...
**النتيجة الفعلية:** ...
**PHP Error log:** (لو في)
**Screenshot:** (لو visual)
**Environment:** WP version, PHP version, plugins active
```

---

*هذا الملف مرجع كامل. خلّيه بمتناولك أثناء الاختبار وحدّثه بأي ملاحظات.*
