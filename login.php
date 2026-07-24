<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = t('login.error_missing_fields');
    } elseif (!captcha_verify($_POST['captcha_answer'] ?? null)) {
        $error = t('login.error_captcha');
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok']) {
            redirect('dashboard.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = t('login.submit');

// Data-driven content for the marketing sections below — kept as small PHP
// arrays (rather than one giant literal template) so the markup loops stay
// short and every string still goes through t() for EN/ES translation.
// Bootstrap Icons are used for the feature/benefit icons (already loaded via
// CDN app-wide) and for the little "badge" icon layered on each mascot
// appearance, so no extra image asset — just the one hand-drawn SVG mascot —
// is needed anywhere on this page.
$landingBenefits = [
    ['icon' => 'bi-eye-fill', 'key' => 'benefit_1'],
    ['icon' => 'bi-clock-history', 'key' => 'benefit_2'],
    ['icon' => 'bi-shield-check', 'key' => 'benefit_3'],
    ['icon' => 'bi-shuffle', 'key' => 'benefit_4'],
];
$landingFeatures = [
    ['icon' => 'bi-kanban', 'key' => 'feature_1'],
    ['icon' => 'bi-stopwatch-fill', 'key' => 'feature_2'],
    ['icon' => 'bi-columns-gap', 'key' => 'feature_3'],
    ['icon' => 'bi-calendar3', 'key' => 'feature_4'],
    ['icon' => 'bi-bar-chart-fill', 'key' => 'feature_5'],
    ['icon' => 'bi-chat-left-text-fill', 'key' => 'feature_6'],
    ['icon' => 'bi-people-fill', 'key' => 'feature_7'],
    ['icon' => 'bi-palette-fill', 'key' => 'feature_8'],
];
$landingWorkflow = [
    ['badge' => 'bi-people-fill', 'key' => 'workflow_step_1'],
    ['badge' => 'bi-list-check', 'key' => 'workflow_step_2'],
    ['badge' => 'bi-stopwatch', 'key' => 'workflow_step_3'],
    ['badge' => 'bi-kanban', 'key' => 'workflow_step_4'],
    ['badge' => 'bi-bar-chart-fill', 'key' => 'workflow_step_5'],
];
$landingReviews = [
    ['key' => 'review_1', 'stars' => 5],
    ['key' => 'review_2', 'stars' => 5],
    ['key' => 'review_3', 'stars' => 5],
    ['key' => 'review_4', 'stars' => 4],
    ['key' => 'review_5', 'stars' => 5],
];

function landing_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $initials);
}
?><!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="<?= e(current_theme()) ?>" data-bs-theme="<?= e(bs_color_mode()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(t('app.name')) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/landing.css')) ?>">
</head>
<body class="af-landing">

<!-- Mascot artwork, defined once and reused everywhere below via <use>. A
     single friendly character re-colored through the active theme's --bs-primary
     (see landing.css), with a small floating icon badge changed per spot to
     hint at what it's "helping" with, instead of drawing a brand new
     illustration for every section. -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="af-mascot" viewBox="0 0 200 220">
      <ellipse class="af-mascot-fill-primary" cx="78" cy="198" rx="16" ry="9"/>
      <ellipse class="af-mascot-fill-primary" cx="122" cy="198" rx="16" ry="9"/>
      <rect class="af-mascot-fill-primary" x="14" y="112" width="24" height="62" rx="12"/>
      <rect class="af-mascot-fill-primary" x="162" y="112" width="24" height="62" rx="12"/>
      <rect class="af-mascot-fill-primary" x="96" y="26" width="8" height="34" rx="4"/>
      <circle class="af-mascot-fill-accent" cx="100" cy="22" r="10"/>
      <rect class="af-mascot-fill-primary" x="34" y="55" width="132" height="140" rx="56"/>
      <ellipse class="af-mascot-fill-light" cx="100" cy="150" rx="40" ry="34" opacity="0.16"/>
      <circle class="af-mascot-fill-cheek" cx="63" cy="128" r="10"/>
      <circle class="af-mascot-fill-cheek" cx="137" cy="128" r="10"/>
      <circle class="af-mascot-fill-light" cx="78" cy="108" r="17"/>
      <circle class="af-mascot-fill-light" cx="122" cy="108" r="17"/>
      <circle class="af-mascot-fill-dark" cx="81" cy="111" r="7.5"/>
      <circle class="af-mascot-fill-dark" cx="125" cy="111" r="7.5"/>
      <circle class="af-mascot-fill-light" cx="83.5" cy="108.5" r="2.4"/>
      <circle class="af-mascot-fill-light" cx="127.5" cy="108.5" r="2.4"/>
      <path class="af-mascot-stroke-dark" d="M 76 138 Q 100 156 124 138" fill="none" stroke-width="5" stroke-linecap="round"/>
    </symbol>
  </defs>
