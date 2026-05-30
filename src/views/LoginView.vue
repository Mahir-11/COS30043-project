<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usersApi } from '../services/api'

const username = ref('')
const password = ref('')
const error = ref('')

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

async function login() {
  error.value = ''

  if (!username.value.trim()) {
    error.value = 'Please enter your username'
    return
  }

  if (!password.value) {
    error.value = 'Please enter your password'
    return
  }

  try {
    const { data } = await usersApi.create({
      action: 'login',
      username: username.value,
      password: password.value
    })

    if (!data.user || !data.token) {
      error.value = 'Invalid username or password'
      return
    }

    auth.setSession(
      {
        id: data.user.id,
        username: data.user.username,
        role: data.user.role || 'user'
      },
      data.token
    )

    router.replace(route.query.redirect || '/')
  } catch (e) {
    if (e.response && e.response.status === 401) {
      error.value = 'Invalid username or password'
    } else {
      error.value = 'Login failed. Please check the API server.'
    }
  }
}
</script>

<template>
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card-tg p-4">
        <h3 class="mb-3">Sign in</h3>

        <p class="text-muted-tg small mb-3">
          Login to create reviews, vote on content and manage your tier lists.
        </p>

        <form @submit.prevent="login" novalidate>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input
              class="form-control"
              v-model="username"
              placeholder="Enter username"
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input
              type="password"
              class="form-control"
              v-model="password"
              placeholder="Enter password"
            />
          </div>

          <div
            v-if="error"
            class="alert alert-danger py-2 small"
          >
            {{ error }}
          </div>

          <button
            class="btn btn-accent w-100"
            type="submit"
          >
            Login
          </button>

          <p class="text-center mt-3 small">
            No account?
            <router-link to="/register">
              Sign up
            </router-link>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>