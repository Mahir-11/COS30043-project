<template>
  <div class="card-tg p-4 mt-4">
    <h4 class="mb-3 text-light">
      <i class="bi bi-pencil-square me-2 text-danger"></i>Write a Review
    </h4>
    
    <form @submit.prevent="handleSubmit" novalidate>
      <div class="mb-3">
        <label class="form-label d-block text-muted-tg small">Your Rating</label>
        <ReviewRating v-model="form.rating" :show-label="true" />
        <div v-if="errors.rating" class="text-danger small mt-1">
          {{ errors.rating }}
        </div>
      </div>

      <div class="mb-3">
        <label for="reviewTitle" class="form-label text-muted-tg small">Review Title</label>
        <input
          id="reviewTitle"
          v-model.trim="form.title"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.title }"
          placeholder="Summarize your opinion in a few words..."
        />
        <div class="invalid-feedback">{{ errors.title }}</div>
      </div>

      <div class="mb-3">
        <label for="reviewBody" class="form-label text-muted-tg small">Review Content</label>
        <textarea
          id="reviewBody"
          v-model.trim="form.body"
          class="form-control"
          :class="{ 'is-invalid': errors.body }"
          rows="4"
          placeholder="What did you like or dislike about this game? Be blunt!"
        ></textarea>
        <div class="invalid-feedback">{{ errors.body }}</div>
      </div>

      <div class="text-end">
        <button 
          type="submit" 
          class="btn btn-accent px-4" 
          :disabled="isSubmitting"
        >
          <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
          Submit Review
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import ReviewRating from './ReviewRating.vue'

const emit = defineEmits(['submit-review'])

defineProps({
  isSubmitting: {
    type: Boolean,
    default: false
  }
})

const form = reactive({
  title: '',
  rating: 0,
  body: ''
})

const errors = reactive({
  title: '',
  rating: '',
  body: ''
})

const clearErrors = () => {
  errors.title = ''
  errors.rating = ''
  errors.body = ''
}

const validateForm = () => {
  clearErrors()
  let isValid = true

  if (form.rating === 0) {
    errors.rating = 'Please select a rating score from 1 to 5 stars.'
    isValid = false
  }

  if (!form.title) {
    errors.title = 'Review title cannot be left empty.'
    isValid = false
  } else if (form.title.length < 3) {
    errors.title = 'Title must be at least 3 characters long.'
    isValid = false
  }

  if (!form.body) {
    errors.body = 'Review content cannot be left empty.'
    isValid = false
  } else if (form.body.length < 10) {
    errors.body = 'Review content must be at least 10 characters long.'
    isValid = false
  }

  return isValid
}

const handleSubmit = () => {
  if (!validateForm()) return

  emit('submit-review', {
    title: form.title,
    rating: form.rating,
    body: form.body
  })
}

const resetForm = () => {
  form.title = ''
  form.rating = 0
  form.body = ''
  clearErrors()
}

defineExpose({ resetForm })
</script>