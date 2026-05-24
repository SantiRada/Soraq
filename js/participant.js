/* participant.js – Card sorting + Tree Testing participant experience.
   Reads from window.STUDY_DATA (injected by participate.php).
   Submits via API.post('/api/responses.php').
*/
'use strict';

const CAT_COLORS = ['#6DDEC5','#4AC882','#5B9EE0','#C06BE0','#E0B84A','#E05757','#4AD4C8','#E07A5B'];
const DEFAULT_GROUP_NAME = 'Clic para renombrar';

// ── State ─────────────────────────────────────
const study   = window.STUDY_DATA;     // injected by PHP
let   cards   = [];   // { id, text, placed }
let   groups  = [];   // { id, name, cards[], named }
let   dragCard = null;
let   dragSrc  = null;
let   qAnswers = {};
let   startTime = Date.now();
const sessionToken = Math.random().toString(36).slice(2) + Date.now().toString(36);

// ── Tree Testing State ────────────────────────
let ttCurrentTask  = 0;
let ttAnswers      = [];    // [{ taskIdx, question, selectedLabel, selectedPath }]
let ttSelectedNode = null;  // label of currently selected node
let ttSelectedPath = [];    // full path to selected node
let ttCurrentPath  = [];    // path to the folder we're currently INSIDE
let ttCurrentNodes = [];    // nodes rendered at current level
let ttNavStack     = [];    // back-navigation stack: [{nodes, path}]
let ttTreeRoots    = [];

// ── Helpers ───────────────────────────────────
function genId(prefix = 'x') {
  return `${prefix}_${Math.random().toString(36).slice(2, 8)}`;
}

function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function showScreen(id) {
  document.querySelectorAll('.p-screen').forEach(s => s.classList.remove('active'));
  const el = document.getElementById(id);
  if (el) el.classList.add('active');
}

// ── Boot ─────────────────────────────────────
function boot() {
  if (!study || !study.id) {
    return;
  }

  // Route to correct experience
  if (study.type === 'tree-testing') {
    bootTT();
    return;
  }

  // ── Card Sorting boot ──
  let items = [...(study.items || [])];
  if (study.randomize !== false) items = shuffle(items);
  cards = items.map((text, i) => ({ id: `card_${i}`, text, placed: false }));

  // Pre-fill closed/hybrid categories
  if (study.type === 'card-sorting-closed' || study.type === 'card-sorting-hybrid') {
    groups = (study.categories || []).map((cat, i) => ({
      id:    `cat_${i}`,
      name:  cat.name || `Grupo ${i + 1}`,
      cards: [],
      named: true,
    }));
  }

  const btnStart = document.getElementById('btn-start');
  if (btnStart) btnStart.addEventListener('click', onStart);

  bindDropZone();
  showScreen('screen-welcome');
}

// ═══════════════════════════════════════════════
// TREE TESTING EXPERIENCE — drill-down navigation
// ═══════════════════════════════════════════════

function bootTT() {
  ttTreeRoots = buildTreeStructure(study.tree || []);

  document.getElementById('btn-start')?.addEventListener('click', onStartTT);
  document.getElementById('tt-back-btn')?.addEventListener('click', onTTBack);
  document.getElementById('btn-tt-confirm')?.addEventListener('click', onTTConfirm);

  showScreen('screen-welcome');
}

function onStartTT() {
  const qs = (study.questions || []).filter(q => q.text && q.options?.length);
  if (qs.length) {
    renderQuestions(qs);
    showScreen('screen-questions');
    const btnNext = document.getElementById('btn-questions-next');
    if (btnNext && !btnNext._ttHandler) {
      btnNext._ttHandler = onTTQuestionsNext;
      btnNext.addEventListener('click', btnNext._ttHandler);
    }
  } else {
    showTTTask(0);
  }
}

