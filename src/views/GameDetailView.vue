<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { gamesApi, reviewsApi, usersApi } from '../services/api'
import ReviewVoteButton from '../components/ReviewVoteButton.vue'
import ReviewReportButton from '../components/ReviewReportButton.vue' // [Person 5]
import { useAuthStore } from '../stores/auth'
import ReviewRating from '../components/ReviewRating.vue'
import ReviewForm from '../components/ReviewForm.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })
const route = useRoute()
const auth = useAuthStore()

const game = ref(null)
const reviews = ref([])
const users = ref([])
const loading = ref(true)
const error = ref('')

const reviewPage = ref(1) //Reviews are now paginated, showing 4 reviews per page. 
const reviewPageSize = 4

//P3 handle form status
const isSubmitting = ref(false) 
const reviewFormRef = ref(null) 

// P3 advanced feature: state for selected star filter
const selectedRatingFilter = ref(null)


const userMap = computed(() => { 
  return Object.fromEntries(users.value.map((user) => [Number(user.id), user]))
})

// P3 advanced feature: compute rating statistics for dashboard
const ratingStats = computed(() => {
  const total = reviews.value.length
  if (total === 0) {
    return { average: 0, total: 0, counts: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }, percentages: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 } }
  }

  let sum = 0
  const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }

  reviews.value.forEach(r => {
    const rate = Number(r.rating)
    sum += rate
    if (counts[rate] !== undefined) counts[rate]++
  })

  const percentages = {}
  for (let i = 1; i <= 5; i++) {
    percentages[i] = Math.round((counts[i] / total) * 100)
  }

  return {
    average: (sum / total).toFixed(1),
    total,
    counts,
    percentages
  }
})

// P3 advanced feature: filter reviews array by selected star rating
const filteredReviews = computed(() => {
  if (!selectedRatingFilter.value) {
    return reviews.value
  }
  return reviews.value.filter(review => Number(review.rating) === selectedRatingFilter.value)
})

// P3 advanced feature: toggle rating filter selection
function toggleRatingFilter(star) {
  if (selectedRatingFilter.value === star) {
    selectedRatingFilter.value = null
  } else {
    selectedRatingFilter.value = star
  }
  reviewPage.value = 1
}

// P3 updated: calculate pages based on filtered reviews
const totalReviewPages = computed(() => Math.max(1, Math.ceil(filteredReviews.value.length / reviewPageSize)))

// P3 updated: paginate filtered reviews list
const pagedReviews = computed(() => {
  const start = (reviewPage.value - 1) * reviewPageSize
  return filteredReviews.value.slice(start, start + reviewPageSize)
})

function reviewerName(userId) { 
  return userMap.value[Number(userId)]?.username || `User #${userId}`
}

function setReviewPage(page) {
  if (page < 1 || page > totalReviewPages.value) return
  reviewPage.value = page
}
//P3

// P3 sent review to API
async function handleReviewSubmit(payload) {
  isSubmitting.value = true
  try {
    const newReview = {
      gameId: Number(props.id), 
      userId: Number(auth.user.id), 
      rating: Number(payload.rating), 
      title: payload.title, 
      body: payload.body, 
      createdAt: new Date().toISOString() 
    }
    
    const { data } = await reviewsApi.create(newReview)
    
    reviews.value = [data, ...reviews.value]
    
    if (reviewFormRef.value) {
      reviewFormRef.value.resetForm()
    }
  } catch (e) {
    alert('Failed to submit your review. Please check if the API server is running.')
  } finally {
    isSubmitting.value = false
  }
}
//P3

async function load(id) {
  loading.value = true
  error.value = ''
  reviewPage.value = 1
  selectedRatingFilter.value = null // P3 advanced feature: reset filter when changing games

  try {
    const [{ data: gameData }, { data: reviewData }, { data: userData }] = await Promise.all([
      gamesApi.get(id),
      reviewsApi.list({ gameId: Number(id), _sort: 'createdAt', _order: 'desc' }),
      usersApi.list()
    ])
    game.value = gameData
    reviews.value = reviewData
    users.value = userData
  } catch (e) {
    error.value = e.message || 'Game not found.'
  } finally {
    loading.value = false
  }
}

onMounted(() => load(props.id))
watch(() => route.params.id, (id) => { if (id) load(id) })
</script>

