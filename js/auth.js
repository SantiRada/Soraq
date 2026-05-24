/* Auth module — localStorage-based simulation */

const AUTH_KEY  = 'soraq_auth';
const USERS_KEY = 'soraq_users';

const Auth = {
  getUser() {
    try { return JSON.parse(localStorage.getItem(AUTH_KEY)); } catch { return null; }
  },

  getUsers() {
    try { return JSON.parse(localStorage.getItem(USERS_KEY)) || []; } catch { return []; }
  },

  saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
  },

  login(user) {
    localStorage.setItem(AUTH_KEY, JSON.stringify(user));
  },

  logout() {
    localStorage.removeItem(AUTH_KEY);
    window.location.href = 'login.html';
  },

  isLoggedIn() { return !!this.getUser(); },

  requireAuth(redirectTo = 'dashboard.html') {
    if (!this.isLoggedIn()) {
      window.location.href = `login.html?redirect=${encodeURIComponent(redirectTo)}`;
      return false;
    }
    return true;
  },

  register({ name, email, password }) {
    const users = this.getUsers();
    if (users.find(u => u.email === email)) {
      return { ok: false, error: 'Ya existe una cuenta con ese email.' };
    }
    const user = {
      id:       genId('user'),
      name,
      email,
      password, // NOTE: in production, never store plain passwords
      plan:     'free',
      avatar:   name.slice(0, 2).toUpperCase(),
      createdAt: new Date().toISOString(),
      planActivatedAt: null,
    };
    users.push(user);
    this.saveUsers(users);
    const { password: _, ...safeUser } = user;
    this.login(safeUser);
    return { ok: true, user: safeUser };
  },

  loginWithCredentials({ email, password }) {
    const users = this.getUsers();
    const found = users.find(u => u.email === email && u.password === password);
    if (!found) return { ok: false, error: 'Email o contraseña incorrectos.' };
    const { password: _, ...safeUser } = found;
    this.login(safeUser);
    return { ok: true, user: safeUser };
  },

  loginWithGoogle() {
    // Simulate Google OAuth: create/find a demo Google user
    const googleUser = {
      id: 'user_google_demo',
      name: 'Usuario Google',
      email: 'usuario@gmail.com',
      plan: 'free',
      avatar: 'UG',
      createdAt: new Date().toISOString(),
      planActivatedAt: null,
      provider: 'google',
    };
    // Persist into users list if not there
    const users = this.getUsers();
    if (!users.find(u => u.id === googleUser.id)) {
      users.push(googleUser);
      this.saveUsers(users);
    }
    this.login(googleUser);
    return { ok: true, user: googleUser };
  },

  updateUser(updates) {
    const current = this.getUser();
    if (!current) return;
    const updated = { ...current, ...updates };
    this.login(updated);
    // Also update in users list
    const users = this.getUsers();
    const idx = users.findIndex(u => u.id === current.id);
    if (idx >= 0) {
      users[idx] = { ...users[idx], ...updates };
      this.saveUsers(users);
    }
    return updated;
  },

  upgradePlan(plan) {
    return this.updateUser({ plan, planActivatedAt: new Date().toISOString() });
  },
};

// Inject user info into sidebar/nav elements if present
function hydrateUserUI() {
  const user = Auth.getUser();
  if (!user) return;

  const avatarEls = document.querySelectorAll('.sidebar-avatar, .nav-avatar');
  avatarEls.forEach(el => { el.textContent = user.avatar || user.name.slice(0,2).toUpperCase(); });

  const nameEls = document.querySelectorAll('.sidebar-user-name');
  nameEls.forEach(el => { el.textContent = user.name; });

  const planEls = document.querySelectorAll('.sidebar-user-plan');
  const PLAN_LABELS = { free: 'Plan Free', pro: 'Plan Pro', team: 'Plan Team' };
  planEls.forEach(el => { el.textContent = PLAN_LABELS[user.plan] || 'Plan Free'; });
}
