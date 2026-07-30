// Mind Map: a Release -> Project -> Task -> assigned-person graph rendered
// with vis-network so the whole task-execution landscape can be seen (and
// manually rearranged / exported as an image) at a glance.
(function () {
  const container = document.getElementById('mindmapCanvas');
  if (!container) return;
  if (typeof vis === 'undefined') {
    container.innerHTML = '<div class="text-muted small p-3">Unable to load the graphing library.</div>';
    return;
  }

  const i18n = window.AF_I18N_MINDMAP || {};
  const globalI18n = window.AF_I18N || {};

  // Same "All / individual picks" checkbox-dropdown behavior used by the Task
  // Board, Vacations, and Workload pages: checking "All" clears individual
  // picks, checking any individual pick clears "All", and unchecking the
  // last individual pick reverts back to "All" (never an ambiguous
  // nothing-picked state).
  function wireMultiSelectDropdown(allId, itemSelector, labelId, allText, singular, plural) {
    const allCb = document.getElementById(allId);
    const itemCbs = Array.from(document.querySelectorAll(itemSelector));
    function updateLabel() {
      const label = document.getElementById(labelId);
      if (!label) return;
      const checked = itemCbs.filter((cb) => cb.checked);
      if (!checked.length) { label.textContent = allText; return; }
      const noun = checked.length === 1 ? singular : plural;
      const suffix = globalI18n.board_selected_suffix || '{count} {noun} selected';
      label.textContent = suffix.replace('{count}', checked.length).replace('{noun}', noun);
    }
    function selectedValues() { return itemCbs.filter((cb) => cb.checked).map((cb) => cb.value); }
    if (allCb) {
      allCb.addEventListener('change', function () {
        if (allCb.checked) itemCbs.forEach((cb) => { cb.checked = false; });
        updateLabel();
      });
    }
    itemCbs.forEach((cb) => {
      cb.addEventListener('change', function () {
        if (cb.checked && allCb) allCb.checked = false;
        if (allCb && !itemCbs.some((c) => c.checked)) allCb.checked = true;
        updateLabel();
      });
    });
    updateLabel();
    return { selectedValues };
  }

  const releaseGroup = wireMultiSelectDropdown(
    'mmReleaseAll', '.mm-release-checkbox', 'mmReleaseLabel',
    globalI18n.mindmap_all_releases || document.getElementById('mmReleaseLabel').textContent,
    globalI18n.mindmap_release_singular || 'release', globalI18n.mindmap_release_plural || 'releases'
  );
  const projectGroup = wireMultiSelectDropdown(
    'mmProjectAll', '.mm-project-checkbox', 'mmProjectLabel',
    globalI18n.calendar_all_projects || 'All projects',
    globalI18n.calendar_project_singular || 'project', globalI18n.calendar_project_plural || 'projects'
  );
  const ownerGroup = wireMultiSelectDropdown(
    'mmOwnerAll', '.mm-owner-checkbox', 'mmOwnerLabel',
    globalI18n.board_all_team_members || 'All team members',
    globalI18n.board_member_singular || 'member', globalI18n.board_member_plural || 'members'
  );
  const statusGroup = wireMultiSelectDropdown(
    'mmStatusAll', '.mm-status-checkbox', 'mmStatusLabel',
    globalI18n.board_all_statuses || 'All statuses',
    globalI18n.board_status_singular || 'status', globalI18n.board_status_plural || 'statuses'
  );

  const NEUTRAL_COLOR = '#adb5bd'; // "No release" / "No project" bucket color
  const RELEASE_COLOR = '#6f42c1';
  const PERSON_COLOR = '#fd7e14';

  // --- Color helpers: pick readable text color per node background, and a
  // slightly-darker shade for the border, so arbitrary project colors (any
  // hex the user picked when creating the project) always stay legible. ---
  function hexToRgb(hex) {
    let h = String(hex || '').replace('#', '').trim();
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    if (!/^[0-9a-fA-F]{6}$/.test(h)) return null;
    return { r: parseInt(h.slice(0, 2), 16), g: parseInt(h.slice(2, 4), 16), b: parseInt(h.slice(4, 6), 16) };
  }
  function contrastTextColor(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return '#ffffff';
    // Perceived brightness (ITU-R BT.601) — light backgrounds get dark text,
    // dark backgrounds get white text, so labels stay readable on any
    // project color the user picked.
    const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000 / 255;
    return brightness > 0.6 ? '#212529' : '#ffffff';
  }
  function darken(hex, amount) {
    const rgb = hexToRgb(hex);
    if (!rgb) return hex;
    const d = (v) => Math.max(0, Math.round(v * (1 - amount)));
    const toHex = (v) => v.toString(16).padStart(2, '0');
    return '#' + toHex(d(rgb.r)) + toHex(d(rgb.g)) + toHex(d(rgb.b));
  }
  function coloredNode(bg) {
    return { background: bg, border: darken(bg, 0.25) };
  }

  let network = null;
  let lastData = null;
  const emptyEl = document.getElementById('mindmapEmpty');

  function buildNodesAndEdges(data) {
    const nodes = [];
    const edges = [];

    data.releases.forEach((r) => {
      nodes.push({
        id: 'r-' + r.id, label: r.name, level: 0, shape: 'box',
        color: coloredNode(RELEASE_COLOR), font: { color: contrastTextColor(RELEASE_COLOR) },
      });
    });
    if (data.has_no_release_bucket) {
      nodes.push({
        id: 'r-none', label: i18n.noRelease || 'No release', level: 0, shape: 'box',
        color: coloredNode(NEUTRAL_COLOR), font: { color: contrastTextColor(NEUTRAL_COLOR) },
      });
    }

    data.projects.forEach((p) => {
      const bg = p.color || '#4361ee';
      nodes.push({
        id: 'p-' + p.id, label: p.name, level: 1, shape: 'box',
        color: coloredNode(bg), font: { color: contrastTextColor(bg) },
      });
      edges.push({ from: p.release_id ? 'r-' + p.release_id : 'r-none', to: 'p-' + p.id });
    });
    if (data.has_no_project_bucket) {
      nodes.push({
        id: 'p-none', label: i18n.noProject || 'No project', level: 1, shape: 'box',
        color: coloredNode(NEUTRAL_COLOR), font: { color: contrastTextColor(NEUTRAL_COLOR) },
      });
    }

    data.tasks.forEach((t) => {
      // Tasks take on their project's color so the map visually groups tasks
      // by project at a glance; unassigned-to-a-project tasks fall back to
      // the same neutral gray as the "No project" bucket.
      const bg = t.project_id ? (t.project_color || '#4361ee') : NEUTRAL_COLOR;
      nodes.push({
        id: 't-' + t.id,
        label: (t.is_issue ? '⚠ ' : '') + t.title,
        title: t.is_issue ? (i18n.issueTooltip || 'Issue') : undefined,
        level: 2, shape: 'ellipse',
        color: coloredNode(bg), font: { color: contrastTextColor(bg) },
      });
      edges.push({ from: t.project_id ? 'p-' + t.project_id : 'p-none', to: 't-' + t.id });
    });

    data.people.forEach((person) => {
      nodes.push({
        id: 'u-' + person.id, label: person.name, level: 3, shape: 'dot', size: 14,
        color: coloredNode(PERSON_COLOR),
        // Person labels float outside the small dot shape onto the page's
        // own background, which changes with the color theme — a plain dark
        // font is unreadable in dark mode. Give the label its own light
        // pill background so it stays legible regardless of theme.
        font: { color: '#212529', background: '#ffffff', strokeWidth: 0, size: 14 },
      });
    });
    data.tasks.forEach((t) => {
      edges.push({ from: 't-' + t.id, to: 'u-' + t.assignee_id });
    });

    return { nodes: nodes, edges: edges };
  }

  const NETWORK_OPTIONS = {
    physics: false,
    interaction: { dragNodes: true, zoomView: true, dragView: true, hover: true },
    edges: { arrows: { to: { enabled: true, scaleFactor: 0.5 } }, color: '#adb5bd', smooth: { type: 'cubicBezier' } },
    nodes: { margin: 10, widthConstraint: { maximum: 160 } },
  };
  const HIERARCHICAL_LAYOUT = {
    hierarchical: { direction: 'UD', sortMethod: 'directed', levelSeparation: 140, nodeSpacing: 160 },
  };

  function attachInteractions(net) {
    net.on('doubleClick', function (params) {
      if (params.nodes.length === 1 && String(params.nodes[0]).indexOf('t-') === 0) {
        const id = parseInt(String(params.nodes[0]).slice(2), 10);
        if (window.afActivities) window.afActivities.openEdit(id);
      }
    });
  }

  function buildGraph(data) {
    lastData = data;
    const built = buildNodesAndEdges(data);
    const nodes = built.nodes;
    const edges = built.edges;

    if (emptyEl) emptyEl.classList.toggle('d-none', nodes.length > 0);
    container.classList.toggle('d-none', nodes.length === 0);

    if (network) { network.destroy(); network = null; }
    if (!nodes.length) return;

    // vis-network's hierarchical layout keeps re-snapping a dragged node
    // back onto its level's row on the hierarchy axis (here, vertically), so
    // free dragging in both directions doesn't work while it stays active.
    // Work around it in two passes: first compute a clean hierarchical
    // arrangement to get good starting coordinates, then rebuild the graph
    // with hierarchical layout turned off (keeping those coordinates as the
    // initial positions) so nodes can be dragged freely on both axes.
    const layoutNodes = new vis.DataSet(nodes);
    const layoutNetwork = new vis.Network(
      container,
      { nodes: layoutNodes, edges: new vis.DataSet(edges) },
      Object.assign({}, NETWORK_OPTIONS, { layout: HIERARCHICAL_LAYOUT })
    );
    const positions = layoutNetwork.getPositions ? layoutNetwork.getPositions() : {};
    layoutNetwork.destroy();

    const positionedNodes = nodes.map(function (n) {
      const pos = positions[n.id];
      return pos ? Object.assign({}, n, { x: pos.x, y: pos.y }) : n;
    });

    network = new vis.Network(
      container,
      { nodes: new vis.DataSet(positionedNodes), edges: new vis.DataSet(edges) },
      Object.assign({}, NETWORK_OPTIONS, { layout: { hierarchical: false } })
    );
    attachInteractions(network);
  }

  function downloadImage(format) {
    if (!network) return;
    const srcCanvas = network.canvas.frame.canvas;
    let dataUrl;
    if (format === 'jpeg') {
      // JPEG has no alpha channel — flatten onto a white background first, or
      // the transparent vis-network canvas would render solid black.
      const flat = document.createElement('canvas');
      flat.width = srcCanvas.width;
      flat.height = srcCanvas.height;
      const ctx = flat.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, flat.width, flat.height);
      ctx.drawImage(srcCanvas, 0, 0);
      dataUrl = flat.toDataURL('image/jpeg', 0.92);
    } else {
      dataUrl = srcCanvas.toDataURL('image/png');
    }
    const a = document.createElement('a');
    a.href = dataUrl;
    a.download = 'mindmap.' + (format === 'jpeg' ? 'jpg' : 'png');
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  function runQuery() {
    const params = new URLSearchParams({ action: 'data' });
    releaseGroup.selectedValues().forEach((id) => params.append('release_id[]', id));
    projectGroup.selectedValues().forEach((id) => params.append('project_id[]', id));
    ownerGroup.selectedValues().forEach((id) => params.append('assignee_id[]', id));
    statusGroup.selectedValues().forEach((slug) => params.append('status[]', slug));
    window.afLoadingShow && window.afLoadingShow();
    fetch(window.AF_BASE_URL + 'api/mindmap.php?' + params.toString(), { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => { if (res.ok) buildGraph(res); else window.afToast && window.afToast('Unable to load mind map.', 'danger'); })
      .catch(() => { window.afToast && window.afToast('Unable to load mind map.', 'danger'); })
      .finally(() => window.afLoadingHide && window.afLoadingHide());
  }

  const formEl = document.getElementById('mindmapFilterForm');
  if (formEl) {
    formEl.addEventListener('submit', function (e) { e.preventDefault(); runQuery(); });
  }
  const resetBtn = document.getElementById('mmResetLayout');
  if (resetBtn) resetBtn.addEventListener('click', function () { if (lastData) buildGraph(lastData); });
  const pngBtn = document.getElementById('mmDownloadPng');
  if (pngBtn) pngBtn.addEventListener('click', function () { downloadImage('png'); });
  const jpgBtn = document.getElementById('mmDownloadJpg');
  if (jpgBtn) jpgBtn.addEventListener('click', function () { downloadImage('jpeg'); });

  // Refresh the graph after a task is edited/saved from the shared activity
  // modal (opened via double-click on a task node).
  window.afOnActivityCreated = function () { runQuery(); };

  runQuery();
})();
