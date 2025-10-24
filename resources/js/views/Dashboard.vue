<template>
  <div class="flex h-full min-h-0 w-full flex-col overflow-hidden text-brand-dark">
    <div
      class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white/90 px-6 py-5 pt-0"
    >
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
        <button
          v-if="canAdvance"
          type="button"
          class="inline-flex items-center justify-center rounded-md border border-brand-mint/40 px-4 py-2 text-sm font-semibold text-brand-dark transition hover:border-brand-mint hover:bg-brand-mint/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!nextPendingPayment"
          @click="openModal('late')"
        >
          Add late rent payment
        </button>
      </div>
    </div>

    <div class="flex-1 min-h-0 overflow-hidden">
      <div class="flex h-full min-h-0 flex-col gap-6 overflow-hidden px-6 pb-6 pt-4 lg:px-8 lg:pb-8 lg:pt-5">
        <div
          v-if="loading"
          class="flex flex-1 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-brand-mint/60 bg-white p-10 text-center shadow-sm"
        >
          <span class="text-2xl">⏳</span>
          <p class="text-sm text-zinc-600">Loading your rent payments…</p>
        </div>

        <div
          v-else-if="loadError"
          class="flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-600 shadow-sm"
        >
          <p>{{ loadError }}</p>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md bg-red-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-red-600"
            @click="retryLoad"
          >
            Try again
          </button>
        </div>

        <div v-else class="flex-1 min-h-0">
          <div
            class="grid h-full min-h-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
          >
            <section class="flex h-full min-h-0 flex-col">
              <div
                v-if="!hasTrackedPayments"
                class="flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-brand-mint/60 bg-white p-10 text-center shadow-sm"
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
                  @click="openModal()"
                >
                  Set up rent reporting
                </button>
              </div>

              <div
                v-else
                class="flex h-full min-h-0 flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm"
              >
                <header class="flex flex-wrap items-center justify-between gap-4">
                  <div>
                    <h3 class="text-lg font-semibold text-brand-dark">Tracked payments</h3>
                    <p class="text-sm text-zinc-600">
                      Verify payments as they are reported to the bureaus.
                    </p>
                  </div>
                  <span
                    class="inline-flex items-center rounded-full bg-brand-mint/10 px-2 py-[2px] text-[11px] font-medium text-brand-primary"
                  >
                    {{ trackedPayments.length }} total
                  </span>
                </header>

                <div class="mt-5 flex-1 overflow-auto pr-1 scrollbar-pebble">
                  <ul class="space-y-4">
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
                              class="inline-flex items-center rounded-full border px-2 py-[2px] text-[10px] font-semibold uppercase tracking-[0.18em]"
                              :class="statusChipClass(payment.status)"
                            >
                              {{ statusLabel(payment.status) }}
                            </span>
                            <span
                              v-if="payment.metadata?.late || isLatePayment(payment)"
                              class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-[2px] text-[10px] font-semibold uppercase tracking-[0.18em] text-red-600"
                            >
                              Late
                            </span>
                          </div>
                          <div class="grid gap-3 text-xs text-zinc-500 sm:grid-cols-2">
                            <div class="space-y-1">
                              <p class="font-medium uppercase tracking-[0.18em] text-zinc-500">
                                Due date
                              </p>
                              <p class="text-sm text-zinc-700">
                                {{ formatDate(paymentDueDate(payment)) }}
                              </p>
                            </div>
                            <div v-if="payment.status !== 'pending'" class="space-y-1">
                              <p class="font-medium uppercase tracking-[0.18em] text-zinc-500">
                                Payment date
                              </p>
                              <p class="text-sm" :class="paidDateTextClass(payment)">
                                {{ formatDate(paymentPaidOn(payment)) }}
                              </p>
                            </div>
                          </div>
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
              </div>
            </section>

            <CreditScoreWidget
              class="w-full self-start justify-self-start lg:justify-self-end"
              :score="scoreData"
              :stats="reportStats"
              :estimate="scorePotentialIncrease"
            />
          </div>
        </div>
      </div>
    </div>

    <BaseModal :open="modalOpen" @close="closeModal">
      <template #title>
        <h3 class="text-lg font-semibold text-brand-dark">
          {{ isLateMode ? 'Report a late rent payment' : 'Report a new rent payment' }}
        </h3>
      </template>
      <template #subtitle>
        <p class="text-sm text-zinc-500">
          {{
            isLateMode
              ? 'Move the pending payment into submitted status with a late paid date.'
              : 'Choose the payment date and tenancy range you want to report.'
          }}
        </p>
      </template>
      <div class="space-y-4">
        <div v-if="isLateMode" class="space-y-4">
          <p class="text-xs text-brand-dark">
            Convert the pending payment due on
            <strong>{{ formatDate(nextPendingDueDate) }}</strong>
            into a submitted record with a paid date after the due date.
          </p>
          <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-sm text-zinc-600">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <span class="font-medium text-zinc-700">Due date</span>
              <span>{{ formatDate(nextPendingDueDate) }}</span>
            </div>
          </div>
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Actual payment date</span>
            <input
              v-model="lateForm.paid_on"
              type="date"
              :min="minLatePaidDate || undefined"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.paid_on" class="text-xs text-red-600">
              {{ fieldErrors.paid_on }}
            </p>
          </label>
          <p v-if="formError" class="text-sm text-red-600">
            {{ formError }}
          </p>
        </div>
        <div v-else class="space-y-4">
          <p class="text-xs text-brand-dark">
            Choose the pattern that best matches your rent due date. We will repeat this schedule
            from your tenancy start through the end date. New entries move straight to
            <strong>Submitted</strong>.
          </p>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-2">
              <span class="text-sm font-medium text-zinc-700">Payment schedule</span>
              <select
                v-model="form.schedule_type"
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
              >
                <option v-for="option in scheduleOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p v-if="fieldErrors.schedule_type" class="text-xs text-red-600">
                {{ fieldErrors.schedule_type }}
              </p>
            </label>
            <label v-if="isSpecificDaySchedule" class="block space-y-2">
              <span class="text-sm font-medium text-zinc-700">Payment day</span>
              <select
                v-model="form.due_day"
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
              >
                <option disabled value="">Select day</option>
                <option v-for="option in dayOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p v-if="fieldErrors.due_day" class="text-xs text-red-600">
                {{ fieldErrors.due_day }}
              </p>
            </label>
          </div>
          <div
            class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-xs text-zinc-600 sm:text-sm"
          >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <span class="font-medium text-zinc-700">First payment generated</span>
              <span>{{ firstDueDatePreview }}</span>
            </div>
            <p v-if="hasRangeSelection && !firstDueDateIsValid" class="mt-2 text-xs text-red-600">
              Double-check that your tenancy dates and payment day line up.
            </p>
          </div>
          <p v-if="fieldErrors.paid_on" class="text-xs text-red-600">
            {{ fieldErrors.paid_on }}
          </p>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-2">
              <span class="text-sm font-medium text-zinc-700">Tenancy start</span>
              <input
                v-model="form.period_start"
                type="date"
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
              />
              <p v-if="fieldErrors.period_start" class="text-xs text-red-600">
                {{ fieldErrors.period_start }}
              </p>
            </label>
            <label class="block space-y-2">
              <span class="text-sm font-medium text-zinc-700">Tenancy end</span>
              <input
                v-model="form.period_end"
                type="date"
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
              />
              <p v-if="fieldErrors.period_end" class="text-xs text-red-600">
                {{ fieldErrors.period_end }}
              </p>
            </label>
          </div>
          <label class="block space-y-2 sm:max-w-sm">
            <span class="text-sm font-medium text-zinc-700">Rent amount</span>
            <input
              v-model="form.amount"
              type="number"
              min="0"
              step="0.01"
              placeholder="1200"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
            />
            <p v-if="fieldErrors.amount" class="text-xs text-red-600">
              {{ fieldErrors.amount }}
            </p>
          </label>
          <label class="block space-y-2">
            <span class="text-sm font-medium text-zinc-700">Notes (optional)</span>
            <textarea
              v-model="form.notes"
              rows="3"
              class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-700 focus:border-brand-mint focus:outline-none focus:ring-brand-mint/40"
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
          @click="isLateMode ? submitLatePayment() : submitPayment()"
        >
          {{
            submitting
              ? 'Saving…'
              : isLateMode
                ? 'Save late payment'
                : 'Record payment'
          }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import CreditScoreWidget from '../components/CreditScoreWidget.vue';
import BaseModal from '../components/BaseModal.vue';

defineOptions({
  name: 'DashboardView',
});

const modalOpen = ref(false);
const modalMode = ref('standard');
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

const isLateMode = computed(() => modalMode.value === 'late');

const form = reactive({
  schedule_type: 'specific_day',
  due_day: '1',
  amount: '',
  period_start: '',
  period_end: '',
  notes: '',
});

watch(
  () => form.schedule_type,
  (type) => {
    if (type !== 'specific_day') {
      form.due_day = '';
      delete fieldErrors.due_day;
      delete fieldErrors.paid_on;
    } else if (!form.due_day) {
      form.due_day = '1';
    }
  }
);

watch(
  () => form.due_day,
  () => {
    delete fieldErrors.due_day;
  }
);

watch(
  () => [form.period_start, form.period_end, form.due_day, form.schedule_type],
  () => {
    delete fieldErrors.paid_on;
  }
);

const lateForm = reactive({
  paid_on: '',
});

const defaultScore = Object.freeze({ start: 582, current: 582, goal: 760 });
const defaultTotals = Object.freeze({ on_time_payments: 0, payment_streak: 0, reports_sent: 0 });
const scheduleOptions = Object.freeze([
  { value: 'specific_day', label: 'Specific day each month' },
  { value: 'last_day', label: 'Last day of the month' },
  { value: 'last_weekday', label: 'Last working day of the month' },
]);
const dayOptions = Object.freeze(
  Array.from({ length: 31 }, (_, index) => {
    const day = index + 1;
    return {
      value: String(day),
      label: formatOrdinal(day),
    };
  })
);
const REPORT_BLOCK_SIZE = 3;
const REPORT_MIN_FOR_BONUS = 2;
const REPORT_POINTS_PER_BLOCK = 5;
// Award additional boosts when a consecutive on-time streak hits these milestones.
const STREAK_BONUSES = Object.freeze([
  { threshold: 6, bonus: 10 },
  { threshold: 12, bonus: 20 },
]);
const MAX_SCHEDULE_LOOKAHEAD = 240;

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
const nextPendingPayment = computed(() =>
  payments.value.find((payment) => payment.status === 'pending')
);
const nextPendingDueDate = computed(() => paymentDueDate(nextPendingPayment.value));
const orderedPayments = computed(() =>
  [...payments.value]
    .map((payment) => ({
      payment,
      due: parseDate(paymentDueDate(payment)),
    }))
    .sort((a, b) => {
      if (a.due && b.due) {
        return a.due.getTime() - b.due.getTime();
      }
      if (a.due) {
        return -1;
      }
      if (b.due) {
        return 1;
      }
      return 0;
    })
    .map((entry) => entry.payment)
);
const currentOnTimeStreak = computed(() => {
  const ordered = orderedPayments.value;
  if (!ordered.length) {
    return 0;
  }

  const pendingIndex = ordered.findIndex((payment) => payment?.status === 'pending');
  let cutoffIndex = pendingIndex === -1 ? ordered.length - 1 : pendingIndex - 1;

  if (cutoffIndex < 0) {
    return 0;
  }

  let streak = 0;
  for (let index = cutoffIndex; index >= 0; index -= 1) {
    const payment = ordered[index];
    if (!isOnTimePayment(payment)) {
      break;
    }
    streak += 1;
  }

  return streak;
});

const scoreData = computed(() => ({
  start: stats.value?.score?.start ?? defaultScore.start,
  current: stats.value?.score?.current ?? defaultScore.current,
  goal: stats.value?.score?.goal ?? defaultScore.goal,
}));
const isSpecificDaySchedule = computed(() => form.schedule_type === 'specific_day');
const firstDueDateValue = computed(() => calculateFirstDueDate());
const firstDueDatePreview = computed(() => {
  const date = firstDueDateValue.value;
  return date ? formatDate(date) : '—';
});
const firstDueDateIsValid = computed(() => Boolean(firstDueDateValue.value));
const hasRangeSelection = computed(() => Boolean(form.period_start && form.period_end));
const reportStats = computed(() => ({
  onTimePayments: stats.value?.totals?.on_time_payments ?? defaultTotals.on_time_payments,
  paymentStreak: currentOnTimeStreak.value,
  reportsSent: stats.value?.totals?.reports_sent ?? defaultTotals.reports_sent,
}));

const activeRangeBounds = computed(() => {
  if (!rangeInfo.value) {
    return { start: null, end: null };
  }

  return {
    start: parseDate(rangeInfo.value.start_date),
    end: parseDate(rangeInfo.value.end_date),
  };
});

const outstandingPaymentsInRange = computed(() => {
  const { start, end } = activeRangeBounds.value;
  if (!start || !end) {
    return 0;
  }

  return payments.value.filter((payment) => {
    if (!payment || payment.status === 'verified') {
      return false;
    }

    const dueValue = paymentDueDate(payment) ?? paymentPaidOn(payment);
    const dueDate = parseDate(dueValue);

    if (!dueDate) {
      return false;
    }

    return dueDate >= start && dueDate <= end;
  }).length;
});

const scorePotentialIncrease = computed(() => {
  const remaining = outstandingPaymentsInRange.value;
  if (remaining <= 0) {
    return 0;
  }

  const currentScore = stats.value?.score?.current ?? defaultScore.current;
  const goalScore = stats.value?.score?.goal ?? defaultScore.goal;
  const headroom = Math.max(goalScore - currentScore, 0);

  if (headroom <= 0) {
    return 0;
  }

  const fullBlocks = Math.floor(remaining / REPORT_BLOCK_SIZE);
  const remainder = remaining % REPORT_BLOCK_SIZE;
  let totalPotential = fullBlocks * REPORT_POINTS_PER_BLOCK;

  if (remainder >= REPORT_MIN_FOR_BONUS) {
    totalPotential += REPORT_POINTS_PER_BLOCK;
  }

  const currentStreak = currentOnTimeStreak.value;
  const projectedStreak = currentStreak + remaining;

  for (const { threshold, bonus } of STREAK_BONUSES) {
    if (currentStreak < threshold && projectedStreak >= threshold) {
      totalPotential += bonus;
    }
  }

  if (totalPotential <= 0) {
    return 0;
  }

  return Math.min(totalPotential, headroom);
});

const defaultLatePaidDate = computed(() => {
  const due = parseDate(nextPendingDueDate.value);
  if (!due) {
    return '';
  }

  const suggestion = new Date(due.getTime());
  suggestion.setDate(suggestion.getDate() + 1);
  return formatInputDate(suggestion);
});

const minLatePaidDate = computed(() => {
  const due = parseDate(nextPendingDueDate.value);
  if (!due) {
    return '';
  }

  const min = new Date(due.getTime());
  min.setDate(min.getDate() + 1);
  return formatInputDate(min);
});

const openModal = (mode = 'standard') => {
  clearErrors();
  formError.value = null;
  const targetMode = typeof mode === 'string' ? mode : 'standard';

  if (targetMode === 'late' && !nextPendingPayment.value) {
    return;
  }

  modalMode.value = targetMode;

  if (modalMode.value === 'late') {
    resetLateForm(defaultLatePaidDate.value);
  } else {
    resetStandardForm();
  }

  modalOpen.value = true;
};

const closeModal = () => {
  modalOpen.value = false;
  modalMode.value = 'standard';
  resetStandardForm();
  resetLateForm();
  clearErrors();
  formError.value = null;
};

const resetStandardForm = () => {
  form.schedule_type = 'specific_day';
  form.due_day = '1';
  form.amount = '';
  form.period_start = '';
  form.period_end = '';
  form.notes = '';
};

const resetLateForm = (suggested = '') => {
  const fallback = suggested || minLatePaidDate.value || '';
  lateForm.paid_on = fallback;
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
    if (form.schedule_type === 'specific_day' && !form.due_day) {
      fieldErrors.due_day = 'Select the day your rent falls due each month.';
      submitting.value = false;
      return;
    }

    const resolvedDueDate = calculateFirstDueDate();

    if (!resolvedDueDate) {
      fieldErrors.paid_on = 'Choose a payment day that sits within your tenancy dates.';
      submitting.value = false;
      return;
    }

    const { data } = await axios.post('/api/rent-payments', {
      paid_on: formatInputDate(resolvedDueDate),
      amount: form.amount,
      period_start: form.period_start || null,
      period_end: form.period_end || null,
      notes: form.notes || null,
      schedule_type: form.schedule_type,
      due_day:
        form.schedule_type === 'specific_day' && form.due_day
          ? Number.parseInt(form.due_day, 10)
          : null,
    });

    applyDashboardPayload(data?.data ?? {});
    closeModal();
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

const submitLatePayment = async () => {
  submitting.value = true;
  formError.value = null;
  clearErrors();

  try {
    const { data } = await axios.post('/api/rent-payments/advance-late', {
      paid_on: lateForm.paid_on || null,
    });

    applyDashboardPayload(data?.data ?? {});
    closeModal();
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      const validationErrors = error.response.data.errors;
      Object.entries(validationErrors).forEach(([field, messages]) => {
        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
      });
    } else {
      formError.value = 'Unable to record the late payment. Please try again.';
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

  const date = parseDate(value);
  if (!date) {
    return value;
  }

  return dateFormatter.format(date);
};

const formatInputDate = (date) => {
  if (!date) {
    return '';
  }
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const parseDate = (value) => {
  if (!value) {
    return null;
  }

  if (typeof value === 'string') {
    const parts = value.split('-').map((segment) => Number.parseInt(segment, 10));
    if (parts.length === 3 && parts.every((part) => Number.isInteger(part))) {
      const [year, month, day] = parts;
      const candidate = new Date(year, month - 1, day);
      if (
        candidate.getFullYear() === year &&
        candidate.getMonth() === month - 1 &&
        candidate.getDate() === day
      ) {
        return candidate;
      }
    }
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date;
};

const daysInMonth = (date) => {
  if (!date) {
    return 31;
  }

  return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
};

function normalizeSchedule(schedule, fallbackDay = null) {
  const allowed = ['specific_day', 'last_day', 'last_weekday'];
  const type = allowed.includes(schedule?.type) ? schedule.type : 'specific_day';

  if (type !== 'specific_day') {
    return { type, day: null };
  }

  const candidate = Number(schedule?.day);
  if (Number.isInteger(candidate) && candidate >= 1 && candidate <= 31) {
    return { type, day: candidate };
  }

  if (fallbackDay !== null) {
    const fallbackNumber = Number(fallbackDay);
    if (Number.isInteger(fallbackNumber) && fallbackNumber >= 1 && fallbackNumber <= 31) {
      return { type, day: fallbackNumber };
    }
  }

  return { type, day: null };
}

function computeScheduledDateForMonth(monthAnchor, scheduleInput, fallbackDay = null) {
  const schedule = normalizeSchedule(scheduleInput, fallbackDay);
  const monthStart = new Date(monthAnchor.getFullYear(), monthAnchor.getMonth(), 1);

  switch (schedule.type) {
    case 'last_day': {
      return new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0);
    }
    case 'last_weekday': {
      const last = new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0);
      while (last.getDay() === 0 || last.getDay() === 6) {
        last.setDate(last.getDate() - 1);
      }
      return last;
    }
    default: {
      if (!schedule.day) {
        return null;
      }
      const clampedDay = Math.min(schedule.day, daysInMonth(monthStart));
      return new Date(monthStart.getFullYear(), monthStart.getMonth(), clampedDay);
    }
  }
}

function calculateFirstDueDateForRange(rangeStart, rangeEnd, schedule) {
  const normalized = normalizeSchedule(schedule, schedule.day ?? null);

  if (normalized.type === 'specific_day' && !normalized.day) {
    return null;
  }

  let anchor = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), 1);
  let candidate = computeScheduledDateForMonth(anchor, normalized);
  let iterations = 0;

  while (candidate && candidate < rangeStart && iterations < MAX_SCHEDULE_LOOKAHEAD) {
    iterations += 1;
    anchor = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1);
    candidate = computeScheduledDateForMonth(anchor, normalized);
  }

  if (!candidate || candidate > rangeEnd) {
    return null;
  }

  return candidate;
}

