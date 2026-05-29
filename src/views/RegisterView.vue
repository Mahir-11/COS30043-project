<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usersApi } from '../services/api'
import { useAuthStore } from '../stores/auth'

const username = ref('')
const password = ref('')
const confirmPassword = ref('')
const error = ref('')
const success = ref('')

const router = useRouter()
const auth = useAuthStore()

const passwordValid = computed(() => password.value.length >= 6)

async function register() {
  error.value = ''
  success.value = ''

  if (!username.value.trim()) {
    error.value = 'Username is required'
    return
  }

  if (username.value.length < 3) {
    error.value = 'Username must be at least 3 characters'
    return
  }

  if (!password.value) {
    error.value = 'Password is required'
    return
  }

  if (!passwordValid.value) {
    error.value = 'Password must be at least 6 characters'
    return
  }

  if (password.value !== confirmPassword.value) {
    error.value = 'Passwords do not match'
    return
  }

  try {
    const exists = await usersApi.list({ username: username.value })

    if (exists.data.length) {
      error.value = 'Username already taken'
      return
    }

    const { data } = await usersApi.create({
      username: username.value,
      password: password.value,
      role: 'user',
      createdAt: new Date().toISOString()
    })

    auth.setSession(
      { id: data.id, username: data.username, role: 'user' },
      'demo-token-' + data.id
    )

    success.value = 'Account created successfully'

    setTimeout(() => {
      router.replace('/')
    }, 800)
  } catch (e) {
    error.value = 'Registration failed. Please check the API server.'
  }
}
</script>

<template>
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card-tg p-4">
        <h3 class="mb-3">Create account</h3>
        <p class="text-muted-tg small">
          Register to create reviews, tier lists and manage your profile.
        </p>

        <form @submit.prevent="register" novalidate>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input
              class="form-control"
              v-model="username"
              required
              minlength="3"
              maxlength="20"
              placeholder="Enter username"
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input
              type="password"
              class="form-control"
              v-model="password"
              required
              minlength="6"
              placeholder="Minimum 6 characters"
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input
              type="password"
              class="form-control"
              v-model="confirmPassword"
              required
              placeholder="Re-enter password"
            />
          </div>

          <div v-if="error" class="alert alert-danger py-2 small">
            {{ error }}
          </div>

          <div v-if="success" class="alert alert-success py-2 small">
            {{ success }}
          </div>

          <button class="btn btn-accent w-100" type="submit">
            Sign up
          </button>

          <p class="text-center mt-3 small">
            Already have an account?
            <router-link to="/login">Login</router-link>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>