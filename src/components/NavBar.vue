<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const isAuthed = computed(() => auth.isAuthenticated)

function logout() { auth.logout() }
</script>

<template>
  <nav class="navbar navbar-expand-lg navbar-dark navbar-tg sticky-top px-3 px-md-4 px-lg-5">
    <router-link class="navbar-brand fw-bold text-light" to="/">
      <i class="bi bi-controller me-2 text-danger"></i>TierGG
    </router-link>
    <button
      class="navbar-toggler border-0"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#mainNav"
      aria-controls="mainNav"
      aria-expanded="false"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><router-link class="nav-link" to="/games">Games</router-link></li>
        <li class="nav-item"><router-link class="nav-link" to="/tierlists">Tier Lists</router-link></li>
        <li class="nav-item"><router-link class="nav-link" to="/about">About</router-link></li>
      </ul>
      <ul class="navbar-nav">
        <template v-if="isAuthed">
          <li class="nav-item">
            <router-link class="nav-link" to="/profile">
              <i class="bi bi-person-circle me-1"></i>{{ auth.user?.username }}
            </router-link>
          </li>
          <li class="nav-item" v-if="auth.user?.role === 'admin'">
            <router-link class="nav-link" to="/admin">Admin</router-link>
          </li>
          <li class="nav-item">
            <button class="btn btn-sm btn-outline-light ms-lg-2 mt-2 mt-lg-0" @click="logout">Logout</button>
          </li>
        </template>
        <template v-else>
          <li class="nav-item"><router-link class="nav-link" to="/login">Login</router-link></li>
          <li class="nav-item">
            <router-link class="btn btn-accent btn-sm ms-lg-2 mt-2 mt-lg-0" to="/register">Sign up</router-link>
          </li>
        </template>
      </ul>
    </div>
  </nav>
</template>
