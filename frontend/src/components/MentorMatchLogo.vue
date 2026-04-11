<script setup>
defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'compact', 'hero'].includes(v),
  },
})
</script>

<template>
  <div class="flex w-full min-w-0 justify-center">
    <img
      src="/logo_mentormatch_2.svg"
      alt="Mentor Match"
      class="mx-auto block object-contain object-center"
      :class="{
        'h-16 w-auto max-w-[400px] sm:h-[4.5rem] sm:max-w-[min(100%,520px)]': variant === 'default',
        'h-12 w-auto max-w-[300px] sm:h-14 sm:max-w-[min(100%,400px)]': variant === 'compact',
        'h-[5.5rem] w-auto max-w-[min(100%,520px)] sm:h-24 sm:max-w-[min(100%,640px)]': variant === 'hero',
      }"
    />
  </div>
</template>
