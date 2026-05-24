/* ─────────────────────────────────────────────────────────
   app.js  –  Core utilities (replaces main.js + seed.js + auth.js)
   ───────────────────────────────────────────────────────── */

'use strict';

// Icons (theme moon/sun, language flags) are CSS-driven via .ti-moon/.ti-sun
// and .ll-ar/.ll-us sibling spans. Toggling data-theme / html[lang] (done by
// prefs.js) automatically shows the correct one — no innerHTML needed here.

// ── Toast notifications ───────────────────────
window.showToast = function(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity='0'; setTimeout(()=>toast.remove(), 400); }, duration);
};

// ── API wrapper ───────────────────────────────
window.API = {
  async get(url) {
    const res  = await fetch(`${window.APP_URL}${url}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    return data.data;
  },
  async post(url, body) {
    const res  = await fetch(`${window.APP_URL}${url}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    return data.data;
  },
  async put(url, body) {
    const res  = await fetch(`${window.APP_URL}${url}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    return data.data;
  },
  async delete(url) {
    const res  = await fetch(`${window.APP_URL}${url}`, {
      method: 'DELETE',
      credentials: 'include',
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    return data.data;
  },
};

// ── Date formatting ───────────────────────────
window.formatDate = function(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });
};

// ── Copy to clipboard ─────────────────────────
window.copyToClipboard = function(text) {
  navigator.clipboard.writeText(text)
    .then(() => showToast('Copiado al portapapeles', 'success'))
    .catch(() => showToast('Error al copiar', 'error'));
};

// ── Modal helpers ─────────────────────────────
window.openModal = function(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('hidden'); m.style.display='flex'; }
};
window.closeModal = function(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('hidden'); m.style.display='none'; }
};

// ── Sidebar mobile toggle ─────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const toggle  = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay?.classList.toggle('show');
    });
    overlay?.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  // Close dropdowns on outside click
  document.addEventListener('click', e => {
    if (!e.target.closest('.study-card-menu')) {
      document.querySelectorAll('.dropdown-menu').forEach(d => d.remove());
    }
    // Close topbar dropdowns when clicking outside
    if (!e.target.closest('#topbar-notif-wrap')) {
      document.getElementById('notif-dropdown')?.classList.add('hidden');
    }
    if (!e.target.closest('#topbar-avatar-wrap')) {
      document.getElementById('topbar-avatar-menu')?.classList.add('hidden');
    }
  });

  // Flash message auto-dismiss
  document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
  });

  // ── Topbar: notification bell ─────────────────
  const notifBtn      = document.getElementById('topbar-notif-btn');
  const notifDropdown = document.getElementById('notif-dropdown');
  const notifBadge    = document.getElementById('notif-badge');

  if (notifBtn && notifDropdown) {
    // Load notifications from localStorage
    function loadNotifs() {
      try {
        return JSON.parse(localStorage.getItem('soraq_notifs') || '[]');
      } catch { return []; }
    }

    function renderNotifs() {
      const notifs  = loadNotifs();
      const unread  = notifs.filter(n => !n.read).length;
      const tFn     = window.t || function() { return null; };
      const notifTitle    = tFn('notif.title')     || 'Notificaciones';
      const notifEmpty    = tFn('notif.empty')     || 'Sin notificaciones por ahora.';
      const notifMarkRead = tFn('notif.mark_read') || 'Marcar leídas';

      if (unread > 0) {
        notifBadge.textContent = unread > 9 ? '9+' : unread;
        notifBadge.style.display = 'flex';
      } else {
        notifBadge.style.display = 'none';
      }

      if (!notifs.length) {
        notifDropdown.innerHTML = `
          <div class="notif-header">
            <span class="notif-header-title">${notifTitle}</span>
          </div>
          <div class="notif-empty">${notifEmpty}</div>`;
        return;
      }

      const items = notifs.slice(0, 12).map(n => `
        <div class="notif-item ${n.read ? '' : 'unread'}">
          <div class="notif-icon">${notifIcon(n.type)}</div>
          <div class="notif-body">
            <div class="notif-text">${escHtml(n.message)}</div>
            <div class="notif-time">${timeAgo(n.at)}</div>
          </div>
        </div>`).join('');

      notifDropdown.innerHTML = `
        <div class="notif-header">
          <span class="notif-header-title">${notifTitle}</span>
          <button class="notif-clear-btn" id="notif-clear">${notifMarkRead}</button>
        </div>
        <div class="notif-list">${items}</div>`;

      document.getElementById('notif-clear')?.addEventListener('click', () => {
        const all = loadNotifs().map(n => ({...n, read: true}));
        localStorage.setItem('soraq_notifs', JSON.stringify(all));
        renderNotifs();
      });
    }

    function notifIcon(type) {
      const icons = {
        response:  '↗',
        milestone: '★',
        purchase:  '✓',
        finished:  '⬜',
        warning:   '⚠',
        launch:    '▶',
      };
      return icons[type] || '·';
    }

    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function timeAgo(iso) {
      if (!iso) return '';
      const diff  = Math.floor((Date.now() - new Date(iso)) / 1000);
      const tFn   = window.t || function() { return null; };
      const isEn  = (window.SoraqPrefs ? window.SoraqPrefs.getLang() : 'es') === 'en';
      if (isEn) {
        if (diff < 60)    return tFn('notif.moment') || 'just now';
        if (diff < 3600)  return `${Math.floor(diff/60)} ${tFn('notif.min') || 'min ago'}`;
        if (diff < 86400) return `${Math.floor(diff/3600)} ${tFn('notif.hour') || 'h ago'}`;
        return `${Math.floor(diff/86400)} ${tFn('notif.days') || 'days ago'}`;
      }
      if (diff < 60)    return 'hace un momento';
      if (diff < 3600)  return `hace ${Math.floor(diff/60)} min`;
      if (diff < 86400) return `hace ${Math.floor(diff/3600)} h`;
      return `hace ${Math.floor(diff/86400)} días`;
    }

    renderNotifs();

    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const hidden = notifDropdown.classList.toggle('hidden');
      // Mark all read when opened
      if (!hidden) {
        const all = loadNotifs().map(n => ({...n, read: true}));
        localStorage.setItem('soraq_notifs', JSON.stringify(all));
        renderNotifs();
      }
    });
  }

  // ── Topbar: avatar menu ───────────────────────
  const avatarBtn  = document.getElementById('topbar-avatar-btn');
  const avatarMenu = document.getElementById('topbar-avatar-menu');

  if (avatarBtn && avatarMenu) {
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      avatarMenu.classList.toggle('hidden');
    });
  }

  // ── Prefs toggles: theme + language ──────────
  if (window.SoraqPrefs) {
    const theme = SoraqPrefs.getTheme();
    const lang  = SoraqPrefs.getLang();

    // Sync initial visual state of toggle elements (CSS handles icon display)
    document.querySelectorAll('[data-theme-toggle]').forEach(el => {
      el.classList.toggle('on', theme === 'dark');
    });
    document.querySelectorAll('[data-lang-toggle]').forEach(el => {
      el.classList.toggle('on', lang === 'en');
    });

    // Click handlers for theme toggles
    document.querySelectorAll('[data-theme-toggle]').forEach(el => {
      el.addEventListener('click', () => {
        const next = SoraqPrefs.getTheme() === 'dark' ? 'light' : 'dark';
        SoraqPrefs.setTheme(next);
      });
    });

    // Click handlers for lang toggles
    document.querySelectorAll('[data-lang-toggle]').forEach(el => {
      el.addEventListener('click', () => {
        const next = SoraqPrefs.getLang() === 'es' ? 'en' : 'es';
        SoraqPrefs.setLang(next);
      });
    });
  }
});
