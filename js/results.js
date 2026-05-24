/* results.js – Analytics engine. Data from PHP API instead of localStorage. */
'use strict';

// i18n helper — uses window.t (loaded by i18n.js before this file)
function tr(key, fallback) { return (window.t && window.t(key)) || fallback; }

const CAT_COLORS = ['#6DDEC5','#4AC882','#5B9EE0','#C06BE0','#E0B84A','#E05757','#4AD4C8','#E07A5B'];

// Study ID injected by results.php via window.STUDY_ID
const studyId  = window.STUDY_ID;
let study     = null;
let responses  = [];

// ── Global tooltip ────────────────────────────
const gTooltip = document.getElementById('g-tooltip');
function showTip(html, e) {
  gTooltip.innerHTML = html; gTooltip.style.opacity = '1'; moveTip(e);
}
function hideTip() { gTooltip.style.opacity = '0'; }
function moveTip(e) {
  const x = e.clientX + 14, y = e.clientY - 10;
  const bw = document.documentElement.clientWidth;
  gTooltip.style.left = (x + 230 > bw ? x - 244 : x) + 'px';
  gTooltip.style.top  = y + 'px';
}
document.addEventListener('mousemove', e => { if (gTooltip?.style.opacity === '1') moveTip(e); });

// ── Boot: fetch data from API ─────────────────
async function boot() {
  try {
    const result = await API.get(`/api/responses.php?study_id=${studyId}`);
    study     = result.study;
    responses  = result.responses || [];
    // items and categories come from the API (wizard tables), with config fallback for legacy studies
    study.items      = study.items      || study.config?.items      || [];
    study.categories = study.categories || study.config?.categories || [];
  } catch (err) {
    document.getElementById('study-title').textContent = 'Error al cargar estudio';
    showToast(err.message, 'error');
    return;
  }

  const TYPE_LABELS   = { 'card-sorting-open':'Card Sorting Abierto','card-sorting-closed':'Card Sorting Cerrado','card-sorting-hybrid':'Card Sorting Híbrido' };
  const STATUS_CLS    = { active:'badge-active', draft:'badge-draft', closed:'badge-closed', paused:'badge-neutral' };
  const STATUS_LABELS = {
    active: tr('study.status_active', 'Activo'),
    draft:  tr('study.status_draft',  'Borrador'),
    closed: tr('study.status_closed', 'Cerrado'),
    paused: tr('study.status_paused', 'Pausado'),
  };

  // Tab switching
  document.querySelectorAll('.results-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.results-tab').forEach(t  => t.classList.remove('active'));
      document.querySelectorAll('.results-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`tab-${tab.dataset.tab}`)?.classList.add('active');
    });
  });

  // Toggle status
  document.getElementById('btn-toggle-status')?.addEventListener('click', async () => {
    const btn    = document.getElementById('btn-toggle-status');
    const badge  = document.getElementById('study-status-badge');
    const banner = document.getElementById('paused-banner');
    const newSt  = study.status === 'active' ? 'paused' : 'active';
    try {
      await API.put(`/api/studies.php?id=${studyId}`, { status: newSt });
      study.status      = newSt;
      badge.textContent = STATUS_LABELS[newSt] || newSt;
      badge.className   = `badge ${STATUS_CLS[newSt] || 'badge-neutral'}`;
      const toggleSpan  = btn.querySelector('[data-i18n]');
      if (toggleSpan) {
        toggleSpan.textContent = newSt === 'active' ? 'Pausar' : 'Activar';
        toggleSpan.dataset.i18n = newSt === 'active' ? 'results.pause' : 'results.activate';
      } else {
        btn.textContent = newSt === 'active' ? 'Pausar' : 'Activar';
      }
      // Show/hide paused banner
      if (banner) {
        banner.style.display = newSt === 'paused' ? 'flex' : 'none';
      }
      showToast('Estado actualizado', 'success');
    } catch(err) { showToast(err.message, 'error'); }
  });

  // Export
  document.getElementById('btn-export')?.addEventListener('click', exportCSV);

  renderAll();
}

// ── Analytics helpers ─────────────────────────
function normName(n) { return (n||'').toLowerCase().trim(); }

function buildCardGroupMap() {
  const map = {};
  study.items.forEach(item => { map[item] = []; });
  responses.forEach(resp => {
    (resp.groups||[]).forEach(g => {
      (g.cards||[]).forEach(card => { if (map[card] !== undefined) map[card].push(normName(g.name)); });
    });
  });
  return map;
}

function buildSimilarityMatrix(items) {
  const n   = items.length;
  const mat = Array.from({length:n}, () => new Array(n).fill(0));
  if (!responses.length) return mat;
  responses.forEach(resp => {
    (resp.groups||[]).forEach(g => {
      const cards = (g.cards||[]).filter(c => items.includes(c));
      for (let a=0;a<cards.length;a++)
        for (let b=a+1;b<cards.length;b++) {
          const ia=items.indexOf(cards[a]), ib=items.indexOf(cards[b]);
          if (ia>=0&&ib>=0) { mat[ia][ib]++; mat[ib][ia]++; }
        }
    });
  });
  const total = responses.length;
  for (let i=0;i<n;i++) for (let j=0;j<n;j++)
    mat[i][j] = i===j ? 100 : Math.round((mat[i][j]/total)*100);
  return mat;
}

function calcAgreement(mat) {
  const n=mat.length; if (!n) return 0;
  let sum=0, count=0;
  for (let i=0;i<n;i++) for (let j=0;j<n;j++) if (i!==j) { sum+=mat[i][j]; count++; }
  return count ? Math.round(sum/count) : 0;
}

