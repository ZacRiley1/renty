<template>
  <section class="h-full rounded-2xl bg-brand-dark text-white shadow-lg ring-1 ring-white/10">
    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.2em] text-white/60">
          Credit score progress
        </p>
        <h3 class="mt-2 text-2xl font-semibold text-brand-accent">
          {{ score.current }}
        </h3>
        <p class="text-sm text-white/70">Current score</p>
      </div>
      <div class="rounded-xl bg-white/10 px-4 py-2 text-right">
        <p class="text-xs uppercase tracking-[0.15em] text-white/60">Starting</p>
        <p class="text-lg font-semibold">{{ score.start }}</p>
      </div>
    </div>

    <div class="px-6 py-5">
      <div
        class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-white/60"
      >
        <span>Start</span>
        <span>Goal {{ score.goal }}</span>
      </div>
      <div class="mt-3 h-2 rounded-full bg-white/10">
        <div
          class="h-full rounded-full bg-brand-accent transition-all"
          :style="{ width: `${progress}%` }"
        ></div>
      </div>
      <p class="mt-2 text-xs text-white/60">
        Your credit score has improved by
        <span class="font-semibold text-brand-accent">{{ scoreDelta }}</span>
        points since joining Renty.
      </p>

      <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white/5 p-4">
          <dt class="text-xs uppercase tracking-[0.2em] text-white/60">On-time payments</dt>
          <dd class="mt-2 text-xl font-semibold text-white">
            {{ stats.onTimePayments }}
          </dd>
        </div>
        <div class="rounded-xl bg-white/5 p-4">
          <dt class="text-xs uppercase tracking-[0.2em] text-white/60">Active streak</dt>
          <dd class="mt-2 text-xl font-semibold text-white">{{ stats.paymentStreak }} mo</dd>
        </div>
        <div class="rounded-xl bg-white/5 p-4">
          <dt class="text-xs uppercase tracking-[0.2em] text-white/60">Reports sent</dt>
          <dd class="mt-2 text-xl font-semibold text-white">
            {{ stats.reportsSent }}
          </dd>
        </div>
      </dl>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  score: {
    type: Object,
    default: () => ({
      start: 582,
      current: 584,
      goal: 760,
    }),
  },
  stats: {
    type: Object,
    default: () => ({
      onTimePayments: '1',
      paymentStreak: '1',
      reportsSent: '1',
    }),
  },
});

const score = computed(() => props.score);
const stats = computed(() => props.stats);

const scoreDelta = computed(() => score.value.current - score.value.start);
const progress = computed(() => {
  const total = score.value.goal - score.value.start;
  if (total <= 0) {
    return 0;
  }
  const achieved = score.value.current - score.value.start;
  const ratio = Math.min(Math.max(achieved / total, 0), 1);
  return Math.round(ratio * 100);
});
</script>
