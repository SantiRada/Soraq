/* create.js – Study creation wizard v2 */
'use strict';

// ─── i18n helper ─────────────────────────────────────────────
function tr(key, fallback) { return (window.t && window.t(key)) || fallback; }

// ─── Edit mode ───────────────────────────────────────────────────────────────
const EDIT_MODE     = !!window.EDIT_STUDY_ID;
const EDIT_STUDY_ID = window.EDIT_STUDY_ID || null;
const EDIT_DATA     = window.EDIT_DATA || null;

// ─── Type flags ───────────────────────────────────────────────
const STUDY_TYPE = window.STUDY_TYPE || 'card-sorting-open';
const isCSOpen   = STUDY_TYPE === 'card-sorting-open';
const isCSClosed = STUDY_TYPE === 'card-sorting-closed';
const isCSHybrid = STUDY_TYPE === 'card-sorting-hybrid';
const isCS       = STUDY_TYPE.startsWith('card-sorting');
const isTT       = STUDY_TYPE === 'tree-testing';

// ─── Type label helper (re-evaluated on each call for lang changes) ──────────
function getTypeLabel(type) {
  const labels = {
    'card-sorting-open':   tr('study.type_open',   'Card Sorting Abierto'),
    'card-sorting-closed': tr('study.type_closed',  'Card Sorting Cerrado'),
    'card-sorting-hybrid': tr('study.type_hybrid',  'Card Sorting Híbrido'),
    'tree-testing':        tr('study.type_tree',    'Tree Testing'),
  };
  return labels[type] || type;
}

// ─── Defaults per type ────────────────────────────────────────
const DEFAULTS = {
  'card-sorting-open': {
    welcomeTitle:  tr('create.def_open_wt',   '¡Bienvenido/a al estudio!'),
    welcomeMsg:    tr('create.def_open_wm',   'En este ejercicio te pedimos que organices un conjunto de tarjetas en grupos que tengan sentido para vos. No hay respuestas correctas ni incorrectas — lo que nos interesa es tu percepción natural.'),
    instructTitle: tr('create.def_instr_t',   '¿Cómo funciona?'),
    instructMsg:   tr('create.def_open_im',   'Vas a ver tarjetas que podés arrastrar y agrupar libremente. Creá los grupos que consideres lógicos, poneles el nombre que quieras y tomá el tiempo que necesites.'),
  },
  'card-sorting-closed': {
    welcomeTitle:  tr('create.def_open_wt',   '¡Bienvenido/a al estudio!'),
    welcomeMsg:    tr('create.def_closed_wm', 'En este ejercicio te pedimos que organices un conjunto de tarjetas en grupos que tengan sentido para vos. No hay respuestas correctas ni incorrectas.'),
    instructTitle: tr('create.def_instr_t',   '¿Cómo funciona?'),
    instructMsg:   tr('create.def_closed_im', 'Vas a ver tarjetas que podés arrastrar y soltar en las categorías que aparecen en pantalla. Colocá cada tarjeta donde creas que mejor encaja.'),
  },
  'card-sorting-hybrid': {
    welcomeTitle:  tr('create.def_open_wt',   '¡Bienvenido/a al estudio!'),
    welcomeMsg:    tr('create.def_hybrid_wm', 'En este ejercicio te pedimos que organices tarjetas en grupos. Hay categorías sugeridas, pero podés modificarlas o crear nuevas si querés.'),
    instructTitle: tr('create.def_instr_t',   '¿Cómo funciona?'),
    instructMsg:   tr('create.def_hybrid_im', 'Vas a ver tarjetas y algunas categorías ya creadas. Podés usarlas como están, editarlas o crear categorías nuevas según lo que te resulte más natural.'),
  },
  'tree-testing': {
    welcomeTitle:  tr('create.def_open_wt',   '¡Bienvenido/a al estudio!'),
    welcomeMsg:    tr('create.def_tt_wm',     'En este ejercicio te pedimos que encuentres elementos dentro de una estructura de navegación. Explorá libremente y seleccioná donde esperarías encontrar lo que se te pide.'),
    instructTitle: tr('create.def_instr_t',   '¿Cómo funciona?'),
    instructMsg:   tr('create.def_tt_im',     'Se te van a mostrar una serie de tareas. Para cada una, explorá el árbol haciendo clic en las secciones hasta llegar al lugar donde creés que está el contenido buscado. No hay respuestas incorrectas.'),
  },
};
const DEF = DEFAULTS[STUDY_TYPE] || DEFAULTS['card-sorting-open'];

// ─── State ───────────────────────────────────────────────────
let _uid = 0;
const uid = () => ++_uid;

const CAT_COLORS = ['#6DDEC5','#5B9EE0','#C06BE0','#E09B5B','#E05B5B','#5BE070','#4AC882'];

const state = {
  // Step 1
  title:        '',
  purpose:      '',
  requirements: '',
  // Step 2 – Card Sorting
  cards:     [],    // { id, name, description }
  randomize: true,
  // Step 2 – Tree Testing
  tree:      [],    // { id, depth, label }
  // Step 3 – CS Closed/Hybrid
  categories: [],   // { id, name }
  // Step 3 – Tree Testing
  tasks:      [],   // { id, question, correctPaths: [] }
  // Step 4 – Flow
  flow: {
    welcome:      { title: DEF.welcomeTitle,  message: DEF.welcomeMsg },
    screening:    { enabled: false, questions: [] },
    rejection:    { title: tr('create.def_reject_t','¡Muchas gracias!'),             message: tr('create.def_reject_m','Lamentablemente tu perfil no coincide con el de los participantes que necesitamos para este estudio.') },
    instructions: { title: DEF.instructTitle, message: DEF.instructMsg },
    post:         { enabled: false, questions: [] },
    thankYou:     { title: tr('create.def_thanks_t','¡Muchas gracias!'),             message: tr('create.def_thanks_m','Tu participación quedó registrada exitosamente. Tus respuestas nos ayudan a mejorar la experiencia del producto.') },
    sorry:        { title: tr('create.def_sorry_t','Este estudio ya no está disponible'), message: tr('create.def_sorry_m','El período de participación de este estudio ha concluido. Gracias por tu interés.') },
  },
};

// ─── Icon SVGs (no emojis) ───────────────────────────────────
const SVG = {
  choice: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>`,
  text:   `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41l-2.34-2.34a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>`,
  rating: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>`,
  faceHappy: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>`,
  faceSad:   `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 3c-2.33 0-4.31 1.46-5.11 3.5h10.22c-.8-2.04-2.78-3.5-5.11-3.5z"/></svg>`,
};

// ─── Utils ───────────────────────────────────────────────────
function esc(s) {
  const d = document.createElement('div');
  d.textContent = String(s ?? '');
  return d.innerHTML;
}
function $(id) { return document.getElementById(id); }

// ─── Markdown helpers ────────────────────────────────────────
function renderMd(raw) {
  if (!raw?.trim()) return `<span style="color:var(--text-3);font-style:italic">${tr('create.no_content','Sin contenido')}</span>`;
  if (typeof marked !== 'undefined') {
    const parse = marked.parse ?? marked;
    return parse(raw, { breaks: true, gfm: true });
  }
  // Fallback: plain text with line breaks
  return esc(raw).replace(/\n/g, '<br>');
}

/**
 * Build the HTML string for a Markdown-enabled textarea.
 */
function buildMdEditor(value = '', extraClasses = '', dataAttrs = '', placeholder = '', rows = 5) {
  const writeLbl   = tr('create.md_write',   'Escribir');
  const previewLbl = tr('create.md_preview', 'Vista previa');
  const hintLbl    = tr('create.md_hint',    '# título &nbsp;**negrita** &nbsp;*cursiva* &nbsp;---');
  const ph = placeholder || tr('create.md_hint', 'Texto en Markdown…');
  return `
    <div class="md-editor">
      <div class="md-editor-tabs">
        <button class="md-tab active" data-mode="write" type="button">${writeLbl}</button>
        <button class="md-tab" data-mode="preview" type="button">${previewLbl}</button>
        <span class="md-tab-spacer"></span>
        <span class="md-syntax-hint">${hintLbl}</span>
      </div>
      <textarea class="form-textarea md-write${extraClasses ? ' ' + extraClasses : ''}"
        ${dataAttrs} rows="${rows}"
        placeholder="${ph}">${value}</textarea>
      <div class="md-preview"></div>
    </div>`;
}

