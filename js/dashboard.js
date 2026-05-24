/* dashboard.js – Studies list, filter, CRUD */
'use strict';

const TYPE_LABELS_ES = {
  'card-sorting-open':   'Card Sorting Abierto',
  'card-sorting-closed': 'Card Sorting Cerrado',
  'card-sorting-hybrid': 'Card Sorting Híbrido',
  'tree-testing':        'Tree Testing',
};
const TYPE_LABEL_KEYS = {
  'card-sorting-open':   'study.type_open',
  'card-sorting-closed': 'study.type_closed',
  'card-sorting-hybrid': 'study.type_hybrid',
  'tree-testing':        'study.type_tree',
};
const STATUS_BADGE = {
  active: 'badge-active',
  draft:  'badge-draft',
  closed: 'badge-closed',
  paused: 'badge-neutral',
};
const STATUS_LABEL_ES = { active:'Activo', draft:'Borrador', closed:'Cerrado', paused:'Pausado' };
const STATUS_LABEL_KEYS = { active:'study.status_active', draft:'study.status_draft', closed:'study.status_closed', paused:'study.status_paused' };

function tStr(key, fallback) {
  return (window.t && window.t(key)) || fallback;
}
function getTypeLabel(type)   { return tStr(TYPE_LABEL_KEYS[type],   TYPE_LABELS_ES[type]   || 'Card Sorting'); }
function getStatusLabel(status) { return tStr(STATUS_LABEL_KEYS[status], STATUS_LABEL_ES[status] || status); }

let allStudies   = [];
let currentFilter = 'all';
let deleteTarget  = null;

const TYPE_ICONS = {
  'card-sorting-open':   '<path d="M3 4a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4zm0 8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4zm8-8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1V4z"/>',
  'card-sorting-closed': '<path d="M3 4a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4zm0 8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4zm8-8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1V4z"/>',
  'card-sorting-hybrid': '<path d="M3 4a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4zm0 8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4zm8-8a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1V4z"/>',
  'tree-testing':        '<path d="M10 2a1 1 0 0 1 1 1v4.5h3.5a1 1 0 0 1 1 1V10H17a1 1 0 1 1 0 2h-1.5v1.5a1 1 0 0 1-1 1H11V17a1 1 0 1 1-2 0v-2.5H5.5a1 1 0 0 1-1-1V12H3a1 1 0 1 1 0-2h1.5V8.5a1 1 0 0 1 1-1H9V3a1 1 0 0 1 1-1z"/>',
};

function typeIcon(type) {
  const path = TYPE_ICONS[type] || TYPE_ICONS['card-sorting-open'];
  return `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">${path}</svg>`;
}