function hierCluster(items, mat) {
  if (items.length<=1) return { id:0, items:[0], dist:0, label:items[0] };
  const dist = mat.map(row=>row.map(v=>100-v));
  let clusters = items.map((item,i) => ({ id:i, items:[i], label:item, dist:0 }));
  while (clusters.length>1) {
    let minD=Infinity, ma=0, mb=1;
    for (let a=0;a<clusters.length;a++)
      for (let b=a+1;b<clusters.length;b++) {
        // Average linkage (UPGMA) – produces balanced, structured trees
        let d=0, cnt=0;
        clusters[a].items.forEach(ia => clusters[b].items.forEach(ib => { d+=dist[ia][ib]; cnt++; }));
        d = cnt ? d/cnt : 0;
        if (d<minD) { minD=d; ma=a; mb=b; }
      }
    const merged = { id:-1, items:[...clusters[ma].items,...clusters[mb].items], label:null,
                     children:[clusters[ma],clusters[mb]], dist:minD, sim:100-minD };
    clusters = clusters.filter((_,i)=>i!==ma&&i!==mb);
    clusters.push(merged);
  }
  return clusters[0];
}

function cutClusters(root, threshold=40) {
  const result=[];
  function walk(node) {
    if (!node.children||(node.dist||0)<=threshold) result.push(node.items);
    else node.children.forEach(walk);
  }
  if (!root.children) return [[root.id]];
  walk(root);
  return result;
}

// Automatically find the most meaningful cut in the dendrogram using the
// elbow / largest-gap method: cut just below the biggest jump in merge
// distances so the resulting clusters are non-trivial even with sparse data.
function smartCutClusters(root) {
  if (!root) return [];
  if (!root.children) return [[root.id??0]];

  // Collect every internal-node merge distance
  const merges=[];
  (function collect(node) {
    if (!node.children) return;
    merges.push(node.dist||0);
    node.children.forEach(collect);
  })(root);

  if (!merges.length) return [root.items];
  merges.sort((a,b)=>a-b); // ascending

  // Find the largest gap between consecutive merge distances
  let bestThreshold = merges[merges.length-1]-0.001; // default: just below root → 2 clusters
  let maxGap = -Infinity;
  for (let i=1;i<merges.length;i++) {
    const gap=merges[i]-merges[i-1];
    if (gap>maxGap) { maxGap=gap; bestThreshold=merges[i]-0.001; }
  }

  return cutClusters(root, bestThreshold).filter(c=>c.length>0);
}

// Returns original-index order of leaves as visited by the dendrogram
function getLeafOrder(root) {
  const order=[];
  function traverse(node) {
    if (!node.children) { order.push(node.id); return; }
    traverse(node.children[0]);
    traverse(node.children[1]);
  }
  traverse(root);
  return order;
}

// ── Render all ────────────────────────────────
function renderAll() {
  // Branch: Tree Testing gets its own render path
  if ((window.STUDY_TYPE || study.study_type || study.type) === 'tree_testing') {
    renderAllTT();
    return;
  }

  const items = study.items||[];
  const mat   = buildSimilarityMatrix(items);
  const cgMap = buildCardGroupMap();
  const score = calcAgreement(mat);
  const root  = items.length>1 ? hierCluster(items, mat) : null;
  const clusterIdxs = root ? smartCutClusters(root) : [items.map((_,i)=>i)];

  // Reorder matrix by hierarchical leaf order so highest-similarity pairs
  // land closest to the staircase diagonal
  let matrixItems = items, matrixMat = mat;
  if (root && items.length>1) {
    const leafOrder = getLeafOrder(root);
    matrixItems = leafOrder.map(i=>items[i]);
    const n=matrixItems.length;
    matrixMat = Array.from({length:n}, (_,r) =>
      Array.from({length:n}, (_,c) => mat[leafOrder[r]][leafOrder[c]]));
  }

  renderKPIs(responses.length, items.length, score, clusterIdxs.length);
  renderAgreementRing(score);
  renderTopGroups(cgMap);
  renderTopPairs(items, mat);
  renderMatrix(matrixItems, matrixMat);
  renderSubMatrices(items, mat, clusterIdxs, cgMap);
  if (root && items.length>1) renderDendrogram(items, mat, root, cgMap);
  renderClusters(items, clusterIdxs, cgMap);
  renderResponses();
}

// ── KPIs ─────────────────────────────────────
function renderKPIs(n, itemCount, score, clusterCount) {
  document.getElementById('kpis-row').innerHTML = `
    <div class="kpi-card"><div class="kpi-label">${tr('results.kpi_responses','Respuestas')}</div><div class="kpi-value">${n}</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.kpi_cards','Tarjetas')}</div><div class="kpi-value">${itemCount}</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.kpi_agreement','Índice de acuerdo')}</div><div class="kpi-value accent">${score}%</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.kpi_clusters','Clusters detectados')}</div><div class="kpi-value">${clusterCount}</div></div>`;
}

function renderAgreementRing(score) {
  const r=48,cx=60,cy=60,circ=2*Math.PI*r,fill=circ*(score/100);
  document.getElementById('agreement-ring').innerHTML = `
    <svg width="120" height="120" viewBox="0 0 120 120">
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="var(--bg-4)" stroke-width="10"/>
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="var(--accent)" stroke-width="10"
        stroke-dasharray="${fill} ${circ}" stroke-linecap="round"/>
    </svg>
    <div class="agreement-ring-val">
      <span class="agreement-ring-num">${score}</span>
      <span style="font-size:.6875rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em">%</span>
    </div>`;
}

