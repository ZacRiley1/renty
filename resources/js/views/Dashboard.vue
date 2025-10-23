<template>
  <div class="space-y-6 text-brand-dark">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-xl font-semibold text-brand-dark">Dashboard</h2>
      <div class="flex items-center gap-2">
        <button
          v-if="canAdvance"
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-brand-mint px-4 py-2 text-sm font-semibold text-brand-dark transition hover:bg-brand-mint-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="advancing"
          @click="advancePayment"
        >
          {{ advancing ? 'Adding…' : 'Add next rent payment' }}
        </button>
      </div>
    </div>

    <div
      v-if="loading"
      class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-brand-mint/60 bg-white p-10 text-center shadow-sm"
    >
      <span class="text-2xl">⏳</span>
      <p class="text-sm text-zinc-600">Loading your rent payments…</p>
    </div>

    <div
      v-else-if="loadError"
      class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-600 shadow-sm"
    >
      <p>{{ loadError }}</p>
      <button
        type="button"
        class="mt-4 inline-flex items-center justify-center rounded-md bg-red-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-red-600"
        @click="retryLoad"
      >
        Try again
      </button>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <section class="space-y-4 lg:col-span-2">
        <div
          v-if="!hasTrackedPayments"
          class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-brand-mint/60 bg-white p-10 text-center shadow-sm"
        >
          <div
            class="flex h-16 w-16 items-center justify-center rounded-full border border-brand-mint/40 bg-brand-mint/10 text-brand-mint"
          >
            <span class="text-2xl">📄</span>
          </div>
          <div class="space-y-2">
            <h3 class="text-lg font-semibold text-brand-dark">No rent payments tracked yet</h3>
            <p class="text-sm text-zinc-600">
              Log your first rent payment to start building verified history.
            </p>
          </div>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md bg-brand-mint px-4 py-2 text-sm font-semibold text-brand-dark transition hover:bg-brand-mint-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
            @click="openModal"
          >
            Set up rent reporting
          </button>
        </div>

        <div v-else class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
          <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-brand-dark">Tracked payments</h3>
              <p class="text-sm text-zinc-600">
                Verify payments as they are reported to the bureaus.
              </p>
            </div>
            <span
              class="inline-flex items-center rounded-full bg-brand-mint/10 px-3 py-1 text-xs font-medium text-brand-primary"
            >
              {{ trackedPayments.length }} total
            </span>
          </header>

          <ul class="mt-5 space-y-4">
            <li
              v-for="payment in trackedPayments"
              :key="payment.id"
              class="rounded-xl border border-zinc-200 bg-zinc-50/60 px-4 py-3 shadow-sm"
            >
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                  <div
                    class="flex flex-wrap items-center gap-2 text-sm font-semibold text-brand-dark"
                  >
                    <p>{{ formatCurrency(payment.amount) }}</p>
                    <span
                      class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                      :class="statusChipClass(payment.status)"
                    >
                      {{ statusLabel(payment.status) }}
                    </span>
                  </div>
                  <p class="text-xs text-zinc-600">Paid on {{ formatDate(payment.paid_on) }}</p>
                  <p
                    v-if="payment.period_start || payment.period_end"
                    class="text-xs text-zinc-500"
                  >
                    Period {{ formatRange(payment.period_start, payment.period_end) }}
                  </p>
                  <p v-if="payment.notes" class="text-xs text-zinc-500">
                    {{ payment.notes }}
                  </p>
                </div>
                <div class="flex flex-col items-end gap-2 text-right">
                  <button
                    v-if="payment.status !== 'verified'"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md bg-brand-primary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="verifying[payment.id] || payment.status !== 'submitted'"
                    @click="verify(payment)"
                  >
                    {{
                      verifying[payment.id]
                        ? 'Verifying…'
                        : payment.status === 'submitted'
                          ? 'Verify payment'
                          : 'Awaiting detection'
                    }}
                  </button>
                  <div v-else class="text-xs font-medium text-brand-mint">
                    Verified {{ formatDate(payment.verified_at) }}
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <CreditScoreWidget :score="scoreData" :stats="reportStats" />
    </div>

    <BaseModal :open="modalOpen" @close="closeModal">
      <template #title>
        <h3 class="text-lg font-semibold text-brand-dark">Report a new rent payment</h3>
      </template>
      <template #subtitle>
        <p class="text-sm text-zinc-500">Add an on-time payment to keep your history current.</p>
      </template>
      <div class="space-y-4">
        <p class="text-xs text-brand-dark">
          A monthly payment will be created for every due date between your period start and today.
          We auto-detect payments for now, so new entries move straight to
          <strong>Submitted</strong>.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Payment date</span>
            <input
              v-model="form.paid_on"
              type="date"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.paid_on" class="text-xs text-red-600">
              {{ fieldErrors.paid_on }}
            </p>
          </label>
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Amount paid</span>
            <input
              v-model="form.amount"
              type="number"
              min="0"
              step="0.01"
              placeholder="1200"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.amount" class="text-xs text-red-600">
              {{ fieldErrors.amount }}
            </p>
          </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Period start</span>
            <input
              v-model="form.period_start"
              type="date"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.period_start" class="text-xs text-red-600">
              {{ fieldErrors.period_start }}
            </p>
          </label>
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Period end</span>
            <input
              v-model="form.period_end"
              type="date"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.period_end" class="text-xs text-red-600">
              {{ fieldErrors.period_end }}
            </p>
          </label>
        </div>
        <label class="block space-y-2">
          <span class="text-sm font-medium text-zinc-700">Notes (optional)</span>
          <textarea
            v-model="form.notes"
            rows="3"
            class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            placeholder="Include any details your landlord should know."
          ></textarea>
          <p v-if="fieldErrors.notes" class="text-xs text-red-600">
            {{ fieldErrors.notes }}
          </p>
        </label>
        <p v-if="formError" class="text-sm text-red-600">
          {{ formError }}
        </p>
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
          class="inline-flex items-center justify-center rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="submitting"
          @click="submitPayment"
        >
          {{ submitting ? 'Saving…' : 'Record payment' }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import CreditScoreWidget from '../components/CreditScoreWidget.vue';
import BaseModal from '../components/BaseModal.vue';

defineOptions({
  name: 'DashboardView',
});

const modalOpen = ref(false);
const loading = ref(true);
const loadError = ref(null);
const submitting = ref(false);
const formError = ref(null);
const stats = ref(null);
const payments = ref([]);
const rangeInfo = ref(null);
const canAdvance = ref(false);
const advancing = ref(false);
const verifying = reactive({});
const fieldErrors = reactive({});

const form = reactive({
  paid_on: '',
  amount: '',
  period_start: '',
  period_end: '',
  notes: '',
});

const defaultScore = Object.freeze({ start: 582, current: 582, goal: 760 });
const defaultTotals = Object.freeze({ on_time_payments: 0, payment_streak: 0, reports_sent: 0 });

const currencyFormatter = new Intl.NumberFormat('en-GB', {
  style: 'currency',
  currency: 'GBP',
  minimumFractionDigits: 2,
});
const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

const loadDashboard = async ({ silent = false } = {}) => {
  if (!silent) {
    loading.value = true;
    loadError.value = null;
  }

  try {
    const { data } = await axios.get('/api/dashboard');
    applyDashboardPayload(data?.data ?? {});
  } catch (error) {
    if (!silent) {
      loadError.value = 'Unable to fetch dashboard data. Please try again.';
    }
    console.error(error);
  } finally {
    if (!silent) {
      loading.value = false;
    }
  }
};

onMounted(() => {
  void loadDashboard();
});

const trackedPayments = computed(() => payments.value);
const hasTrackedPayments = computed(() => trackedPayments.value.length > 0);

const scoreData = computed(() => ({
  start: stats.value?.score?.start ?? defaultScore.start,
  current: stats.value?.score?.current ?? defaultScore.current,
  goal: stats.value?.score?.goal ?? defaultScore.goal,
}));

const reportStats = computed(() => ({
  onTimePayments: stats.value?.totals?.on_time_payments ?? defaultTotals.on_time_payments,
  paymentStreak: stats.value?.totals?.payment_streak ?? defaultTotals.payment_streak,
  reportsSent: stats.value?.totals?.reports_sent ?? defaultTotals.reports_sent,
}));

const openModal = () => {
  clearErrors();
  formError.value = null;
  modalOpen.value = true;
};

const closeModal = () => {
  modalOpen.value = false;
};

const resetForm = () => {
  form.paid_on = '';
  form.amount = '';
  form.period_start = '';
  form.period_end = '';
  form.notes = '';
  clearErrors();
  formError.value = null;
};

const clearErrors = () => {
  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key];
  });
};

