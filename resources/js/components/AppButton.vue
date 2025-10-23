<template>
  <component
    :is="resolvedComponent"
    v-bind="componentAttrs"
    class="inline-flex items-center justify-center gap-2 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-60"
    :class="[sizeClasses, variantClasses, block ? 'w-full' : '']"
  >
    <slot />
  </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
  to: [String, Object],
  href: String,
  size: {
    type: String,
    default: 'md',
  },
  variant: {
    type: String,
    default: 'primary',
  },
  block: {
    type: Boolean,
    default: false,
  },
});

const resolvedComponent = computed(() => {
  if (props.to) {
    return RouterLink;
  }

  if (props.href) {
    return 'a';
  }

  return 'button';
});

const componentAttrs = computed(() => {
  if (props.to) {
    return { to: props.to };
  }

  if (props.href) {
    return {
      href: props.href,
      rel: 'noreferrer noopener',
    };
  }

  return { type: 'button' };
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'rounded-xl px-4 py-2 text-sm';
    case 'lg':
      return 'rounded-xl px-6 py-3 text-base';
    default:
      return 'rounded-xl px-5 py-2.5 text-sm';
  }
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'bg-white text-brand-dark shadow-sm ring-1 ring-inset ring-white/20 hover:bg-white/90 focus-visible:outline-brand-accent';
    case 'ghost':
      return 'bg-transparent text-white hover:bg-white/10 focus-visible:outline-brand-accent';
    default:
      return 'bg-brand-primary text-white hover:bg-brand-primary-dark focus-visible:outline-brand-accent';
  }
});
</script>
