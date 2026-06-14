# CLAUDE.md — Core Events Pro

> **ملف السياق الرئيسي للمشروع.** Claude Code بيقراه تلقائياً ببداية كل جلسة.
> آخر تحديث: 14 يونيو 2026

---

## 👤 عن المستخدم

- **الاسم:** عبدالرحمن سرسر
- **اللغة الرئيسية:** عربي (لهجة سورية) — **جاوب بالعربي دائماً إلا لو طلب غير هيك**
- **الدور:** Solo Founder + Full-Stack WordPress Developer
- **الهدف من المشروع:** بيع الإضافة على CodeCanyon (Envato Market)

### أسلوب العمل المفضّل
- **مباشر وعملي** — بدون تكرار الأسئلة اللي تأكدت منها سابقاً
- **القرارات المعمارية إله** — Claude يقترح ويتحدى، لكن الكلمة الأخيرة لعبدالرحمن
- يفضّل **comprehensive solutions** على incremental patches
- يرفض الـ over-engineering و التعقيد بدون مبرر
- يحب يشوف نتيجة فورية وقابلة للاختبار

---

## 🎯 نظرة عامة على المشروع

**EventCore – Advanced Events & Booking Manager** — إضافة WordPress احترافية لإدارة الأحداث، مستهدفة سوق CodeCanyon.

> **مهم — الاسم vs الـ slug الداخلي:**
> - **الاسم التجاري المعروض للمشترين:** `EventCore – Advanced Events & Booking Manager` (في الترويسة + readme + النصوص المرئية).
> - **الـ slug / text-domain / namespace الداخلي:** يبقى `core-events-pro` / `CoreEventsPro` / prefix `cep_` بدون تغيير (لأن كل الكود وملف `.pot` مبنيّان عليه، و CodeCanyon لا يشترط التطابق). **لا تعيد تسمية الـ slug الداخلي.**

### Stack & Compatibility
- **PHP:** 7.4+
- **WordPress:** 5.8+ (Tested up to 6.8)
- **WooCommerce:** اختياري (للتذاكر المدفوعة)
- **License:** GPLv2+

### الإصدار الحالي
- **Version:** 1.0.0
- **Branch:** main
- **GitHub:** AbdulrahmanSarsar/[repo]

---

## 🏗️ المعمارية

### Pattern
- **Singleton** للكلاس الرئيسي `\CoreEventsPro\Plugin`
- **Namespaces** (PSR-4 like manual loading)
- **Service classes** لكل feature

### هيكل الملفات

```
core-events-pro/
├── core-events-pro.php          ← Main plugin file (Singleton + bootstrap)
├── readme.txt                    ← WordPress plugin readme
├── uninstall.php                 ← Cleanup on uninstall
│
├── includes/
│   ├── PostTypes/
│   │   ├── MainEvent.php        ← main_event CPT
│   │   ├── SubEvent.php         ← sub_event CPT
│   │   └── Taxonomies.php       ← event_cat, event_type, event_tag
│   │
│   ├── Admin/
│   │   ├── MetaBoxes.php        ← Event config + tickets + media
│   │   ├── Settings.php         ← Global settings page
│   │   ├── Dashboard.php        ← Stats + CSV import
│   │   ├── EventTools.php       ← Clone + bulk actions + CSV import
│   │   ├── AttendeesPage.php    ← Attendees admin page
│   │   ├── AttendeesTable.php   ← WP_List_Table for attendees
│   │   └── SetupWizard.php      ← 3-step onboarding
│   │
│   ├── Modules/
│   │   ├── Attendees.php        ← RSVP + Waitlist + QR scan
│   │   ├── WooCommerce.php      ← Paid tickets integration
│   │   └── Licensing/
│   │       └── Licensing.php    ← Envato license activation
│   │
│   ├── Api/
│   │   └── EventController.php  ← REST API endpoints
│   │
│   ├── Shortcodes/
│   │   ├── Calendar.php         ← [event_calendar]
│   │   └── EventViews.php       ← All other shortcodes
│   │
│   └── Helpers/
│       ├── Database.php         ← Custom table creation
│       ├── Cron.php             ← Hourly job + reminders
│       ├── TemplateLoader.php   ← Template override
│       └── Utils.php            ← Static helpers
│
├── templates/
│   ├── single-event.php
│   ├── single-sub-event.php
│   └── loop-event.php
│
├── assets/
│   ├── css/ (admin.css, frontend.css)
│   └── js/  (admin.js, calendar.js)
│
└── languages/                    ← Translation files
```

