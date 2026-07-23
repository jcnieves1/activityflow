(function () {
  const DAY_MINUTES = 1440;
  const plannedTrack = document.getElementById('tlPlannedTrack');
  const actualTrack = document.getElementById('tlActualTrack');
  const unplannedTrack = document.getElementById('tlUnplannedTrack');
  const ruler = document.getElementById('tlRuler');
  const detailPanel = document.getElementById('tlDetailPanel');
  const scrubber = document.getElementById('tlScrubber');
  const clock = document.getElementById('tlClock');

  let playTimer = null;
  let cursorMinutes = 360; // start the "day" view at 06:00
  let data = null;

  function minutesSinceMidnight(dtStr) {
    if (!dtStr) return null;
    const d = new Date(dtStr.replace(' ', 'T'));
    return d.getHours() * 60 + d.getMinutes();
  }

  function fmtClock(mins) {
    const h = Math.floor(mins / 60) % 24;
    const m = Math.floor(mins % 60);
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
  }

  function buildRuler() {
    ruler.innerHTML = '';
    for (let h = 0; h <= 24; h += 2) {
      const pct = (h * 60 / DAY_MINUTES) * 100;
      const label = document.createElement('span');
      label.textContent = String(h).padStart(2, '0') + ':00';
      label.style.cssText = `position:absolute; left:${pct}%; font-size:.7rem; color:#9ca3af;`;
      ruler.appendChild(label);
    }
  }

  function clearTracks() {
    [plannedTrack, actualTrack, unplannedTrack].forEach((t) => {
      [...t.querySelectorAll('.af-timeline-block, .af-timeline-cursor')].forEach((el) => el.remove());
    });
  }

  function addBlock(track, startMin, endMin, colorClass, bg, text, revealAtMin, detail, shape) {
    const block = document.createElement('div');
    const left = Math.max(0, (startMin / DAY_MINUTES) * 100);
    const width = Math.max(0.6, ((endMin - startMin) / DAY_MINUTES) * 100);
    block.className = 'af-timeline-block';
    block.style.left = left + '%';
    block.style.width = (shape === 'pin' ? 10 : width * (track.clientWidth || 1) / 100 < 6 ? 1.2 : width) + '%';
    block.style.background = bg;
    block.textContent = text;
    block.dataset.revealAt = revealAtMin;
    block.dataset.detail = JSON.stringify(detail);
    block.addEventListener('click', () => showDetail(detail));
    track.appendChild(block);
    return block;
  }

  function showDetail(detail) {
    detailPanel.innerHTML = `
      <h6>${afEscapeHtml(detail.title)}</h6>
      <table class="table table-sm small mb-0">
        ${detail.classification ? `<tr><th>Classification</th><td>${afEscapeHtml(detail.classification)}</td></tr>` : ''}
        ${detail.project ? `<tr><th>Project</th><td>${afEscapeHtml(detail.project)}</td></tr>` : ''}
        ${detail.requester ? `<tr><th>Requester</th><td>${afEscapeHtml(detail.requester)}</td></tr>` : ''}
        ${detail.start ? `<tr><th>Start</th><td>${afEscapeHtml(detail.start)}</td></tr>` : ''}
        ${detail.end ? `<tr><th>End</th><td>${afEscapeHtml(detail.end)}</td></tr>` : ''}
        ${detail.status ? `<tr><th>Status</th><td>${afEscapeHtml(detail.status)}</td></tr>` : ''}
        ${detail.interruption ? `<tr><th>Interrupted</th><td>${afEscapeHtml(detail.interruption)}</td></tr>` : ''}
      </table>`;
  }

  function render() {
    if (!data) return;
    clearTracks();

    const showPlanned = document.getElementById('tlShowPlanned').checked;
    const showUnplanned = document.getElementById('tlShowUnplanned').checked;

    if (showPlanned) {
      data.planned.forEach((a) => {
        const start = minutesSinceMidnight(a.planned_start_at) ?? 480;
        const end = minutesSinceMidnight(a.target_completion_at) ?? start + 60;
        addBlock(plannedTrack, start, end, '', '#4361ee', a.title, 0, {
          title: a.title, classification: 'Planned', project: a.project_name, requester: a.requester_name,
          start: a.planned_start_at, end: a.target_completion_at, status: a.status,
        });
      });
    }

    data.actual.forEach((t) => {
      const start = minutesSinceMidnight(t.started_at);
      const end = t.ended_at ? minutesSinceMidnight(t.ended_at) : start + 15;
      const bg = t.activity_type === 'unplanned' ? '#f4a261' : '#4361ee';
      addBlock(actualTrack, start, end, '', bg, t.title, start, {
        title: t.title, classification: t.activity_type === 'unplanned' ? 'Unplanned' : 'Planned',
        project: t.project_name, start: t.started_at, end: t.ended_at || 'running', status: t.status,
      });
    });

    if (showUnplanned) {
      data.unplanned.forEach((u) => {
        const reqMin = minutesSinceMidnight(u.requested_at);
        const bg = u.priority === 'urgent' ? '#e63946' : '#f4a261';
        addBlock(unplannedTrack, reqMin, reqMin + 10, '', bg, u.title, reqMin, {
          title: u.title, classification: 'Unplanned (requested)', project: u.project_name,
          requester: u.requester_name, start: u.requested_at, status: u.status,
          interruption: u.interruption ? `${u.interruption.interrupted_title || ''} — ${u.interruption.time_lost_minutes || 0} min lost` : null,
        }, 'pin');
      });
    }

    const movedBox = document.getElementById('tlMoved');
    movedBox.innerHTML = data.moved.length
      ? '<strong>Moved to another day:</strong> ' + data.moved.map((m) => afEscapeHtml(m.title)).join(', ')
      : '';

    applyReveal();
  }

  function applyReveal() {
    document.querySelectorAll('#tlActualTrack .af-timeline-block, #tlUnplannedTrack .af-timeline-block').forEach((el) => {
      const revealAt = Number(el.dataset.revealAt || 0);
      el.style.opacity = revealAt <= cursorMinutes ? '1' : '.15';
    });
    [plannedTrack, actualTrack, unplannedTrack].forEach((track) => {
      let cursor = track.querySelector('.af-timeline-cursor');
      if (!cursor) {
        cursor = document.createElement('div');
        cursor.className = 'af-timeline-cursor';
        track.appendChild(cursor);
      }
      cursor.style.left = (cursorMinutes / DAY_MINUTES * 100) + '%';
    });
    clock.textContent = fmtClock(cursorMinutes);
    scrubber.value = cursorMinutes;
  }

  function loadData() {
    const params = new URLSearchParams({
      action: 'day',
      employee_id: document.getElementById('tlEmployee').value,
      date: document.getElementById('tlDate').value,
      project_id: document.getElementById('tlProject').value,
      requester_id: document.getElementById('tlRequester').value,
    });
    afFetch(window.AF_BASE_URL + 'api/timeline.php?' + params.toString())
      .then((res) => { data = res; render(); })
      .catch((err) => afToast(err.message, 'danger'));
  }

  ['tlEmployee', 'tlDate', 'tlProject', 'tlRequester', 'tlShowPlanned', 'tlShowUnplanned'].forEach((id) => {
    document.getElementById(id).addEventListener('change', loadData);
  });

  scrubber.addEventListener('input', () => { cursorMinutes = Number(scrubber.value); applyReveal(); });

  document.getElementById('tlPlay').addEventListener('click', () => {
    if (playTimer) return;
    const speed = Number(document.getElementById('tlSpeed').value);
    playTimer = setInterval(() => {
      cursorMinutes = Math.min(DAY_MINUTES, cursorMinutes + speed);
      applyReveal();
      if (cursorMinutes >= DAY_MINUTES) { clearInterval(playTimer); playTimer = null; }
    }, 200);
  });
  document.getElementById('tlPause').addEventListener('click', () => { clearInterval(playTimer); playTimer = null; });
  document.getElementById('tlRestart').addEventListener('click', () => { cursorMinutes = 360; applyReveal(); });

  buildRuler();
  loadData();
})();
