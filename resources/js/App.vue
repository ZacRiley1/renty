<template>
  <div class="min-h-screen bg-zinc-100">
    <header class="bg-white border-b border-zinc-200">
      <nav class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
        <RouterLink to="/" class="text-lg font-semibold text-emerald-600">
          Renty
        </RouterLink>
        <div class="flex items-center gap-4 text-sm font-medium text-zinc-600">
          <RouterLink
            v-if="isAuthenticated"
            to="/dashboard"
            class="transition hover:text-emerald-600"
            :class="{ 'text-emerald-600': isCurrent('/dashboard') }"
          >
            Dashboard
          </RouterLink>
          <RouterLink
            v-if="!isAuthenticated"
            to="/auth"
            class="rounded-md bg-emerald-600 px-3 py-1.5 text-white transition hover:bg-emerald-500"
          >
            Sign in
          </RouterLink>
          <button
            v-else
            type="button"
            class="rounded-md border border-emerald-600 px-3 py-1.5 text-emerald-600 transition hover:bg-emerald-50"
            @click="handleLogout"
          >
            Sign out
          </button>
        </div>
      </nav>
    </header>

    <div class="p-6 lg:p-8">
      <main class="mx-auto max-w-4xl">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from './stores/auth';

const auth = useAuth();
const router = useRouter();
const route = useRoute();

auth.init();

const isAuthenticated = computed(() => auth.isAuthenticated.value);

const handleLogout = async () => {
  try {
    await auth.logout();
  } catch (error) {
    console.error('Failed to sign out', error);
  } finally {
    if (route.name !== 'home') {
      router.push({ name: 'home' });
    }
  }
};

const isCurrent = (path) => route.path === path;
</script>
