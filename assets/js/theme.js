// Color scheme + language switchers, shared by the main app topbar and the
// login/register auth pages. Theme applies instantly (just a CSS variable
// swap); language requires a reload since page text is rendered server-side.
(function () {
  function persist(payload) {
    return afFetch(window.AF_BASE_URL + 'api/preferences.php', { method: 'POST', body: payload });
  }

  window.afSetTheme = function (theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.querySelectorAll('[data-theme-active]').forEach(function (el) {
      el.classList.toggle('active', el.dataset.themeOption === theme);
    });
    persist({ theme: theme }).catch(function (err) { afToast(err.message, 'danger'); });
  };

  window.afSetLocale = function (locale) {
    persist({ locale: locale })
      .then(function () { location.reload(); })
      .catch(function (err) { afToast(err.message, 'danger'); });
  };

  document.addEventListener('click', function (e) {
    const themeEl = e.target.closest('[data-theme-option]');
    if (themeEl) {
      e.preventDefault();
      window.afSetTheme(themeEl.dataset.themeOption);
      return;
    }
    const localeEl = e.target.closest('[data-locale-option]');
    if (localeEl) {
      e.preventDefault();
      window.afSetLocale(localeEl.dataset.localeOption);
    }
  });
})();
