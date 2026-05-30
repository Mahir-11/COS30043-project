<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { reportsApi } from '../services/api'

const props = defineProps({
  reviewId: { type: [String, Number], required: true }
})

const REASONS = ['Spam or advertising', 'Harassment or hate', 'Off-topic', 'Spoilers', 'Other']

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const open = ref(false)
const reason = ref('')
const note = ref('')
const busy = ref(false)
const error = ref('')
const reported = ref(false)

const reviewIdNumber = computed(() => Number(props.reviewId))

// Check whether the current user has already flagged this review.
async function checkExisting() {
  reported.value = false
  if (!auth.isAuthenticated || !auth.user?.id) return
  try {
    const { data } = await reportsApi.list({
      reviewId: reviewIdNumber.value,
      userId: auth.user.id,
      status: 'open'
    })
    reported.value = Array.isArray(data) && data.length > 0
  } catch (e) {
    // Non-blocking: if this fails the user can still try to report.
  }
}

function togglePanel() {
  error.value = ''
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  open.value = !open.value
}

function cancel() {
  open.value = false
  reason.value = ''
  note.value = ''
  error.value = ''
}

async function submit() {
  error.value = ''
  if (!reason.value) {
    error.value = 'Please choose a reason.'
    return
  }

  busy.value = true
  try {
    await reportsApi.create({
      reviewId: reviewIdNumber.value,
      reason: reason.value,
      note: note.value
    })
    reported.value = true
    open.value = false
    reason.value = ''
    note.value = ''
  } catch (e) {
    if (e.response?.status === 409) {
      reported.value = true
      open.value = false
      error.value = 'You already reported this review.'
    } else {
      error.value = e.response?.data?.message || 'Could not submit report. Please try again.'
    }
  } finally {
    busy.value = false
  }
}

onMounted(checkExisting)
watch(reviewIdNumber, checkExisting)
</script>

<template>
  <div class="review-report position-relative d-inline-block">
    <button
      v-if="reported"
      class="btn btn-sm btn-outline-secondary"
      type="button"
      disabled
    >
      <i class="bi bi-flag-fill me-1"></i>Reported
    </button>

    <button
      v-else
      class="btn btn-sm btn-outline-secondary"
      type="button"
      :aria-expanded="open"
      @click="togglePanel"
    >
      <i class="bi bi-flag me-1"></i>Report
    </button>

    <!-- Inline reason picker -->
    <div v-if="open" class="report-panel card-tg p-3 mt-2 text-start">
      <label class="form-label small text-muted-tg mb-1">Why are you reporting this?</label>
      <select v-model="reason" class="form-select form-select-sm mb-2">
        <option value="" disabled>Choose a reason...</option>
        <option v-for="r in REASONS" :key="r" :value="r">{{ r }}</option>
      </select>

      <textarea
        v-model="note"
        class="form-control form-control-sm mb-2"
        rows="2"
        maxlength="280"
        placeholder="Add an optional note for the moderator..."
      ></textarea>

      <div v-if="error" class="small text-warning mb-2">{{ error }}</div>

      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-danger flex-grow-1" type="button" :disabled="busy" @click="submit">
          <span v-if="busy" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
          Submit report
        </button>
        <button class="btn btn-sm btn-outline-light" type="button" :disabled="busy" @click="cancel">
          Cancel
        </button>
      </div>
    </div>

    <div v-else-if="error" class="small text-warning mt-1">{{ error }}</div>
  </div>
</template>

<style scoped>
.report-panel {
  position: absolute;
  right: 0;
  z-index: 20;
  width: min(20rem, 80vw);
}
</style>
