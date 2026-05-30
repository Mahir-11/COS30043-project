<template>
  <div>
    <h1 class="mb-4">Tier Lists from the Community</h1>
    <p class="text-muted-tg mb-4">Rank your favourite games and share with others.</p>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
      <div class="d-flex gap-2">
        <button 
          class="btn btn-sm" 
          :class="sortBy === 'likes' ? 'btn-accent' : 'btn-outline-light'"
          @click="sortBy = 'likes'; currentPage = 1"
        >
          <i class="bi bi-hand-thumbs-up"></i> Sort by Likes
        </button>
        <button 
          class="btn btn-sm" 
          :class="sortBy === 'date' ? 'btn-accent' : 'btn-outline-light'"
          @click="sortBy = 'date'; currentPage = 1"
        >
          <i class="bi bi-calendar"></i> Sort by Date
        </button>
      </div>
      <router-link to="/tierlists/new" class="btn btn-accent">
        <i class="bi bi-plus-lg me-1"></i> Create new
      </router-link>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-danger"></div>
    </div>
    <div v-else-if="error" class="alert alert-warning">{{ error }}</div>

    <div v-else>
      <!-- Pagination top -->
      <nav v-if="totalPages > 1" class="mb-3">
        <ul class="pagination justify-content-center">
          <li class="page-item" :class="{ disabled: currentPage === 1 }">
            <button class="page-link" @click="currentPage--">‹</button>
          </li>
          <li v-for="p in totalPages" :key="p" class="page-item" :class="{ active: p === currentPage }">
            <button class="page-link" @click="currentPage = p">{{ p }}</button>
          </li>
          <li class="page-item" :class="{ disabled: currentPage === totalPages }">
            <button class="page-link" @click="currentPage++">›</button>
          </li>
        </ul>
      </nav>

      <div class="row g-4">
        <div v-for="tl in paginatedTierlists" :key="tl.id" class="col-12 col-md-6 col-lg-4">
          <div class="card-tg h-100 d-flex flex-column">
            <div class="p-3 flex-grow-1">
              <h5 class="mb-2">{{ tl.title }}</h5>
              <p class="text-muted-tg small mb-2">
                by {{ getUserName(tl.userId) }} · {{ formatDate(tl.createdAt) }}
              </p>
              <p class="small" v-if="tl.description">{{ tl.description }}</p>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="badge badge-genre">
                  <i class="bi bi-grid-3x3-gap-fill"></i> {{ countGames(tl) }} games
                </span>
                <div class="d-flex gap-2">
                  <span class="badge bg-success">
                    <i class="bi bi-hand-thumbs-up"></i> {{ tl.likeCount }}
                  </span>
                  <span class="badge bg-danger">
                    <i class="bi bi-hand-thumbs-down"></i> {{ tl.dislikeCount }}
                  </span>
                </div>
              </div>
            </div>
            <div class="p-3 pt-0 d-flex gap-2">
              <router-link :to="`/tierlists/${tl.id}`" class="btn btn-sm btn-outline-light flex-grow-1">
                View
              </router-link>
              <button 
                v-if="canDeleteTierlist(tl)" 
                class="btn btn-sm btn-outline-danger" 
                @click.stop="deleteTierlist(tl)"
              >
                <i class="bi bi-trash"></i>
              </button>
            </div>
            <div class="px-3 pb-3 d-flex gap-2">
              <button 
                class="btn btn-sm flex-grow-1"
                :class="getUserReaction(tl) === 'like' ? 'btn-accent' : 'btn-outline-light'"
                @click.stop="toggleReaction(tl, 'like')"
                :disabled="reactionBusy[tl.id]"
              >
                <span v-if="reactionBusy[tl.id]" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi me-1" :class="getUserReaction(tl) === 'like' ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up'"></i>
                Like
              </button>
              <button 
                class="btn btn-sm flex-grow-1"
                :class="getUserReaction(tl) === 'dislike' ? 'btn-accent' : 'btn-outline-light'"
                @click.stop="toggleReaction(tl, 'dislike')"
                :disabled="reactionBusy[tl.id]"
              >
                <span v-if="reactionBusy[tl.id]" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi me-1" :class="getUserReaction(tl) === 'dislike' ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-down'"></i>
                Dislike
              </button>
            </div>
          </div>
        </div>
      </div>

      <nav v-if="totalPages > 1" class="mt-4">
        <ul class="pagination justify-content-center">
          <li class="page-item" :class="{ disabled: currentPage === 1 }">
            <button class="page-link" @click="currentPage--">‹</button>
          </li>
          <li v-for="p in totalPages" :key="p" class="page-item" :class="{ active: p === currentPage }">
            <button class="page-link" @click="currentPage = p">{{ p }}</button>
          </li>
          <li class="page-item" :class="{ disabled: currentPage === totalPages }">
            <button class="page-link" @click="currentPage++">›</button>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { tierlistsApi, usersApi, tierlistReactionsApi } from '../services/api'

