  <footer class="site-footer">
    <div class="footer-main">
      <div class="footer-brand">
        <div class="footer-title">هنرستان دارالفنون</div>
        <div class="footer-sub">هنرستانی مدرن برای آموزش و تعلیم هنرجویان</div>
        <div class="footer-contact">
          <span>آدرس هنرستان</span>
          <span>شماره هنرستان</span>
          <span>ایمیل هنرستان</span>
        </div>
      </div>
      <div class="footer-columns">
        <div class="footer-col">
          <div class="footer-col-title">پیوندهای سریع</div>
          <a href="#">برنامه زنگ ها</a>
          <a href="#">منوی غذا</a>
          <a href="#">حمل و نقل</a>
        </div>
        <div class="footer-col">
          <div class="footer-col-title">منابع</div>
          <a href="#">مشاوره</a>
          <a href="#">کتابخانه</a>
          <a href="#">حمایت دانش آموزی</a>
          <a href="#">ایمنی</a>
        </div>
        <div class="footer-col">
          <div class="footer-col-title">مشارکت</div>
          <a href="#">انجمن اولیا و مربیان</a>
          <a href="#">داوطلبی</a>
          <a href="#">شورای دانش آموزی</a>
          <a href="#">فرصت های شغلی</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <span id="year"></span> تمامی حقوق این سایت متعلق به هنرستان دارالفنون می باشد</span>
    </div>
  </footer>

  <script src="js/script.js"></script>
  <?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $scriptPath): ?>
      <script src="<?= htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