function renderTopGroups(cgMap) {
  const gc={};
  Object.values(cgMap).forEach(names=>names.forEach(n=>{ if(n) gc[n]=(gc[n]||0)+1; }));
  const sorted=Object.entries(gc).sort((a,b)=>b[1]-a[1]).slice(0,8);
  const max=sorted[0]?.[1]||1;
  const list=document.getElementById('top-groups-list');
  if (!sorted.length) { list.innerHTML=`<p style="color:var(--text-3);font-size:.875rem">${tr('results.no_data','Sin datos aún.')}</p>`; return; }
  list.innerHTML=sorted.map(([name,count])=>`
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:.8125rem;color:var(--text-1)">
      <span style="min-width:120px;text-transform:capitalize">${name}</span>
      <div style="flex:1;height:4px;background:var(--bg-4);border-radius:100px;overflow:hidden">
        <div style="width:${Math.round(count/max*100)}%;height:100%;background:var(--accent);border-radius:100px"></div>
      </div>
      <span style="min-width:28px;text-align:right;color:var(--text-3)">${count}</span>
    </div>`).join('');
}

function renderTopPairs(items,mat) {
  const pairs=[];
  for (let i=0;i<items.length;i++)
    for (let j=i+1;j<items.length;j++)
      pairs.push({a:items[i],b:items[j],score:mat[i][j]});
  pairs.sort((a,b)=>b.score-a.score);
  const top=pairs.slice(0,6);
  const el=document.getElementById('top-pairs-list');
  if (!top.length||!responses.length) { el.innerHTML=`<p style="color:var(--text-3);font-size:.875rem">${tr('results.no_data','Sin datos aún.')}</p>`; return; }
  el.innerHTML=top.map(p=>`
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:.8125rem;color:var(--text-1)">
      <span style="min-width:200px">${p.a} + ${p.b}</span>
      <div style="flex:1;height:4px;background:var(--bg-4);border-radius:100px;overflow:hidden">
        <div style="width:${p.score}%;height:100%;background:var(--accent);border-radius:100px"></div>
      </div>
      <span style="min-width:40px;text-align:right;color:var(--text-3)">${p.score}%</span>
    </div>`).join('');
}

function renderMatrix(items,mat) {
  const container=document.getElementById('matrix-container');
  if (!items.length) { container.innerHTML='<p style="color:var(--text-3)">Sin tarjetas.</p>'; return; }
  if (!responses.length) { container.innerHTML='<p style="color:var(--text-3)">Sin respuestas aún.</p>'; return; }

  const n   = items.length;
  const cs  = n>30 ? 20 : n>22 ? 24 : n>15 ? 28 : 34; // cell size px
  const fs  = n>30 ? '.52rem' : n>22 ? '.58rem' : n>15 ? '.62rem' : '.67rem';
  const lfs = n>22 ? '.7rem' : '.75rem';

  // Div/flexbox layout: each row is independent, so row-label width never
  // bleeds into the cell columns of other rows — no more large gaps.
  let html=`<div class="matrix-scroll"><div class="tri-matrix-flex">`;
  for (let i=0;i<n;i++) {
    html+=`<div class="matrix-row">`;
    for (let j=0;j<i;j++) {
      const val=mat[i][j], alpha=val/100;
      const bg=`rgba(109,222,197,${(alpha*0.82).toFixed(2)})`;
      const fg=val>52?'var(--matrix-hi-text)':'var(--text-0)';
      html+=`<div class="matrix-cell" data-i="${i}" data-j="${j}" data-val="${val}"
        style="width:${cs}px;height:${cs}px;background:${bg};color:${fg};font-size:${fs}">${val}</div>`;
    }
    // Full label text — no truncation needed; label is at the end of the row
    // and does not affect the fixed-width cells of other rows.
    html+=`<div class="matrix-row-label" style="font-size:${lfs}" title="${items[i]}">${items[i]}</div>`;
    html+=`</div>`;
  }
  html+=`</div></div>`;
  container.innerHTML=html;

  const allCells=container.querySelectorAll('.matrix-cell');
  allCells.forEach(cell=>{
    cell.addEventListener('mouseenter',e=>{
      const r=+cell.dataset.i, c=+cell.dataset.j;
      showTip(`<strong>${items[r]}</strong><br>+ <strong>${items[c]}</strong><br><span style="color:var(--accent)">${cell.dataset.val}% ${tr('results.matrix_tip_grouped','los agrupó juntos')}</span>`,e);
      // Highlight the row of item r and the column of item c (2-strip cross)
      allCells.forEach(other=>{
        const ci=+other.dataset.i, cj=+other.dataset.j;
        other.classList.toggle('hl', ci===r || cj===c);
      });
    });
    cell.addEventListener('mouseleave',()=>{
      hideTip();
      allCells.forEach(other=>other.classList.remove('hl'));
    });
  });
}

