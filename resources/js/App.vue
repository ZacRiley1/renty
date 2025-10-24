<template>
  <div
    :class="[
      'flex flex-col min-h-screen',
      isHome
        ? 'bg-brand-dark text-white'
        : 'h-screen bg-white text-brand-dark overflow-hidden',
    ]"
  >
    <header
      :class="
        isHome
          ? 'border-b border-white/10 bg-brand-dark/80 backdrop-blur'
          : 'border-b border-zinc-200 bg-white/90 backdrop-blur'
      "
    >
      <nav class="mx-auto flex max-w-[66vw] items-center justify-between px-6 py-4">
        <RouterLink
          to="/"
          :class="[
            'text-lg font-semibold transition',
            isHome
              ? 'text-brand-accent hover:text-white'
              : 'text-brand-primary hover:text-brand-dark',
          ]"
        >
          Renty
        </RouterLink>
        <div
          :class="[
            'flex items-center gap-4 text-sm font-medium',
            isHome ? 'text-white/80' : 'text-zinc-600',
          ]"
        >
          <RouterLink
            v-if="isAuthenticated"
            to="/dashboard"
            :class="[
              'transition',
              isHome ? 'hover:text-brand-accent' : 'hover:text-brand-primary',
              { [isHome ? 'text-brand-accent' : 'text-brand-primary']: isCurrent('/dashboard') },
            ]"
          >
            <HomeIcon class="h-6 w-6" />
          </RouterLink>
          <RouterLink
            v-if="!isAuthenticated"
            to="/auth"
            class="rounded-md bg-brand-primary px-3 py-1.5 text-white transition hover:bg-brand-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
          >
            Sign in
          </RouterLink>
          <button
            v-else
            type="button"
            :class="[
              'rounded-md px-3 py-1.5 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent',
              isHome
                ? 'border border-white/30 text-white hover:bg-white/10'
                : 'border border-zinc-300 text-brand-dark hover:bg-zinc-50',
            ]"
            @click="handleLogout"
          >
            Sign out
          </button>
        </div>
      </nav>
    </header>

    <div class="flex-1 min-h-0 overflow-hidden p-6 lg:p-8">
      <main
        :class="[
          'mx-auto flex h-full min-h-0 w-full flex-col overflow-hidden',
          isHome ? 'max-w-[66vw]' : 'max-w-6xl',
        ]"
      >
        <router-view v-slot="{ Component }">
          <component :is="Component" class="flex-1 min-h-0" />
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from './stores/auth';
import { HomeIcon } from '@heroicons/vue/24/solid';

const auth = useAuth();
const router = useRouter();
const route = useRoute();

auth.init();

const isAuthenticated = computed(() => auth.isAuthenticated.value);
const isHome = computed(() => route.name === 'home');

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
