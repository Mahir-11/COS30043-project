<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-danger"></div>
  </div>

  <div v-else-if="error" class="alert alert-warning">{{ error }}</div>

  <div v-else-if="tierlist">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
      <div>
        <h1 class="mb-1">{{ tierlist.title }}</h1>
        <p class="text-muted-tg">
          by {{ creatorName }} · {{ formatDate(tierlist.createdAt) }}
        </p>
        <p v-if="tierlist.description" class="lead">{{ tierlist.description }}</p>
      </div>
      <div>
        <router-link to="/tierlists" class="btn btn-outline-light me-2">
          <i class="bi bi-arrow-left"></i> All
        </router-link>
        <router-link v-if="canEdit" :to="`/tierlists/${tierlist.id}/edit`" class="btn btn-accent me-2">
          <i class="bi bi-pencil"></i> Edit
        </router-link>
        <button v-if="canDelete" class="btn btn-outline-danger" @click="confirmDelete">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </div>

    <div class="tier-list-view">
      <div v-for="tier in orderedTiers" :key="tier.name" class="tier-row mb-3">
        <div class="tier-label" :class="tier.colorClass">{{ tier.name }}</div>
        <div class="tier-games">
          <div v-for="game in tier.games" :key="game.id" class="game-card-view">
            <img v-if="game.cover" :src="game.cover" class="game-cover-sm" />
            <div class="game-title">{{ game.title }}</div>
          </div>
          <div v-if="tier.games.length === 0" class="text-muted-tg small p-2">No games</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { tierlistsApi, gamesApi, usersApi } from '../services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const tierlist = ref(null)
const allGames = ref([])
const users = ref([])
const loading = ref(true)
const error = ref('')

const orderedTiers = computed(() => {
  if (!tierlist.value || !tierlist.value.tiers) return []
  const order = ['S', 'A', 'B', 'C', 'D', 'F']
  const colorMap = {
    S: 'tier-s', A: 'tier-a', B: 'tier-b', C: 'tier-c', D: 'tier-d', F: 'tier-f'
  }
  return order.map(name => ({
    name,
    colorClass: colorMap[name] || '',
    games: (tierlist.value.tiers[name] || []).map(id => {
      const game = allGames.value.find(g => g.id === id)
      return game || { id, title: `Game #${id}`, cover: null }
    })
  }))
})

const creatorName = computed(() => {
  if (!tierlist.value) return ''
  const user = users.value.find(u => u.id === tierlist.value.userId)
  return user ? user.username : `User #${tierlist.value.userId}`
})

const canEdit = computed(() => {
  if (!auth.isAuthenticated) return false
  if (auth.user?.role === 'admin') return true
  return tierlist.value && auth.user?.id === tierlist.value.userId
})

const canDelete = computed(() => {
  if (!auth.isAuthenticated) return false
  if (auth.user?.role === 'admin') return true
  return tierlist.value && auth.user?.id === tierlist.value.userId
})

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString()
}

async function confirmDelete() {
  if (!confirm(`Are you sure you want to delete "${tierlist.value.title}"? This action cannot be undone.`)) return
  try {
    await tierlistsApi.remove(tierlist.value.id)
    router.push('/tierlists')
  } catch (err) {
    alert('Failed to delete tier list.')
  }
}

async function load() {
  loading.value = true
  const id = route.params.id
  try {
    const [tlRes, gamesRes, usersRes] = await Promise.all([
      tierlistsApi.get(id),
      gamesApi.list(),
      usersApi.list()
    ])
    tierlist.value = tlRes.data
    allGames.value = gamesRes.data
    users.value = usersRes.data
  } catch (err) {
    error.value = 'Tier list not found.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.tier-row {
  display: flex;
  flex-wrap: wrap;
  background: #1e1e2a;
  border-radius: 12px;
  overflow: hidden;
}
.tier-label {
  width: 80px;
  padding: 1rem;
  font-weight: bold;
  font-size: 1.8rem;
  text-align: center;
  background: #2a2a3a;
  color: white;
}
.tier-s { background: #ffd966; color: #000; }
.tier-a { background: #a8e6cf; color: #000; }
.tier-b { background: #b0c4de; color: #000; }
.tier-c { background: #f7c9a0; color: #000; }
.tier-d { background: #f4acb7; color: #000; }
.tier-f { background: #9b9b9b; color: #000; }

.tier-games {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #1e1e2a;
}
.game-card-view {
  background: #2a2a3a;
  border-radius: 8px;
  padding: 0.5rem;
  width: 100px;
  text-align: center;
}
.game-cover-sm {
  width: 100%;
  height: 60px;
  object-fit: cover;
  border-radius: 6px;
}
.game-title {
  font-size: 0.75rem;
  margin-top: 0.25rem;
  color: #e0e0e0;
}
@media (max-width: 576px) {
  .tier-label { width: 60px; font-size: 1.2rem; padding: 0.5rem; }
  .game-card-view { width: 70px; }
}
</style>
