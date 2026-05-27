<template>
  <div>
    <h1 class="mb-3">{{ isEditing ? 'Edit Tier List' : 'Create New Tier List' }}</h1>

    <div class="card-tg p-3 mb-4">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Title</label>
          <input v-model="form.title" class="form-control" placeholder="e.g., Best RPGs" />
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Description (optional)</label>
          <input v-model="form.description" class="form-control" placeholder="My personal ranking..." />
        </div>
      </div>
    </div>

    <!-- Tier rows (drag & drop) -->
    <div class="tier-builder mb-5">
      <div v-for="tier in tiers" :key="tier.name" class="tier-row mb-3">
        <div class="tier-label" :class="tier.colorClass">{{ tier.name }}</div>
        <draggable
          v-model="tier.games"
          group="games"
          item-key="id"
          class="tier-games"
          @end="onDragEnd"
        >
          <template #item="{ element }">
            <div class="draggable-game-card">
              <img v-if="element.cover" :src="element.cover" class="game-thumb" />
              <span class="game-title-sm">{{ element.title }}</span>
              <button class="remove-game" @click.stop="removeFromTier(tier, element)">×</button>
            </div>
          </template>
        </draggable>
      </div>
    </div>

    <!-- Available games pool -->
    <div class="card-tg p-3">
      <h5 class="mb-3">All Games <span class="text-muted-tg">(drag into tiers above)</span></h5>
      <draggable
        v-model="availableGames"
        group="games"
        item-key="id"
        class="games-pool"
        @end="onDragEnd"
      >
        <template #item="{ element }">
          <div class="draggable-game-card pool-card">
            <img v-if="element.cover" :src="element.cover" class="game-thumb" />
            <span>{{ element.title }}</span>
          </div>
        </template>
      </draggable>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button class="btn btn-outline-danger" @click="resetAllTiers" :disabled="saving">
        Reset all tiers
      </button>
      <button class="btn btn-accent" @click="saveTierList" :disabled="saving">
        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
        {{ isEditing ? 'Update' : 'Publish' }} Tier List
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { gamesApi, tierlistsApi } from '../services/api'
import draggable from 'vuedraggable'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const allGames = ref([])          // all games from API
const availableGames = ref([])    // games not yet placed in any tier
const saving = ref(false)
const loading = ref(true)

const isEditing = computed(() => !!route.params.id)

const form = reactive({
  title: 'My Tier List',
  description: ''
})

// Define tiers with predefined order and colors
const tiers = reactive([
  { name: 'S', colorClass: 'tier-s', games: [] },
  { name: 'A', colorClass: 'tier-a', games: [] },
  { name: 'B', colorClass: 'tier-b', games: [] },
  { name: 'C', colorClass: 'tier-c', games: [] },
  { name: 'D', colorClass: 'tier-d', games: [] },
  { name: 'F', colorClass: 'tier-f', games: [] }
])

function removeFromTier(tier, game) {
  const idx = tier.games.findIndex(g => g.id === game.id)
  if (idx !== -1) tier.games.splice(idx, 1)
  // push back to available pool (unless it's already there)
  if (!availableGames.value.find(g => g.id === game.id)) {
    availableGames.value.push(game)
  }
}

function resetAllTiers() {
  for (const tier of tiers) {
    // move all games back to available pool
    availableGames.value.push(...tier.games)
    tier.games = []
  }
}

// Called after any drag & drop – no auto-save, just update UI
function onDragEnd() {
  // do nothing extra; v-model already synced
}

function buildTiersObject() {
  const obj = {}
  for (const tier of tiers) {
    obj[tier.name] = tier.games.map(g => g.id)
  }
  return obj
}

async function saveTierList() {
  if (!auth.user) {
    router.push('/login')
    return
  }
  if (!form.title.trim()) {
    alert('Please enter a title')
    return
  }

  saving.value = true
  const payload = {
    userId: auth.user.id,
    title: form.title.trim(),
    description: form.description.trim(),
    tiers: buildTiersObject(),
    createdAt: isEditing.value ? undefined : new Date().toISOString()
  }

  try {
    if (isEditing.value) {
      await tierlistsApi.update(route.params.id, payload)
    } else {
      await tierlistsApi.create(payload)
    }
    router.push('/tierlists')
  } catch (err) {
    console.error(err)
    alert('Failed to save tier list. Check API.')
  } finally {
    saving.value = false
  }
}

// Load all games and (if editing) load existing tier list
async function initialize() {
  loading.value = true
  try {
    // fetch all games
    const { data: gamesData } = await gamesApi.list()
    allGames.value = gamesData
    // initially, all games are available
    availableGames.value = [...gamesData]

    // if editing, load existing tier list
    if (isEditing.value) {
      const { data: tl } = await tierlistsApi.get(route.params.id)
      // authorization: only owner or admin can edit
      if (tl.userId !== auth.user?.id && auth.user?.role !== 'admin') {
        alert('You are not allowed to edit this tier list.')
        router.push('/tierlists')
        return
      }
      form.title = tl.title
      form.description = tl.description || ''
      // populate tiers
      for (const tier of tiers) {
        const gameIds = tl.tiers[tier.name] || []
        tier.games = gameIds.map(id => allGames.value.find(g => g.id === id)).filter(Boolean)
        // remove those games from available pool
        for (const g of tier.games) {
          const idx = availableGames.value.findIndex(ag => ag.id === g.id)
          if (idx !== -1) availableGames.value.splice(idx, 1)
        }
      }
    }
  } catch (err) {
    console.error(err)
    alert('Could not load data. Make sure API is running.')
  } finally {
    loading.value = false
  }
}

onMounted(initialize)
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
  min-height: 100px;
}
.draggable-game-card {
  background: #2a2a3a;
  border-radius: 8px;
  padding: 0.5rem;
  width: 90px;
  cursor: grab;
  position: relative;
  text-align: center;
}
.draggable-game-card:active { cursor: grabbing; }
.game-thumb {
  width: 100%;
  height: 50px;
  object-fit: cover;
  border-radius: 6px;
}
.game-title-sm {
  font-size: 0.7rem;
  display: block;
  margin-top: 4px;
  color: #e0e0e0;
}
.remove-game {
  position: absolute;
  top: 2px;
  right: 6px;
  background: none;
  border: none;
  color: #ff8888;
  font-weight: bold;
  cursor: pointer;
  font-size: 1.2rem;
}
.games-pool {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: #14141c;
  padding: 1rem;
  border-radius: 12px;
  max-height: 300px;
  overflow-y: auto;
}
.pool-card {
  background: #2a2a3a;
  width: 80px;
}
@media (max-width: 576px) {
  .tier-label { width: 60px; font-size: 1.2rem; padding: 0.5rem; }
  .draggable-game-card { width: 70px; }
}
</style>