<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-danger" role="status"></div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <template v-else-if="game">
    <div class="row g-4 mb-5">
      <div class="col-12 col-md-4 col-lg-3">
        <img :src="game.cover" :alt="game.title" class="img-fluid rounded shadow-lg"
             @error="$event.target.src='https://placehold.co/300x400/171a21/9aa3b2?text=No+Cover'" />
      </div>
      <div class="col-12 col-md-8 col-lg-9">
        <h1 class="display-6 fw-bold mb-2">{{ game.title }}</h1>
        <div class="mb-3">
          <span class="badge badge-genre me-2">{{ game.genre }}</span>
          <span class="text-muted-tg">{{ game.developer }} &middot; {{ game.year }}</span>
        </div>
        <p class="lead">{{ game.summary }}</p>
        <div class="mb-3">
          <strong class="text-muted-tg small d-block mb-1">Platforms</strong>
          <span v-for="p in game.platforms" :key="p" class="badge badge-genre me-1">{{ p }}</span>
        </div>
        <router-link to="/games" class="btn btn-outline-light btn-sm mt-2">
          <i class="bi bi-arrow-left me-1"></i>Back to games
        </router-link>
      </div>
    </div>

    <section>
      <h3 class="mb-3">Reviews <span class="text-muted-tg">({{ reviews.length }})</span></h3>
      
      <div v-if="reviews.length > 0" class="card-tg p-4 mb-4 hero-tg">
        <div class="row align-items-center g-3">
          <div class="col-12 col-md-4 text-center border-end border-secondary-subtle border-md-end-none">
            <h1 class="display-3 fw-bold text-light mb-0">{{ ratingStats.average }}</h1>
            <div class="my-1">
              <ReviewRating :model-value="Math.round(Number(ratingStats.average))" :readonly="true" />
            </div>
            <p class="text-muted-tg small mb-0">Community Rating Average</p>
          </div>
          <div class="col-12 col-md-8">
            <p class="text-muted-tg small mb-2 d-none d-md-block">Click on any row below to filter reviews by star score:</p>
            <div 
              v-for="star in [5, 4, 3, 2, 1]" 
              :key="star" 
              class="dashboard-row d-flex align-items-center gap-2 py-1 px-2 rounded"
              :class="{ 'active-filter': selectedRatingFilter === star }"
              @click="toggleRatingFilter(star)"
            >
              <span class="text-light small fw-bold" style="width: 45px;">{{ star }} Star</span>
              <div class="progress flex-grow-1 bg-dark-tg" style="height: 10px;">
                <div 
                  class="progress-bar" 
                  :class="selectedRatingFilter === star ? 'bg-danger' : 'bg-warning'"
                  role="progressbar" 
                  :style="{ width: ratingStats.percentages[star] + '%' }" 
                  :aria-valuenow="ratingStats.percentages[star]" 
                  aria-valuemin="0" 
                  aria-valuemax="100"
                ></div>
              </div>
              <span class="text-muted-tg small text-end" style="width: 70px;">
                {{ ratingStats.percentages[star] }}% ({{ ratingStats.counts[star] }})
              </span>
            </div>
            <div v-if="selectedRatingFilter" class="text-end mt-2">
              <button class="btn btn-sm btn-link text-danger p-0 small" @click="selectedRatingFilter = null">
                Clear filter and show all reviews
              </button>
            </div>
          </div>
        </div>
      </div>

      <ReviewForm 
        v-if="auth.isAuthenticated" 
        ref="reviewFormRef"
        :is-submitting="isSubmitting" 
        @submit-review="handleReviewSubmit" 
      />
      <div v-else class="card-tg p-3 text-center mb-4 text-muted-tg small">
        Please <router-link to="/login" class="fw-bold text-danger">Sign in</router-link> to write a blunt review for this game.
      </div>
      
      <div v-if="reviews.length === 0" class="text-muted-tg">No reviews yet.</div>
      
      <div v-else-if="filteredReviews.length === 0" class="text-muted-tg text-center py-4">
        <p class="mb-0">No {{ selectedRatingFilter }}-star reviews found for this game.</p>
      </div>

      <TransitionGroup name="review-list" tag="div" class="position-relative">
        <div v-for="review in pagedReviews" :key="review.id" class="card-tg p-3 p-md-4 mb-3">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <strong class="fs-5">{{ review.title }}</strong>
                <ReviewRating :model-value="Number(review.rating)" :readonly="true" />
              </div>
              <p class="mb-2">{{ review.body }}</p>
              <small class="text-muted-tg">
                Posted by {{ reviewerName(review.userId) }} · {{ new Date(review.createdAt).toLocaleDateString() }}
              </small>
            </div>
            <div class="review-action-panel text-md-end">
              <ReviewVoteButton :review-id="review.id" />
              <!-- [Person 5] flag this review for moderation -->
              <div class="mt-2">
                <ReviewReportButton :review-id="review.id" />
              </div>
            </div>
          </div>
        </div>
      </TransitionGroup>

      <nav v-if="totalReviewPages > 1" class="mt-3">
        <ul class="pagination justify-content-center">
          <li class="page-item" :class="{ disabled: reviewPage === 1 }">
            <button class="page-link" @click="setReviewPage(reviewPage - 1)" aria-label="Previous">‹</button>
          </li>
          <li v-for="p in totalReviewPages" :key="p" class="page-item" :class="{ active: p === reviewPage }">
            <button class="page-link" @click="setReviewPage(p)">{{ p }}</button>
          </li>
          <li class="page-item" :class="{ disabled: reviewPage === totalReviewPages }">
            <button class="page-link" @click="setReviewPage(reviewPage + 1)" aria-label="Next">›</button>
          </li>
        </ul>
      </nav>
    </section>
  </template>
</template>

<style scoped>
/* P3 advanced feature: styles for interactive dashboard rows */
.dashboard-row {
  cursor: pointer;
  transition: background-color 0.2s ease, transform 0.15s ease;
}
.dashboard-row:hover {
  background-color: rgba(255, 255, 255, 0.05);
  transform: translateX(4px);
}
.bg-dark-tg {
  background-color: var(--tg-surface-2) !important;
}
.active-filter {
  background-color: rgba(255, 90, 95, 0.1) !important;
  border-left: 3px solid var(--tg-accent);
}

/* P3 advanced feature: transition group animations */
.review-list-enter-from,
.review-list-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
.review-list-enter-active,
.review-list-leave-active {
  transition: all 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}
.review-list-move {
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.review-list-leave-active {
  position: absolute;
  width: 100%;
  z-index: 0;
}
</style>