const auth = useAuthStore()
const tierlists = ref([])
const users = ref([])
const reactions = ref([])       // { id, tierlistId, userId, type }
const reactionBusy = ref({})
const loading = ref(true)
const error = ref('')
const sortBy = ref('likes')     // 'likes' or 'date'
const currentPage = ref(1)
const pageSize = 9

const tierlistsWithStats = computed(() => {
  return tierlists.value.map(tl => {
    const likes = reactions.value.filter(r => r.tierlistId === tl.id && r.type === 'like').length
    const dislikes = reactions.value.filter(r => r.tierlistId === tl.id && r.type === 'dislike').length
    return { ...tl, likeCount: likes, dislikeCount: dislikes }
  })
})

const sortedTierlists = computed(() => {
  const list = [...tierlistsWithStats.value]
  if (sortBy.value === 'likes') {
    list.sort((a, b) => b.likeCount - a.likeCount)
  } else {
    list.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(sortedTierlists.value.length / pageSize)))
const paginatedTierlists = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return sortedTierlists.value.slice(start, start + pageSize)
})

function getUserName(userId) {
  const user = users.value.find(u => u.id === userId)
  return user ? user.username : `User #${userId}`
}

function formatDate(iso) {
  if (!iso) return 'recent'
  return new Date(iso).toLocaleDateString()
}

function countGames(tl) {
  if (!tl.tiers) return 0
  return Object.values(tl.tiers).reduce((sum, arr) => sum + arr.length, 0)
}

function canDeleteTierlist(tl) {
  if (!auth.isAuthenticated) return false
  if (auth.user?.role === 'admin') return true
  return auth.user?.id === tl.userId
}

function getUserReaction(tl) {
  if (!auth.user) return null
  const react = reactions.value.find(r => r.tierlistId === tl.id && r.userId === auth.user.id)
  return react ? react.type : null
}

async function toggleReaction(tl, newType) {
  if (!auth.isAuthenticated) {
    alert('Please login to react.')
    return
  }
  if (reactionBusy.value[tl.id]) return
  reactionBusy.value[tl.id] = true

  const existing = reactions.value.find(r => r.tierlistId === tl.id && r.userId === auth.user.id)
  try {
    if (existing) {
      if (existing.type === newType) {
        // Remove reaction
        await tierlistReactionsApi.remove(existing.id)
        reactions.value = reactions.value.filter(r => r.id !== existing.id)
      } else {
        // Change reaction
        await tierlistReactionsApi.remove(existing.id)
        const { data } = await tierlistReactionsApi.create({
          tierlistId: tl.id,
          userId: auth.user.id,
          type: newType,
          createdAt: new Date().toISOString()
        })
        reactions.value = reactions.value.filter(r => r.id !== existing.id)
        reactions.value.push(data)
      }
    } else {
      // Add new reaction
      const { data } = await tierlistReactionsApi.create({
        tierlistId: tl.id,
        userId: auth.user.id,
        type: newType,
        createdAt: new Date().toISOString()
      })
      reactions.value.push(data)
    }
  } catch (err) {
    console.error(err)
    alert('Failed to update reaction.')
  } finally {
    delete reactionBusy.value[tl.id]
  }
}

async function deleteTierlist(tl) {
  if (!confirm(`Delete "${tl.title}"? This action cannot be undone.`)) return
  try {
    // Delete all related reactions first
    const related = reactions.value.filter(r => r.tierlistId === tl.id)
    for (const react of related) {
      await tierlistReactionsApi.remove(react.id)
    }
    await tierlistsApi.remove(tl.id)
    await load()
  } catch (err) {
    alert('Failed to delete tier list.')
  }
}

async function load() {
  loading.value = true
  try {
    const [tierRes, userRes, reactRes] = await Promise.all([
      tierlistsApi.list({ _sort: 'createdAt', _order: 'desc' }),
      usersApi.list(),
      tierlistReactionsApi.list()
    ])
    tierlists.value = tierRes.data
    users.value = userRes.data
    reactions.value = reactRes.data
  } catch (err) {
    error.value = 'Could not load tier lists. Is the API running?'
  } finally {
    loading.value = false
  }
}

watch(sortBy, () => { currentPage.value = 1 })
onMounted(load)
</script>
