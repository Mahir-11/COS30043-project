<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { adminApi, gamesApi, reviewsApi, usersApi, votesApi } from '../services/api'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const activeTab = ref('games')

const games = ref([])
const reviews = ref([])
const users = ref([])
const votes = ref([])
const reports = ref([]) // [Person 5]

const gameSearch = ref('')
const reviewSearch = ref('')
const gamePage = ref(1)
const reviewPage = ref(1)
const pageSize = 6
const editingId = ref(null)
const formErrors = reactive({})

const emptyGame = {
  title: '',
  genre: '',
  developer: '',
  year: new Date().getFullYear(),
  platformsText: 'PC',
  cover: '',
  summary: '',
  featured: false
}

const gameForm = reactive({ ...emptyGame })

const stats = computed(() => ({
  games: games.value.length,
  reviews: reviews.value.length,
  votes: votes.value.length,
  users: users.value.length,
  featured: games.value.filter((game) => game.featured).length,
  openReports: reports.value.length // [Person 5]
}))

const userMap = computed(() => Object.fromEntries(users.value.map((user) => [Number(user.id), user])))
const gameMap = computed(() => Object.fromEntries(games.value.map((game) => [Number(game.id), game])))

// [Person 5] reviewMap and a grouped view of open reports for the moderation queue
const reviewMap = computed(() => Object.fromEntries(reviews.value.map((review) => [Number(review.id), review])))

const reportGroups = computed(() => {
  const groups = {}
  for (const report of reports.value) {
    const key = Number(report.reviewId)
    if (!groups[key]) {
      const review = reviewMap.value[key] || null
      groups[key] = {
        reviewId: key,
        review,
        reviewTitle: review?.title || 'Deleted review',
        reviewBody: review?.body || '',
        gameTitle: review ? (gameMap.value[Number(review.gameId)]?.title || 'Deleted game') : '—',
        authorName: review ? (userMap.value[Number(review.userId)]?.username || `User #${review.userId}`) : '—',
        reports: []
      }
    }
    groups[key].reports.push({
      ...report,
      reporterName: userMap.value[Number(report.userId)]?.username || `User #${report.userId}`
    })
  }
  return Object.values(groups).sort((a, b) => b.reports.length - a.reports.length)
})

const filteredGames = computed(() => {
  const q = gameSearch.value.trim().toLowerCase()
  if (!q) return games.value
  return games.value.filter((game) => {
    return [game.title, game.genre, game.developer, String(game.year)]
      .some((field) => String(field).toLowerCase().includes(q))
  })
})

const gameTotalPages = computed(() => Math.max(1, Math.ceil(filteredGames.value.length / pageSize)))
const pagedGames = computed(() => {
  const start = (gamePage.value - 1) * pageSize
  return filteredGames.value.slice(start, start + pageSize)
})

const joinedReviews = computed(() => reviews.value.map((review) => ({
  ...review,
  gameTitle: gameMap.value[Number(review.gameId)]?.title || 'Deleted game',
  username: userMap.value[Number(review.userId)]?.username || `User #${review.userId}`,
  voteCount: votes.value.filter((vote) => Number(vote.reviewId) === Number(review.id)).length
})))

const filteredReviews = computed(() => {
  const q = reviewSearch.value.trim().toLowerCase()
  if (!q) return joinedReviews.value
  return joinedReviews.value.filter((review) => {
    return [review.title, review.body, review.gameTitle, review.username]
      .some((field) => String(field).toLowerCase().includes(q))
  })
})

const reviewTotalPages = computed(() => Math.max(1, Math.ceil(filteredReviews.value.length / pageSize)))
const pagedReviews = computed(() => {
  const start = (reviewPage.value - 1) * pageSize
  return filteredReviews.value.slice(start, start + pageSize)
})

function setGamePage(page) {
  if (page < 1 || page > gameTotalPages.value) return
  gamePage.value = page
}

function setReviewPage(page) {
  if (page < 1 || page > reviewTotalPages.value) return
  reviewPage.value = page
}

function resetForm() {
  Object.assign(gameForm, emptyGame)
  editingId.value = null
  Object.keys(formErrors).forEach((key) => delete formErrors[key])
}

