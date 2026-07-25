// "Stop impersonating" control in the topbar banner (only present/loaded
// while an admin is actively impersonating someone — see is_impersonating()
// in includes/layout_header.php / layout_footer.php).
(function () {
  const btn = document.getElementById('afStopImpersonationBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    afFetch(window.AF_BASE_URL + 'api/impersonate.php', { method: 'POST', body: { action: 'stop' } })
      .then(() => {
        // Identity is reverting to the original admin — everything derived
        // from the session (nav, permissions, theme/locale) needs a fresh
        // page load, same reasoning as the "start impersonating" redirect.
        window.location.href = window.AF_BASE_URL + 'dashboard.php';
      })
      .catch((err) => afToast(err.message, 'danger'));
  });
})();