</svg>

<?php
/** Renders one mascot instance at a given size, with a small badge icon layered on top. */
function landing_mascot(string $size, string $badgeIcon, bool $flip = false): void {
    ?><div class="af-mascot-wrap<?= $flip ? ' af-mascot-flip' : '' ?>">
      <svg class="af-mascot <?= e($size) ?>" viewBox="0 0 200 220" aria-hidden="true"><use href="#af-mascot"></use></svg>
      <span class="af-mascot-badge"><i class="bi <?= e($badgeIcon) ?>"></i></span>
    </div><?php
}
?>

<nav class="navbar navbar-expand-md af-landing-nav">
  <div class="container">
    <a class="af-landing-brand" href="<?= e(base_url('login.php')) ?>"><i class="bi bi-activity text-primary"></i> <?= e(t('app.name')) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#afLandingNavCollapse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="afLandingNavCollapse">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
        <li class="nav-item"><a class="nav-link" href="#features"><?= e(t('landing.nav_features')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="#how-it-works"><?= e(t('landing.nav_how_it_works')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="#reviews"><?= e(t('landing.nav_reviews')) ?></a></li>
        <li class="nav-item dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="<?= e(t('topbar.language')) ?>"><i class="bi bi-translate"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach (available_locales() as $key => $label): ?>
              <li><a class="dropdown-item <?= current_locale() === $key ? 'active' : '' ?>" href="#" data-locale-option="<?= e($key) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li class="nav-item"><a class="btn btn-outline-secondary btn-sm ms-md-2" href="#auth"><?= e(t('landing.nav_login')) ?></a></li>
        <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="#auth"><?= e(t('landing.nav_get_started')) ?></a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ---- Hero ---- -->
<header class="af-hero">
  <div class="container">
    <div class="row align-items-center gy-4">
      <div class="col-lg-7">
        <span class="af-hero-eyebrow"><?= e(t('landing.hero_eyebrow')) ?></span>
        <h1 class="af-hero-title"><?= e(t('landing.hero_title')) ?></h1>
        <p class="af-hero-subtitle"><?= e(t('landing.hero_subtitle')) ?></p>
        <div class="d-flex flex-wrap gap-2">
          <a href="#auth" class="btn btn-primary btn-lg"><?= e(t('landing.hero_cta_primary')) ?></a>
          <a href="#auth" class="btn btn-outline-secondary btn-lg"><?= e(t('landing.hero_cta_secondary')) ?></a>
        </div>
        <div class="af-hero-note"><i class="bi bi-check-circle"></i> <?= e(t('landing.hero_note')) ?></div>
      </div>
      <div class="col-lg-5 text-center">
        <?php landing_mascot('af-mascot-hero', 'bi-clipboard-check-fill'); ?>
      </div>
    </div>
  </div>
</header>

<!-- ---- Stats ---- -->
<section class="af-stats">
  <div class="container">
    <div class="row text-center g-3">
      <?php foreach ([1, 2, 3, 4] as $i): ?>
        <div class="col-6 col-md-3">
          <div class="af-stat-number"><?= e(t('landing.stat_' . $i . '_number')) ?></div>
          <div class="af-stat-label"><?= e(t('landing.stat_' . $i . '_label')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---- About ---- -->
<section class="af-section">
  <div class="container">
    <div class="row justify-content-center text-center">
      <div class="col-lg-8">
        <h2 class="af-section-title"><?= e(t('landing.about_title')) ?></h2>
        <p class="text-muted fs-5"><?= e(t('landing.about_body')) ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ---- Benefits ---- -->
<section class="af-section af-section-alt">
  <div class="container">
    <div class="af-section-header mb-3">
      <h2 class="af-section-title"><?= e(t('landing.benefits_title')) ?></h2>
    </div>
    <div class="row g-4">
      <?php foreach ($landingBenefits as $b): ?>
        <div class="col-md-6 col-lg-3">
          <div class="af-card-tile">
            <div class="af-card-tile-icon"><i class="bi <?= e($b['icon']) ?>"></i></div>
            <h5><?= e(t('landing.' . $b['key'] . '_title')) ?></h5>
            <p><?= e(t('landing.' . $b['key'] . '_body')) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---- Features ---- -->
<section class="af-section" id="features">
  <div class="container">
    <div class="af-section-header mb-3">
      <h2 class="af-section-title"><?= e(t('landing.features_title')) ?></h2>
      <p class="af-section-subtitle"><?= e(t('landing.features_subtitle')) ?></p>
    </div>
    <div class="row g-4">
      <?php foreach ($landingFeatures as $f): ?>
        <div class="col-md-6 col-lg-3">
          <div class="af-card-tile">
            <div class="af-card-tile-icon"><i class="bi <?= e($f['icon']) ?>"></i></div>
            <h5><?= e(t('landing.' . $f['key'] . '_title')) ?></h5>
            <p><?= e(t('landing.' . $f['key'] . '_body')) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---- How it works ---- -->
<section class="af-section af-section-alt" id="how-it-works">
  <div class="container">
    <div class="row gy-5 align-items-center">
      <div class="col-lg-6">
        <h2 class="af-section-title"><?= e(t('landing.workflow_title')) ?></h2>
        <p class="af-section-subtitle"><?= e(t('landing.workflow_subtitle')) ?></p>
        <?php foreach ($landingWorkflow as $i => $step): ?>
          <div class="af-workflow-step">
            <div class="af-workflow-number"><?= $i + 1 ?></div>
            <div>
              <h5><?= e(t('landing.' . $step['key'] . '_title')) ?></h5>
              <p><?= e(t('landing.' . $step['key'] . '_body')) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="col-lg-6 text-center">
        <?php landing_mascot('af-mascot-hero', 'bi-signpost-2-fill', true); ?>
      </div>
    </div>
  </div>
</section>

<!-- ---- Mid-page CTA ---- -->
<section class="af-section">
  <div class="container">
    <div class="af-cta-banner">
      <h2><?= e(t('landing.cta_mid_title')) ?></h2>
      <p><?= e(t('landing.cta_mid_body')) ?></p>
      <a href="#auth" class="btn btn-light btn-lg fw-semibold"><?= e(t('landing.cta_mid_button')) ?></a>
    </div>
  </div>
</section>

<!-- ---- Reviews ---- -->
<section class="af-section" id="reviews">
  <div class="container">
    <div class="row align-items-center mb-4 gy-3">
      <div class="col-lg-8">
        <h2 class="af-section-title"><?= e(t('landing.reviews_title')) ?></h2>
        <p class="af-section-subtitle mb-0"><?= e(t('landing.reviews_subtitle')) ?></p>
      </div>
      <div class="col-lg-4 text-center text-lg-end">
        <?php landing_mascot('af-mascot-md', 'bi-emoji-heart-eyes-fill'); ?>
      </div>
    </div>
    <div class="row g-4">
      <?php foreach ($landingReviews as $r):
        $name = t('landing.' . $r['key'] . '_name');
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="af-review-card">
            <div class="af-review-stars">
              <?php for ($s = 0; $s < 5; $s++): ?><i class="bi <?= $s < $r['stars'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?>
            </div>
            <p class="af-review-quote">&ldquo;<?= e(t('landing.' . $r['key'] . '_quote')) ?>&rdquo;</p>
            <div class="af-review-author">
              <div class="af-review-avatar"><?= e(landing_initials($name)) ?></div>
              <div>
                <div class="af-review-name"><?= e($name) ?></div>
                <div class="af-review-role"><?= e(t('landing.' . $r['key'] . '_role')) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---- Auth (preserved login / create-account section) ---- -->
<section class="af-auth-section af-section-alt" id="auth">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="af-section-title"><?= e(t('landing.auth_title')) ?></h2>
      <p class="af-section-subtitle mx-auto"><?= e(t('landing.auth_subtitle')) ?></p>
    </div>
    <div class="af-auth-card">
      <?php foreach (flash_all() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <h5 class="mb-3"><?= e(t('login.title')) ?></h5>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="email"><?= e(t('login.email')) ?></label>
          <input type="email" class="form-control" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="password"><?= e(t('login.password')) ?></label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <?= captcha_field() ?>
        <button type="submit" class="btn btn-primary w-100"><?= e(t('login.submit')) ?></button>
      </form>
      <div class="d-flex justify-content-between mt-3 small">
        <a href="<?= e(base_url('forgot_password.php')) ?>"><?= e(t('login.forgot_password')) ?></a>
        <a href="<?= e(base_url('register.php')) ?>"><?= e(t('login.create_account')) ?></a>
      </div>
    </div>
  </div>
</section>

<footer class="af-landing-footer">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><i class="bi bi-activity text-primary"></i> <strong><?= e(t('app.name')) ?></strong> &middot; <?= e(t('landing.footer_tagline')) ?></div>
    <div>&copy; <?= date('Y') ?> <?= e(t('app.name')) ?>. <?= e(t('landing.footer_rights')) ?></div>
  </div>
</footer>

<div id="afLoadingOverlay" class="af-loading-overlay" aria-hidden="true" aria-live="polite">
  <div class="af-loading-box">
    <div class="spinner-border text-primary" role="status"></div>
    <div class="af-loading-text"><?= e(t('common.loading')) ?></div>
  </div>
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
