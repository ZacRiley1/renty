<template>
  <div class="space-y-20">
    <section class="relative overflow-hidden rounded-3xl bg-brand-dark text-white shadow-xl">
      <div class="absolute -top-24 right-10 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
      <div
        class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-t from-black/30 to-transparent"
      ></div>
      <div
        class="relative mx-auto flex max-w-[66vw] flex-col items-center gap-10 px-6 py-20 text-center sm:px-12 sm:py-24"
      >
        <span
          class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-white/80"
        >
          <span class="h-2 w-2 rounded-full bg-brand-accent"></span>
          The UK's smallest rent reporting company.
        </span>
        <div class="space-y-6">
          <h1 class="text-4xl font-semibold tracking-tight text-brand-accent sm:text-5xl">
            Report Rent, Build Cred.
          </h1>
          <p class="mx-auto max-w-2xl text-base text-white/80 sm:text-lg">
            Renty tracks on-time payments and reports them to the bureaus that matter. <br />
            No new debt, just the credit progress you deserve.
          </p>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-4">
          <AppButton :to="primaryCta.to" size="lg">
            {{ primaryCta.label }}
          </AppButton>
          <AppButton v-if="secondaryCta" :to="secondaryCta.to" size="lg" variant="secondary">
            {{ secondaryCta.label }}
          </AppButton>
        </div>
        <dl class="grid w-full max-w-[66vw] grid-cols-1 gap-5 text-left sm:grid-cols-3">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="rounded-2xl bg-white/10 p-5 backdrop-blur"
          >
            <dt class="text-xs font-medium uppercase tracking-[0.2em] text-brand-accent">
              {{ stat.label }}
            </dt>
            <dd class="mt-2 text-2xl font-semibold">
              {{ stat.value }}
            </dd>
          </div>
        </dl>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import AppButton from '../components/AppButton.vue';
import { useAuth } from '../stores/auth';

defineOptions({
  name: 'HomeView',
});

const auth = useAuth();

const isAuthenticated = computed(() => auth.isAuthenticated.value);

const primaryCta = computed(() => {
  if (isAuthenticated.value) {
    return {
      to: { name: 'dashboard' },
      label: 'Open dashboard',
    };
  }

  return {
    to: { name: 'auth' },
    label: 'Log in to get started',
  };
});

const secondaryCta = computed(() => {
  if (isAuthenticated.value) {
    return null;
  }

  return {
    to: { name: 'auth', query: { mode: 'register' } },
    label: 'Create a free account',
  };
});

const stats = [
  { label: 'Average score lift', value: '+42 pts' },
  { label: 'On-time rent tracked', value: '18k+ payments' },
  { label: 'Support rating', value: '4.9 / 5' },
];
</script>
