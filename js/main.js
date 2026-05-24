/* Shared utilities */

const DB_KEY = 'soraq_data';

const DB = {
  get() {
    try {
      return JSON.parse(localStorage.getItem(DB_KEY)) || { studies: [], responses: [] };
    } catch { return { studies: [], responses: [] }; }
  },
  save(data) {
    localStorage.setItem(DB_KEY, JSON.stringify(data));
  },
  getStudies() { return this.get().studies; },
  getStudy(id) { return this.get().studies.find(s => s.id === id); },
  saveStudy(study) {
    const data = this.get();
    const idx = data.studies.findIndex(s => s.id === study.id);
    if (idx >= 0) data.studies[idx] = study;
    else data.studies.unshift(study);
    this.save(data);
  },
  deleteStudy(id) {
    const data = this.get();
    data.studies = data.studies.filter(s => s.id !== id);
    data.responses = data.responses.filter(r => r.studyId !== id);
    this.save(data);
  },
  getResponses(studyId) { return this.get().responses.filter(r => r.studyId === studyId); },
  saveResponse(response) {
    const data = this.get();
    data.responses.push(response);
    this.save(data);
    // bump study response count
    const studyIdx = data.studies.findIndex(s => s.id === response.studyId);
    if (studyIdx >= 0) {
      data.studies[studyIdx].responseCount = (data.studies[studyIdx].responseCount || 0) + 1;
      this.save(data);
    }
  }
};

function genId(prefix = 'id') {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
}

function toast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = msg;
  container.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(8px)';
    el.style.transition = 'all 0.3s';
    setTimeout(() => el.remove(), 300);
  }, 3000);
}

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
}

function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// Dismiss open dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.study-card-menu')) {
    document.querySelectorAll('.dropdown-menu').forEach(d => d.remove());
  }
});
