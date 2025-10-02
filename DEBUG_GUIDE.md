# راهنمای عیب‌یابی - WooCommerce AutoResponder AI

## مشکل: ربات خودکار پاسخ کامنت‌ها را نمی‌دهد

### مراحل بررسی

#### 1. بررسی تنظیمات پلاگین
```
WordPress Admin > WooCommerce > AI AutoResponder
```
- `Enable Automation` باید فعال باشد ✅
- `Workflow Mode` باید روی `Auto-publish responses` یا `Require approval` باشد ✅
- یکی از AI Provider ها (OpenAI/Gemini/OpenRouter) باید انتخاب شده باشد
- API Key مربوطه باید وارد شده باشد

#### 2. بررسی لاگ‌های WordPress
```
wp-content/debug.log
```
دنبال این پیام‌ها بگردید:
- `WC AI: Processing new review ID:`
- `WC AI: Automation is disabled`
- `WC AI: Not a product review`
- `WC AI: Comment approved via [hook_name] hook`

#### 3. تست دستی
فایل‌های تست در پوشه پلاگین:
```bash
# تست تنظیمات و وضعیت
php test-automation.php

# شبیه‌سازی کامنت جدید
php simulate-review.php
```

### علل احتمالی مشکل

#### 1. Hook های WordPress
- Hook های `comment_post`, `wp_insert_comment`, `comment_approved` ممکن است فعال نباشند
- Theme یا پلاگین دیگری hook ها را override کرده باشد

#### 2. نوع کامنت (Comment Type)
در WooCommerce، کامنت‌های محصولات ممکن است:
- `comment_type` خالی باشد
- یا `comment_type = 'review'` باشد

#### 3. API Key یا Provider
- API Key اشتباه یا منقضی شده
- Provider انتخابی در دسترس نباشد
- Network connection مشکل داشته باشد

#### 4. Database Tables
بررسی کنید جداول پلاگین ایجاد شده‌اند:
- `wp_wc_ai_responses`
- `wp_wc_ai_logs`

### راه‌حل‌ها

#### 1. فعال‌سازی Debug Mode
در `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### 2. Manual Test
برای تست دستی، از WordPress Admin:
```
Products > Reviews > [یک review انتخاب کنید] > "Generate AI Response"
```

#### 3. Hook Priority
اگر hook ها کار نمی‌کنند، priority را تغییر دهید:
```php
// در Plugin.php
add_action('comment_post', [$this, 'handle_comment_post'], 5, 3); // priority کمتر
```

#### 4. Cron Jobs
بررسی کنید WordPress Cron کار می‌کند:
```bash
wp cron event list
```

### نکات مهم

1. **Review Type**: کامنت‌های محصولات WooCommerce ممکن است `comment_type` خالی داشته باشند
2. **Immediate Processing**: در حالت Auto، پلاگین سعی می‌کند فوراً پاسخ تولید کند
3. **Queue System**: اگر پردازش فوری شکست بخورد، در صف قرار می‌گیرد
4. **API Limits**: Provider های AI ممکن است محدودیت نرخ داشته باشند

### کدهای مفید برای Debug

#### بررسی کامنت‌های اخیر محصولات:
```php
$comments = get_comments([
    'post_type' => 'product',
    'status' => 'approve',
    'number' => 10
]);

foreach ($comments as $comment) {
    echo "ID: {$comment->comment_ID}, Type: '{$comment->comment_type}', Product: {$comment->comment_post_ID}\n";
}
```

#### Manual trigger:
```php
$plugin = WC_AutoResponder_AI\Plugin::get_instance();
$plugin->handle_new_review($comment_id);
```

#### بررسی پاسخ‌های تولید شده:
```php
$database = new WC_AutoResponder_AI\Database();
$responses = $database->get_responses_by_review($comment_id);
```
