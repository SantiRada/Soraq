<?php
// ─────────────────────────────────────────────
// includes/layout.php  –  Shared HTML components
// ─────────────────────────────────────────────

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';

/**
 * Emit <head> for app pages (light theme)
 */
function app_head(string $title = '', array $extraCss = []): void {
    $t      = $title ? h($title) . ' — ' . APP_NAME : APP_NAME;
    $appUrl = APP_URL;
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$t}</title>
  <meta name="robots" content="noindex">
  <meta property="og:title" content="{$t}">
  <meta property="og:image" content="{$appUrl}/img/og.png">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" href="{$appUrl}/img/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{$appUrl}/css/main.css">
  <link rel="stylesheet" href="{$appUrl}/css/app.css">
HTML;
    foreach ($extraCss as $css) {
        echo "  <link rel=\"stylesheet\" href=\"" . APP_URL . "/css/{$css}\">\n";
    }
    echo "  <script>(function(){var s=function(k){try{return localStorage.getItem(k)}catch(e){return null}};var t=s('soraq_theme')||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);var l=s('soraq_lang');if(l==='en'||l==='es')document.documentElement.lang=l;})();</script>\n";
    echo "  <script src=\"" . APP_URL . "/js/prefs.js\"></script>\n";
    echo "</head>\n<body>\n";
}

/**
 * Emit <head> for auth pages (light theme)
 */
function auth_head(string $title = ''): void {
    $t      = $title ? h($title) . ' — ' . APP_NAME : APP_NAME;
    $appUrl = APP_URL;
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$t}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
  <link rel="icon" href="{$appUrl}/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="{$appUrl}/css/auth.css">
</head>
<body>
HTML;
}

/**
 * App sidebar
 * $active: 'panel' | 'studies' | 'profile' | 'support' | 'admin'
 */
function sidebar(array $user, string $active = 'panel'): void {
    $initial  = strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1));
    $isAdmin  = ($user['role'] ?? '') === 'admin';

    // Plan label — resolved here so we can emit data-i18n attributes
    require_once __DIR__ . '/auth.php';
    $sub     = active_subscription($user['id']);
    $credits = available_credits($user['id']);

    $nav = [
        'panel'   => ['href' => APP_URL . '/dashboard.php', 'icon' => icon('home'),      'label' => 'Panel',       'i18n' => 'nav.panel'],
        'studies' => ['href' => APP_URL . '/studies.php',   'icon' => icon('grid'),      'label' => 'Mis estudios','i18n' => 'nav.studies'],
        'profile' => ['href' => APP_URL . '/profile.php',   'icon' => icon('person'),    'label' => 'Mi perfil',   'i18n' => 'nav.profile'],
        'support' => ['href' => APP_URL . '/support.php',   'icon' => icon('chat_help'), 'label' => 'Soporte',     'i18n' => 'nav.support'],
    ];
    if ($isAdmin) {
        $nav['admin'] = ['href' => APP_URL . '/admin/', 'icon' => icon('shield'), 'label' => 'Admin', 'i18n' => 'nav.admin'];
    }

    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-logo"><a href="' . APP_URL . '/"><img src="' . APP_URL . '/img/logo.svg" alt="Soraq" height="26" style="display:block"></a></div>';

    echo '<nav class="sidebar-nav">';
    echo '<div class="sidebar-section-label" data-i18n="nav.workspace">Workspace</div>';
    foreach ($nav as $key => $item) {
        $cls = $key === $active ? ' class="active"' : '';
        echo "<a href=\"{$item['href']}\"{$cls}><span class=\"nav-icon\">{$item['icon']}</span><span data-i18n=\"{$item['i18n']}\">{$item['label']}</span></a>";
    }
    echo '</nav>';

    // "Nuevo estudio" CTA button in sidebar
    echo '<div class="sidebar-create">'
       . '<a href="' . APP_URL . '/create.php" class="sidebar-create-btn">'
       . icon('add', '', 16) . '<span data-i18n="nav.new_study"> Nuevo estudio</span>'
       . '</a></div>';

    echo '<div class="sidebar-bottom">';
    echo '<div class="sidebar-user">';
    echo "<div class=\"sidebar-avatar\">{$initial}</div>";
    echo '<div class="sidebar-user-info">';
    echo '<span class="sidebar-user-name">' . h($user['name'] ?: $user['email']) . '</span>';
    // Plan label with i18n support
    if ($sub) {
        echo '<span class="sidebar-user-plan">' . h($sub['plan_name']) . '</span>';
    } elseif ($credits === 1) {
        echo '<span class="sidebar-user-plan">1 <span data-i18n="dash.credits_sub_one">proyecto disponible</span></span>';
    } else {
        echo '<span class="sidebar-user-plan">' . (int)$credits . ' <span data-i18n="dash.credits_sub_many">proyectos disponibles</span></span>';
    }
    echo '</div></div></div>';
    echo '</aside>';

    // Mobile overlay
    echo '<div class="sidebar-overlay" id="sidebar-overlay"></div>';
}