### Custom Post Types
| CPT | Purpose | Hierarchical |
|-----|---------|--------------|
| `main_event` | Main events | No |
| `sub_event` | Sessions / workshops | No (linked via `_cep_parent_id` meta) |

### Taxonomies
| Slug | Hierarchical | Purpose |
|------|--------------|---------|
| `event_cat` | ✅ | Broad themes |
| `event_type` | ✅ | Format / audience |
| `event_tag` | ❌ | Free-form keywords |

### Custom DB Tables
| Table | Purpose |
|-------|---------|
| `wp_cep_attendees` | Registrations + waitlist + check-in + QR tokens |

**Schema:**
```sql
id, event_id, name, email, phone, status (confirmed/waitlist),
qr_token, check_in (0/1), created_at
```

---

## 💾 Post Meta Keys

### Main Event / Sub Event
| Key | Type | Purpose |
|-----|------|---------|
| `_cep_start` | datetime | Start date/time |
| `_cep_end` | datetime | End date/time |
| `_cep_status` | string | upcoming/ongoing/finished/cancelled |
| `_cep_color` | hex | Calendar color |
| `_cep_overview` | text | Short summary |
| `_cep_capacity` | int | Max confirmed seats |
| `_cep_location` | string | Venue / address |
| `_cep_video_url` | url | Embedded video |
| `_cep_gallery_ids` | csv | Attachment IDs |
| `_cep_custom_banner` | url | Hero image |
| `_cep_is_recurring` | 0/1 | Recurrence flag |
| `_cep_recurrence_type` | string | daily/weekly/monthly/yearly |
| `_cep_enable_rsvp` | 0/1 | Registration toggle |
| `_cep_rsvp_type` | string | free / paid |
| `_cep_tickets` | array | Paid ticket tiers |
| `_cep_reminder_sent` | 0/1 | Cron flag |
| `_cep_parent_id` | int | (sub_event) Parent main event |

### WooCommerce Product
| Key | Purpose |
|-----|---------|
| `_cep_event_id` | Links product to event |
| `_cep_tickets_generated` | Order processing flag |

---

## ⚙️ Options Keys

| Option | Default | Purpose |
|--------|---------|---------|
| `cep_enable_time` | 1 | Show hours/minutes |
| `cep_enable_location` | 1 | Show maps link |
| `cep_enable_countdown` | 1 | Live countdown |
| `cep_enable_ics` | 1 | Add to calendar button |
| `cep_hide_past_events` | 0 | Auto-hide finished |
| `cep_location_type` | free_text | free_text / predefined |
| `cep_predefined_locations` | "" | Newline-separated venues |
| `cep_email_confirm_sub` | (default) | Confirmation email subject |
| `cep_email_confirm_body` | (default) | Confirmation email body |
| `cep_email_remind_sub` | (default) | Reminder email subject |
| `cep_email_remind_body` | (default) | Reminder email body |
| `cep_label_*` | (defaults) | Section title labels |
| `cep_text_*` | (defaults) | UI labels |
| `core_events_pro_purchase_code` | "" | Activated license |
| `core_events_pro_license_status` | inactive | active/inactive |
| `core_events_pro_activated_domain` | "" | Bound domain |

---

## ✅ الميزات الحالية (Phase 0 — Done)

- [x] Custom Post Types (main_event, sub_event)
- [x] 3 Taxonomies (cat, type, tag)
- [x] Custom DB table for attendees
- [x] Free RSVP + Smart Waitlist + Auto-promotion
- [x] Paid Tickets (WooCommerce integration)
- [x] QR Code generation (local via phpqrcode — no external dependency)
- [x] QR Scan check-in
- [x] Manual check-in
- [x] CSV Import (events) + CSV Export (attendees)
- [x] Recurring events (daily/weekly/monthly/yearly)
- [x] Venue conflict detection
- [x] Setup Wizard (3 steps)
- [x] Hourly cron (status update + 24h reminders)
- [x] REST API (events/v1)
- [x] 7 Shortcodes (calendar, list, filter, grouped, next, single, subs)
- [x] Email templates with dynamic tags
- [x] License system (3 verification modes: server / envato_direct / format_only + weekly heartbeat + 30-day grace)
- [x] WP_List_Table for attendees
- [x] Bulk actions (clone, mark finished/cancelled)
- [x] i18n ready (text-domain: core-events-pro)
- [x] Setup Wizard with auto-redirect after activation

