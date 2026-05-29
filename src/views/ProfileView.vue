<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { reviewsApi, tierlistsApi } from '../services/api'

const auth = useAuthStore()

const reviews = ref([])
const tierlists = ref([])

async function loadProfileData() {
  try {
    const reviewsRes = await reviewsApi.list({
      userId: auth.user.id
    })

    reviews.value = reviewsRes.data

    const tierlistsRes = await tierlistsApi.list({
      userId: auth.user.id
    })

    tierlists.value = tierlistsRes.data
  } catch (err) {
    console.error(err)
  }
}

onMounted(() => {
  loadProfileData()
})
</script>

<template>
  <div>
    <div class="card-tg p-4 mb-4">
      <h2 class="mb-1">
        {{ auth.user?.username }}
      </h2>

      <p class="text-muted-tg small">
        Role: {{ auth.user?.role }}
      </p>
    </div>

    <div class="card-tg p-4 mb-4">
      <h3 class="mb-3">My Reviews</h3>

      <div v-if="reviews.length === 0">
        No reviews yet.
      </div>

      <div
        v-for="review in reviews"
        :key="review.id"
        class="border rounded p-3 mb-3"
      >
        <h5>{{ review.title }}</h5>

        <p>{{ review.content }}</p>

        <small>
          ⭐ {{ review.rating }}/5
        </small>
      </div>
    </div>

    <div class="card-tg p-4">
      <h3 class="mb-3">My Tier Lists</h3>

      <div v-if="tierlists.length === 0">
        No tier lists yet.
      </div>

      <div
        v-for="tier in tierlists"
        :key="tier.id"
        class="border rounded p-3 mb-3"
      >
        <h5>{{ tier.title }}</h5>

        <p>{{ tier.description }}</p>
      </div>
    </div>
  </div>
</template>