import axios from 'axios'
const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' }
})
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('tg_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})
export const gamesApi = {
  list: (params) => api.get('/games', { params }),
  get: (id) => api.get(`/games/${id}`),
  create: (data) => api.post('/games', data),
  update: (id, data) => api.patch(`/games/${id}`, data),
  remove: (id) => api.delete(`/games/${id}`)
}
export const usersApi = {
  list: (params) => api.get('/users', { params }),
  get: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.patch(`/users/${id}`, data)
}
export const reviewsApi = {
  list: (params) => api.get('/reviews', { params }),
  get: (id) => api.get(`/reviews/${id}`),
  create: (data) => api.post('/reviews', data),
  update: (id, data) => api.patch(`/reviews/${id}`, data),
  remove: (id) => api.delete(`/reviews/${id}`)
}
// person 4 - tier lists
export const tierlistsApi = {
  list: (params) => api.get('/tierlists', { params }),
  get: (id) => api.get(`/tierlists/${id}`),
  create: (data) => api.post('/tierlists', data),
  update: (id, data) => api.patch(`/tierlists/${id}`, data),
  remove: (id) => api.delete(`/tierlists/${id}`)
}
export const tierlistReactionsApi = {
  list: (params) => api.get('/tierlistReactions', { params }),
  create: (data) => api.post('/tierlistReactions', data),
  remove: (id) => api.delete(`/tierlistReactions/${id}`)
}
export const votesApi = {
  list: (params) => api.get('/votes', { params }),
  create: (data) => api.post('/votes', data),
  remove: (id) => api.delete(`/votes/${id}`)
}
// [Person 5] users flagging reviews
export const reportsApi = {
  list: (params) => api.get('/reports', { params }),
  create: (data) => api.post('/reports', data)
}
export const adminApi = {
  stats: () => api.get('/admin/stats'),
  listGames: () => api.get('/admin/games'),
  createGame: (data) => api.post('/admin/games', data),
  updateGame: (id, data) => api.patch(`/admin/games/${id}`, data),
  removeGame: (id) => api.delete(`/admin/games/${id}`),
  listReviews: (params) => api.get('/admin/reviews', { params }),
  removeReview: (id) => api.delete(`/admin/reviews/${id}`),
  
  listReports: (params) => api.get('/admin/reports', { params }),
  updateReport: (id, data) => api.patch(`/admin/reports/${id}`, data),
  dismissReports: (reviewId) => api.post('/admin/reports/dismiss', { reviewId }),
  removeReport: (id) => api.delete(`/admin/reports/${id}`)
}
export default api