function renderSubMatrices(items, mat, clusterIdxs, cgMap) {
  if (!responses.length) return;
  const container = document.getElementById('matrix-container');
  const meaningful = clusterIdxs.filter(c => c.length >= 3);
  if (!meaningful.length) return;

  meaningful.forEach((idxList, ci) => {
    const clusterItems = idxList.map(i => items[i]).filter(Boolean);
    const n = clusterItems.length;

    // Determine suggested category names + their confidence
    const nc = {};
    clusterItems.forEach(item => (cgMap[item]||[]).forEach(name => { nc[name]=(nc[name]||0)+1; }));
    const sortedNames = Object.entries(nc).sort((a,b)=>b[1]-a[1]);
    const topName  = sortedNames[0]?.[0] || `Cluster ${ci+1}`;
    const topNames = sortedNames.slice(0,3).map(([n,c])=>`${n} (${Math.round(c/clusterItems.length*100)}%)`).join(' · ');

    // Build sub-matrix from original mat indices
    const subMat = Array.from({length:n}, (_,r) =>
      Array.from({length:n}, (_,c) => mat[idxList[r]][idxList[c]]));

    // Average agreement within cluster (off-diagonal)
    const offDiag = subMat.flat().filter((_,k) => Math.floor(k/n) !== k%n);
    const avgScore = offDiag.length ? Math.round(offDiag.reduce((a,b)=>a+b,0)/offDiag.length) : 0;

    const cs  = n > 15 ? 26 : 32;
    const fs  = n > 15 ? '.58rem' : '.65rem';
    const lfs = '.72rem';

    const grpLabel  = tr('results.submatrix_group',     'Grupo');
    const cardsLbl  = tr('results.submatrix_cards',     'tarjetas');
    const agreeLbl  = tr('results.submatrix_agree',     '% acuerdo promedio');
    const sugLbl    = tr('results.submatrix_suggested', 'Categorías sugeridas');
    let html = `<div class="sub-matrix-section">
      <div class="sub-matrix-title">${grpLabel} ${ci+1}: <em style="text-transform:capitalize">${topName}</em></div>
      <div class="sub-matrix-desc">${n} ${cardsLbl} · ${avgScore}${agreeLbl}${topNames ? ' · ' + sugLbl + ': ' + topNames : ''}</div>
      <div class="matrix-scroll"><div class="tri-matrix-flex">`;

    for (let i=0; i<n; i++) {
      html += `<div class="matrix-row">`;
      for (let j=0; j<i; j++) {
        const val   = subMat[i][j];
        const alpha = val/100;
        const bg    = `rgba(109,222,197,${(alpha*0.82).toFixed(2)})`;
        const fg    = val > 52 ? 'var(--matrix-hi-text)' : 'var(--text-0)';
        html += `<div class="matrix-cell" data-val="${val}"
          style="width:${cs}px;height:${cs}px;background:${bg};color:${fg};font-size:${fs};cursor:default;border:1px solid rgba(0,0,0,.06)"
          title="${clusterItems[i]} + ${clusterItems[j]}: ${val}%">${val}</div>`;
      }
      html += `<div class="matrix-row-label" style="font-size:${lfs}">${clusterItems[i]}</div></div>`;
    }
    html += `</div></div></div>`;
    container.insertAdjacentHTML('beforeend', html);
  });
}

