<template>
  <div class="space-y-6 text-brand-dark">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-xl font-semibold text-brand-dark">Dashboard</h2>
      <button
        v-if="hasRentReport"
        type="button"
        class="inline-flex items-center justify-center rounded-md bg-brand-mint px-4 py-2 text-sm font-semibold text-brand-dark transition hover:bg-brand-mint-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
        @click="incrementReports"
      >
        Add another rent report
      </button>
    </div>
    <div class="grid gap-6">
      <section
        v-if="!hasRentReport"
        class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-brand-mint/60 bg-white p-10 text-center shadow-sm"
      >
        <div
          class="flex h-16 w-16 items-center justify-center rounded-full border border-brand-mint/40 bg-brand-mint/10 text-brand-mint"
        >
          <span class="text-2xl">📄</span>
        </div>
        <div class="space-y-2">
          <h3 class="text-lg font-semibold text-brand-dark">No rent reports yet</h3>
          <p class="text-sm text-zinc-600">
            Start reporting your rent to begin building your credit progress.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-brand-mint px-4 py-2 text-sm font-semibold text-brand-dark transition hover:bg-brand-mint-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
          @click="openModal"
        >
          Add rent report
        </button>
      </section>
      <CreditScoreWidget v-else :score="scoreData" :stats="reportStats" />
    </div>
    <BaseModal :open="modalOpen" @close="closeModal">
      <template #title>
        <h3 class="text-lg font-semibold text-brand-dark">Report a new rent payment</h3>
      </template>
      <template #subtitle>
        <p class="text-sm text-zinc-500">Add rent to improve score.</p>
      </template>
      <div class="space-y-4">
        <label class="block space-y-2">
          <span class="text-sm font-medium text-zinc-700">Payment date</span>
          <input
            type="date"
            class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
          />
        </label>
        <label class="block space-y-2">
          <span class="text-sm font-medium text-zinc-700">Amount paid</span>
          <input
            type="number"
            placeholder="£1,200"
            class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
          />
        </label>
        <label class="block space-y-2">
          <span class="text-sm font-medium text-zinc-700">Notes (optional)</span>
          <textarea
            rows="3"
            class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            placeholder="Include any details your landlord should know."
          ></textarea>
        </label>
      </div>
      <template #footer>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition hover:border-zinc-300 hover:text-zinc-800"
          @click="closeModal"
        >
          Cancel
        </button>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
          @click="submitReport"
        >
          Record payment
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import CreditScoreWidget from '../components/CreditScoreWidget.vue';
import BaseModal from '../components/BaseModal.vue';

defineOptions({
  name: 'DashboardView',
});

const modalOpen = ref(false);
const rentReportCount = ref(0);

const hasRentReport = computed(() => rentReportCount.value > 0);

const scoreData = computed(() => {
  const start = 582;
  const increasePerReport = 2;
  const current = Math.min(start + rentReportCount.value * increasePerReport, 760);
  return {
    start,
    current,
    goal: 760,
  };
});

const reportStats = computed(() => {
  const count = rentReportCount.value;
  const formatted = count.toString();
  return {
    onTimePayments: formatted,
    paymentStreak: formatted,
    reportsSent: formatted,
  };
});

const openModal = () => {
  modalOpen.value = true;
};

const closeModal = () => {
  modalOpen.value = false;
};

const incrementReports = () => {
  rentReportCount.value += 1;
};

const submitReport = () => {
  incrementReports();
  closeModal();
};
</script>
