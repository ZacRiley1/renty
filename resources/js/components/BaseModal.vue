<template>
  <Teleport to="body">
    <transition name="modal" appear>
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8"
        @keydown.escape="emit('close')"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')" />
        <div
          class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"
          role="dialog"
          aria-modal="true"
        >
          <header class="mb-4 flex items-start justify-between gap-4">
            <div>
              <slot name="title" />
              <p v-if="$slots.subtitle" class="mt-1 text-sm text-zinc-500">
                <slot name="subtitle" />
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-zinc-300 hover:text-zinc-700"
              @click="emit('close')"
            >
              <span class="text-lg leading-none">&times;</span>
            </button>
          </header>
          <div class="space-y-5">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="mt-6 flex justify-end gap-3">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
defineProps({
  open: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition:
    opacity 200ms ease,
    transform 200ms ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}
</style>