function renderDendrogram(items,mat,root,cgMap) {
  const container=document.getElementById('dendrogram-container');
  if (!responses.length) { container.innerHTML='<p style="color:var(--text-3)">Sin respuestas aún.</p>'; return; }

  const n=items.length;
  const rowH = n>35 ? 22 : n>25 ? 26 : n>18 ? 30 : 34;
  const labelW=150, treeW=500, padTop=28;
  const totalH=n*rowH+padTop*2, totalW=labelW+treeW+20;
  const labelCh = n>25 ? 16 : 20; // label char limit

  // Assign y-positions in dendrogram traversal order
  let leafIdx=0;
  function assignY(node) {
    if (!node.children) { node._y=padTop+(leafIdx++)*rowH+rowH/2; return; }
    node.children.forEach(assignY);
    node._y=node.children.reduce((s,c)=>s+c._y,0)/node.children.length;
  }
  assignY(root);

  // Map original item index → actual y position (from assignY traversal)
  const leafY={};
  function collectLeafY(node) {
    if (!node.children) { leafY[node.id]=node._y; return; }
    node.children.forEach(collectLeafY);
  }
  collectLeafY(root);

  function nodeX(dist) { return labelW+((dist||0)/100)*treeW; }
  function bestName(node) {
    const leafItems=node.items.map(i=>items[i]).filter(Boolean);
    const nc={};
    leafItems.forEach(item=>(cgMap[item]||[]).forEach(n=>{nc[n]=(nc[n]||0)+1;}));
    return Object.entries(nc).sort((a,b)=>b[1]-a[1])[0]?.[0]||null;
  }

  const segs=[];
  function collectSegs(node) {
    if (!node.children) return;
    const sim=Math.round(node.sim||0), px=nodeX(node.dist||0), nodeName=bestName(node)||'—';
    node.children.forEach(child=>{
      // Use the child's own dominant category — not the merged parent's
      const childName = bestName(child) || nodeName;
      const childSim  = child.children ? Math.round(child.sim||0) : sim;
      segs.push({x1:nodeX(child.dist||0),y1:child._y,x2:px,y2:child._y,sim:childSim,label:childName});
      collectSegs(child);
    });
    const c0=node.children[0],c1=node.children[1];
    // The vertical joining bar carries the merged node's info
    segs.push({x1:px,y1:Math.min(c0._y,c1._y),x2:px,y2:Math.max(c0._y,c1._y),sim,label:nodeName,isV:true});
  }
  collectSegs(root);

  const xMax=labelW+treeW;
  let svg=[];
  // Grid lines — x axis shows SIMILARITY (100% near labels on left, 0% far right)
  // nodeX maps distance → x, so similarity S = 100-dist → x = labelW + ((100-S)/100)*treeW
  const dendroAgree100 = tr('results.dendro_agree_100', '100% Acuerdo');
  const dendroAgree0   = tr('results.dendro_agree_0',   '0% Acuerdo');
  svg.push(`<line x1="${labelW}" y1="${padTop-10}" x2="${labelW}" y2="${totalH-padTop+10}" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>`);
  svg.push(`<text x="${labelW}" y="${padTop-14}" text-anchor="start" font-size="9" fill="var(--text-3)" font-family="DM Sans,sans-serif">${dendroAgree100}</text>`);
  // Intermediate grid lines at 75%, 50%, 25% similarity
  [75,50,25].forEach(sim=>{
    const x=labelW+((100-sim)/100)*treeW;
    svg.push(`<line x1="${x}" y1="${padTop-10}" x2="${x}" y2="${totalH-padTop+10}" stroke="rgba(255,255,255,0.04)" stroke-width="1" stroke-dasharray="3,4"/>`);
    svg.push(`<text x="${x}" y="${padTop-14}" text-anchor="middle" font-size="9" fill="var(--text-3)" font-family="DM Sans,sans-serif">${sim}%</text>`);
  });
  svg.push(`<line x1="${xMax}" y1="${padTop-10}" x2="${xMax}" y2="${totalH-padTop+10}" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>`);
  svg.push(`<text x="${xMax}" y="${padTop-14}" text-anchor="end" font-size="9" fill="var(--text-3)" font-family="DM Sans,sans-serif">${dendroAgree0}</text>`);
  // Branches
  segs.forEach((seg,si)=>{
    const sw=1.5+(seg.sim/100)*14, alpha=0.45+(seg.sim/100)*0.50;
    svg.push(`<line x1="${seg.x1.toFixed(1)}" y1="${seg.y1.toFixed(1)}" x2="${seg.x2.toFixed(1)}" y2="${seg.y2.toFixed(1)}" stroke="rgba(109,222,197,${alpha.toFixed(2)})" stroke-width="${sw.toFixed(1)}" stroke-linecap="round" class="dendro-seg"/>`);
    svg.push(`<line x1="${seg.x1.toFixed(1)}" y1="${seg.y1.toFixed(1)}" x2="${seg.x2.toFixed(1)}" y2="${seg.y2.toFixed(1)}" stroke="transparent" stroke-width="${Math.max(sw+10,16)}" class="dendro-hit" data-si="${si}"/>`);
  });
  // Leaf labels — placed at the y position the dendrogram actually assigned
  const fontSize = n>30 ? 9 : n>20 ? 10 : 11;
  items.forEach((item,i)=>{
    const y = leafY[i] ?? (padTop+i*rowH+rowH/2);
    const short = item.length>labelCh ? item.slice(0,labelCh)+'…' : item;
    svg.push(`<text x="${labelW-10}" y="${y+4}" text-anchor="end" font-size="${fontSize}" fill="var(--text-2)" font-family="DM Sans,sans-serif">${short}</text>`);
    svg.push(`<circle cx="${labelW}" cy="${y}" r="3" fill="var(--accent)" opacity="0.7"/>`);
  });

  container.innerHTML=`<svg width="${totalW}" height="${totalH}" style="min-width:${totalW}px;display:block">${svg.join('')}</svg>`;
  container.querySelectorAll('.dendro-hit').forEach(el=>{
    const si=+el.dataset.si, seg=segs[si];
    if (!seg) return;
    el.addEventListener('mouseenter',e=>{
      const catLabel = tr('results.dendro_category','Categoría');
      const grpLabel = tr('results.dendro_grouped','de participantes<br>agrupó estos ítems juntos');
      const labelHtml=seg.label&&seg.label!=='—'?`<span style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3)">${catLabel}</span><br><strong style="text-transform:capitalize;font-size:.9375rem">${seg.label}</strong><br>`:'';
      showTip(`${labelHtml}<span style="color:var(--accent);font-size:1.125rem;font-weight:600">${seg.sim}%</span> <span style="color:var(--text-2);font-size:.8125rem">${grpLabel}</span>`,e);
    });
    el.addEventListener('mouseleave',hideTip);
  });
}

function renderClusters(items,clusterIdxs,cgMap) {
  const grid=document.getElementById('clusters-grid');
  if (!responses.length) { grid.innerHTML='<p style="color:var(--text-3)">Sin respuestas aún.</p>'; return; }

  // Separate clusters with 2+ cards (meaningful) from singletons
  const meaningful = clusterIdxs.filter(c=>c.length>=2);
  const singletons = clusterIdxs.filter(c=>c.length<2).flat();

  if (!meaningful.length) {
    grid.innerHTML=`<p style="color:var(--text-3);font-size:.875rem;line-height:1.7">
      ${tr('results.clusters_empty','No se detectaron grupos consistentes todavía.')}<br>
      <span style="color:var(--text-4)">${tr('results.clusters_need_more','Necesitás más respuestas para que el algoritmo encuentre patrones.')}</span>
    </p>`;
    return;
  }

  // Build display list: meaningful clusters + optional "no clear group" bucket
  const groups=[...meaningful];
  if (singletons.length) groups.push(singletons);

  grid.innerHTML=groups.map((idxList,ci)=>{
    const clusterItems=idxList.map(i=>items[i]).filter(Boolean);
    const isSingBucket = singletons.length && ci===groups.length-1 && meaningful.length>0;
    let name, color;
    if (isSingBucket) {
      name=tr('results.cluster_none','Sin grupo claro');
      color='var(--text-3)';
    } else {
      const nc={};
      clusterItems.forEach(item=>(cgMap[item]||[]).forEach(n=>{nc[n]=(nc[n]||0)+1;}));
      name=Object.entries(nc).sort((a,b)=>b[1]-a[1])[0]?.[0]||`Cluster ${ci+1}`;
      color=CAT_COLORS[ci%CAT_COLORS.length];
    }
    return `<div class="cluster-card">
      <div class="cluster-header">
        <span class="cluster-dot" style="background:${color}"></span>
        <span class="cluster-name" style="text-transform:capitalize">${name}</span>
        <span class="cluster-count">${clusterItems.length} ${clusterItems.length===1?tr('results.cluster_card_one','tarjeta'):tr('results.cluster_card_many','tarjetas')}</span>
      </div>
      ${clusterItems.map(it=>`<div class="cluster-item">${it}</div>`).join('')}
    </div>`;
  }).join('');
}