const submitPayment = async () => {
  submitting.value = true;
  formError.value = null;
  clearErrors();

  try {
    const { data } = await axios.post('/api/rent-payments', {
      paid_on: form.paid_on,
      amount: form.amount,
      period_start: form.period_start || null,
      period_end: form.period_end || null,
      notes: form.notes || null,
    });

    applyDashboardPayload(data?.data ?? {});
    closeModal();
    resetForm();
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      const validationErrors = error.response.data.errors;
      Object.entries(validationErrors).forEach(([field, messages]) => {
        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
      });
    } else {
      formError.value = 'Something went wrong while saving the payment. Please try again.';
      console.error(error);
    }
  } finally {
    submitting.value = false;
  }
};

const verify = async (payment) => {
  verifying[payment.id] = true;
  try {
    const { data } = await axios.post(`/api/rent-payments/${payment.id}/verify`);
    const payload = data?.data ?? {};

    payments.value = payments.value.map((existing) =>
      existing.id === payment.id ? (payload.rent_payment ?? existing) : existing
    );

    if (payload.stats) {
      stats.value = payload.stats;
    }

    canAdvance.value = payments.value.some((item) => item.status === 'pending');
  } catch (error) {
    console.error(error);
  } finally {
    verifying[payment.id] = false;
  }
};