function editGame(game) {
  editingId.value = game.id
  Object.assign(gameForm, {
    title: game.title,
    genre: game.genre,
    developer: game.developer,
    year: game.year,
    platformsText: Array.isArray(game.platforms) ? game.platforms.join(', ') : String(game.platforms || ''),
    cover: game.cover,
    summary: game.summary,
    featured: Boolean(game.featured)
  })
  activeTab.value = 'games'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function validateGame() {
  Object.keys(formErrors).forEach((key) => delete formErrors[key])
  const currentYear = new Date().getFullYear() + 1
  const platforms = gameForm.platformsText.split(',').map((item) => item.trim()).filter(Boolean)

  if (gameForm.title.trim().length < 2) formErrors.title = 'Title must be at least 2 characters.'
  if (gameForm.genre.trim().length < 2) formErrors.genre = 'Genre is required.'
  if (gameForm.developer.trim().length < 2) formErrors.developer = 'Developer is required.'
  if (!Number(gameForm.year) || Number(gameForm.year) < 1970 || Number(gameForm.year) > currentYear) {
    formErrors.year = `Year must be between 1970 and ${currentYear}.`
  }
  if (!platforms.length) formErrors.platformsText = 'Add at least one platform.'
  if (gameForm.summary.trim().length < 20) formErrors.summary = 'Summary should be at least 20 characters.'

  return Object.keys(formErrors).length === 0
}

function gamePayload() {
  return {
    title: gameForm.title.trim(),
    genre: gameForm.genre.trim(),
    developer: gameForm.developer.trim(),
    year: Number(gameForm.year),
    platforms: gameForm.platformsText.split(',').map((item) => item.trim()).filter(Boolean),
    cover: gameForm.cover.trim() || 'https://placehold.co/300x400/171a21/9aa3b2?text=No+Cover',
    summary: gameForm.summary.trim(),
    featured: Boolean(gameForm.featured)
  }
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const [{ data: gameData }, { data: reviewData }, { data: userData }, { data: voteData }, { data: reportData }] = await Promise.all([
      gamesApi.list({ _sort: 'title', _order: 'asc' }),
      reviewsApi.list({ _sort: 'createdAt', _order: 'desc' }),
      usersApi.list(),
      votesApi.list(),
      adminApi.listReports({ status: 'open' }) // [Person 5]
    ])

    games.value = gameData
    reviews.value = reviewData
    users.value = userData
    votes.value = voteData
    reports.value = reportData // [Person 5]
  } catch (e) {
    error.value = 'Could not load the dashboard. Check that the API server is running.'
  } finally {
    loading.value = false
  }
}

async function saveGame() {
  success.value = ''
  error.value = ''
  if (!validateGame()) return

  saving.value = true
  try {
    if (editingId.value) {
      const { data } = await adminApi.updateGame(editingId.value, gamePayload())
      games.value = games.value.map((game) => Number(game.id) === Number(editingId.value) ? data : game)
      success.value = 'Game updated successfully.'
    } else {
      const { data } = await adminApi.createGame(gamePayload())
      games.value = [...games.value, data].sort((a, b) => a.title.localeCompare(b.title))
      success.value = 'New game added successfully.'
    }
    resetForm()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save game. Admin endpoint may not be running.'
  } finally {
    saving.value = false
  }
}

async function deleteGame(game) {
  const confirmed = window.confirm(`Remove "${game.title}" and its related reviews/votes?`)
  if (!confirmed) return

  error.value = ''
  success.value = ''
  try {
    await adminApi.removeGame(game.id)
    await loadDashboard()
    success.value = 'Game and related content removed.'
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not remove game.'
  }
}

async function deleteReview(review) {
  const confirmed = window.confirm(`Remove review "${review.title}" from ${review.gameTitle}?`)
  if (!confirmed) return

  error.value = ''
  success.value = ''
  try {
    await adminApi.removeReview(review.id)
    await loadDashboard()
    success.value = 'Review removed and its likes were cleared.'
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not remove review.'
  }
}

// [Person 5] dismiss every open report on a review (keeps the review)
async function dismissReports(group) {
  error.value = ''
  success.value = ''
  try {
    await adminApi.dismissReports(group.reviewId)
    await loadDashboard()
    success.value = `Cleared ${group.reports.length} report(s) on "${group.reviewTitle}".`
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not dismiss reports.'
  }
}

