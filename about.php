<?php
declare(strict_types=1);

// نیازی به لاگین نیست → require_login() رو کامنت یا حذف کردیم
// اگر بعداً خواستی بخشی رو محدود کنی، می‌تونی شرطی بذاری

$pageTitle = 'درباره هنرستان دارالفنون | شاهرود';
$activeNav = 'about';   // اگر منو داری، این رو فعال کن

require __DIR__ . '/partials/header.php';
?>

<main class="container about-page" style="max-width: 1100px; margin: 2rem auto 4rem; line-height: 1.8; font-size: 1.05rem; color: var(--ink);">

    <section class="page-header" style="text-align: center; margin-bottom: 3rem;">
        <h1 style="font-size: 2.4rem; margin-bottom: 0.6rem;">هنرستان فنی نمونه دولتی دارالفنون شاهرود</h1>
        <p style="color: var(--muted); font-size: 1.2rem; max-width: 800px; margin: 0 auto;">
            دوره دوم متوسطه – فنی پسرانه | شبکه و نرم‌افزار • برق • الکترونیک
        </p>
    </section>

    <section class="intro" style="background: var(--card); border-radius: 16px; padding: 2.5rem; margin-bottom: 2.5rem; box-shadow: 0 8px 24px rgba(15,23,42,0.08);">
        <h2 style="font-size: 1.8rem; margin-top: 0;">معرفی هنرستان</h2>
        <p>
            هنرستان فنی دارالفنون شاهرود، یکی از مدارس نمونه دولتی استان سمنان است که در مقطع دوره دوم متوسطه – فنی پسرانه فعالیت می‌کند. این هنرستان در رشته‌های پرطرفدار فنی و حرفه‌ای شامل <strong>شبکه و نرم‌افزار رایانه</strong>، <strong>برق</strong> و <strong>الکترونیک</strong> دانش‌آموز تربیت می‌نماید.
        </p>
        <p>
            مدرسه فنی دارالفنون به مدیریت فعلی، در نشانی <strong>پشت مهدیه بزرگ شاهرود، کوچه نبش ورودی نادر، انتهای کوچه دست راست</strong> واقع شده است. این هنرستان با هدف تربیت نیروی متخصص و آماده ورود به بازار کار، امکانات علمی و آموزشی مناسبی فراهم نموده و آمادگی پاسخگویی مستمر به سوالات اولیاء محترم را دارد.
        </p>
    </section>

    <section class="history" style="margin-bottom: 2.5rem;">
        <h2 style="font-size: 1.8rem;">تأسیس</h2>
        <p>
            هنرستان فنی دارالفنون در سال <strong>۱۳۸۹</strong> شمسی توسط دولت و با تلاش سه‌ساله عوامل مختلف اجرایی و آموزشی تأسیس گردید. این مدرسه از همان ابتدا با هدف ارائه آموزش‌های فنی-حرفه‌ای با کیفیت در سطح استان سمنان راه‌اندازی شد.
        </p>
    </section>

    <section class="facilities grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
        <div style="background: var(--card); border-radius: 16px; padding: 2rem; box-shadow: 0 6px 16px rgba(15,23,42,0.06);">
            <h3 style="font-size: 1.5rem; margin-top: 0;">فضای فیزیکی</h3>
            <ul style="padding-right: 1.5rem; list-style: disc;">
                <li>بنای آموزشی: حدود <strong>۴۵۹ متر مربع</strong></li>
                <li>حیاط و فضای باز: حدود <strong>۴۵۰ متر مربع</strong></li>
                <li>کلاس‌های مجهز با صندلی‌های تک‌نفره</li>
                <li>حیاط ورزشی متناسب با ظرفیت دانش‌آموزی</li>
            </ul>
        </div>

        <div style="background: var(--card); border-radius: 16px; padding: 2rem; box-shadow: 0 6px 16px rgba(15,23,42,0.06);">
            <h3 style="font-size: 1.5rem; margin-top: 0;">ظرفیت آموزشی</h3>
            <ul style="padding-right: 1.5rem; list-style: disc;">
                <li>تعداد دانش‌آموز میانگین سالانه: حدود <strong>۲۲۷ نفر</strong></li>
                <li>تعداد کلاس‌های آموزشی: حدود <strong>۱۳ کلاس</strong></li>
                <li>میانگین دانش‌آموز در هر کلاس: حدود <strong>۱۷ نفر</strong></li>
            </ul>
        </div>
    </section>

    <section class="amenities" style="background: var(--card); border-radius: 16px; padding: 2.5rem; margin-bottom: 3rem; box-shadow: 0 8px 24px rgba(15,23,42,0.08);">
        <h2 style="font-size: 1.8rem; margin-top: 0;">امکانات محیطی و خدمات رفاهی</h2>
        <p>هنرستان دارالفنون دارای امکانات متنوعی است که محیطی مناسب برای یادگیری و رشد دانش‌آموزان فراهم می‌کند:</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.2rem; margin-top: 1.5rem;">
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                کتابخانه با بیش از <strong>۲۶۰ جلد کتاب</strong>
            </div>
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                بوفه عرضه اغذیه سالم
            </div>
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                نمازخانه با ظرفیت <strong>۱۴۵ نفر</strong> همزمان
            </div>
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                حیاط ورزشی مناسب
            </div>
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                سرویس ایاب و ذهاب (در صورت نیاز اولیاء)
            </div>
            <div class="amenity-item" style="background: rgba(37,99,235,0.06); padding: 1.2rem; border-radius: 12px; text-align: center;">
                کارگاه‌های تخصصی فنی و حرفه‌ای
            </div>
        </div>
    </section>

    <section class="contact" style="text-align: center; padding: 2.5rem; background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(59,130,246,0.05)); border-radius: 16px;">
        <h2 style="font-size: 1.8rem; margin-bottom: 1rem;">ارتباط با ما</h2>
        <p style="font-size: 1.1rem; margin-bottom: 1.5rem;">
            برای اطلاعات بیشتر، ثبت‌نام و مشاوره، با ما در ارتباط باشید:
        </p>
        <div style="font-size: 1.2rem; font-weight: 600; color: var(--accent); margin: 1rem 0;">
            تلفن: <?php echo defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '۰۲۳-۳۳۶۲۸۲۱ یا ۰۲۳-۳۲۳۳۴۴۲۰'; ?>
        </div>
        <p style="color: var(--muted);">
            آدرس: شاهرود – پشت مهدیه بزرگ – کوچه نبش ورودی نادر – انتهای کوچه دست راست
        </p>
    </section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>