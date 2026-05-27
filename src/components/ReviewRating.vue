<template>
  <div class="review-rating d-inline-flex align-items-center" aria-label="Game Rating Stars">
    <button
      v-for="star in 5"
      :key="star"
      type="button"
      class="btn-star-trigger"
      :class="{ 'readonly': readonly }"
      :disabled="readonly"
      @click="updateRating(star)"
      @mouseover="handleMouseOver(star)"
      @mouseleave="handleMouseLeave"
    >
      <i 
        class="bi" 
        :class="star <= (hoverValue || modelValue) ? 'bi-star-fill text-warning-tg' : 'bi-star text-muted-custom'"
      ></i>
    </button>
    
    <span v-if="showLabel && modelValue > 0" class="ms-2 text-muted-tg small">
      ({{ modelValue }}/5)
    </span>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: Number,
    default: 0
  },
  readonly: {
    type: Boolean,
    default: false
  },
  showLabel: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const hoverValue = ref(0)

const updateRating = (value) => {
  if (props.readonly) return
  emit('update:modelValue', value)
}

const handleMouseOver = (value) => {
  if (props.readonly) return
  hoverValue.value = value
}

const handleMouseLeave = () => {
  if (props.readonly) return
  hoverValue.value = 0
}
</script>

<style scoped>
.btn-star-trigger {
  background: none;
  border: none;
  padding: 0 2px;
  margin: 0;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.text-warning-tg {
  color: #ffc107; 
}

.text-muted-custom {
  color: var(--tg-muted); 
}

.btn-star-trigger:not(.readonly):hover {
  transform: scale(1.2);
}

.btn-star-trigger.readonly {
  cursor: default;
  padding: 0 1px;
}
</style>