/**
 * App topbar — always shows: hamburger (mobile) · title · [create btn] · notifications · avatar
 */
function topbar(string $title, array $actions = [], string $i18nKey = ''): void {
    // Resolve current user for avatar (session already started by page)
    $topbarUser = current_user();
    $initial    = strtoupper(mb_substr($topbarUser['name'] ?: $topbarUser['email'] ?? '?', 0, 1));
    $avatarUrl  = $topbarUser['avatar_url'] ?? null;

    $i18nAttr = $i18nKey ? " data-i18n=\"{$i18nKey}\"" : '';
    echo '<div class="app-topbar">';
    echo '<button class="sidebar-toggle" id="sidebar-toggle" aria-label="Abrir menú">' . icon('grid') . '</button>';
    echo "<span class=\"app-topbar-title\"{$i18nAttr}>{$title}</span>";
    echo '<div class="app-topbar-right">';

    // Extra actions (injected per page)
    foreach ($actions as $a) {
        $cls   = $a['class'] ?? 'btn btn-primary btn-sm';
        $href  = $a['href']  ?? '#';
        $lbl   = $a['label'] ?? '';
        $i18nA = isset($a['i18n']) ? ' data-i18n="' . htmlspecialchars($a['i18n']) . '"' : '';
        echo "<a href=\"{$href}\" class=\"{$cls}\"{$i18nA}>{$lbl}</a>";
    }

    // Theme toggle button — dual-icon: CSS shows moon in light mode, sun in dark mode (no flash)
    echo '<button class="topbar-icon-btn" id="topbar-theme-btn" data-theme-toggle title="Toggle dark/light mode">'
       . '<span class="theme-icon">'
       . '<span class="ti-moon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.26 5.4 5.4 0 0 1-3.14-9.8A9.1 9.1 0 0 0 12 3z"/></svg></span>'
       . '<span class="ti-sun"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/></svg></span>'
       . '</span></button>';

    // Language toggle button — dual-flag: CSS shows AR flag for ES, US flag for EN (no flash)
    echo '<button class="topbar-icon-btn" id="topbar-lang-btn" data-lang-toggle title="Switch language">'
       . '<span data-lang-label>'
       . '<span class="ll-ar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" aria-label="Español" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#74ACDF"/><rect y="5" width="22" height="5" fill="#fff"/><circle cx="11" cy="7.5" r="1.9" fill="#F6B40E"/><g fill="#F6B40E"><rect x="10.6" y="3.8" width=".8" height="1.6" rx=".4"/><rect x="10.6" y="9.6" width=".8" height="1.6" rx=".4"/><rect x="7.8" y="7.1" width="1.6" height=".8" rx=".4"/><rect x="12.6" y="7.1" width="1.6" height=".8" rx=".4"/><rect x="9.05" y="4.65" width=".8" height="1.6" rx=".4" transform="rotate(45 9.45 5.45)"/><rect x="12.15" y="8.75" width=".8" height="1.6" rx=".4" transform="rotate(45 12.55 9.55)"/><rect x="12.15" y="4.65" width=".8" height="1.6" rx=".4" transform="rotate(-45 12.55 5.45)"/><rect x="9.05" y="8.75" width=".8" height="1.6" rx=".4" transform="rotate(-45 9.45 9.55)"/></g></svg></span>'
       . '<span class="ll-us"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" aria-label="English" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#B22234"/><rect y="1.15" width="22" height="1.15" fill="#fff"/><rect y="3.46" width="22" height="1.15" fill="#fff"/><rect y="5.77" width="22" height="1.15" fill="#fff"/><rect y="8.08" width="22" height="1.15" fill="#fff"/><rect y="10.38" width="22" height="1.15" fill="#fff"/><rect y="12.69" width="22" height="1.15" fill="#fff"/><rect width="8.8" height="8.08" fill="#3C3B6E"/><g fill="#fff"><circle cx="1.1" cy="1" r=".5"/><circle cx="2.9" cy="1" r=".5"/><circle cx="4.7" cy="1" r=".5"/><circle cx="6.5" cy="1" r=".5"/><circle cx="8.3" cy="1" r=".5"/><circle cx="2" cy="2.15" r=".5"/><circle cx="3.8" cy="2.15" r=".5"/><circle cx="5.6" cy="2.15" r=".5"/><circle cx="7.4" cy="2.15" r=".5"/><circle cx="1.1" cy="3.3" r=".5"/><circle cx="2.9" cy="3.3" r=".5"/><circle cx="4.7" cy="3.3" r=".5"/><circle cx="6.5" cy="3.3" r=".5"/><circle cx="8.3" cy="3.3" r=".5"/><circle cx="2" cy="4.45" r=".5"/><circle cx="3.8" cy="4.45" r=".5"/><circle cx="5.6" cy="4.45" r=".5"/><circle cx="7.4" cy="4.45" r=".5"/><circle cx="1.1" cy="5.6" r=".5"/><circle cx="2.9" cy="5.6" r=".5"/><circle cx="4.7" cy="5.6" r=".5"/><circle cx="6.5" cy="5.6" r=".5"/><circle cx="8.3" cy="5.6" r=".5"/><circle cx="2" cy="6.75" r=".5"/><circle cx="3.8" cy="6.75" r=".5"/><circle cx="5.6" cy="6.75" r=".5"/><circle cx="7.4" cy="6.75" r=".5"/></g></svg></span>'
       . '</span></button>';

    // Notifications
    echo '<div class="topbar-notif-wrap" id="topbar-notif-wrap">'
       . '<button class="topbar-icon-btn" id="topbar-notif-btn" aria-label="Notificaciones">'
       . icon('bell', '', 20)
       . '<span class="notif-badge" id="notif-badge" style="display:none">0</span>'
       . '</button>'
       . '<div class="notif-dropdown hidden" id="notif-dropdown"></div>'
       . '</div>';

    // User avatar — Google photo or initial
    $avatarInner = $avatarUrl
        ? '<img src="' . h($avatarUrl) . '" class="topbar-avatar-photo" alt="' . h($initial) . '">'
        : '<span class="topbar-avatar-initials">' . h($initial) . '</span>';

    echo '<div class="topbar-avatar-wrap" id="topbar-avatar-wrap">'
       . '<button class="topbar-avatar" id="topbar-avatar-btn" aria-label="Mi perfil">'
       . $avatarInner
       . '</button>'
       . '<div class="topbar-avatar-menu hidden" id="topbar-avatar-menu">'
       . '<a href="' . APP_URL . '/profile.php" class="topbar-menu-item">' . icon('person', '', 16) . ' <span data-i18n="topbar.my_profile">Mi perfil</span></a>'
       . '<a href="' . APP_URL . '/logout.php" class="topbar-menu-item topbar-menu-item--danger">' . icon('sign_out', '', 16) . ' <span data-i18n="topbar.signout">Cerrar sesión</span></a>'
       . '</div>'
       . '</div>';

    echo '</div>'; // .app-topbar-right
    echo '</div>'; // .app-topbar
}

/**
 * Generic page footer (closes body+html)
 */
function app_foot(array $scripts = []): void {
    echo '<div id="toast-container"></div>' . "\n";
    echo '<script src="' . APP_URL . '/js/app.js"></script>' . "\n";
    echo '<script src="' . APP_URL . '/js/i18n.js"></script>' . "\n";
    foreach ($scripts as $s) {
        echo '<script src="' . APP_URL . "/js/{$s}\"></script>\n";
    }
    echo '<script>window.APP_URL = "' . APP_URL . '";</script>' . "\n";
    echo "</body>\n</html>\n";
}

/**
 * Flash message renderer
 */
function render_flash(): void {
    foreach (['success', 'error', 'info'] as $type) {
        $msg = flash($type);
        if ($msg) {
            echo "<div class=\"flash flash-{$type}\">" . h($msg) . "</div>\n";
        }
    }
}