function renderResponses() {
  const list=document.getElementById('responses-list');
  const label=document.getElementById('resp-count-label');
  const respWord = responses.length===1 ? tr('results.resp_one','respuesta') : tr('results.resp_many','respuestas');
  label.textContent=`${responses.length} ${respWord}`;
  if (!responses.length) {
    const noResp = tr('results.no_responses_yet','Sin respuestas aún');
    const hint   = tr('results.share_empty_hint','Compartí el enlace con tus participantes.');
    list.innerHTML=`<div style="text-align:center;padding:40px;color:var(--text-3)"><h3 style="margin-bottom:8px;color:var(--text-2)">${noResp}</h3><p>${hint}</p></div>`;
    return;
  }
  const participantLabel = tr('results.participant','Participante');
  list.innerHTML=responses.map((resp,i)=>{
    const groups=(resp.groups||[]).filter(g=>g.cards?.length>0);
    const date=resp.completed_at||resp.completedAt||'';
    return `<div class="response-item">
      <div class="response-header">
        <span class="response-id">${participantLabel} ${i+1}</span>
        <span style="font-size:.8125rem;color:var(--text-3)">${formatDate(date)}</span>
      </div>
      <div class="response-groups">
        ${groups.map(g=>`<span class="response-group-tag">${g.name} (${g.cards.length})</span>`).join('')}
      </div>
    </div>`;
  }).join('');
}

// ═══════════════════════════════════════════════
// ── Tree Testing Analytics ────────────────────
// ═══════════════════════════════════════════════

/**
 * Compare a participant's selected path to the study's correct paths.
 * Both arrays are compared case-insensitively with whitespace trimmed.
 */
function isPathCorrect(selectedPath, correctPaths) {
  if (!correctPaths || !correctPaths.length) return false;
  const sel = (selectedPath || []).map(s => (s || '').toLowerCase().trim());
  if (!sel.length) return false;
  return correctPaths.some(cp => {
    const c = (cp || []).map(s => (s || '').toLowerCase().trim());
    if (c.length !== sel.length) return false;
    return c.every((v, i) => v === sel[i]);
  });
}

/** Build per-task analytics from responses */
function buildTTTaskData() {
  const tasks = study.tasks || [];
  return tasks.map((task, ti) => {
    const results = responses.map((resp, ri) => {
      const ttTasks = resp.answers?.tt_tasks || [];
      // Match by taskIdx or by array position
      const answer = ttTasks.find(a => a.taskIdx === ti) ?? ttTasks[ti] ?? null;
      const selectedPath  = answer?.selectedPath  || [];
      const selectedLabel = answer?.selectedLabel || null;
      const correct = isPathCorrect(selectedPath, task.correctPaths || []);
      return { participant: ri + 1, selectedPath, selectedLabel, correct, answered: !!answer };
    });
    const correctCount = results.filter(r => r.correct).length;
    const correctPct   = results.length ? Math.round(correctCount / results.length * 100) : 0;
    return {
      question:     task.question,
      correctPaths: task.correctPaths || [],
      results,
      correctCount,
      correctPct,
    };
  });
}

/** Render an SVG donut chart. correctPct 0–100 */
function donutSvg(correctPct, size = 88) {
  const r    = (size - 18) / 2;
  const cx   = size / 2, cy = size / 2;
  const circ = 2 * Math.PI * r;
  const fill = circ * (correctPct / 100);
  const fs   = size > 80 ? 15 : 12;
  return `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
    <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="var(--bg-4)" stroke-width="9"/>
    <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="var(--accent)" stroke-width="9"
      stroke-dasharray="${fill.toFixed(2)} ${circ.toFixed(2)}" stroke-linecap="round"
      transform="rotate(-90 ${cx} ${cy})"/>
    <text x="${cx}" y="${cy + 5}" text-anchor="middle" font-size="${fs}"
      fill="var(--text-0)" font-family="DM Sans,sans-serif" font-weight="600">${correctPct}%</text>
  </svg>`;
}

/** Render a path as a breadcrumb row */
function pathHtml(pathArr) {
  if (!pathArr || !pathArr.length) return `<span style="color:var(--text-4);font-style:italic">${tr('results.tt_no_answer','Sin respuesta')}</span>`;
  return pathArr.map(node =>
    `<span class="tt-path-node">${node}</span>`
  ).join(`<span class="tt-path-sep">›</span>`);
}