const formatCurrency = (value) => {
  const numeric = Number(value);
  if (Number.isNaN(numeric)) {
    return '£0.00';
  }

  return currencyFormatter.format(numeric);
};

const formatDate = (value) => {
  if (!value) {
    return '—';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return dateFormatter.format(date);
};

const statusLabel = (status) => {
  switch (status) {
    case 'verified':
      return 'Verified';
    case 'submitted':
      return 'Submitted';
    case 'rejected':
      return 'Rejected';
    case 'pending':
      return 'Pending';
    default:
      return 'Unknown';
  }
};

const statusChipClass = (status) => {
  switch (status) {
    case 'verified':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    case 'submitted':
      return 'border-blue-200 bg-blue-50 text-blue-700';
    case 'pending':
      return 'border-amber-200 bg-amber-50 text-amber-700';
    case 'rejected':
      return 'border-red-200 bg-red-50 text-red-700';
    default:
      return 'border-zinc-200 bg-white text-zinc-500';
  }
};

const formatRange = (start, end) => {
  const startLabel = formatDate(start);
  const endLabel = formatDate(end);

  if (!start && !end) {
    return '—';
  }

  if (startLabel === endLabel) {
    return startLabel;
  }

  return `${startLabel} – ${endLabel}`;
};

const retryLoad = () => {
  void loadDashboard();
};

const applyDashboardPayload = (payload) => {
  payments.value = Array.isArray(payload.rent_payments) ? payload.rent_payments : [];
  stats.value = payload.stats ?? null;
  rangeInfo.value = payload.range ?? null;
  canAdvance.value = Boolean(payload.actions?.can_advance);
};

const advancePayment = async () => {
  advancing.value = true;
  try {
    const { data } = await axios.post('/api/rent-payments/advance');
    applyDashboardPayload(data?.data ?? {});
  } catch (error) {
    console.error(error);
  } finally {
    advancing.value = false;
  }
};
</script>
