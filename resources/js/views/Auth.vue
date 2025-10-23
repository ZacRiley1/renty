<template>
  <div class="max-w-md mx-auto bg-white rounded-lg border border-zinc-200 shadow-sm p-6">
    <header class="mb-6 text-center space-y-2">
      <h1 class="text-2xl font-semibold text-zinc-800">
        {{ isRegistering ? 'Create an account' : 'Welcome back' }}
      </h1>
      <p class="text-sm text-zinc-500">
        {{ isRegistering ? 'Register to start building your credit.' : 'Sign in to continue to your dashboard.' }}
      </p>
    </header>

    <form class="space-y-5" @submit.prevent="handleSubmit">
      <div v-if="isRegistering" class="space-y-2">
        <label class="block text-sm font-medium text-zinc-700" for="name">Name</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          autocomplete="name"
          class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500"
        />
        <p v-if="errors.name" class="text-xs text-red-600">{{ errors.name }}</p>
      </div>

      <div class="space-y-2">
        <label class="block text-sm font-medium text-zinc-700" for="email">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500"
        />
        <p v-if="errors.email" class="text-xs text-red-600">{{ errors.email }}</p>
      </div>

      <div class="space-y-2">
        <label class="block text-sm font-medium text-zinc-700" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500"
        />
        <p v-if="errors.password" class="text-xs text-red-600">{{ errors.password }}</p>
      </div>

      <div v-if="isRegistering" class="space-y-2">
        <label class="block text-sm font-medium text-zinc-700" for="password_confirmation">Confirm password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500"
        />
        <p v-if="errors.password_confirmation" class="text-xs text-red-600">{{ errors.password_confirmation }}</p>
      </div>

      <div v-if="!isRegistering" class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2">
          <input
            v-model="form.remember"
            type="checkbox"
            class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
          />
          <span class="text-zinc-600">Remember me</span>
        </label>
      </div>

      <p v-if="errors.general" class="text-sm text-red-600">
        {{ errors.general }}
      </p>

      <button
        type="submit"
        class="w-full rounded-md bg-emerald-600 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300"
        :disabled="submitting"
      >
        {{ submitting ? 'Please wait…' : isRegistering ? 'Create account' : 'Sign in' }}
      </button>
    </form>

    <footer class="mt-6 text-center text-sm text-zinc-600">
      <button class="font-medium text-emerald-600 hover:text-emerald-500" type="button" @click="toggleMode">
        {{ isRegistering ? 'Already have an account? Sign in' : 'Need an account? Sign up' }}
      </button>
    </footer>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';

const auth = useAuth();
const router = useRouter();
const route = useRoute();

const mode = ref('login');
const submitting = ref(false);
const errors = reactive({
  general: null,
  name: null,
  email: null,
  password: null,
  password_confirmation: null,
});

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  remember: false,
});

const redirectTo = computed(() => {
  const target = route.query.redirect;
  return typeof target === 'string' && target !== '/auth' ? target : '/dashboard';
});

const isRegistering = computed(() => mode.value === 'register');

const resetErrors = () => {
  errors.general = null;
  errors.name = null;
  errors.email = null;
  errors.password = null;
  errors.password_confirmation = null;
};

const handleSubmit = async () => {
  submitting.value = true;
  resetErrors();

  try {
    if (isRegistering.value) {
      await auth.register({
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      });
    } else {
      await auth.login({
        email: form.email,
        password: form.password,
        remember: form.remember,
      });
    }

    await router.push(redirectTo.value);
  } catch (error) {
    const responseErrors = error.response?.data?.errors;

    if (responseErrors) {
      Object.entries(responseErrors).forEach(([field, messages]) => {
        if (Array.isArray(messages) && messages.length) {
          errors[field] = messages[0];
        }
      });
      errors.general = error.response?.data?.message ?? 'Please fix the highlighted fields.';
    } else {
      errors.general = 'Something went wrong. Please try again.';
    }
  } finally {
    submitting.value = false;
  }
};

const toggleMode = () => {
  mode.value = isRegistering.value ? 'login' : 'register';
  resetErrors();
};

watch(
  () => mode.value,
  () => {
    if (isRegistering.value) {
      form.remember = false;
    } else {
      form.name = '';
      form.password_confirmation = '';
    }
  }
);

const syncModeFromQuery = (value) => {
  if (value === 'register' || value === 'login') {
    mode.value = value;
  }
};

syncModeFromQuery(route.query.mode);

watch(
  () => route.query.mode,
  (value) => {
    if (value) {
      syncModeFromQuery(value);
    }
  }
);
</script>
