import { computed, reactive } from 'vue';
import axios from 'axios';

const state = reactive({
  user: null,
  initialized: false,
});

let initPromise = null;

const setUser = (user) => {
  state.user = user;
  state.initialized = true;
};

export async function initAuth() {
  if (state.initialized) {
    return state.user;
  }

  if (!initPromise) {
    initPromise = (async () => {
      try {
        const { data } = await axios.get('/api/auth/user');
        setUser(data.user);
      } catch {
        setUser(null);
      } finally {
        initPromise = null;
      }
    })();
  }

  return initPromise;
}

export async function login(credentials) {
  await axios.get('/sanctum/csrf-cookie');
  const { data } = await axios.post('/api/auth/login', credentials);
  setUser(data.user);
}

export async function register(payload) {
  await axios.get('/sanctum/csrf-cookie');
  const { data } = await axios.post('/api/auth/register', payload);
  setUser(data.user);
}

export async function logout() {
  try {
    await axios.post('/api/auth/logout');
  } finally {
    setUser(null);
  }
}

export function useAuth() {
  return {
    user: computed(() => state.user),
    isAuthenticated: computed(() => Boolean(state.user)),
    isReady: computed(() => state.initialized),
    init: initAuth,
    login,
    register,
    logout,
  };
}
