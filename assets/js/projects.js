(function () {
  window.afInitRichText && window.afInitRichText('newProjectDescription');

  const form = document.getElementById('projectForm');
  form && form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.member_ids = Array.from(form.querySelectorAll('.project-member-checkbox:checked')).map((cb) => cb.value);
    afFetch(window.AF_BASE_URL + 'api/projects.php', { method: 'POST', body: Object.assign({ action: 'create' }, data) })
      .then((res) => { afToast('Project created.'); window.location.href = window.AF_BASE_URL + 'project_detail.php?id=' + res.project.id; })
      .catch((err) => afToast(err.message, 'danger'));
  });
})();