---

## 🚧 خطة التطوير (3 مراحل)

### 🔴 المرحلة 1 — Pre-Launch Critical (أسبوعين)
**الهدف:** تجاوز Envato review + سد الثغرات الحرجة

| # | المهمة | الأولوية | الحالة |
|---|--------|---------|--------|
| 1.1 | **QR محلي** بدل QuickChart.io (phpqrcode bundled) | 🔥 Critical | ✅ |
| 1.2 | **Envato License flow صحيح** (3 modes + heartbeat + grace period) | 🔥 Critical | ✅ |
| 1.3 | **Anti-spam على RSVP** (Honeypot + age check + per-IP rate limit + disposable email) | 🔥 Critical | ✅ |
| 1.4 | **Email queue** (custom DB-backed worker, every-minute cron, retry + backoff) | 🔴 High | ✅ |
| 1.5 | **Event Schema (JSON-LD)** للـ SEO (Event + Offers + VirtualLocation/Place) | 🔴 High | ✅ |
| 1.6 | **PDF Ticket Attachment** بدل صورة من API | 🔴 High | ⏳ |
| 1.7 | **Custom Registration Fields** (configurable form) | 🔴 High | ⏳ |
| 1.8 | **Discount Codes / Coupons** | 🔴 High | ⏳ |
| 1.9 | **Multiple Reminders** (configurable: 7d / 1d / 1h) | 🟠 Medium | ⏳ |
| 1.10 | **Memory-safe queries** (batching للـ Cron + Calendar) | 🟠 Medium | ⏳ |
| 1.11 | **Strict capability checks** (دور `cep_scanner` مخصص) | 🟠 Medium | ⏳ |
| 1.12 | **`.pot` file generation** + Arabic translation | 🟠 Medium | ⏳ |

### 🟡 المرحلة 2 — Competitive Parity (شهر)
| # | المهمة |
|---|--------|
| 2.1 | Gutenberg Blocks (event list, calendar, single event) |
| 2.2 | Frontend User Dashboard (تذاكري + إلغاء) |
| 2.3 | Calendar Views متعددة (Week / Day / List / Agenda) |
| 2.4 | Speakers Post Type + display |
| 2.5 | Demo Content Importer (one-click) |
| 2.6 | Real Analytics Dashboard (revenue, top events, conversions) |
| 2.7 | HTML Email Templates (3-5 designs) |
| 2.8 | Print-friendly attendee badges |

### 🟢 المرحلة 3 — Differentiation (شهر)
| # | المهمة |
|---|--------|
| 3.1 | Stripe Direct (بدون WooCommerce) |
| 3.2 | Zoom / Google Meet integration |
| 3.3 | Frontend Event Submission |
| 3.4 | Group / Team Tickets |
| 3.5 | Elementor Widgets |
| 3.6 | Mobile Check-in PWA |
| 3.7 | Affiliate / Referral system |
| 3.8 | Multi-organizer roles |

---

## 💰 استراتيجية التسعير

| المرحلة | السعر المتوقع | السوق المستهدف |
|---------|---------------|----------------|
| **بعد Phase 1** | $29 - $39 (Regular) | Indie users |
| **بعد Phase 2** | $49 - $59 | Small businesses |
| **بعد Phase 3** | $79 + Subscription | Agencies / SaaS |

### Extended License (CodeCanyon)
- Phase 1+: $999 - $1,499

---

## ⚠️ القرارات المعمارية (ADRs)

| # | القرار | السبب |
|---|--------|------|
| **ADR-001** | Singleton + Namespaces بدون Composer | يشتغل على أي shared hosting بدون مشاكل |
| **ADR-002** | DB table مخصص للـ attendees بدل CPT | Performance — millions of rows possible |
| **ADR-003** | QR Token بدل meta query | Lookup سريع + secure |
| **ADR-004** | شفي gateway: WC أساسي + Stripe direct اختياري | كل مين عندو بيئة |
| **ADR-005** | Recurrence cap على 5 سنوات | حماية من infinite loops |
| **ADR-006** | i18n strict — كل النصوص عبر `__()` / `esc_html__()` | Envato requirement |

---

## 🔐 Security Standards

