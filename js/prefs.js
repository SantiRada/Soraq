/* prefs.js — Theme + language preferences.
   Loaded synchronously in <head> to apply theme before first paint (no FOUC). */
(function () {
  'use strict';

  // Icons are now pure-CSS: .ti-moon/.ti-sun and .ll-ar/.ll-us spans are
  // shown/hidden by CSS rules keyed on data-theme and html[lang]. No SVG
  // strings needed here — the HTML already contains both icons.

  function gs(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function ss(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function getTheme() {
    var s = gs('soraq_theme');
    if (s === 'dark' || s === 'light') return s;
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches) ? 'dark' : 'light';
  }
  function getLang() {
    var s = gs('soraq_lang');
    if (s === 'en' || s === 'es') return s;
    var nav = (navigator.language || navigator.userLanguage || 'es').toLowerCase();
    return nav.startsWith('es') ? 'es' : 'en';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
  }
  function applyLang(lang) {
    document.documentElement.lang = lang;
  }

  // Apply immediately (before DOM / CSS paint)
  applyTheme(getTheme());
  applyLang(getLang());

  window.SoraqPrefs = {
    getTheme: getTheme,
    getLang:  getLang,

    setTheme: function (theme) {
      ss('soraq_theme', theme);
      applyTheme(theme);
      // Sync all toggle elements
      document.querySelectorAll('[data-theme-toggle]').forEach(function (el) {
        el.classList.toggle('on', theme === 'dark');
      });
      // CSS handles icon display via data-theme attribute — no innerHTML needed
    },

    setLang: function (lang) {
      ss('soraq_lang', lang);
      applyLang(lang);
      document.querySelectorAll('[data-lang-toggle]').forEach(function (el) {
        el.classList.toggle('on', lang === 'en');
      });
      // CSS handles flag display via html[lang] attribute — no innerHTML needed
      // Persist lang as a cookie so PHP (e.g. checkout.php) can read the preference
      try { document.cookie = 'soraq_lang=' + lang + ';path=/;max-age=31536000;SameSite=Lax'; } catch(e){}
      if (typeof window.applyI18n === 'function') window.applyI18n();
    },
  };
})();