// [Person 5] remove a reported review entirely (also clears its reports + likes)
async function removeReportedReview(group) {
  const confirmed = window.confirm(`Remove review "${group.reviewTitle}" from ${group.gameTitle}? This also clears its reports and likes.`)
  if (!confirmed) return

  error.value = ''
  success.value = ''
  try {
    await adminApi.removeReview(group.reviewId)
    await loadDashboard()
    success.value = 'Reported review removed.'
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not remove review.'
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <p class="text-muted-tg small mb-1">Admin only</p>
      <h1 class="mb-0">Social &amp; Admin Dashboard</h1>
    </div>
    <button class="btn btn-outline-light" type="button" @click="loadDashboard">
      <i class="bi bi-arrow-clockwise me-1"></i>Refresh data
    </button>
  </div>

  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-danger" role="status"></div>
  </div>

  <template v-else>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="success" class="alert alert-success">{{ success }}</div>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg" v-for="item in [
        { label: 'Games', value: stats.games, icon: 'bi-controller' },
        { label: 'Reviews', value: stats.reviews, icon: 'bi-chat-left-text' },
        { label: 'Likes', value: stats.votes, icon: 'bi-hand-thumbs-up' },
        { label: 'Users', value: stats.users, icon: 'bi-people' },
        { label: 'Featured', value: stats.featured, icon: 'bi-star' },
        { label: 'Open reports', value: stats.openReports, icon: 'bi-flag' }
      ]" :key="item.label">
        <div class="card-tg p-3 h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <p class="text-muted-tg small mb-1">{{ item.label }}</p>
              <h3 class="mb-0">{{ item.value }}</h3>
            </div>
            <i class="bi fs-2 text-muted-tg" :class="item.icon"></i>
          </div>
        </div>
      </div>
    </div>

    <ul class="nav nav-pills admin-tabs mb-4">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'games' }" type="button" @click="activeTab = 'games'">
          Manage games
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'reviews' }" type="button" @click="activeTab = 'reviews'">
          Moderate reviews
        </button>
      </li>
      <!-- [Person 5] Reports tab with live open-count badge -->
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'reports' }" type="button" @click="activeTab = 'reports'">
          Reports
          <span v-if="stats.openReports" class="badge text-bg-danger ms-1">{{ stats.openReports }}</span>
        </button>
      </li>
    </ul>

    <section v-if="activeTab === 'games'">
      <div class="row g-4">
        <div class="col-12 col-xl-4">
          <div class="card-tg p-4 sticky-xl-top admin-form-card">
            <h4 class="mb-3">{{ editingId ? 'Edit game' : 'Add new game' }}</h4>
            <form @submit.prevent="saveGame" novalidate>
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input v-model.trim="gameForm.title" class="form-control" :class="{ 'is-invalid': formErrors.title }" required />
                <div class="invalid-feedback">{{ formErrors.title }}</div>
              </div>

              <div class="row g-2">
                <div class="col-12 col-md-6">
                  <label class="form-label">Genre</label>
                  <input v-model.trim="gameForm.genre" class="form-control" :class="{ 'is-invalid': formErrors.genre }" required />
                  <div class="invalid-feedback">{{ formErrors.genre }}</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Year</label>
                  <input v-model.number="gameForm.year" class="form-control" :class="{ 'is-invalid': formErrors.year }" type="number" min="1970" required />
                  <div class="invalid-feedback">{{ formErrors.year }}</div>
                </div>
              </div>

              <div class="mb-3 mt-3">
                <label class="form-label">Developer</label>
                <input v-model.trim="gameForm.developer" class="form-control" :class="{ 'is-invalid': formErrors.developer }" required />
                <div class="invalid-feedback">{{ formErrors.developer }}</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Platforms <span class="text-muted-tg small">comma separated</span></label>
                <input v-model="gameForm.platformsText" class="form-control" :class="{ 'is-invalid': formErrors.platformsText }" placeholder="PC, PS5, Xbox" required />
                <div class="invalid-feedback">{{ formErrors.platformsText }}</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Cover URL</label>
                <input v-model.trim="gameForm.cover" class="form-control" type="url" placeholder="https://..." />
              </div>

              <div class="mb-3">
                <label class="form-label">Summary</label>
                <textarea v-model.trim="gameForm.summary" class="form-control" :class="{ 'is-invalid': formErrors.summary }" rows="4" required></textarea>
                <div class="invalid-feedback">{{ formErrors.summary }}</div>
              </div>

              <div class="form-check form-switch mb-3">
                <input id="featuredSwitch" v-model="gameForm.featured" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="featuredSwitch">Show as featured</label>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-accent flex-grow-1" type="submit" :disabled="saving">
                  <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                  {{ editingId ? 'Update game' : 'Create game' }}
                </button>
                <button class="btn btn-outline-light" type="button" @click="resetForm">Clear</button>
              </div>
            </form>
          </div>
        </div>

        <div class="col-12 col-xl-8">
          <div class="card-tg p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
              <h4 class="mb-0">Game catalogue</h4>
              <input v-model="gameSearch" class="form-control admin-search" type="search" placeholder="Search games..." @input="gamePage = 1" />
            </div>

            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle admin-table mb-0">
                <thead>
                  <tr>
                    <th>Game</th>
                    <th>Genre</th>
                    <th>Year</th>
                    <th>Featured</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="game in pagedGames" :key="game.id">
                    <td>
                      <div class="fw-semibold">{{ game.title }}</div>
                      <small class="text-muted-tg">{{ game.developer }}</small>
                    </td>
                    <td>{{ game.genre }}</td>
                    <td>{{ game.year }}</td>
                    <td>
                      <span class="badge" :class="game.featured ? 'text-bg-warning' : 'badge-genre'">
                        {{ game.featured ? 'Yes' : 'No' }}
                      </span>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-light me-2" type="button" @click="editGame(game)">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteGame(game)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <nav v-if="gameTotalPages > 1" class="mt-3" aria-label="Admin game pagination">
              <ul class="pagination justify-content-end mb-0">
                <li class="page-item" :class="{ disabled: gamePage === 1 }">
                  <button class="page-link" type="button" @click="setGamePage(gamePage - 1)">‹</button>
                </li>
                <li v-for="page in gameTotalPages" :key="page" class="page-item" :class="{ active: page === gamePage }">
                  <button class="page-link" type="button" @click="setGamePage(page)">{{ page }}</button>
                </li>
                <li class="page-item" :class="{ disabled: gamePage === gameTotalPages }">
                  <button class="page-link" type="button" @click="setGamePage(gamePage + 1)">›</button>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </section>

    <section v-else-if="activeTab === 'reviews'">
      <div class="card-tg p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
          <div>
            <h4 class="mb-1">Review moderation</h4>
            <p class="text-muted-tg small mb-0">Remove inappropriate content and automatically clear its likes.</p>
          </div>
          <input v-model="reviewSearch" class="form-control admin-search" type="search" placeholder="Search reviews..." @input="reviewPage = 1" />
        </div>

        <div v-if="filteredReviews.length === 0" class="text-center text-muted-tg py-5">
          <i class="bi bi-shield-check" style="font-size: 3rem;"></i>
          <p class="mt-3 mb-0">No reviews match the current search.</p>
        </div>

        <div v-for="review in pagedReviews" :key="review.id" class="border-bottom border-secondary-subtle py-3">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <strong>{{ review.title }}</strong>
                <span class="text-warning small">
                  <i v-for="n in Number(review.rating)" :key="n" class="bi bi-star-fill"></i>
                </span>
                <span class="badge badge-genre">{{ review.voteCount }} likes</span>
              </div>
              <p class="mb-1">{{ review.body }}</p>
              <small class="text-muted-tg">{{ review.gameTitle }} · by {{ review.username }}</small>
            </div>
            <div class="text-lg-end">
              <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteReview(review)">
                <i class="bi bi-trash me-1"></i>Remove
              </button>
            </div>
          </div>
        </div>

        <nav v-if="reviewTotalPages > 1" class="mt-3" aria-label="Admin review pagination">
          <ul class="pagination justify-content-end mb-0">
            <li class="page-item" :class="{ disabled: reviewPage === 1 }">
              <button class="page-link" type="button" @click="setReviewPage(reviewPage - 1)">‹</button>
            </li>
            <li v-for="page in reviewTotalPages" :key="page" class="page-item" :class="{ active: page === reviewPage }">
              <button class="page-link" type="button" @click="setReviewPage(page)">{{ page }}</button>
            </li>
            <li class="page-item" :class="{ disabled: reviewPage === reviewTotalPages }">
              <button class="page-link" type="button" @click="setReviewPage(reviewPage + 1)">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </section>

    <!-- [Person 5] community moderation queue -->
    <section v-else>
      <div class="card-tg p-3 p-md-4">
        <div class="mb-3">
          <h4 class="mb-1">Reported content</h4>
          <p class="text-muted-tg small mb-0">
            Reviews flagged by the community. Dismiss the reports if the content is fine, or remove the review.
          </p>
        </div>

        <div v-if="reportGroups.length === 0" class="text-center text-muted-tg py-5">
          <i class="bi bi-shield-check" style="font-size: 3rem;"></i>
          <p class="mt-3 mb-0">Nothing in the queue — no open reports.</p>
        </div>

        <div v-for="group in reportGroups" :key="group.reviewId" class="border-bottom border-secondary-subtle py-3">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <strong>{{ group.reviewTitle }}</strong>
                <span class="badge text-bg-danger">
                  <i class="bi bi-flag-fill me-1"></i>{{ group.reports.length }} report{{ group.reports.length === 1 ? '' : 's' }}
                </span>
              </div>
              <p class="mb-1">{{ group.reviewBody }}</p>
              <small class="text-muted-tg">{{ group.gameTitle }} · by {{ group.authorName }}</small>

              <ul class="list-unstyled mt-2 mb-0 report-reason-list">
                <li v-for="report in group.reports" :key="report.id" class="small mb-1">
                  <span class="badge badge-genre me-2">{{ report.reason }}</span>
                  <span class="text-muted-tg">
                    by {{ report.reporterName }}
                    <template v-if="report.note">— "{{ report.note }}"</template>
                  </span>
                </li>
              </ul>
            </div>

            <div class="text-lg-end d-flex flex-row flex-lg-column gap-2 align-self-start">
              <button class="btn btn-sm btn-outline-light" type="button" @click="dismissReports(group)">
                <i class="bi bi-check2 me-1"></i>Dismiss
              </button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="removeReportedReview(group)">
                <i class="bi bi-trash me-1"></i>Remove review
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </template>
</template>