/**
 * Wire all .md-editor widgets inside a container.
 * Safe to call multiple times (skips already-wired editors).
 */
function wireMdEditors(container = document) {
  container.querySelectorAll('.md-editor').forEach(editor => {
    if (editor.dataset.mdWired) return;
    editor.dataset.mdWired = '1';

    const tabs    = editor.querySelectorAll('.md-tab');
    const write   = editor.querySelector('.md-write');
    const preview = editor.querySelector('.md-preview');
    if (!write || !preview) return;

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        if (tab.dataset.mode === 'preview') {
          write.style.display   = 'none';
          preview.classList.add('active');
          preview.innerHTML = renderMd(write.value);
        } else {
          write.style.display = 'block';
          preview.classList.remove('active');
        }
      });
    });
  });
}

// ─── Step navigation ─────────────────────────────────────────
let currentStep = 1;
const TOTAL_STEPS = 5;

function stepApplicable(n) {
  if (n === 3 && isCSOpen) return false; // skip categories for open CS
  return true;
}

function goToStep(n) {
  if (n < 1 || n > TOTAL_STEPS) return;
  if (!stepApplicable(n)) {
    goToStep(n > currentStep ? n + 1 : n - 1);
    return;
  }
  document.querySelectorAll('.wizard-panel').forEach((p, i) =>
    p.classList.toggle('active', i === n - 1));
  document.querySelectorAll('.wizard-step-item').forEach((s, i) => {
    s.classList.remove('active', 'done');
    if (i < n - 1) s.classList.add('done');
    if (i === n - 1) s.classList.add('active');
  });
  currentStep = n;

  const applicable = [1,2,3,4,5].filter(stepApplicable);
  const pos = applicable.indexOf(n) + 1;
  $('step-indicator').textContent = `${tr('create.step_label','Paso')} ${pos} ${tr('create.step_of','de')} ${applicable.length}`;
  $('btn-prev').style.visibility = n === 1 ? 'hidden' : 'visible';
  $('btn-next').classList.toggle('hidden', n === TOTAL_STEPS);
  $('btn-publish').classList.toggle('hidden', n < TOTAL_STEPS);
  if (n === TOTAL_STEPS) buildReview();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Step 2: Cards ───────────────────────────────────────────
function renderCards() {
  const list = $('cards-list');
  if (!list) return;
  list.innerHTML = '';
  const cardNamePh  = tr('create.card_name_ph',  'Nombre de la tarjeta *');
  const cardDescPh  = tr('create.card_desc_ph',  'Descripción opcional (visible al pasar el cursor)');
  const addDescLbl  = tr('create.add_desc',    '+ desc');
  const remDescLbl  = tr('create.remove_desc', '− desc');
  state.cards.forEach((card, i) => {
    const row = document.createElement('div');
    row.className = 'card-row';
    row.innerHTML = `
      <div class="card-row-main">
        <span class="card-drag-handle" title="Mover">⠿</span>
        <input class="card-name-input" placeholder="${cardNamePh}"
          value="${esc(card.name)}" maxlength="200">
        <button class="card-desc-toggle">${card.description ? remDescLbl : addDescLbl}</button>
        <button class="item-delete" title="Eliminar">✕</button>
      </div>
      <div class="card-desc-wrap${card.description ? ' show' : ''}">
        <textarea class="card-desc-input" rows="2"
          placeholder="${cardDescPh}">${esc(card.description)}</textarea>
      </div>`;
    row.querySelector('.card-name-input').oninput = e => { state.cards[i].name = e.target.value; updateCardsCount(); };
    row.querySelector('.card-name-input').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addCard(); } };
    row.querySelector('.card-desc-toggle').onclick = () => {
      const wrap = row.querySelector('.card-desc-wrap');
      const show = wrap.classList.toggle('show');
      row.querySelector('.card-desc-toggle').textContent = show ? remDescLbl : addDescLbl;
      if (show) wrap.querySelector('.card-desc-input').focus();
    };
    row.querySelector('.card-desc-input').oninput = e => { state.cards[i].description = e.target.value; };
    row.querySelector('.item-delete').onclick = () => { state.cards.splice(i, 1); renderCards(); };
    list.appendChild(row);
  });
  updateCardsCount();
}

function addCard(name = '', desc = '') {
  state.cards.push({ id: uid(), name, description: desc });
  renderCards();
  setTimeout(() => {
    const all = document.querySelectorAll('.card-name-input');
    all[all.length - 1]?.focus();
  }, 30);
}

function updateCardsCount() {
  const n = state.cards.filter(c => c.name.trim()).length;
  const el = $('cards-count');
  if (el) {
    const word = n !== 1 ? tr('create.review_cards_pl','tarjetas') : tr('create.review_card','tarjeta');
    el.textContent = `${n} ${word}`;
  }
}

