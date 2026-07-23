(function () {
  window.afOnActivityCreated = function () { location.reload(); };
  if (window.AF_OPEN_ACTIVITY) {
    afActivities.openEdit(window.AF_OPEN_ACTIVITY);
  }
})();
