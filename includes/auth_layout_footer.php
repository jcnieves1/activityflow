</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
window.AF_BASE_URL = <?= json_encode(base_url()) ?>;
window.AF_CSRF = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/theme.js')) ?>"></script>
</body>
</html>