/** Main TT render entry point */
function renderAllTT() {
  const taskData = buildTTTaskData();
  const taskCount = taskData.length;
  const totalCorrect  = taskData.reduce((s, t) => s + t.correctCount, 0);
  const totalAnswers  = responses.length * taskCount;
  const avgSuccessRate = totalAnswers > 0 ? Math.round(totalCorrect / totalAnswers * 100) : 0;
  const avgTime = responses.length
    ? Math.round(responses.reduce((s, r) => s + (+(r.time_spent_seconds || 0)), 0) / responses.length)
    : 0;

  renderTTKPIs(responses.length, taskCount, avgSuccessRate, avgTime);
  renderTTOverview(taskData);
  renderTTTaskAnalysis(taskData);
  renderTTResponses(taskData);
}

function renderTTKPIs(respCount, taskCount, avgSuccessRate, avgTime) {
  const el = document.getElementById('kpis-row');
  if (!el) return;
  const timeLabel = avgTime > 0
    ? (avgTime >= 60 ? `${Math.floor(avgTime/60)}m ${avgTime%60}${tr('results.tt_seconds','s')}` : `${avgTime}${tr('results.tt_seconds','s')}`)
    : '—';
  el.innerHTML = `
    <div class="kpi-card"><div class="kpi-label">${tr('results.kpi_responses','Respuestas')}</div><div class="kpi-value">${respCount}</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.tt_kpi_tasks','Tareas')}</div><div class="kpi-value">${taskCount}</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.tt_kpi_success','Éxito promedio')}</div><div class="kpi-value accent">${avgSuccessRate}%</div></div>
    <div class="kpi-card"><div class="kpi-label">${tr('results.tt_kpi_time','Tiempo promedio')}</div><div class="kpi-value">${timeLabel}</div></div>`;
}

/** Findability summary: one bar per task in the overview tab */
function renderTTOverview(taskData) {
  const el = document.getElementById('tt-tasks-summary');
  if (!el) return;
  if (!taskData.length) {
    el.innerHTML = `<p style="color:var(--text-3);font-size:.875rem">${tr('results.tt_no_tasks','No hay tareas definidas para este estudio.')}</p>`;
    return;
  }
  if (!responses.length) {
    el.innerHTML = `<p style="color:var(--text-3);font-size:.875rem">${tr('results.no_responses_yet','Sin respuestas aún.')}</p>`;
    return;
  }
  const taskLabel = tr('results.tt_task', 'Tarea');
  el.innerHTML = taskData.map((t, i) => `
    <div class="tt-summary-row">
      <span class="tt-summary-label" title="${t.question}">${taskLabel} ${i + 1}: ${t.question}</span>
      <div class="tt-summary-bar"><div class="tt-summary-fill" style="width:${t.correctPct}%"></div></div>
      <span class="tt-summary-pct">${t.correctPct}%</span>
    </div>`).join('');
}

/** Per-task detailed cards with donut + paths */
function renderTTTaskAnalysis(taskData) {
  const el = document.getElementById('tt-task-panels');
  if (!el) return;
  if (!taskData.length) {
    el.innerHTML = `<div class="result-section"><p style="color:var(--text-3)">${tr('results.tt_no_tasks','No hay tareas definidas.')}</p></div>`;
    return;
  }

  const taskLabel       = tr('results.tt_task',          'Tarea');
  const correctLabel    = tr('results.tt_correct',        'Correctos');
  const incorrectLabel  = tr('results.tt_incorrect',      'Incorrectos');
  const cpTitle         = tr('results.tt_correct_path',   'Ruta correcta:');
  const pathsTitle      = tr('results.tt_paths_taken',    'Rutas tomadas:');
  const noCpLabel       = tr('results.tt_no_correct_path','Sin ruta correcta definida.');
  const badgeOk         = tr('results.tt_path_correct_badge', '✓ Correcto');
  const badgeFail       = tr('results.tt_path_wrong_badge',   '✗ Incorrecto');
  const noAnswerLabel   = tr('results.tt_no_answer',      'Sin respuesta');
  const participantLabel= tr('results.participant',       'Participante');

  el.innerHTML = taskData.map((t, i) => {
    const incorrectCount = responses.length - t.correctCount;

    // Correct paths section
    let cpHtml = '';
    if (t.correctPaths.length) {
      cpHtml = t.correctPaths.map(cp =>
        `<div class="tt-correct-path">${pathHtml(cp)}</div>`
      ).join('');
    } else {
      cpHtml = `<p style="color:var(--text-4);font-size:.8125rem;font-style:italic">${noCpLabel}</p>`;
    }

    // Paths taken by participants
    let pathsHtml = '';
    if (responses.length) {
      pathsHtml = t.results.map(r => {
        const badge = r.answered
          ? (r.correct
              ? `<span class="tt-badge ok">${badgeOk}</span>`
              : `<span class="tt-badge fail">${badgeFail}</span>`)
          : '';
        const pathLine = r.answered
          ? pathHtml(r.selectedPath)
          : `<span style="color:var(--text-4);font-style:italic">${noAnswerLabel}</span>`;
        return `<div class="tt-path-row">
          <span style="min-width:95px;color:var(--text-3);flex-shrink:0">${participantLabel} ${r.participant}</span>
          ${badge}
          <span style="flex:1;display:flex;align-items:center;gap:4px;flex-wrap:wrap">${pathLine}</span>
        </div>`;
      }).join('');
    } else {
      pathsHtml = `<p style="color:var(--text-3);font-size:.8125rem">${tr('results.no_responses_yet','Sin respuestas aún.')}</p>`;
    }

    return `<div class="tt-task-card">
      <div class="tt-task-header">
        <div class="tt-task-donut">${donutSvg(t.correctPct)}</div>
        <div class="tt-task-meta">
          <div class="tt-task-num">${taskLabel} ${i + 1}</div>
          <div class="tt-task-question">${t.question}</div>
          <div class="tt-task-stats">
            <div class="tt-stat">
              <span class="tt-stat-dot correct"></span>
              <span class="tt-stat-val">${t.correctCount}</span>
              <span class="tt-stat-lbl">${correctLabel}</span>
            </div>
            <div class="tt-stat">
              <span class="tt-stat-dot incorrect"></span>
              <span class="tt-stat-val">${incorrectCount}</span>
              <span class="tt-stat-lbl">${incorrectLabel}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="tt-paths-section">
        <div class="tt-paths-title">${cpTitle}</div>
        ${cpHtml}
      </div>
      <div class="tt-paths-section" style="margin-top:16px">
        <div class="tt-paths-title">${pathsTitle}</div>
        ${pathsHtml}
      </div>
    </div>`;
  }).join('');
}

