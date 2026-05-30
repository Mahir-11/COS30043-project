import axios from 'axios'

// ── Base URL ────────────────────────────────────────────────────────────────
// For LOCAL development:   baseURL should be '/api' (Vite proxies to json-server)
// For MERCURY deployment:  baseURL should be the full URL to the api/ folder
//
// Toggle by commenting / uncommenting the relevant line below.
// ─────────────────────────────────────────────────────────────────────────────

// const API_BASE = '/api'                                                     // LOCAL dev (Vite proxy)
const API_BASE = 'https://mercury.swin.edu.au/cos30043/s104799137/project/api' // MERCURY


const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json' }
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('tg_token')
  if (token && token !== 'null') {
    config.headers['X-TG-Token'] = token
  }
  return config
})

api.interceptors.response.use(
  (response) => {
    if (typeof response.data === 'string' && response.data.includes('<')) {
      return Promise.reject(new Error('API returned an HTML error instead of JSON. Please check database setup.'))
    }
    return response
  },
  (error) => {
    if (error.response && typeof error.response.data === 'string' && error.response.data.includes('<')) {
      error.message = 'API returned an HTML error instead of JSON. Please check database setup.'
    }
    return Promise.reject(error)
  }
)


// ── Helper: build query-string params for PHP endpoints ─────────────────────
// json-server used RESTful paths like /games/3, but PHP uses /games.php?id=3
// These wrappers translate automatically.

export const gamesApi = {
  list:   (params)    => api.get('/games.php',          { params }),
  get:    (id)        => api.get('/games.php',          { params: { id } }),
  create: (data)      => api.post('/games.php',         data),
  update: (id, data)  => api.patch(`/games.php?id=${id}`, data),
  remove: (id)        => api.delete('/games.php',       { params: { id } })
}

export const usersApi = {
  list:   (params)    => api.get('/users.php',          { params }),
  get:    (id)        => api.get('/users.php',          { params: { id } }),
  create: (data)      => api.post('/users.php',         data),
  update: (id, data)  => api.patch(`/users.php?id=${id}`, data)
}

export const reviewsApi = {
  list:   (params)    => api.get('/reviews.php',        { params }),
  get:    (id)        => api.get('/reviews.php',        { params: { id } }),
  create: (data)      => api.post('/reviews.php',       data),
  update: (id, data)  => api.patch(`/reviews.php?id=${id}`, data),
  remove: (id)        => api.delete('/reviews.php',     { params: { id } })
}

// person 4 - tier lists
export const tierlistsApi = {
  list:   (params)    => api.get('/tierlists.php',      { params }),
  get:    (id)        => api.get('/tierlists.php',      { params: { id } }),
  create: (data)      => api.post('/tierlists.php',     data),
  update: (id, data)  => api.patch(`/tierlists.php?id=${id}`, data),
  remove: (id)        => api.delete('/tierlists.php',   { params: { id } })
}

export const tierlistReactionsApi = {
  list:   (params)    => api.get('/tierlist_reactions.php', { params }),
  create: (data)      => api.post('/tierlist_reactions.php', data),
  remove: (id)        => api.delete('/tierlist_reactions.php', { params: { id } })
}

export const votesApi = {
  list:   (params)    => api.get('/votes.php',          { params }),
  create: (data)      => api.post('/votes.php',         data),
  remove: (id)        => api.delete('/votes.php',       { params: { id } })
}

// [Person 5] users flagging reviews
export const reportsApi = {
  list:   (params)    => api.get('/reports.php',        { params }),
  create: (data)      => api.post('/reports.php',       data)
}

export const adminApi = {
  stats:          ()           => api.get('/admin.php',  { params: { action: 'stats' } }),
  listGames:      ()           => api.get('/admin.php',  { params: { action: 'games' } }),
  createGame:     (data)       => api.post('/admin.php?action=games', data),
  updateGame:     (id, data)   => api.patch(`/admin.php?action=games&id=${id}`, data),
  removeGame:     (id)         => api.delete('/admin.php', { params: { action: 'games', id } }),
  listReviews:    (params)     => api.get('/admin.php',  { params: { action: 'reviews', ...params } }),
  removeReview:   (id)         => api.delete('/admin.php', { params: { action: 'reviews', id } }),

  listReports:    (params)     => api.get('/admin.php',  { params: { action: 'reports', ...params } }),
  updateReport:   (id, data)   => api.patch(`/admin.php?action=reports&id=${id}`, data),
  dismissReports: (reviewId)   => api.post('/admin.php?action=dismiss', { reviewId }),
  removeReport:   (id)         => api.delete('/admin.php', { params: { action: 'reports', id } })
}

export default api
