# WooCommerce Guest Order Account Matcher

Matches eligible guest WooCommerce orders to existing customer accounts using normalized Iranian mobile numbers and configurable name similarity checks.

## Description

WooCommerce Guest Order Account Matcher allows a guest order to be attached automatically to an existing WooCommerce customer account only when the match is strong and unambiguous.

The plugin evaluates:

- Iranian mobile number
- Billing first name
- Billing last name
- Duplicate accounts using the same phone number
- Similarity between checkout and account names

## Features

- Iranian mobile-number normalization
- Persian and Arabic digit conversion
- Supports common Iranian mobile formats
- Exact and normalized `billing_phone` lookup
- First-name similarity scoring
- Last-name token matching
- Configurable match threshold
- Duplicate-phone candidate ranking
- Ambiguous-match rejection
- Automatic guest-order customer assignment
- Billing-phone normalization on matched orders
- Order audit metadata
- Internal order notes
- WooCommerce HPOS compatibility
- No custom database tables
- Single-file architecture

## Requirements

- PHP 7.4+
- WordPress 6.0+
- WooCommerce

## Installation

1. Download the repository as a ZIP file.
2. Open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP file.
4. Install and activate the plugin.

## Default Match Threshold

The default minimum score is `80%` for both first name and last name.

```php
add_filter(
	'wc_goam_name_match_threshold',
	static function () {
		return 85;
	}
);
```

## Duplicate Candidate Margin

The default ambiguity margin is `5` percentage points.

```php
add_filter(
	'wc_goam_duplicate_candidate_margin',
	static function () {
		return 10;
	}
);
```

## Accepted Phone Formats

```text
09121234567
9121234567
989121234567
00989121234567
```

All valid values are normalized to:

```text
09121234567
```

## Rejection Reasons

- Invalid phone
- No account found
- Name mismatch
- Ambiguous duplicate phone

## Data Storage

The plugin stores audit metadata on the WooCommerce order and temporarily caches user IDs found for a normalized phone number.

No custom database tables are created.

## Security Note

Name similarity is heuristic and does not prove account ownership. For sensitive or high-risk stores, requiring login is safer than automatic guest matching.

## HPOS Compatibility

The plugin declares compatibility with WooCommerce High-Performance Order Storage.

## Changelog

### 1.0.0

- Initial release
- Iranian phone normalization
- Guest-account matching
- Name similarity scoring
- Duplicate-phone ambiguity protection
- Order audit metadata
- WooCommerce order notes
- HPOS support

## License

GPL-3.0

## Author

**Amirreza Shayesteh Far**

- Website: https://amirrezaa.ir/
- GitHub: https://github.com/amirrezashf
- Repository: https://github.com/amirrezashf/WooCommerce-Guest-Order-Account-Matcher

---

# اتصال سفارش مهمان به حساب کاربری ووکامرس

اتصال سفارش مهمان به حساب مشتری موجود بر اساس شماره موبایل ایران و تطابق نام و نام خانوادگی.

## توضیحات

افزونه تنها زمانی سفارش مهمان را به حساب موجود متصل می‌کند که تطابق شماره موبایل و اطلاعات نام قوی و بدون ابهام باشد.

## ویژگی‌ها

- نرمال‌سازی شماره موبایل ایران
- تبدیل اعداد فارسی و عربی
- جستجوی دقیق و نرمال‌شده `billing_phone`
- محاسبه تطابق نام
- محاسبه تطابق نام خانوادگی
- آستانه قابل تنظیم
- جلوگیری از انتخاب حساب مبهم
- اتصال خودکار سفارش مهمان
- ذخیره audit log روی سفارش
- افزودن یادداشت داخلی سفارش
- سازگار با HPOS
- بدون جدول اختصاصی
- معماری تک‌فایلی

## نیازمندی‌ها

- PHP 7.4+
- WordPress 6.0+
- WooCommerce

## نصب

1. repository را به‌صورت ZIP دانلود کنید.
2. وارد **افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه** شوید.
3. فایل ZIP را بارگذاری کنید.
4. افزونه را نصب و فعال کنید.

## آستانه تطابق

آستانه پیش‌فرض برای نام و نام خانوادگی `80%` است.

```php
add_filter(
	'wc_goam_name_match_threshold',
	static function () {
		return 85;
	}
);
```

## فاصله لازم بین حساب‌های تکراری

مقدار پیش‌فرض `5` درصد است.

```php
add_filter(
	'wc_goam_duplicate_candidate_margin',
	static function () {
		return 10;
	}
);
```

## قالب‌های قابل قبول شماره موبایل

```text
09121234567
9121234567
989121234567
00989121234567
```

خروجی نهایی:

```text
09121234567
```

## دلایل رد

- شماره نامعتبر
- حساب پیدا نشد
- عدم تطابق نام
- وجود چند حساب مشابه و مبهم

## ذخیره‌سازی داده

نتیجه بررسی به‌صورت metadata روی سفارش ذخیره می‌شود و شناسه کاربران پیدا شده برای مدت کوتاه cache می‌شود.

جدول اختصاصی دیتابیس ایجاد نمی‌شود.

## نکته امنیتی

تطابق نام یک روش احتمالی است و مالکیت واقعی حساب را اثبات نمی‌کند. برای فروشگاه‌های حساس، الزام ورود به حساب کاربری امن‌تر است.

## سازگاری با HPOS

افزونه سازگاری خود را با WooCommerce High-Performance Order Storage اعلام می‌کند.

## تغییرات نسخه‌ها

### 1.0.0

- انتشار اولیه
- نرمال‌سازی موبایل ایران
- اتصال سفارش مهمان
- محاسبه شباهت نام
- جلوگیری از تطابق مبهم
- audit metadata
- یادداشت سفارش
- پشتیبانی HPOS

## مجوز

GPL-3.0

## نویسنده

**Amirreza Shayesteh Far**

- وب‌سایت: https://amirrezaa.ir/
- GitHub: https://github.com/amirrezashf
- Repository: https://github.com/amirrezashf/WooCommerce-Guest-Order-Account-Matcher