function calculateFirstDueDate() {
  const start = parseDate(form.period_start);
  const end = parseDate(form.period_end);

  if (!start || !end || start > end) {
    return null;
  }

  const rawDay = Number.parseInt(form.due_day, 10);
  const scheduleType = form.schedule_type;

  if (scheduleType === 'specific_day') {
    if (!Number.isInteger(rawDay) || rawDay < 1 || rawDay > 31) {
      return null;
    }
  }

  const schedule = {
    type: scheduleType,
    day: scheduleType === 'specific_day' ? rawDay : null,
  };

  return calculateFirstDueDateForRange(start, end, schedule);
}

function formatOrdinal(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) {
    return String(value ?? '');
  }

  const remainder100 = number % 100;
  if (remainder100 >= 11 && remainder100 <= 13) {
    return `${number}th`;
  }

  switch (number % 10) {
    case 1:
      return `${number}st`;
    case 2:
      return `${number}nd`;
    case 3:
      return `${number}rd`;
    default:
      return `${number}th`;
  }
}

const inferDueDateFromRange = (payment) => {
  const range = payment?.range;
  if (!range) {
    return null;
  }

  const reference =
    parseDate(payment?.paid_on) ??
    parseDate(payment?.period_start) ??
    parseDate(payment?.period_end) ??
    parseDate(range?.start_date);

  if (!reference) {
    return null;
  }

  const schedule = normalizeSchedule(
    range.metadata?.schedule ?? { type: 'specific_day', day: range.day_of_month },
    Number(range?.day_of_month) || 1
  );

  const monthAnchor = new Date(reference.getFullYear(), reference.getMonth(), 1);
  const dueDate = computeScheduledDateForMonth(monthAnchor, schedule, Number(range?.day_of_month) || 1);

  return dueDate ? formatInputDate(dueDate) : null;
};

const paymentDueDate = (payment) => {
  if (!payment) {
    return null;
  }

  if (payment.metadata?.due_date) {
    return payment.metadata.due_date;
  }

  const inferred = inferDueDateFromRange(payment);
  if (inferred) {
    return inferred;
  }

  return payment.period_end ?? payment.paid_on ?? null;
};

const paymentPaidOn = (payment) => {
  if (!payment) {
    return null;
  }

  if (payment.status === 'pending') {
    return null;
  }

  if (payment.metadata?.late && payment.metadata?.late_paid_on) {
    return payment.metadata.late_paid_on;
  }

  return payment.paid_on ?? null;
};

const isLatePayment = (payment) => {
  const due = parseDate(paymentDueDate(payment));
  const paid = parseDate(paymentPaidOn(payment));

  if (!due || !paid) {
    return false;
  }

  return paid > due;
};

const isOnTimePayment = (payment) => {
  if (!payment) {
    return false;
  }

  if (!['submitted', 'verified'].includes(payment.status)) {
    return false;
  }

  if (payment.metadata?.late) {
    return false;
  }

  return !isLatePayment(payment);
};

const paidDateTextClass = (payment) => (isLatePayment(payment) ? 'text-red-600 font-semibold' : 'text-zinc-600');

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
