import { createRouter, createWebHistory } from 'vue-router';
import Home from './views/Home.vue';
import Dashboard from './views/Dashboard.vue';
import Auth from './views/Auth.vue';
import { useAuth } from './stores/auth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: Home },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: Dashboard,
      meta: { requiresAuth: true },
    },
    {
      path: '/auth',
      name: 'auth',
      component: Auth,
      meta: { requiresGuest: true },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuth();

  if (!auth.isReady.value) {
    try {
      await auth.init();
    } catch {
      // ignore bootstrap errors; guards below handle state
    }
  }

  if (to.meta?.requiresAuth && !auth.isAuthenticated.value) {
    return {
      name: 'auth',
      query: { redirect: to.fullPath },
    };
  }

  if (to.meta?.requiresGuest && auth.isAuthenticated.value) {
    return { name: 'dashboard' };
  }

  return true;
});

export default router;