/** Per-participant TT response list */
function renderTTResponses(taskData) {
  const list  = document.getElementById('responses-list');
  const label = document.getElementById('resp-count-label');
  if (!list) return;

  const respWord = responses.length === 1
    ? tr('results.resp_one','respuesta')
    : tr('results.resp_many','respuestas');
  if (label) label.textContent = `${responses.length} ${respWord}`;

  if (!responses.length) {
    const noResp = tr('results.no_responses_yet','Sin respuestas aún');
    const hint   = tr('results.share_empty_hint','Compartí el enlace con tus participantes.');
    list.innerHTML = `<div style="text-align:center;padding:40px;color:var(--text-3)">
      <h3 style="margin-bottom:8px;color:var(--text-2)">${noResp}</h3><p>${hint}</p></div>`;
    return;
  }

  const participantLabel = tr('results.participant', 'Participante');
  const taskLabel        = tr('results.tt_task',     'Tarea');
  const badgeOk          = tr('results.tt_path_correct_badge', '✓ Correcto');
  const badgeFail        = tr('results.tt_path_wrong_badge',   '✗ Incorrecto');
  const noAnswerLabel    = tr('results.tt_no_answer', 'Sin respuesta');

  list.innerHTML = responses.map((resp, ri) => {
    const date = resp.completed_at || resp.completedAt || '';
    const ttTasks = resp.answers?.tt_tasks || [];

    const tasksHtml = taskData.map((t, ti) => {
      const answer = ttTasks.find(a => a.taskIdx === ti) ?? ttTasks[ti] ?? null;
      const selectedPath = answer?.selectedPath || [];
      const correct = isPathCorrect(selectedPath, t.correctPaths);
      const badge = answer
        ? (correct
            ? `<span class="tt-badge ok" style="font-size:.7rem">${badgeOk}</span>`
            : `<span class="tt-badge fail" style="font-size:.7rem">${badgeFail}</span>`)
        : '';
      const pathLine = answer
        ? `<span style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:.8rem">${pathHtml(selectedPath)}</span>`
        : `<span style="color:var(--text-4);font-style:italic;font-size:.8rem">${noAnswerLabel}</span>`;
      return `<div class="tt-resp-task">
        <div class="tt-resp-task-q">${taskLabel} ${ti + 1}: ${t.question}</div>
        <div class="tt-resp-path-line">${badge}${pathLine}</div>
      </div>`;
    }).join('');

    return `<div class="response-item">
      <div class="response-header">
        <span class="response-id">${participantLabel} ${ri + 1}</span>
        <span style="font-size:.8125rem;color:var(--text-3)">${formatDate(date)}</span>
      </div>
      ${tasksHtml}
    </div>`;
  }).join('');
}

function exportCSV() {
  if (!responses.length) {
    showToast('Este estudio no tiene respuestas todavía. Compartí el enlace con tus participantes para recibir datos.', 'info', 5000);
    return;
  }

  let rows, csv;
  const isTT = (window.STUDY_TYPE || study.study_type || study.type) === 'tree_testing';

  if (isTT) {
    rows = [['Participante','Fecha','Tarea','Pregunta','Ruta seleccionada','Correcto']];
    const tasks = study.tasks || [];
    responses.forEach((resp, ri) => {
      const ttTasks = resp.answers?.tt_tasks || [];
      tasks.forEach((task, ti) => {
        const answer = ttTasks.find(a => a.taskIdx === ti) ?? ttTasks[ti] ?? null;
        const selectedPath = answer?.selectedPath || [];
        const correct = isPathCorrect(selectedPath, task.correctPaths || []);
        rows.push([
          `Participante ${ri + 1}`,
          resp.completed_at || '',
          `Tarea ${ti + 1}`,
          task.question,
          selectedPath.join(' > '),
          answer ? (correct ? 'Sí' : 'No') : 'Sin respuesta',
        ]);
      });
    });
  } else {
    rows = [['Participante','Fecha','Grupo','Tarjeta']];
    responses.forEach((resp, i) => {
      (resp.groups||[]).forEach(g => {
        (g.cards||[]).forEach(card => { rows.push([`Participante ${i+1}`, resp.completed_at||'', g.name, card]); });
      });
    });
  }

  csv = rows.map(r => r.map(v => `"${(v||'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = `${study.title||'resultados'}.csv`; a.click();
  URL.revokeObjectURL(url);
  showToast('CSV exportado', 'success');
}

document.addEventListener('DOMContentLoaded', boot);