function onTTQuestionsNext() {
  const qs = (study.questions || []).filter(q => q.text && q.options?.length);
  if (!qs.length) { showTTTask(0); return; }

  let allAnswered = true;
  let rejectOpt   = null;
  qs.forEach((q, i) => {
    const sel = document.querySelector(`input[name="q_${i}"]:checked`);
    if (!sel) { allAnswered = false; return; }
    const oi  = parseInt(sel.dataset.oidx);
    const opt = q.options[oi];
    if (opt?.action === 'reject' && !rejectOpt) rejectOpt = opt;
    qAnswers[i] = { question: q.text, answer: opt?.text || '', oi };
  });

  if (!allAnswered) { showToast('Respondé todas las preguntas para continuar', 'error'); return; }
  if (rejectOpt) {
    const rejMsg = document.getElementById('rejected-msg');
    if (rejMsg) rejMsg.textContent = rejectOpt.rejectMsg || 'Gracias por tu tiempo. Este estudio está orientado a un perfil específico de participantes.';
    showScreen('screen-rejected');
  } else {
    showTTTask(0);
  }
}

// Build a nested tree from the flat depth-sorted array
function buildTreeStructure(nodes) {
  const roots = [];
  const stack = [];
  nodes.forEach((node, idx) => {
    const item = { label: node.label, depth: node.depth, idx, children: [] };
    while (stack.length > 0 && stack[stack.length - 1].depth >= node.depth) stack.pop();
    if (!stack.length) roots.push(item);
    else stack[stack.length - 1].children.push(item);
    stack.push(item);
  });
  return roots;
}

// ── Show task (reset state + enter navigating mode) ────────────────

function showTTTask(taskIdx) {
  ttCurrentTask  = taskIdx;
  ttSelectedNode = null;
  ttSelectedPath = [];
  ttCurrentPath  = [];
  ttCurrentNodes = ttTreeRoots;
  ttNavStack     = [];

  const tasks   = study.tasks || [];
  const task    = tasks[taskIdx];

  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  setEl('tt-task-num',   taskIdx + 1);
  setEl('tt-task-total', tasks.length);
  setEl('tt-task-question', task?.question || '');

  // Progress: portion of completed tasks (not counting current)
  const fillEl = document.getElementById('tt-progress-fill');
  if (fillEl) fillEl.style.width = Math.round((taskIdx / tasks.length) * 100) + '%';

  enterNavigatingState();
  showScreen('screen-tt-task');
}

// ── Navigating state ───────────────────────────────────────────────

function enterNavigatingState() {
  setDisplay('tt-nav-header',     '');
  setDisplay('tt-navigating-body','');
  setDisplay('tt-task-confirmed', 'none');

  renderTTLevel();
  renderTTBreadcrumb();
  renderTTBackBtn();
  renderTTConfirmBtn();
  renderTTSelectedDisplay();
}

function setDisplay(id, val) {
  const el = document.getElementById(id);
  if (el) el.style.display = val;
}

// ── Render one level of the tree ───────────────────────────────────