### إلزامي على كل feature جديدة:
- ✅ `wp_verify_nonce()` على كل form/AJAX
- ✅ `current_user_can()` capability check
- ✅ `sanitize_*()` على كل input
- ✅ `wp_unslash()` قبل sanitize
- ✅ `esc_*()` على كل output
- ✅ `$wpdb->prepare()` على كل DB query
- ✅ Direct file access prevention (`if (!defined('ABSPATH')) exit;`)
- ✅ Rate limiting على public AJAX endpoints

---

## ⚙️ مبادئ العمل (DOs & DON'Ts)

### ✅ افعل
- اقرأ الكود الحالي قبل أي تعديل (لا تفترض)
- استخدم namespaces (`\CoreEventsPro\...`)
- ابق على الـ pattern الموجود (Class-per-feature)
- اكتب بالعربي بالتواصل، بالإنجليزي بالكود والتعليقات
- اختبر كل ميزة قبل ما تقول "خلصت"
- اعمل commits منطقية صغيرة بأسماء واضحة
- لو في feature بتأثر على المرحلة الثانية، اعملها بشكل extensible

### ❌ لا تفعل
- ما تستخدم `quickchart.io` — حرام علينا dependency خارجية للـ QR
- ما تعمل `SELECT *` بدون LIMIT
- ما تكتب `posts_per_page => -1` بدون batching
- ما تحط nonces على client-side script بدون verification على server
- ما تثق بـ `current_user_can('edit_posts')` للـ scanner — استخدم capability مخصصة
- ما تعرف functions في `core-events-pro.php` — استخدم classes
- ما تضيف emojis بالكود (إلا في UI strings للمستخدم)
- ما تحط TODOs بالكود — افتح GitHub issues بدلاً منها

---

## 🧪 أوامر مفيدة

```bash
# PHP lint
php -l includes/Modules/Attendees.php

# Find pattern usage
grep -rn "quickchart.io" --include="*.php"
grep -rn "SHOW TABLES LIKE" --include="*.php"
grep -rn "posts_per_page.*-1" --include="*.php"

# Generate POT file
wp i18n make-pot . languages/core-events-pro.pot --domain=core-events-pro

# Test on local WP
# (depends on local stack: Local by Flywheel / wp-env / DDEV)

# Build user guide PDF
python build_user_guide.py
```

---

## 📞 Migration State (14 June 2026)

**الحالة الحالية:** الإضافة **جاهزة للتسليم على CodeCanyon (Release 1.0.0)**.

تم دمج شغل Phase 1 المتقدّم (كان uncommitted في worktree) داخل `main` (commit `57682eb`)، ثم في نفس الجلسة:
1. ~~Text Domain غلط في الترويسة (`core-events` → `core-events-pro`)~~ ✅ (كان بيكسر الترجمة)
2. ~~ضبط الاسم التجاري "EventCore" + ترويسات احترافية (URI/Requires/License)~~ ✅
3. ~~ثغرة CSRF في `manual_checkin` (أضفنا nonce)~~ ✅
4. ~~ملف ميت `includes/loader.php`~~ ✅ (انحذف)
5. ~~تقوية استيراد CSV (`is_uploaded_file` + filetype check)~~ ✅
6. ~~توحيد النصوص المرئية على "EventCore"~~ ✅
7. ~~`readme.txt` (وصف + إفصاح طرف ثالث + changelog)~~ ✅
8. ~~إعادة توليد `.pot` (384 نص، يشمل كل الوحدات الجديدة)~~ ✅
9. ~~lint لكل ملفات PHP (PHP 8.2، صفر أخطاء)~~ ✅
10. ~~حزمة Envato كاملة (plugin zip + documentation/ + licensing/)~~ ✅

**الحزمة:** `dist/eventcore-1.0.0.zip` (الإضافة القابلة للتثبيت) + `dist/eventcore-codecanyon-1.0.0.zip` (حزمة الرفع الكاملة).

**متبقّي على عبدالرحمن قبل الرفع (مو كود):** Screenshots للـ item، تأكيد `Tested up to` لإصدار WP المُختبَر فعلياً، ووصف المنتج على Envato.

**أول مهمة feature قادمة (اختيارية، Phase 1.6+):** PDF Ticket Attachment ثم Custom Registration Fields / Coupons.

---

*هذا الملف هو الحقيقة الوحيدة (Single Source of Truth) للمشروع. كل جلسة جديدة تبدأ من هنا.*
