<template>
  <div>
    <h1 class="mb-4">Tier Lists from the Community</h1>
    <p class="text-muted-tg mb-4">Rank your favourite games and share with others.</p>

    <div class="d-flex justify-content-end mb-3">
      <router-link to="/tierlists/new" class="btn btn-accent">
        <i class="bi bi-plus-lg me-1"></i> Create new
      </router-link>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-danger"></div>
    </div>

    <div v-else-if="error" class="alert alert-warning">{{ error }}</div>

    <div v-else>
      <div v-if="tierlists.length === 0" class="text-center py-5">
        <i class="bi bi-emoji-frown" style="font-size: 3rem;"></i>
        <p class="mt-3">No tier lists created yet. Be the first!</p>
      </div>

      <div class="row g-4">
        <div v-for="tl in tierlists" :key="tl.id" class="col-12 col-md-6 col-lg-4">
          <div class="card-tg h-100 d-flex flex-column">
            <div class="p-3 flex-grow-1">
              <h5 class="mb-2">{{ tl.title }}</h5>
              <p class="text-muted-tg small mb-2">
                by {{ getUserName(tl.userId) }} · {{ formatDate(tl.createdAt) }}
              </p>
              <p class="small" v-if="tl.description">{{ tl.description }}</p>
              <div class="mt-2">
                <span class="badge badge-genre">
                  <i class="bi bi-grid-3x3-gap-fill"></i> {{ countGames(tl) }} games
                </span>
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
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { tierlistsApi, usersApi } from '../services/api'

const auth = useAuthStore()
const tierlists = ref([])
const users = ref([])
const loading = ref(true)
const error = ref('')

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

async function deleteTierlist(tl) {
  if (!confirm(`Delete "${tl.title}"? This action cannot be undone.`)) return
  try {
    await tierlistsApi.remove(tl.id)
    tierlists.value = tierlists.value.filter(t => t.id !== tl.id)
    // also refresh users list? no need
  } catch (err) {
    alert('Failed to delete tier list.')
  }
}

async function load() {
  loading.value = true
  try {
    const [tierRes, userRes] = await Promise.all([
      tierlistsApi.list({ _sort: 'createdAt', _order: 'desc' }),
      usersApi.list()
    ])
    tierlists.value = tierRes.data
    users.value = userRes.data
  } catch (err) {
    error.value = 'Could not load tier lists. Is the API running?'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
