import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', name: 'home', component: () => import('../views/HomeView.vue') },
  { path: '/games', name: 'games', component: () => import('../views/GamesView.vue') },
  { path: '/games/:id', name: 'game-detail', component: () => import('../views/GameDetailView.vue'), props: true },

  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { guestOnly: true } },
  { path: '/profile', name: 'profile', component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },

  { path: '/tierlists', name: 'tierlists', component: () => import('../views/TierlistsView.vue') },
  { path: '/tierlists/new', name: 'tierlist-new', component: () => import('../views/TierlistBuilderView.vue'), meta: { requiresAuth: true } },
  { path: '/tierlists/:id', name: 'tierlist-detail', component: () => import('../views/TierlistDetailView.vue'), props: true },
  { path: '/tierlists/:id/edit', name: 'tierlist-edit', component: () => import('../views/TierlistBuilderView.vue'), props: true, meta: { requiresAuth: true } },

  { path: '/admin', name: 'admin', component: () => import('../views/AdminView.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/about', name: 'about', component: () => import('../views/AboutView.vue') },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue') }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 })
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'home' }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }
})

export default router