function renderTTLevel() {
  const container = document.getElementById('tt-level-container');
  if (!container) return;
  container.innerHTML = '';

  if (!ttCurrentNodes.length) {
    container.innerHTML = '<div class="p-tt-empty">Esta sección no tiene subsecciones.</div>';
    return;
  }

  ttCurrentNodes.forEach(node => {
    const hasChildren = node.children.length > 0;
    const nodePath    = [...ttCurrentPath, node.label];
    const isSelected  = ttSelectedPath.length > 0 &&
      ttSelectedPath.length === nodePath.length &&
      ttSelectedPath.every((v, i) => v === nodePath[i]);

    const item = document.createElement('div');
    item.className = 'p-tt-node-item' +
      (hasChildren ? ' p-tt-node-item--folder' : ' p-tt-node-item--leaf') +
      (isSelected  ? ' p-tt-node-item--selected' : '');
    item.setAttribute('role', 'button');
    item.setAttribute('tabindex', '0');
    item.setAttribute('aria-label', node.label + (hasChildren ? ' (abre subsección)' : ''));

    const folderSvg = `<svg width="18" height="18" viewBox="0 0 20 18" fill="none" aria-hidden="true">
      <path d="M1 5a2 2 0 012-2h5l2 2h7a2 2 0 012 2v7a2 2 0 01-2 2H3a2 2 0 01-2-2V5z"
        fill="currentColor" opacity=".15" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>`;
    const leafSvg = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
      <circle cx="7" cy="7" r="3" fill="currentColor"/>
    </svg>`;

    item.innerHTML = `
      <span class="p-tt-node-icon ${hasChildren ? 'p-tt-node-icon--folder' : 'p-tt-node-icon--leaf'}">
        ${hasChildren ? folderSvg : leafSvg}
      </span>
      <span class="p-tt-node-label">${escHtml(node.label)}</span>
      ${hasChildren ? '<span class="p-tt-node-arrow" aria-hidden="true">›</span>' : ''}
    `;

    const onClick = () => onTTNodeClick(node, nodePath, hasChildren);
    item.addEventListener('click', onClick);
    item.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(); }
    });

    container.appendChild(item);
  });

  // Fade-in animation on level change
  container.style.opacity = '0';
  requestAnimationFrame(() => {
    container.style.transition = 'opacity .18s ease';
    container.style.opacity    = '1';
  });
}

// ── Node click: select + optional drill-down ───────────────────────

function onTTNodeClick(node, nodePath, hasChildren) {
  // Select this node as current answer
  ttSelectedNode = node.label;
  ttSelectedPath = nodePath;
  renderTTConfirmBtn();
  renderTTSelectedDisplay();

  if (hasChildren) {
    // Navigate one level deeper
    ttNavStack.push({ nodes: ttCurrentNodes, path: [...ttCurrentPath] });
    ttCurrentPath  = nodePath;
    ttCurrentNodes = node.children;
    renderTTLevel();
    renderTTBreadcrumb();
    renderTTBackBtn();
  } else {
    // Leaf: just re-render to update selection highlight
    renderTTLevel();
  }
}

// ── Back navigation ────────────────────────────────────────────────

function onTTBack() {
  if (!ttNavStack.length) return;
  const prev     = ttNavStack.pop();
  ttCurrentNodes = prev.nodes;
  ttCurrentPath  = prev.path;
  renderTTLevel();
  renderTTBreadcrumb();
  renderTTBackBtn();
  // Keep selection intact — user's choice persists across back navigation
}

// ── UI helpers ─────────────────────────────────────────────────────

function renderTTBreadcrumb() {
  const bc = document.getElementById('tt-breadcrumb');
  if (!bc) return;
  const parts = ['Inicio', ...ttCurrentPath];
  bc.innerHTML = parts.map((p, i) => {
    const active = i === parts.length - 1;
    return `<span class="p-tt-bc-item${active ? ' p-tt-bc-item--active' : ''}">${escHtml(p)}</span>`;
  }).join('<span class="p-tt-bc-sep"> › </span>');
}

function renderTTBackBtn() {
  const btn = document.getElementById('tt-back-btn');
  if (btn) btn.style.visibility = ttNavStack.length > 0 ? 'visible' : 'hidden';
}

function renderTTConfirmBtn() {
  const btn = document.getElementById('btn-tt-confirm');
  if (!btn) return;
  btn.disabled = !ttSelectedNode;
  if (ttSelectedNode) {
    const short = ttSelectedNode.length > 30 ? ttSelectedNode.slice(0, 30) + '…' : ttSelectedNode;
    btn.textContent = `Elegir "${short}" ✓`;
  } else {
    btn.textContent = 'Seleccionar este lugar ✓';
  }
}

function renderTTSelectedDisplay() {
  const display  = document.getElementById('tt-selected-display');
  const pathText = document.getElementById('tt-selected-path-text');
  if (!display) return;
  if (ttSelectedPath.length) {
    display.classList.add('p-tt-selected-display--visible');
    if (pathText) pathText.textContent = ttSelectedPath.join(' › ');
  } else {
    display.classList.remove('p-tt-selected-display--visible');
    if (pathText) pathText.textContent = '';
  }
}

// ── Confirm current selection → save + show task summary ──────────

function onTTConfirm() {
  if (!ttSelectedNode || !ttSelectedPath.length) return;

  const tasks = study.tasks || [];
  ttAnswers.push({
    taskIdx:       ttCurrentTask,
    question:      tasks[ttCurrentTask]?.question || '',
    selectedLabel: ttSelectedNode,
    selectedPath:  [...ttSelectedPath],
  });

  const isLast = ttCurrentTask >= tasks.length - 1;
  showTTConfirmedState(isLast);
}

function showTTConfirmedState(isLast) {
  // Hide navigating UI
  setDisplay('tt-nav-header',     'none');
  setDisplay('tt-navigating-body','none');

  // Build and show confirmed panel
  const confirmed = document.getElementById('tt-task-confirmed');
  if (!confirmed) { isLast ? submitTTResponse() : showTTTask(ttCurrentTask + 1); return; }
  confirmed.style.display = '';

  // Render path as breadcrumb nodes
  const pathEl = document.getElementById('tt-confirmed-path');
  if (pathEl) {
    pathEl.innerHTML = ttSelectedPath.map(p =>
      `<span class="p-tt-confirmed-path-node">${escHtml(p)}</span>`
    ).join('<span class="p-tt-confirmed-path-sep"> › </span>');
  }

  // Update progress to reflect this completed task
  const tasks  = study.tasks || [];
  const fillEl = document.getElementById('tt-progress-fill');
  if (fillEl) fillEl.style.width = Math.round(((ttCurrentTask + 1) / tasks.length) * 100) + '%';

  // Wire "next / finish" button (clone to remove stale listeners)
  const oldBtn = document.getElementById('tt-btn-next-task');
  if (oldBtn) {
    const newBtn = oldBtn.cloneNode(true);
    newBtn.textContent = isLast ? 'Finalizar →' : 'Siguiente tarea →';
    newBtn.addEventListener('click', () => {
      if (isLast) submitTTResponse();
      else        showTTTask(ttCurrentTask + 1);
    });
    oldBtn.parentNode.replaceChild(newBtn, oldBtn);
  }
}

// ── Submit all TT answers ──────────────────────────────────────────

async function submitTTResponse() {
  // Disable next button while saving
  const nextBtn = document.getElementById('tt-btn-next-task');
  if (nextBtn) { nextBtn.disabled = true; nextBtn.textContent = 'Guardando…'; }

  const timeSpent = Math.round((Date.now() - startTime) / 1000);
  const payload = {
    study_id:      study.id,
    session_token: sessionToken,
    time_spent:    timeSpent,
    answers:       { tt_tasks: ttAnswers, screening: qAnswers },
    groups:        [],
  };

  try {
    await API.post('/api/responses.php', payload);
    showScreen('screen-finish');
  } catch (err) {
    showToast(err.message || 'Error al guardar la respuesta. Intentá de nuevo.', 'error');
    if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Finalizar →'; }
  }
}

// ── Drop-zone binding (once) ─────────────────
function bindDropZone() {
  if (study.type === 'card-sorting-closed') return;

  const dropZone  = document.getElementById('drop-zone');
  const groupList = document.getElementById('groups-list');

  function createGroupFromDrag(e) {
    e.preventDefault();
    if (dropZone) dropZone.classList.remove('drag-over');
    if (groupList) groupList.classList.remove('drag-over');
    handleDropToNewGroup();
  }

  // The explicit drop-zone strip
  if (dropZone) {
    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', e => { if (!dropZone.contains(e.relatedTarget)) dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', createGroupFromDrag);
  }

  // The groups list itself — catches drops on empty space between/around groups
  if (groupList) {
    groupList.addEventListener('dragover', e => {
      // Only intercept if not over a child group column
      if (!e.target.closest('.p-group-col')) {
        e.preventDefault();
        groupList.classList.add('drag-over');
      }
    });
    groupList.addEventListener('dragleave', e => {
      if (!groupList.contains(e.relatedTarget)) groupList.classList.remove('drag-over');
    });
    groupList.addEventListener('drop', e => {
      // Only create a new group if not dropped on an existing group column
      if (!e.target.closest('.p-group-col')) {
        createGroupFromDrag(e);
        groupList.classList.remove('drag-over');
      }
    });
  }
}

function onStart() {
  const qs = (study.questions || []).filter(q => q.text && q.options?.length);
  if (qs.length) {
    renderQuestions(qs);
    showScreen('screen-questions');
  } else {
    startSorting();
  }
}

// ── Questions (screener) ─────────────────────
function renderQuestions(qs) {
  const container = document.getElementById('questions-container');
  if (!container) return;
  container.innerHTML = qs.map((q, i) => {
    const opts = (q.options || []).map((opt, oi) => `
      <label class="q-radio-opt">
        <input type="radio" name="q_${i}" value="${oi}" data-qidx="${i}" data-oidx="${oi}">
        <span>${escHtml(opt.text || '(opción sin texto)')}</span>
      </label>`).join('');
    return `
      <div class="question-block">
        <p class="question-text">${i + 1}. ${escHtml(q.text)}</p>
        <div class="q-radio-group">${opts}</div>
      </div>`;
  }).join('');

  const prog = document.getElementById('q-progress');
  if (prog) prog.textContent = `${qs.length} pregunta${qs.length !== 1 ? 's' : ''}`;
}

// Note: btn-questions-next for Card Sorting is wired here;
// Tree Testing overrides this in onStartTT() via _ttHandler.
const btnQNext = document.getElementById('btn-questions-next');
if (btnQNext) {
  btnQNext._csHandler = () => {
    // Ignore if TT has taken over
    if (study?.type === 'tree-testing') return;
    const qs = (study?.questions || []).filter(q => q.text && q.options?.length);
    if (!qs.length) { startSorting(); return; }

    let allAnswered = true;
    let rejectOpt   = null;

    qs.forEach((q, i) => {
      const sel = document.querySelector(`input[name="q_${i}"]:checked`);
      if (!sel) { allAnswered = false; return; }
      const oi  = parseInt(sel.dataset.oidx);
      const opt = q.options[oi];
      if (opt?.action === 'reject' && !rejectOpt) rejectOpt = opt;
      qAnswers[i] = { question: q.text, answer: opt?.text || '', oi };
    });

    if (!allAnswered) {
      showToast('Respondé todas las preguntas para continuar', 'error');
      return;
    }

    if (rejectOpt) {
      const rejMsg = document.getElementById('rejected-msg');
      if (rejMsg) rejMsg.textContent = rejectOpt.rejectMsg || 'Gracias por tu tiempo. Este estudio está orientado a un perfil específico de participantes.';
      showScreen('screen-rejected');
    } else {
      startSorting();
    }
  };
  btnQNext.addEventListener('click', btnQNext._csHandler);
}

// ── Sorting ──────────────────────────────────
function startSorting() {
  renderPool();
  renderGroups();
  updateProgress();
  updateFinishBtn();
  showScreen('screen-sorting');
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

// ── Card pool ────────────────────────────────
function renderPool() {
  const list = document.getElementById('pool-cards');
  if (!list) return;
  list.innerHTML = '';
  const unplaced = cards.filter(c => !c.placed);
  unplaced.forEach(card => list.appendChild(makeCardEl(card)));

  // Pool drop-back target
  list.addEventListener('dragover', e => e.preventDefault());
  list.addEventListener('drop', e => { e.preventDefault(); handleDropToPool(); });
}

function makeCardEl(card) {
  const el = document.createElement('div');
  el.className = 'p-sort-card';
  el.dataset.id = card.id;
  el.textContent = card.text;
  el.draggable = true;

  el.addEventListener('dragstart', e => {
    dragCard = card.id;
    dragSrc  = 'pool';
    el.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  el.addEventListener('dragend', () => el.classList.remove('dragging'));

  // Touch support
  let touchTimeout;
  el.addEventListener('touchstart', e => {
    touchTimeout = setTimeout(() => {
      el.classList.add('touch-dragging');
    }, 200);
  }, { passive: true });
  el.addEventListener('touchend', () => {
    clearTimeout(touchTimeout);
    el.classList.remove('touch-dragging');
  });

  return el;
}

function makePlacedEl(cardId, groupId) {
  const card = cards.find(c => c.id === cardId);
  if (!card) return null;
  const el = document.createElement('div');
  el.className = 'p-placed-card';
  el.dataset.cardId  = cardId;
  el.dataset.groupId = groupId;
  el.draggable = true;
  el.innerHTML = `<span class="p-placed-text">${escHtml(card.text)}</span>
    <button class="p-placed-return" title="Devolver al pool">↩</button>`;

  el.querySelector('.p-placed-return').addEventListener('click', () => returnToPool(cardId, groupId));

  el.addEventListener('dragstart', e => {
    dragCard = cardId;
    dragSrc  = groupId;
    e.dataTransfer.effectAllowed = 'move';
  });
  return el;
}

function returnToPool(cardId, fromGroupId) {
  const group = groups.find(g => g.id === fromGroupId);
  if (group) group.cards = group.cards.filter(c => c !== cardId);
  const card = cards.find(c => c.id === cardId);
  if (card) card.placed = false;
  renderPool();
  refreshGroupEl(fromGroupId);
  updateProgress();
  updateFinishBtn();
}

function handleDropToPool() {
  if (!dragCard || dragSrc === 'pool') return;
  const fromGroup = groups.find(g => g.id === dragSrc);
  if (fromGroup) fromGroup.cards = fromGroup.cards.filter(c => c !== dragCard);
  const card = cards.find(c => c.id === dragCard);
  if (card) card.placed = false;
  dragCard = null;
  dragSrc  = null;
  renderPool();
  renderGroups();
  updateProgress();
  updateFinishBtn();
}

// ── Groups ───────────────────────────────────
function renderGroups() {
  const list   = document.getElementById('groups-list');
  const addBtn = document.getElementById('btn-add-group');
  const dropZone = document.getElementById('drop-zone');
  if (!list) return;

  list.innerHTML = '';
  groups.forEach(g => list.appendChild(makeGroupEl(g)));

  // Show/hide "add group" button and drop zone
  if (addBtn) {
    const isOpen = study.type !== 'card-sorting-closed';
    addBtn.style.display = isOpen ? '' : 'none';
    addBtn.onclick = addNewGroup;
  }
  if (dropZone) {
    dropZone.style.display = study.type === 'card-sorting-closed' ? 'none' : '';
  }
}

function makeGroupEl(group) {
  const colIdx = groups.indexOf(group);
  const color  = CAT_COLORS[colIdx % CAT_COLORS.length];
  const canRename = study.type !== 'card-sorting-closed';
  const canDelete = study.type !== 'card-sorting-closed';

  const wrapper = document.createElement('div');
  wrapper.className = 'p-group-col';
  wrapper.dataset.groupId = group.id;

  wrapper.innerHTML = `
    <div class="p-group-header">
      <span class="p-group-dot" style="background:${color}"></span>
      <input class="p-group-name" value="${escHtml(group.name)}"
        ${canRename ? '' : 'readonly'} placeholder="Nombre del grupo"
        data-default="${!group.named}">
      <span class="p-group-count">${group.cards.length}</span>
      ${canDelete ? `<button class="p-group-delete" title="Eliminar grupo">✕</button>` : ''}
    </div>
    <div class="p-group-zone" id="zone_${group.id}">
      ${group.cards.length === 0 ? `<div class="p-group-hint">Arrastrá tarjetas aquí</div>` : ''}
    </div>`;

  // Name input logic
  const nameInput = wrapper.querySelector('.p-group-name');
  nameInput.addEventListener('focus', () => {
    if (nameInput.dataset.default === 'true') {
      nameInput.value = '';
    }
  });
  nameInput.addEventListener('change', () => {
    const val = nameInput.value.trim();
    if (val) {
      group.name  = val;
      group.named = true;
      nameInput.dataset.default = 'false';
    } else {
      group.name  = DEFAULT_GROUP_NAME;
      group.named = false;
      nameInput.value = DEFAULT_GROUP_NAME;
      nameInput.dataset.default = 'true';
    }
    updateFinishBtn();
  });

  // Delete button
  const delBtn = wrapper.querySelector('.p-group-delete');
  if (delBtn) {
    delBtn.addEventListener('click', () => {
      group.cards.forEach(cid => {
        const c = cards.find(x => x.id === cid);
        if (c) c.placed = false;
      });
      groups = groups.filter(g => g.id !== group.id);
      renderPool();
      renderGroups();
      updateProgress();
      updateFinishBtn();
    });
  }

  // Drop zone in group
  const zone = wrapper.querySelector('.p-group-zone');
  zone.addEventListener('dragover',  e => { e.preventDefault(); wrapper.classList.add('drag-over'); });
  zone.addEventListener('dragleave', e => { if (!wrapper.contains(e.relatedTarget)) wrapper.classList.remove('drag-over'); });
  zone.addEventListener('drop', e => { e.preventDefault(); wrapper.classList.remove('drag-over'); handleDrop(group.id); });
  wrapper.addEventListener('dragover',  e => { e.preventDefault(); wrapper.classList.add('drag-over'); });
  wrapper.addEventListener('dragleave', e => { if (!wrapper.contains(e.relatedTarget)) wrapper.classList.remove('drag-over'); });
  wrapper.addEventListener('drop', e => { e.preventDefault(); wrapper.classList.remove('drag-over'); handleDrop(group.id); });

  // Render placed cards
  const placed = group.cards.map(cid => makePlacedEl(cid, group.id)).filter(Boolean);
  if (placed.length) {
    const hint = zone.querySelector('.p-group-hint');
    if (hint) hint.remove();
    placed.forEach(el => zone.appendChild(el));
  }

  return wrapper;
}

function refreshGroupEl(groupId) {
  const group = groups.find(g => g.id === groupId);
  if (!group) return;
  const old = document.querySelector(`.p-group-col[data-group-id="${groupId}"]`);
  if (!old) return;
  old.replaceWith(makeGroupEl(group));
}

function handleDrop(toGroupId) {
  if (!dragCard) return;
  const target = groups.find(g => g.id === toGroupId);
  if (!target) return;

  // Remove from source group if coming from another group
  if (dragSrc && dragSrc !== 'pool') {
    const src = groups.find(g => g.id === dragSrc);
    if (src) src.cards = src.cards.filter(c => c !== dragCard);
  }

  if (!target.cards.includes(dragCard)) {
    target.cards.push(dragCard);
  }
  const card = cards.find(c => c.id === dragCard);
  if (card) card.placed = true;

  dragCard = null;
  dragSrc  = null;

  renderPool();
  renderGroups();
  updateProgress();
  updateFinishBtn();
}

function handleDropToNewGroup() {
  if (!dragCard || study.type === 'card-sorting-closed') return;

  // Remove from previous group
  if (dragSrc && dragSrc !== 'pool') {
    const src = groups.find(g => g.id === dragSrc);
    if (src) src.cards = src.cards.filter(c => c !== dragCard);
  }

  const newGroup = { id: genId('grp'), name: DEFAULT_GROUP_NAME, cards: [dragCard], named: false };
  groups.push(newGroup);

  const card = cards.find(c => c.id === dragCard);
  if (card) card.placed = true;

  dragCard = null;
  dragSrc  = null;

  renderPool();
  renderGroups();
  updateProgress();
  updateFinishBtn();

  // Focus new group name
  setTimeout(() => {
    const inputs = document.querySelectorAll('.p-group-name');
    const last   = inputs[inputs.length - 1];
    if (last && !last.readOnly) { last.focus(); last.select(); }
  }, 60);
}

function addNewGroup() {
  const newGroup = { id: genId('grp'), name: DEFAULT_GROUP_NAME, cards: [], named: false };
  groups.push(newGroup);
  renderGroups();
  updateFinishBtn();
  setTimeout(() => {
    const inputs = document.querySelectorAll('.p-group-name');
    const last   = inputs[inputs.length - 1];
    if (last && !last.readOnly) { last.focus(); last.select(); }
  }, 50);
}

// ── Progress ─────────────────────────────────
function updateProgress() {
  const placed = cards.filter(c => c.placed).length;
  const total  = cards.length;
  const pct    = total > 0 ? Math.round((placed / total) * 100) : 0;

  const fill = document.getElementById('p-progress-fill');
  const text = document.getElementById('p-progress-text');
  if (fill) fill.style.width = `${pct}%`;
  if (text) text.textContent = `${placed} de ${total} tarjetas colocadas`;
}

// ── Finish button ─────────────────────────────
function getBlockers() {
  const blockers = [];
  const unplaced = cards.filter(c => !c.placed).length;
  if (unplaced > 0) blockers.push(`${unplaced} tarjeta${unplaced !== 1 ? 's' : ''} sin colocar`);
  if (study.type !== 'card-sorting-closed') {
    const unnamed = groups.filter(g => !g.named).length;
    if (unnamed > 0) blockers.push(`${unnamed} grupo${unnamed !== 1 ? 's' : ''} sin nombre`);
    if (groups.length === 0) blockers.push('Creá al menos un grupo');
  }
  return blockers;
}

function updateFinishBtn() {
  const btn     = document.getElementById('btn-finish');
  const tooltip = document.getElementById('finish-tooltip');
  if (!btn) return;

  const blockers  = getBlockers();
  const canFinish = blockers.length === 0;

  btn.disabled = !canFinish;

  if (tooltip) {
    if (canFinish) {
      tooltip.textContent = '';
      tooltip.style.display = 'none';
    } else {
      tooltip.innerHTML = 'Necesitás:<br>• ' + blockers.join('<br>• ');
      btn.onmouseenter = () => { tooltip.style.display = 'block'; };
      btn.onmouseleave = () => { tooltip.style.display = 'none'; };
    }
  }
}

// ── Finish / submit ───────────────────────────
const btnFinish = document.getElementById('btn-finish');
if (btnFinish) {
  btnFinish.addEventListener('click', () => {
    if (getBlockers().length > 0) return;
    submitResponse();
  });
}

async function submitResponse() {
  const btn = document.getElementById('btn-finish');
  if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }

  const timeSpent = Math.round((Date.now() - startTime) / 1000);

  const payload = {
    study_id:      study.id,
    session_token: sessionToken,
    time_spent:    timeSpent,
    answers:       qAnswers,
    groups: groups.map(g => ({
      name:  g.name,
      cards: g.cards.map(cid => cards.find(c => c.id === cid)?.text).filter(Boolean),
    })),
  };

  try {
    await API.post('/api/responses.php', payload);
    showScreen('screen-finish');
  } catch (err) {
    showToast(err.message || 'Error al guardar la respuesta. Intenta de nuevo.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Finalizar'; }
  }
}

// ── Init ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', boot);