function studyCardHTML(s) {
  const badgeCls  = STATUS_BADGE[s.status] || 'badge-neutral';
  const label     = getStatusLabel(s.status);
  const typeLabel = getTypeLabel(s.type);
  const untitled  = tStr('study.untitled', 'Sin título');
  const respLbl   = tStr('study.responses', 'Respuestas');
  const cardsLbl  = tStr('study.cards', 'Tarjetas');
  return `
    <div class="study-card" data-id="${s.id}" data-status="${s.status}">
      <div class="study-card-header">
        <div class="study-card-type">
          <div class="study-type-icon">${typeIcon(s.type)}</div>
          <span class="study-type-label">${typeLabel}</span>
        </div>
        <div class="study-card-menu">
          <button class="study-card-menu-btn" data-id="${s.id}">···</button>
        </div>
      </div>
      <div class="study-card-title">${escHtml(s.title || untitled)}</div>
      <div class="study-card-meta">${formatDate(s.created_at)}</div>
      <div style="margin-bottom:16px"><span class="badge ${badgeCls}">${label}</span></div>
      <div class="study-card-stats">
        <div class="study-stat">
          <span class="study-stat-num">${s.response_count || 0}</span>
          <span class="study-stat-label">${respLbl}</span>
        </div>
        <div class="study-stat">
          <span class="study-stat-num">${(s.config?.items ?? []).length}</span>
          <span class="study-stat-label">${cardsLbl}</span>
        </div>
      </div>
    </div>`;
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function renderGrid() {
  const grid   = document.getElementById('studies-grid');
  const search = (document.getElementById('search-input')?.value || '').toLowerCase();

  let filtered = allStudies;
  if (currentFilter !== 'all') filtered = filtered.filter(s => s.status === currentFilter);
  if (search) filtered = filtered.filter(s => (s.title||'').toLowerCase().includes(search));

  if (!filtered.length) {
    const isAll  = currentFilter === 'all';
    const eTitle = isAll ? tStr('studies.empty_all',    'Sin estudios aún')                : tStr('studies.empty_filter',  'Sin estudios en esta categoría');
    const eDesc  = isAll ? tStr('studies.empty_all_desc','Creá tu primer estudio de Card Sorting.') : tStr('studies.empty_filter_desc','Cambiá el filtro o creá un nuevo estudio.');
    const eBtn   = tStr('studies.create_btn', 'Crear estudio');
    grid.innerHTML = `<div class="empty-state">
      <div class="empty-icon">${typeIcon('card-sorting-open')}</div>
      <h3>${eTitle}</h3>
      <p>${eDesc}</p>
      ${isAll ? `<a href="${window.APP_URL}/create.php" class="btn btn-primary">${eBtn}</a>` : ''}
    </div>`;
    return;
  }

  grid.innerHTML = filtered.map(studyCardHTML).join('');

  // Card click → results
  grid.querySelectorAll('.study-card').forEach(card => {
    card.addEventListener('click', e => {
      if (e.target.closest('.study-card-menu')) return;
      window.location.href = `${window.APP_URL}/results.php?id=${card.dataset.id}`;
    });
  });

  // Menu buttons
  grid.querySelectorAll('.study-card-menu-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.dropdown-menu').forEach(d => d.remove());
      const id    = btn.dataset.id;
      const study = allStudies.find(s => s.id === id);
      const menu  = document.createElement('div');
      menu.className = 'dropdown-menu';
      menu.innerHTML = `
        <a href="${window.APP_URL}/results.php?id=${id}" class="dropdown-item">${tStr('study.menu_results','Ver resultados')}</a>
        <a href="${window.APP_URL}/participate.php?s=${study.slug}" class="dropdown-item" target="_blank">${tStr('study.menu_preview','Ver como participante ↗')}</a>
        <div class="dropdown-divider"></div>
        ${study.status!=='active' ? `<button class="dropdown-item" data-action="activate" data-id="${id}">${tStr('study.menu_activate','Activar')}</button>` : ''}
        ${study.status!=='paused' ? `<button class="dropdown-item" data-action="pause"    data-id="${id}">${tStr('study.menu_pause','Pausar')}</button>` : ''}
        ${study.status!=='closed' ? `<button class="dropdown-item" data-action="close"    data-id="${id}">${tStr('study.menu_close','Cerrar')}</button>` : ''}
        <div class="dropdown-divider"></div>
        <button class="dropdown-item danger" data-action="delete" data-id="${id}">${tStr('study.menu_delete','Eliminar')}</button>`;
      btn.closest('.study-card-menu').appendChild(menu);

      menu.querySelectorAll('[data-action]').forEach(item => {
        item.addEventListener('click', async ev => {
          ev.stopPropagation();
          const action = item.dataset.action;
          menu.remove();
          if (action === 'delete') {
            deleteTarget = id;
            openModal('confirm-modal');
          } else {
            const statusMap = { activate:'active', pause:'paused', close:'closed' };
            try {
              await API.put(`/api/studies.php?id=${id}`, { status: statusMap[action] });
              await loadStudies();
              showToast(tStr('studies.updated','Estado actualizado'), 'success');
            } catch(err) { showToast(err.message, 'error'); }
          }
        });
      });
    });
  });
}

async function loadStudies() {
  try {
    const data = await API.get('/api/studies.php');
    // Config may be JSON string from server
    allStudies = data.map(s => ({
      ...s,
      config: typeof s.config === 'string' ? JSON.parse(s.config || '{}') : (s.config || {}),
    }));
    renderGrid();
  } catch(err) {
    document.getElementById('studies-grid').innerHTML =
      `<div class="empty-state"><p style="color:#E05757">Error: ${err.message}</p></div>`;
  }
}

// ── Init ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadStudies();

  // Filters
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      renderGrid();
    });
  });

  // Search
  document.getElementById('search-input')?.addEventListener('input', renderGrid);

  // Delete modal
  document.getElementById('confirm-delete-btn')?.addEventListener('click', async () => {
    if (!deleteTarget) return;
    try {
      await API.delete(`/api/studies.php?id=${deleteTarget}`);
      closeModal('confirm-modal');
      deleteTarget = null;
      await loadStudies();
      showToast(tStr('studies.deleted','Estudio eliminado'), 'info');
    } catch(err) { showToast(err.message, 'error'); }
  });

  document.getElementById('confirm-cancel-btn')?.addEventListener('click', () => {
    deleteTarget = null;
    closeModal('confirm-modal');
  });
});
