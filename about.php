<?php
declare(strict_types=1);

// نیازی به لاگین نیست → require_login() رو کامنت یا حذف کردیم
// اگر بعداً خواستی بخشی رو محدود کنی، می‌تونی شرطی بذاری

$pageTitle = 'درباره هنرستان دارالفنون | شاهرود';
$activeNav = 'about';   // اگر منو داری، این رو فعال کن
$extraStyles = ['css/about.css?v=' . filemtime(__DIR__ . '/css/about.css')];
$extraScripts = ['js/about-entrance.js?v=' . filemtime(__DIR__ . '/js/about-entrance.js')];

require __DIR__ . '/partials/header.php';
?>

<main class="about-page">
  <section class="about-hero">
    <p class="about-kicker">درباره هنرستان</p>
    <h1>هنرستان فنی نمونه دولتی دارالفنون شاهرود</h1>
    <p class="about-subtitle">
      دوره دوم متوسطه – فنی پسرانه | شبکه و نرم‌افزار • برق • الکترونیک
    </p>
  </section>

  <section class="about-card">
    <h2>معرفی هنرستان</h2>
    <p>
      هنرستان فنی دارالفنون شاهرود، یکی از مدارس نمونه دولتی استان سمنان است که در مقطع دوره دوم متوسطه – فنی پسرانه فعالیت می‌کند. این هنرستان در رشته‌های پرطرفدار فنی و حرفه‌ای شامل <strong>شبکه و نرم‌افزار رایانه</strong>، <strong>برق</strong> و <strong>الکترونیک</strong> دانش‌آموز تربیت می‌نماید.
    </p>
    <p>
      مدرسه فنی دارالفنون به مدیریت فعلی، در نشانی <strong>پشت مهدیه بزرگ شاهرود، کوچه نبش ورودی نادر، انتهای کوچه دست راست</strong> واقع شده است. این هنرستان با هدف تربیت نیروی متخصص و آماده ورود به بازار کار، امکانات علمی و آموزشی مناسبی فراهم نموده و آمادگی پاسخگویی مستمر به سوالات اولیاء محترم را دارد.
    </p>
  </section>

  <section class="about-card about-history">
    <h2>تأسیس</h2>
    <p>
      هنرستان فنی دارالفنون در سال <strong>۱۳۸۹</strong> شمسی توسط دولت و با تلاش سه‌ساله عوامل مختلف اجرایی و آموزشی تأسیس گردید. این مدرسه از همان ابتدا با هدف ارائه آموزش‌های فنی-حرفه‌ای با کیفیت در سطح استان سمنان راه‌اندازی شد.
    </p>
  </section>

  <section class="about-grid-two">
    <article class="about-card about-hover">
      <h3>فضای فیزیکی</h3>
      <ul>
        <li>بنای آموزشی: حدود <strong>۴۵۹ متر مربع</strong></li>
        <li>حیاط و فضای باز: حدود <strong>۴۵۰ متر مربع</strong></li>
        <li>کلاس‌های مجهز با صندلی‌های تک‌نفره</li>
        <li>حیاط ورزشی متناسب با ظرفیت دانش‌آموزی</li>
      </ul>
    </article>

    <article class="about-card about-hover">
      <h3>ظرفیت آموزشی</h3>
      <ul>
        <li>تعداد دانش‌آموز میانگین سالانه: حدود <strong>۲۲۷ نفر</strong></li>
        <li>تعداد کلاس‌های آموزشی: حدود <strong>۱۳ کلاس</strong></li>
        <li>میانگین دانش‌آموز در هر کلاس: حدود <strong>۱۷ نفر</strong></li>
      </ul>
    </article>
  </section>

  <section class="about-card">
    <h2>امکانات محیطی و خدمات رفاهی</h2>
    <p class="about-muted">هنرستان دارالفنون دارای امکانات متنوعی است که محیطی مناسب برای یادگیری و رشد دانش‌آموزان فراهم می‌کند:</p>
    <div class="amenities-grid">
      <div class="amenity-item">کتابخانه با بیش از <strong>۲۶۰ جلد کتاب</strong></div>
      <div class="amenity-item">بوفه عرضه اغذیه سالم</div>
      <div class="amenity-item">نمازخانه با ظرفیت <strong>۱۴۵ نفر</strong> همزمان</div>
      <div class="amenity-item">حیاط ورزشی مناسب</div>
      <div class="amenity-item">سرویس ایاب و ذهاب (در صورت نیاز اولیاء)</div>
      <div class="amenity-item">کارگاه‌های تخصصی فنی و حرفه‌ای</div>
    </div>
  </section>

  <section class="about-contact">
    <h2>ارتباط با ما</h2>
    <p>برای اطلاعات بیشتر، ثبت‌نام و مشاوره، با ما در ارتباط باشید:</p>
    <p class="about-phone">
      تلفن: <?php echo defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '۰۲۳-۳۳۶۲۸۲۱ یا ۰۲۳-۳۲۳۳۴۴۲۰'; ?>
    </p>
    <p class="about-address">آدرس: شاهرود – پشت مهدیه بزرگ – کوچه نبش ورودی نادر – انتهای کوچه دست راست</p>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