// Tabs (cards)
document.querySelectorAll('.items-tab').forEach(tab => {
  tab.onclick = () => {
    document.querySelectorAll('.items-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.items-tab-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    $(`tab-${tab.dataset.tab}`)?.classList.add('active');
  };
});

$('btn-add-card')?.addEventListener('click', () => addCard());

$('btn-import-bulk')?.addEventListener('click', () => {
  const lines = ($('bulk-input')?.value || '').split('\n').map(l => l.trim()).filter(Boolean);
  lines.forEach(l => state.cards.push({ id: uid(), name: l, description: '' }));
  renderCards();
  if (lines.length) showToast(`${lines.length} ${tr('create.imported_cards','tarjetas importadas')}`, 'success');
});

$('btn-import-csv')?.addEventListener('click', () => {
  const file = $('csv-input')?.files[0];
  if (!file) { showToast(tr('create.select_file','Seleccioná un archivo primero.'), 'error'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    const rows = e.target.result.split('\n').map(l => l.trim()).filter(Boolean);
    rows.forEach(row => {
      const [name, desc] = row.split(',').map(c => c.trim().replace(/^"|"$/g, ''));
      if (name) state.cards.push({ id: uid(), name, description: desc || '' });
    });
    renderCards();
    showToast(`${rows.length} ${tr('create.imported_cards','tarjetas importadas')}`, 'success');
  };
  reader.readAsText(file, 'UTF-8');
});

// Randomize toggle
const randToggle = $('randomize-toggle');
randToggle?.addEventListener('click', () => {
  state.randomize = !state.randomize;
  randToggle.classList.toggle('on', state.randomize);
});

// ─── Step 2: Tree ─────────────────────────────────────────────
function renderTree() {
  const container = $('tree-builder');
  if (!container) return;
  container.innerHTML = '';
  if (!state.tree.length) {
    container.innerHTML = `<p style="font-size:.875rem;color:var(--text-3);padding:12px 0">${tr('create.tree_empty','Aún no hay nodos. Usá el botón de abajo para agregar el primer nodo raíz.')}</p>`;
    return;
  }
  const rootPh = tr('create.tree_root_ph', 'Sección raíz…');
  const subPh  = tr('create.tree_sub_ph',  'Sub-sección…');
  state.tree.forEach((node, i) => {
    const row = document.createElement('div');
    row.className = 'tree-node-row';

    // Indent pips
    const indent = document.createElement('div');
    indent.className = 'tree-node-indent';
    for (let d = 0; d < node.depth; d++) {
      const pip = document.createElement('div');
      pip.className = 'tree-node-indent-pip';
      indent.appendChild(pip);
    }
    row.appendChild(indent);

    // Input
    const input = document.createElement('input');
    input.className = 'tree-node-input';
    input.type = 'text';
    input.value = node.label;
    input.placeholder = node.depth === 0 ? rootPh : subPh;
    input.maxLength = 200;
    input.oninput = () => { state.tree[i].label = input.value; };
    input.onkeydown = e => {
      if (e.key === 'Enter') { e.preventDefault(); addTreeSibling(i); }
      if (e.key === 'Tab' && !e.shiftKey) { e.preventDefault(); indentNode(i, 1); }
      if (e.key === 'Tab' && e.shiftKey)  { e.preventDefault(); indentNode(i, -1); }
    };
    row.appendChild(input);

    // Buttons
    const mkBtn = (label, title, cls, handler) => {
      const b = document.createElement('button');
      b.className = `tree-node-btn${cls ? ' ' + cls : ''}`;
      b.title = title; b.textContent = label;
      b.onclick = handler;
      return b;
    };
    row.appendChild(mkBtn('→', 'Hacer sub-nodo', '', () => indentNode(i, 1)));
    row.appendChild(mkBtn('←', 'Subir nivel', '', () => indentNode(i, -1)));
    row.appendChild(mkBtn('+', 'Agregar hijo', '', () => addTreeChild(i)));
    row.appendChild(mkBtn('✕', 'Eliminar', 'tree-node-btn--danger', () => {
      state.tree.splice(i, 1); renderTree();
    }));
    container.appendChild(row);
  });
}

function focusTreeNode(idx) {
  setTimeout(() => {
    document.querySelectorAll('.tree-node-input')[idx]?.focus();
  }, 30);
}

function addTreeNode(depth, afterIdx) {
  const node = { id: uid(), depth, label: '' };
  if (afterIdx === null || afterIdx === undefined) {
    state.tree.push(node);
  } else {
    state.tree.splice(afterIdx + 1, 0, node);
  }
  renderTree();
  focusTreeNode(afterIdx === null || afterIdx === undefined ? state.tree.length - 1 : afterIdx + 1);
}

function addTreeSibling(i) {
  // Insert after node and its descendants, same depth
  const depth = state.tree[i].depth;
  let ins = i + 1;
  while (ins < state.tree.length && state.tree[ins].depth > depth) ins++;
  addTreeNode(depth, ins - 1);
}

function addTreeChild(parentIdx) {
  const pDepth = state.tree[parentIdx].depth;
  let ins = parentIdx + 1;
  while (ins < state.tree.length && state.tree[ins].depth > pDepth) ins++;
  addTreeNode(pDepth + 1, ins - 1);
}

function indentNode(i, delta) {
  const nd = state.tree[i].depth + delta;
  if (nd < 0) return;
  const prevDepth = i > 0 ? state.tree[i - 1].depth : 0;
  if (delta > 0 && nd > prevDepth + 1) return;
  state.tree[i].depth = nd;
  renderTree();
  focusTreeNode(i);
}

$('btn-add-node')?.addEventListener('click', () => addTreeNode(0));

// Tree import tabs
document.querySelectorAll('.tree-tab').forEach(tab => {
  tab.onclick = () => {
    document.querySelectorAll('.tree-tab').forEach(t => t.classList.remove('active'));
    ['tab-tree-manual','tab-tree-paste','tab-tree-csv'].forEach(id => $(`${id}`)?.classList.remove('active'));
    tab.classList.add('active');
    $(`tab-${tab.dataset.tab}`)?.classList.add('active');
  };
});

// Parse indented text into tree nodes (2 spaces or 1 tab = 1 level)
function parseIndentedTree(text) {
  const lines = text.split('\n');
  const nodes = [];
  lines.forEach(line => {
    if (!line.trim()) return;
    let depth = 0;
    let rest  = line;
    // Count leading tabs
    if (/^\t/.test(line)) {
      const m = line.match(/^(\t+)/);
      depth = m ? m[1].length : 0;
      rest  = line.slice(depth);
    } else {
      // Count leading spaces (every 2 = 1 level)
      const m = line.match(/^( +)/);
      const spaces = m ? m[1].length : 0;
      depth = Math.floor(spaces / 2);
      rest  = line.slice(spaces);
    }
    const label = rest.trim();
    if (label) nodes.push({ id: uid(), depth, label });
  });
  return nodes;
}

$('btn-import-tree-paste')?.addEventListener('click', () => {
  const text = $('tree-paste-input')?.value || '';
  if (!text.trim()) { showToast(tr('create.paste_first','Pegá texto primero.'), 'error'); return; }
  const nodes = parseIndentedTree(text);
  if (!nodes.length) { showToast(tr('create.no_nodes','No se encontraron nodos.'), 'error'); return; }
  state.tree.push(...nodes);
  renderTree();
  if ($('tree-paste-input')) $('tree-paste-input').value = '';
  showToast(`${nodes.length} ${tr('create.imported_nodes','nodos importados')}`, 'success');
  // Switch to manual tab so user can review
  document.querySelectorAll('.tree-tab').forEach(t => t.classList.remove('active'));
  ['tab-tree-manual','tab-tree-paste','tab-tree-csv'].forEach(id => $(`${id}`)?.classList.remove('active'));
  const manualTab = document.querySelector('.tree-tab[data-tab="tree-manual"]');
  if (manualTab) manualTab.classList.add('active');
  $('tab-tree-manual')?.classList.add('active');
});

$('btn-import-tree-csv')?.addEventListener('click', () => {
  const file = $('tree-csv-input')?.files[0];
  if (!file) { showToast(tr('create.select_file','Seleccioná un archivo primero.'), 'error'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    const text = e.target.result;
    // Try 2-column CSV (label, depth) first
    const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
    const nodes = [];
    let isTwoCol = false;
    // Check if first data line has a comma and second field is numeric
    if (lines.length && lines[0].includes(',')) {
      const parts = lines[0].split(',');
      if (parts.length >= 2 && !isNaN(parseInt(parts[parts.length - 1], 10))) isTwoCol = true;
    }
    if (isTwoCol) {
      lines.forEach(line => {
        const parts = line.split(',').map(p => p.trim().replace(/^"|"$/g, ''));
        const label = parts[0];
        const depth = parseInt(parts[1] ?? '0', 10) || 0;
        if (label) nodes.push({ id: uid(), depth, label });
      });
    } else {
      // Fallback: treat as indented single-column
      nodes.push(...parseIndentedTree(text));
    }
    if (!nodes.length) { showToast(tr('create.no_nodes','No se encontraron nodos en el CSV.'), 'error'); return; }
    state.tree.push(...nodes);
    renderTree();
    showToast(`${nodes.length} ${tr('create.imported_nodes','nodos importados')}`, 'success');
    document.querySelectorAll('.tree-tab').forEach(t => t.classList.remove('active'));
    ['tab-tree-manual','tab-tree-paste','tab-tree-csv'].forEach(id => $(`${id}`)?.classList.remove('active'));
    const manualTab = document.querySelector('.tree-tab[data-tab="tree-manual"]');
    if (manualTab) manualTab.classList.add('active');
    $('tab-tree-manual')?.classList.add('active');
  };
  reader.readAsText(file, 'UTF-8');
});

// ─── Step 3: Categories ───────────────────────────────────────
function renderCategories() {
  const list = $('cat-list');
  if (!list) return;
  list.innerHTML = '';
  const catPh = tr('create.cat_name_ph','Nombre de la categoría');
  state.categories.forEach((cat, i) => {
    const row = document.createElement('div');
    row.className = 'cat-row';
    row.innerHTML = `
      <div class="cat-color-dot" style="background:${CAT_COLORS[i % CAT_COLORS.length]}"></div>
      <input class="cat-input" placeholder="${catPh}" value="${esc(cat.name)}" maxlength="200">
      <button class="cat-delete">✕</button>`;
    row.querySelector('.cat-input').oninput = e => { state.categories[i].name = e.target.value; };
    row.querySelector('.cat-input').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addCategory(); } };
    row.querySelector('.cat-delete').onclick = () => { state.categories.splice(i, 1); renderCategories(); };
    list.appendChild(row);
  });
}

function addCategory(name = '') {
  state.categories.push({ id: uid(), name });
  renderCategories();
  setTimeout(() => {
    const all = document.querySelectorAll('.cat-input');
    all[all.length - 1]?.focus();
  }, 30);
}

$('btn-add-cat')?.addEventListener('click', () => addCategory());

// ─── Step 3: Tasks (TT) ───────────────────────────────────────
function cpPathLabel(count) {
  if (!count) return tr('create.cp_mark','+ Marcar camino correcto');
  const word = count !== 1
    ? tr('create.cp_path_many','caminos correctos')
    : tr('create.cp_path_one','camino correcto');
  return `✓ ${count} ${word}`;
}

function renderTasks() {
  const list = $('tasks-list');
  if (!list) return;
  list.innerHTML = '';
  const taskPh = tr('create.task_ph','ej: ¿Dónde encontrarías el historial de tus compras?');
  state.tasks.forEach((task, i) => {
    const row = document.createElement('div');
    row.className = 'task-row';
    const correctCount = (task.correctPaths || []).length;
    row.innerHTML = `
      <div class="task-row-main">
        <div class="task-num">${i + 1}</div>
        <textarea class="task-input" rows="2"
          placeholder="${taskPh}">${esc(task.question)}</textarea>
        <button class="item-delete" title="Eliminar">✕</button>
      </div>
      <div class="task-correct-wrap">
        <button class="task-correct-toggle" type="button" data-tidx="${i}">
          ${cpPathLabel(correctCount)}
        </button>
      </div>`;
    row.querySelector('.task-input').oninput = e => { state.tasks[i].question = e.target.value; };
    row.querySelector('.item-delete').onclick  = () => { state.tasks.splice(i, 1); renderTasks(); };
    row.querySelector('.task-correct-toggle').onclick = () => openCPModal(i);
    list.appendChild(row);
  });
}

// ─── Correct-Path Modal ────────────────────────────────────────
let _cpModal     = null;   // DOM element (created once)
let _cpTaskIdx   = -1;     // which task we're editing
let _cpCurrent   = [];     // working path: [{ label, children }]
let _cpConfirmed = [];     // saved paths: [['A','B','C'], ...]

// Build nested tree from state.tree (flat depth-sorted array)
function cpBuildTree() {
  const roots = [];
  const stack = [];
  state.tree.forEach(node => {
    if (!node.label.trim()) return;
    const item = { label: node.label, depth: node.depth, children: [] };
    while (stack.length && stack[stack.length - 1].depth >= node.depth) stack.pop();
    (stack.length ? stack[stack.length - 1].children : roots).push(item);
    stack.push(item);
  });
  return roots;
}
let _cpRoots = [];

function openCPModal(taskIdx) {
  _cpTaskIdx   = taskIdx;
  _cpRoots     = cpBuildTree();
  _cpCurrent   = [];
  // Clone stored paths (array of arrays)
  const stored = state.tasks[taskIdx]?.correctPaths || [];
  _cpConfirmed = stored.map(p => Array.isArray(p) ? [...p] : [p]);

  // Build modal DOM once
  if (!_cpModal) {
    _cpModal = document.createElement('div');
    _cpModal.className = 'cp-overlay';
    _cpModal.innerHTML = `
      <div class="cp-card" role="dialog" aria-modal="true">
        <div class="cp-header">
          <div>
            <div class="cp-title">${tr('create.cp_title','Camino(s) correcto(s)')}</div>
            <div class="cp-subtitle">${tr('create.cp_subtitle','Marcá los nodos donde el participante debería llegar. No lo verán.')}</div>
          </div>
          <button class="cp-close" id="cp-close" aria-label="${tr('create.cp_cancel','Cerrar')}">✕</button>
        </div>

        <!-- Confirmed chips -->
        <div class="cp-confirmed-wrap" id="cp-confirmed-wrap"></div>

        <!-- Breadcrumb of current working path -->
        <div class="cp-bc-wrap" id="cp-bc-wrap"></div>

        <!-- Drilldown levels -->
        <div class="cp-drilldown" id="cp-drilldown"></div>

        <div class="cp-footer">
          <button class="btn btn-primary btn-sm" id="cp-add-btn" disabled>${tr('create.cp_confirm','Confirmar este camino')}</button>
          <div style="flex:1"></div>
          <button class="btn btn-ghost btn-sm" id="cp-cancel-btn">${tr('create.cp_cancel','Cancelar')}</button>
          <button class="btn btn-primary btn-sm" id="cp-save-btn">${tr('create.cp_save','Guardar')}</button>
        </div>
      </div>`;
    document.body.appendChild(_cpModal);

    _cpModal.addEventListener('click', e => { if (e.target === _cpModal) cpClose(false); });
    _cpModal.querySelector('#cp-close').onclick    = () => cpClose(false);
    _cpModal.querySelector('#cp-cancel-btn').onclick = () => cpClose(false);
    _cpModal.querySelector('#cp-save-btn').onclick  = () => cpClose(true);
    _cpModal.querySelector('#cp-add-btn').onclick   = () => cpConfirmCurrent();
  }

  _cpModal.classList.add('active');
  document.body.style.overflow = 'hidden';
  cpRender();
}

function cpClose(save) {
  if (save && _cpTaskIdx >= 0) {
    state.tasks[_cpTaskIdx].correctPaths = _cpConfirmed.map(p => [...p]);
    // Refresh toggle button label
    const btn = document.querySelector(`.task-correct-toggle[data-tidx="${_cpTaskIdx}"]`);
    if (btn) btn.textContent = cpPathLabel(_cpConfirmed.length);
  }
  _cpModal.classList.remove('active');
  document.body.style.overflow = '';
}

function cpConfirmCurrent() {
  if (!_cpCurrent.length) return;
  const path = _cpCurrent.map(n => n.label);
  const key  = path.join('\0');
  if (!_cpConfirmed.some(p => p.join('\0') === key)) _cpConfirmed.push(path);
  _cpCurrent = [];
  cpRender();
}

function cpRender() {
  cpRenderConfirmed();
  cpRenderBreadcrumb();
  cpRenderDrilldown();
  const addBtn = _cpModal?.querySelector('#cp-add-btn');
  if (addBtn) addBtn.disabled = _cpCurrent.length === 0;
}

function cpRenderConfirmed() {
  const wrap = _cpModal?.querySelector('#cp-confirmed-wrap');
  if (!wrap) return;
  if (!_cpConfirmed.length) {
    wrap.innerHTML = `<span class="cp-confirmed-empty">${tr('create.cp_empty','Sin caminos marcados todavía')}</span>`;
    return;
  }
  wrap.innerHTML = '';
  _cpConfirmed.forEach((path, pi) => {
    const chip = document.createElement('div');
    chip.className = 'cp-chip';
    chip.innerHTML = `<span class="cp-chip-text">${path.map(esc).join(' <span class="cp-chip-sep">›</span> ')}</span>
      <button class="cp-chip-remove" aria-label="Eliminar">✕</button>`;
    chip.querySelector('.cp-chip-remove').onclick = () => { _cpConfirmed.splice(pi, 1); cpRender(); };
    wrap.appendChild(chip);
  });
}

function cpRenderBreadcrumb() {
  const wrap = _cpModal?.querySelector('#cp-bc-wrap');
  if (!wrap) return;
  if (!_cpCurrent.length) {
    wrap.innerHTML = `<span class="cp-bc-hint">${tr('create.cp_select_hint','Seleccioná un nodo del árbol')}</span>`;
    return;
  }
  wrap.innerHTML = _cpCurrent
    .map((n, i) => `<span class="cp-bc-crumb${i === _cpCurrent.length - 1 ? ' cp-bc-last' : ''}">${esc(n.label)}</span>`)
    .join('<span class="cp-bc-sep">›</span>');
}

function cpRenderDrilldown() {
  const dd = _cpModal?.querySelector('#cp-drilldown');
  if (!dd) return;
  dd.innerHTML = '';

  if (!_cpRoots.length) {
    dd.innerHTML = `<p class="cp-empty">${tr('create.cp_no_tree','Primero agregá nodos al árbol (Paso 2).')}</p>`;
    return;
  }

  // Level 0: roots
  cpAddLevel(dd, _cpRoots, 0);

  // For each selected node in the working path, add its children as the next level
  _cpCurrent.forEach((selected, levelIdx) => {
    if (selected.children?.length) {
      cpAddLevel(dd, selected.children, levelIdx + 1);
    }
  });
}

function cpAddLevel(container, nodes, levelIdx) {
  const wrap = document.createElement('div');
  wrap.className = 'cp-level';
  if (levelIdx > 0) wrap.classList.add('cp-level--child');

  // Indent arrow connector
  if (levelIdx > 0) {
    const arrow = document.createElement('div');
    arrow.className = 'cp-level-arrow';
    arrow.innerHTML = '↳';
    wrap.appendChild(arrow);
  }

  const nodeRow = document.createElement('div');
  nodeRow.className = 'cp-node-row';
  nodes.forEach(node => {
    const isSelected = _cpCurrent[levelIdx]?.label === node.label;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cp-node' + (isSelected ? ' cp-node--selected' : '');
    btn.innerHTML = `<span class="cp-node-label">${esc(node.label)}</span>`
                  + (node.children.length ? '<span class="cp-node-chevron">›</span>' : '');
    btn.onclick = () => {
      _cpCurrent = _cpCurrent.slice(0, levelIdx);
      _cpCurrent.push(node);
      cpRender();
    };
    nodeRow.appendChild(btn);
  });

  wrap.appendChild(nodeRow);
  container.appendChild(wrap);
}

function addTask() {
  state.tasks.push({ id: uid(), question: '', correctPaths: [] });
  renderTasks();
  setTimeout(() => {
    const all = document.querySelectorAll('.task-input');
    all[all.length - 1]?.focus();
  }, 30);
}

$('btn-add-task')?.addEventListener('click', addTask);

// ─── Step 4: Flow builder ─────────────────────────────────────
function getFlowSections() {
  return [
    { key: 'welcome',      num: 1,
      name: tr('create.flow_welcome_name','Pantalla de bienvenida'),
      desc: tr('create.flow_welcome_desc','Primera pantalla que ven los participantes'),
      type: 'editable' },
    { key: 'screening',    num: 2,
      name: tr('create.flow_screen_name','Preguntas de validación'),
      desc: tr('create.flow_screen_desc','Filtrá participantes antes del estudio · Opcional'),
      type: 'toggle' },
    { key: 'rejection',    num: 3,
      name: tr('create.flow_reject_name','Mensaje de rechazo'),
      desc: tr('create.flow_reject_desc','Aparece cuando un participante no cumple el perfil'),
      type: 'editable', cond: 'screening' },
    { key: 'instructions', num: 4,
      name: tr('create.flow_instr_name','Instrucciones'),
      desc: tr('create.flow_instr_desc','Explica a los participantes qué deben hacer'),
      type: 'editable' },
    { key: 'study',        num: 5,
      name: isTT ? 'Tree Testing' : `Card Sorting ${isCSOpen ? tr('create.cs_open','Abierto') : isCSClosed ? tr('create.cs_closed','Cerrado') : tr('create.cs_hybrid','Híbrido')}`,
      desc: tr('create.flow_study_desc','El ejercicio (preparado en los pasos anteriores)'),
      type: 'info' },
    { key: 'post',         num: 6,
      name: tr('create.flow_post_name','Preguntas post-estudio'),
      desc: tr('create.flow_post_desc','Preguntas adicionales después del ejercicio · Opcional'),
      type: 'toggle' },
    { key: 'thankYou',     num: 7,
      name: tr('create.flow_thanks_name','Pantalla de gracias'),
      desc: tr('create.flow_thanks_desc','Mensaje final tras completar el estudio'),
      type: 'editable' },
    { key: 'sorry',        num: 8,
      name: tr('create.flow_sorry_name','Estudio no disponible'),
      desc: tr('create.flow_sorry_desc','Aparece cuando el estudio está pausado o cerrado'),
      type: 'editable' },
  ];
}

function buildFlowUI() {
  const container = $('flow-builder');
  if (!container) return;
  container.innerHTML = '';

  const FLOW_SECTIONS = getFlowSections();

  FLOW_SECTIONS.forEach(sec => {
    const item = document.createElement('div');
    item.className = 'flow-item';
    item.dataset.section = sec.key;
    if (sec.cond) {
      item.classList.add(`flow-cond-${sec.cond}`);
      if (!state.flow[sec.cond]?.enabled) item.style.display = 'none';
    }

    const badgeCls = sec.type === 'info'                                  ? 'flow-item-badge--info'
                   : sec.type === 'toggle' && !state.flow[sec.key]?.enabled ? 'flow-item-badge--optional'
                   : '';

    const rightHtml = sec.type === 'info' ? ''
      : sec.type === 'toggle'
        ? `<label class="flow-toggle" onclick="event.stopPropagation()">
             <input type="checkbox" class="flow-chk" data-toggle="${sec.key}"${state.flow[sec.key]?.enabled ? ' checked' : ''}>
             <span class="flow-toggle-track"></span>
           </label>`
        : `<span class="flow-item-chevron">▾</span>`;

    item.innerHTML = `
      <div class="flow-item-header">
        <div class="flow-item-badge ${badgeCls}">${sec.num}</div>
        <div class="flow-item-info">
          <div class="flow-item-name">${sec.name}</div>
          <div class="flow-item-desc">${sec.desc}</div>
        </div>
        ${rightHtml}
      </div>
      <div class="flow-item-body">${buildFlowBody(sec)}</div>`;

    // Editable: click header to toggle body open; wire md editors on first open
    if (sec.type === 'editable') {
      const hdr = item.querySelector('.flow-item-header');
      hdr.style.cursor = 'pointer';
      hdr.onclick = () => {
        item.classList.toggle('open');
        if (item.classList.contains('open')) wireMdEditors(item);
      };
    }

    // Toggle: checkbox controls body + conditionals
    if (sec.type === 'toggle') {
      const chk = item.querySelector('.flow-chk');
      const body = item.querySelector('.flow-item-body');
      body.style.display = state.flow[sec.key]?.enabled ? 'block' : 'none';
      chk.onchange = () => {
        state.flow[sec.key].enabled = chk.checked;
        body.style.display = chk.checked ? 'block' : 'none';
        item.querySelector('.flow-item-badge').className = `flow-item-badge${chk.checked ? '' : ' flow-item-badge--optional'}`;
        document.querySelectorAll(`.flow-cond-${sec.key}`).forEach(el => {
          el.style.display = chk.checked ? '' : 'none';
          if (chk.checked) wireMdEditors(el);
        });
        if (chk.checked) wireMdEditors(item);
      };
    }

    // Wire text fields
    item.querySelectorAll('.flow-field').forEach(f => {
      f.oninput = () => {
        if (!state.flow[sec.key]) state.flow[sec.key] = {};
        state.flow[sec.key][f.dataset.field] = f.value;
      };
    });

    container.appendChild(item);

    // Sub-builders
    if (sec.key === 'screening') wireScreeningBuilder(item.querySelector('.flow-item-body'));
    if (sec.key === 'post')      wirePostBuilder(item.querySelector('.flow-item-body'));
  });
}

function buildFlowBody(sec) {
  if (sec.type === 'info') {
    return `<div style="padding:12px 0;font-size:.875rem;color:var(--text-2)">${tr('create.flow_static_info','Definido en los pasos anteriores. No hay configuración adicional aquí.')}</div>`;
  }
  if (sec.type === 'toggle') {
    if (sec.key === 'screening') return buildScreeningHTML();
    if (sec.key === 'post')      return buildPostHTML();
    return '';
  }
  // Editable
  const d = state.flow[sec.key] || {};
  const titleLbl  = tr('create.flow_field_title','Título');
  const titleHint = tr('create.flow_title_hint','recomendado: menos de 20 caracteres');
  const msgLbl    = tr('create.flow_field_msg','Mensaje');
  return `
    <div class="form-group">
      <label class="form-label">${titleLbl}
        <span class="wiz-hint">${titleHint}</span>
      </label>
      <input type="text" class="form-input flow-field"
        data-section="${sec.key}" data-field="title"
        value="${esc(d.title || '')}" maxlength="80" placeholder="${titleLbl}">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label">${msgLbl}</label>
      ${buildMdEditor(
          esc(d.message || ''),
          'flow-field',
          `data-section="${sec.key}" data-field="message"`,
          tr('create.md_hint','Escribí el mensaje en Markdown…'),
          4
      )}
    </div>`;
}

// ─── Screening questions ──────────────────────────────────────
function buildScreeningHTML() {
  return `
    <p style="font-size:.875rem;color:var(--text-2);margin-bottom:16px">
      ${tr('create.flow_screen_info','Hasta 5 preguntas de opción múltiple (de 2 a 4 opciones cada una). Al menos una opción debe permitir continuar y otra debe rechazar al participante.')}
    </p>
    <div id="screening-list"></div>
    <button class="btn btn-ghost btn-sm" id="btn-add-screening">${tr('create.btn_add_screen','+ Agregar pregunta')}</button>`;
}

function renderScreening() {
  const list = $('screening-list');
  if (!list) return;
  list.innerHTML = '';
  const noQs       = tr('create.no_questions','Sin preguntas todavía.');
  const qNumLabel  = tr('create.question_num','Pregunta');
  const delQLabel  = tr('create.btn_del_q','Eliminar pregunta');
  const addOptLbl  = tr('create.btn_add_opt','+ Agregar opción');
  const contLbl    = tr('create.opt_continues','✓ Continúa');
  const rejLbl     = tr('create.opt_rejects','✕ Rechaza');
  const scrQPh     = tr('create.scr_q_ph','Escribí tu pregunta de validación…');
  if (!state.flow.screening.questions.length) {
    list.innerHTML = `<p style="font-size:.8125rem;color:var(--text-3);margin-bottom:12px">${noQs}</p>`;
  }
  state.flow.screening.questions.forEach((q, qi) => {
    const card = document.createElement('div');
    card.className = 'screening-q-card';
    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="font-size:.8125rem;font-weight:600;color:var(--text-3)">${qNumLabel} ${qi + 1}</span>
        <div style="flex:1"></div>
        <button class="btn btn-ghost btn-sm scr-del">${delQLabel}</button>
      </div>
      <div class="form-group">
        <input type="text" class="form-input scr-q-text"
          placeholder="${scrQPh}"
          value="${esc(q.text)}" maxlength="300">
      </div>
      <div class="scr-opts" id="scr-opts-${qi}"></div>
      <button class="btn btn-ghost btn-sm scr-add-opt" style="font-size:.8125rem;margin-top:6px"
        ${q.options.length >= 4 ? 'disabled' : ''}>${addOptLbl}</button>`;

    card.querySelector('.scr-q-text').oninput = e => { state.flow.screening.questions[qi].text = e.target.value; };
    card.querySelector('.scr-del').onclick = () => {
      state.flow.screening.questions.splice(qi, 1); renderScreening();
    };
    card.querySelector('.scr-add-opt').onclick = () => {
      if (q.options.length < 4) {
        state.flow.screening.questions[qi].options.push({ text: '', allows: true });
        renderScreening();
      }
    };
    list.appendChild(card);

    // Options
    const optsList = $(`scr-opts-${qi}`);
    q.options.forEach((opt, oi) => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px';
      row.innerHTML = `
        <input type="text" class="form-input" style="flex:1"
          placeholder="${tr('create.question_num','Opción')} ${oi + 1}…" value="${esc(opt.text)}" maxlength="200">
        <select class="form-select" style="width:130px;font-size:.8125rem">
          <option value="1"${opt.allows  ? ' selected' : ''}>${contLbl}</option>
          <option value="0"${!opt.allows ? ' selected' : ''}>${rejLbl}</option>
        </select>
        <button class="item-delete" ${q.options.length <= 2 ? 'disabled style="opacity:.4"' : ''}>✕</button>`;
      row.querySelector('input').oninput  = e => { state.flow.screening.questions[qi].options[oi].text = e.target.value; };
      row.querySelector('select').onchange = e => { state.flow.screening.questions[qi].options[oi].allows = e.target.value === '1'; };
      row.querySelector('.item-delete').onclick = () => {
        if (q.options.length > 2) { state.flow.screening.questions[qi].options.splice(oi, 1); renderScreening(); }
      };
      optsList.appendChild(row);
    });
  });
}

function wireScreeningBuilder(body) {
  $('btn-add-screening')?.addEventListener('click', () => {
    if (state.flow.screening.questions.length >= 5) {
      showToast(tr('create.max_screening','Máximo 5 preguntas de validación.'), 'info'); return;
    }
    state.flow.screening.questions.push({ text: '', options: [{ text: '', allows: true }, { text: '', allows: false }] });
    renderScreening();
  });
  renderScreening();
}

// ─── Post-study questions ─────────────────────────────────────
const RATING_STYLES = [
  { key: 'faces_happy', icon: () => SVG.faceHappy, label: tr('create.rating_faces_happy','Caritas (neutro → feliz)') },
  { key: 'faces_angry', icon: () => SVG.faceSad,   label: tr('create.rating_faces_angry','Caritas (enojado → feliz)') },
  { key: 'numbers',     icon: () => `<span style="font-size:.875rem;font-weight:700;letter-spacing:-.03em">1–5</span>`, label: tr('create.rating_numbers','Números 1 a 5') },
  { key: 'stars',       icon: () => `<span style="font-size:.875rem;letter-spacing:.05em;color:var(--accent)">★★★★★</span>`, label: tr('create.rating_stars','Estrellas 1 a 5') },
];

function buildPostHTML() {
  return `
    <p style="font-size:.875rem;color:var(--text-2);margin-bottom:16px">
      ${tr('create.flow_post_info','Hasta 5 preguntas opcionales que aparecen después de completar el estudio. Disponibles: opción múltiple, texto libre y valoración.')}
    </p>
    <div id="post-list"></div>
    <button class="btn btn-ghost btn-sm" id="btn-add-post">${tr('create.btn_add_post','+ Agregar pregunta')}</button>`;
}

function renderPostQuestions() {
  const list = $('post-list');
  if (!list) return;
  list.innerHTML = '';
  const noQs      = tr('create.no_questions','Sin preguntas todavía.');
  const qNumLabel = tr('create.question_num','Pregunta');
  const delLabel  = tr('create.btn_del','Eliminar');
  const choiceLbl = tr('create.post_type_choice','Opción múltiple');
  const textLbl   = tr('create.post_type_text','Texto libre');
  const ratingLbl = tr('create.post_type_rating','Valoración');
  if (!state.flow.post.questions.length) {
    list.innerHTML = `<p style="font-size:.8125rem;color:var(--text-3);margin-bottom:12px">${noQs}</p>`;
  }
  state.flow.post.questions.forEach((q, qi) => {
    const isChoice = q.type === 'choice';
    const isText   = q.type === 'text';
    const isRating = q.type === 'rating';
    const card = document.createElement('div');
    card.className = 'post-q-card';
    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="font-size:.8125rem;font-weight:600;color:var(--text-3)">${qNumLabel} ${qi + 1}</span>
        <div style="flex:1"></div>
        <button class="btn btn-ghost btn-sm post-del">${delLabel}</button>
      </div>
      <div class="post-q-type-tabs">
        <button class="post-q-type-btn${isChoice ? ' active' : ''}" data-ptype="choice">${SVG.choice} ${choiceLbl}</button>
        <button class="post-q-type-btn${isText   ? ' active' : ''}" data-ptype="text">${SVG.text} ${textLbl}</button>
        <button class="post-q-type-btn${isRating ? ' active' : ''}" data-ptype="rating">${SVG.rating} ${ratingLbl}</button>
      </div>
      <div class="form-group">
        <input type="text" class="form-input post-q-text"
          placeholder="${tr('create.scr_q_ph','Escribí tu pregunta…')}" value="${esc(q.text)}" maxlength="300">
      </div>
      <div class="post-q-extra" id="post-extra-${qi}">
        ${buildPostExtra(q, qi)}
      </div>`;

    card.querySelector('.post-del').onclick = () => { state.flow.post.questions.splice(qi, 1); renderPostQuestions(); };
    card.querySelector('.post-q-text').oninput = e => { state.flow.post.questions[qi].text = e.target.value; };
    card.querySelectorAll('.post-q-type-btn').forEach(btn => {
      btn.onclick = () => {
        const newType = btn.dataset.ptype;
        state.flow.post.questions[qi].type = newType;
        if (newType === 'choice' && !(state.flow.post.questions[qi].options?.length)) {
          state.flow.post.questions[qi].options = [{ text: '' }, { text: '' }];
        }
        if (newType === 'rating' && !state.flow.post.questions[qi].ratingStyle) {
          state.flow.post.questions[qi].ratingStyle = 'stars';
        }
        renderPostQuestions();
      };
    });
    list.appendChild(card);
    wirePostExtra(qi, q);
  });
}

function buildPostExtra(q, qi) {
  if (q.type === 'choice') {
    const opts = q.options || [{ text: '' }, { text: '' }];
    const addOptLbl = tr('create.btn_add_opt','+ Agregar opción');
    const allowMultiLbl = tr('create.allow_multiple','Permitir selección múltiple');
    const optsHtml = opts.map((opt, oi) => `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <input type="text" class="form-input post-opt-inp" data-oi="${oi}"
          placeholder="${tr('create.question_num','Opción')} ${oi + 1}…" value="${esc(opt.text)}" maxlength="200">
        <button class="item-delete post-opt-del" data-oi="${oi}"
          ${opts.length <= 2 ? 'disabled style="opacity:.4"' : ''}>✕</button>
      </div>`).join('');
    return `
      <div style="margin-bottom:12px">
        <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;color:var(--text-2);cursor:pointer">
          <input type="checkbox" class="post-multi-chk"${q.isMultiple ? ' checked' : ''}>
          ${allowMultiLbl}
        </label>
      </div>
      ${optsHtml}
      <button class="btn btn-ghost btn-sm post-add-opt"
        ${opts.length >= 4 ? 'disabled' : ''}>${addOptLbl}</button>`;
  }
  if (q.type === 'text') {
    return `<p style="font-size:.8125rem;color:var(--text-3)">${tr('create.post_text_info','El participante responderá escribiendo libremente. No hay configuración adicional.')}</p>`;
  }
  if (q.type === 'rating') {
    const cur = q.ratingStyle || 'stars';
    const ratingStyleLabel = tr('create.rating_style_label','Estilo de valoración:');
    return `
      <p style="font-size:.875rem;color:var(--text-1);margin-bottom:10px">${ratingStyleLabel}</p>
      <div class="rating-styles">
        ${RATING_STYLES.map(rs => `
          <div class="rating-style-opt${cur === rs.key ? ' selected' : ''}" data-rkey="${rs.key}">
            <span class="rating-style-icon">${rs.icon()}</span>
            <span class="rating-style-label">${rs.label}</span>
          </div>`).join('')}
      </div>`;
  }
  return '';
}

function wirePostExtra(qi, q) {
  const extra = $(`post-extra-${qi}`);
  if (!extra) return;
  extra.querySelector('.post-multi-chk')?.addEventListener('change', e => {
    state.flow.post.questions[qi].isMultiple = e.target.checked;
  });
  extra.querySelectorAll('.post-opt-inp').forEach(inp => {
    inp.oninput = () => {
      if (!state.flow.post.questions[qi].options) state.flow.post.questions[qi].options = [];
      state.flow.post.questions[qi].options[+inp.dataset.oi].text = inp.value;
    };
  });
  extra.querySelectorAll('.post-opt-del').forEach(btn => {
    btn.onclick = () => {
      const oi = +btn.dataset.oi;
      if ((state.flow.post.questions[qi].options?.length || 0) > 2) {
        state.flow.post.questions[qi].options.splice(oi, 1);
        renderPostQuestions();
      }
    };
  });
  extra.querySelector('.post-add-opt')?.addEventListener('click', () => {
    if (!state.flow.post.questions[qi].options) state.flow.post.questions[qi].options = [];
    if (state.flow.post.questions[qi].options.length < 4) {
      state.flow.post.questions[qi].options.push({ text: '' });
      renderPostQuestions();
    }
  });
  extra.querySelectorAll('.rating-style-opt').forEach(opt => {
    opt.onclick = () => {
      state.flow.post.questions[qi].ratingStyle = opt.dataset.rkey;
      extra.querySelectorAll('.rating-style-opt').forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
    };
  });
}

function wirePostBuilder(body) {
  $('btn-add-post')?.addEventListener('click', () => {
    if (state.flow.post.questions.length >= 5) {
      showToast(tr('create.max_post','Máximo 5 preguntas post-estudio.'), 'info'); return;
    }
    state.flow.post.questions.push({ type: 'choice', text: '', options: [{ text: '' }, { text: '' }], isMultiple: false, ratingStyle: 'stars' });
    renderPostQuestions();
  });
  renderPostQuestions();
}

// ─── Step 5: Review ───────────────────────────────────────────
function buildReview() {
  const rows = [
    [tr('create.review_type','Tipo'),   getTypeLabel(STUDY_TYPE)],
    [tr('create.review_name','Nombre'), state.title || tr('create.review_no_name','(sin nombre)')],
  ];
  if (isCS) {
    const n    = state.cards.filter(c => c.name.trim()).length;
    const cWord = n !== 1 ? tr('create.review_cards_pl','tarjetas') : tr('create.review_card','tarjeta');
    const randNote = state.randomize ? ` · ${tr('create.review_random','orden aleatorio')}` : '';
    rows.push([tr('create.review_cards','Tarjetas'), `${n} ${cWord}${randNote}`]);
    if (!isCSOpen) {
      const c     = state.categories.filter(c => c.name.trim()).length;
      const catW  = c !== 1 ? tr('create.review_cats_pl','categorías') : tr('create.review_cat','categoría');
      rows.push([tr('create.review_cats','Categorías'), `${c} ${catW}`]);
    }
  } else {
    const n    = state.tree.filter(n => n.label.trim()).length;
    const nW   = n !== 1 ? tr('create.review_nodes_pl','nodos') : tr('create.review_node','nodo');
    rows.push([tr('create.review_nodes','Nodos del árbol'), `${n} ${nW}`]);
    const t    = state.tasks.filter(t => t.question.trim()).length;
    const tW   = t !== 1 ? tr('create.review_tasks_pl','tareas') : tr('create.review_task','tarea');
    rows.push([tr('create.review_tasks','Tareas'), `${t} ${tW}`]);
  }
  if (state.flow.screening.enabled) {
    const n  = state.flow.screening.questions.filter(q => q.text.trim()).length;
    const qW = n !== 1 ? tr('create.review_questions_pl','preguntas') : tr('create.review_question','pregunta');
    rows.push([tr('create.review_screening','Validación'), `${n} ${qW}`]);
  }
  if (state.flow.post.enabled) {
    const n  = state.flow.post.questions.filter(q => q.text.trim()).length;
    const qW = n !== 1 ? tr('create.review_questions_pl','preguntas') : tr('create.review_question','pregunta');
    rows.push([tr('create.review_post','Post-estudio'), `${n} ${qW}`]);
  }
  $('review-grid').innerHTML = rows
    .map(([l, v]) => `<div class="review-row"><span class="review-label">${l}</span><span>${esc(v)}</span></div>`)
    .join('');
}

// ─── Validation ───────────────────────────────────────────────
function validate(step) {
  if (step === 1) {
    const t = $('study-title')?.value.trim();
    if (!t) { showToast(tr('create.err_title','El nombre del estudio es obligatorio.'), 'error'); $('study-title')?.focus(); return false; }
    state.title        = t;
    state.purpose      = $('study-purpose')?.value      || '';
    state.requirements = $('study-requirements')?.value || '';
  }
  if (step === 2 && isCS) {
    if (state.cards.filter(c => c.name.trim()).length < 2) {
      showToast(tr('create.err_cards','Agregá al menos 2 tarjetas.'), 'error'); return false;
    }
  }
  if (step === 2 && isTT) {
    if (state.tree.filter(n => n.label.trim()).length < 2) {
      showToast(tr('create.err_nodes','Agregá al menos 2 nodos al árbol.'), 'error'); return false;
    }
  }
  if (step === 3 && isCSClosed || step === 3 && isCSHybrid) {
    if (state.categories.filter(c => c.name.trim()).length < 2) {
      showToast(tr('create.err_cats','Agregá al menos 2 categorías.'), 'error'); return false;
    }
  }
  if (step === 3 && isTT) {
    if (state.tasks.filter(t => t.question.trim()).length < 1) {
      showToast(tr('create.err_tasks','Agregá al menos 1 tarea.'), 'error'); return false;
    }
  }
  return true;
}

// ─── Publish / Save ───────────────────────────────────────────
async function publishStudy() {
  const btn = $('btn-publish');
  btn.disabled = true;
  btn.textContent = EDIT_MODE
    ? tr('create.saving','Guardando…')
    : tr('create.publishing','Publicando…');
  const payload = {
    type:         STUDY_TYPE,
    status:       'active',
    title:        state.title,
    purpose:      state.purpose,
    requirements: state.requirements,
    randomize:    state.randomize ? 1 : 0,
    flow:         state.flow,
    ...(isCS ? {
      cards:      state.cards.filter(c => c.name.trim()),
      categories: state.categories.filter(c => c.name.trim()),
    } : {
      tree:  state.tree.filter(n => n.label.trim()),
      tasks: state.tasks.filter(t => t.question.trim()).map(t => ({
        question:     t.question,
        correctPaths: t.correctPaths || [],
      })),
    }),
  };
  try {
    if (EDIT_MODE) {
      payload.studyId = EDIT_STUDY_ID;
      await API.post('/api/study-update.php', payload);
      window.location.href = `${window.APP_URL}/results.php?id=${EDIT_STUDY_ID}`;
    } else {
      const res = await API.post('/api/study-create.php', payload);
      window.location.href = `${window.APP_URL}/results.php?id=${res.id}`;
    }
  } catch (err) {
    showToast(err.message || tr('create.err_save','Error al guardar el estudio.'), 'error');
    btn.disabled = false;
    btn.textContent = EDIT_MODE
      ? tr('create.btn_save_publish','Guardar y publicar →')
      : tr('create.btn_publish','Publicar estudio →');
  }
}

// ─── Nav buttons ─────────────────────────────────────────────
$('btn-next')?.addEventListener('click', () => { if (validate(currentStep)) goToStep(currentStep + 1); });
$('btn-prev')?.addEventListener('click', () => goToStep(currentStep - 1));
$('btn-publish')?.addEventListener('click', publishStudy);
document.querySelectorAll('.wizard-step-item').forEach((s, i) => { s.onclick = () => goToStep(i + 1); });

// ─── Init ─────────────────────────────────────────────────────
function init() {
  // Type badge
  const badge = $('wizard-type-badge');
  if (badge) badge.innerHTML = `<span class="wizard-type-label">${getTypeLabel(STUDY_TYPE)}</span>`;

  // Step labels
  if (isTT) {
    const l2 = $('step2-label'), s2 = $('step2-sub'), l3 = $('step3-label'), s3 = $('step3-sub');
    if (l2) l2.textContent = tr('create.step2_label_tt','Árbol');
    if (s2) s2.textContent = tr('create.step2_sub_tt','Estructura de navegación');
    if (l3) l3.textContent = tr('create.step3_label_tt','Tareas');
    if (s3) s3.textContent = tr('create.step3_sub_tt','Preguntas del árbol');
  } else if (isCSOpen) {
    // Hide step 3 entirely — doesn't apply for open card sorting
    const s3nav = $('step3-nav');
    if (s3nav) s3nav.style.display = 'none';
  }

  // Renumber visible sidebar steps consecutively (1, 2, 3… ignoring hidden)
  let visibleNum = 1;
  document.querySelectorAll('.wizard-step-item').forEach(item => {
    if (item.style.display !== 'none') {
      const numEl = item.querySelector('.wizard-step-num');
      if (numEl) numEl.textContent = visibleNum;
      visibleNum++;
    }
  });

  // Show correct panels
  if (isTT) {
    $('content-cards') && ($('content-cards').style.display = 'none');
    $('content-tree')  && ($('content-tree').style.display  = 'block');
    $('structure-tasks') && ($('structure-tasks').style.display = 'block');
  } else {
    $('content-cards') && ($('content-cards').style.display = 'block');
    if (isCSOpen)             $('structure-skip')?.style && ($('structure-skip').style.display = 'block');
    else if (isCS)            $('structure-categories')?.style && ($('structure-categories').style.display = 'block');
  }

  // Pre-populate from EDIT_DATA if in edit mode
  if (EDIT_DATA) {
    state.title        = EDIT_DATA.title        || '';
    state.purpose      = EDIT_DATA.purpose      || '';
    state.requirements = EDIT_DATA.requirements || '';
    state.randomize    = EDIT_DATA.randomize !== false;

    if (EDIT_DATA.cards?.length)      state.cards      = EDIT_DATA.cards.map(c => ({ id: uid(), name: c.name, description: c.description || '' }));
    if (EDIT_DATA.categories?.length) state.categories = EDIT_DATA.categories.map(c => ({ id: uid(), name: c.name }));
    if (EDIT_DATA.tree?.length)       state.tree       = EDIT_DATA.tree.map(n => ({ id: uid(), depth: n.depth || 0, label: n.label }));
    if (EDIT_DATA.tasks?.length)      state.tasks      = EDIT_DATA.tasks.map(t => ({ id: uid(), question: t.question, correctPaths: (t.correctPaths || []).map(p => Array.isArray(p) ? p : [p]) }));

    if (EDIT_DATA.flow) {
      Object.keys(EDIT_DATA.flow).forEach(key => {
        if (state.flow[key] !== undefined) state.flow[key] = EDIT_DATA.flow[key];
      });
    }

    // Prefill step-1 inputs
    const titleEl = $('study-title');
    if (titleEl) titleEl.value = state.title;
    const purposeEl = $('study-purpose');
    if (purposeEl) purposeEl.value = state.purpose;
    const reqEl = $('study-requirements');
    if (reqEl) reqEl.value = state.requirements;

    // Render loaded items
    renderCards();
    renderCategories();
    renderTree();
    renderTasks();
  } else {
    // Fresh start — add first empty item
    addCard();
    if (!isCSOpen && isCS) addCategory();
  }

  randToggle?.classList.toggle('on', state.randomize);

  // Wire Markdown editors in step 1 (purpose / requirements)
  const step1 = $('step-1');
  if (step1) wireMdEditors(step1);

  buildFlowUI();
  goToStep(1);
}

document.addEventListener('DOMContentLoaded', init);

// ─── Re-render dynamic content on language change ─────────────
(function () {
  const _origApply = window.applyI18n;
  window.applyI18n = function () {
    if (_origApply) _origApply();
    // Re-render all dynamic wizard content with new tr() values
    renderCards();
    renderTree();
    renderCategories();
    renderTasks();
    // Rebuild flow UI (preserves state, updates translated section names)
    const flowCont = $('flow-builder');
    if (flowCont) buildFlowUI();
    // Rebuild review if on last step
    if (currentStep === TOTAL_STEPS) buildReview();
    // Update step indicator
    const applicable = [1,2,3,4,5].filter(stepApplicable);
    const pos = applicable.indexOf(currentStep) + 1;
    const ind = $('step-indicator');
    if (ind) ind.textContent = `${tr('create.step_label','Paso')} ${pos} ${tr('create.step_of','de')} ${applicable.length}`;
    // Update type badge
    const badge = $('wizard-type-badge');
    if (badge) {
      const lbl = badge.querySelector('.wizard-type-label');
      if (lbl) lbl.textContent = getTypeLabel(STUDY_TYPE);
    }
    // Update step2/step3 labels for TT
    if (isTT) {
      const l2 = $('step2-label'), s2 = $('step2-sub'), l3 = $('step3-label'), s3 = $('step3-sub');
      if (l2) l2.textContent = tr('create.step2_label_tt','Árbol');
      if (s2) s2.textContent = tr('create.step2_sub_tt','Estructura de navegación');
      if (l3) l3.textContent = tr('create.step3_label_tt','Tareas');
      if (s3) s3.textContent = tr('create.step3_sub_tt','Preguntas del árbol');
    }
    // Update cards count
    updateCardsCount();
  };
})();
