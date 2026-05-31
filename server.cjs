const jsonServer = require('json-server')
const path = require('path')

const server = jsonServer.create()
const router = jsonServer.router(path.join(__dirname, 'db.json'))
const middlewares = jsonServer.defaults()
const PORT = process.env.PORT || 3001

server.use(middlewares)
server.use(jsonServer.bodyParser)

function db() {
  return router.db
}

function nextId(collectionName) {
  const collection = db().get(collectionName).value() || []
  return collection.length ? Math.max(...collection.map((item) => Number(item.id) || 0)) + 1 : 1
}

function getTokenUser(req) {
  // Accept X-TG-Token (sent by the Vue frontend) or Authorization: Bearer <token>
  const token = req.headers['x-tg-token'] || req.headers.authorization?.replace(/^Bearer\s+/i, '') || ''
  const match = token.match(/(\d+)$/)
  if (!match) return null

  const userId = Number(match[1])
  return db().get('users').find({ id: userId }).value() || null
}

function requireAuth(req, res, next) {
  const user = getTokenUser(req)
  if (!user) {
    return res.status(401).json({ message: 'Login required.' })
  }
  req.currentUser = user
  next()
}

function requireAdmin(req, res, next) {
  const user = getTokenUser(req)
  if (!user || user.role !== 'admin') {
    return res.status(403).json({ message: 'Admin access required.' })
  }
  req.currentUser = user
  next()
}

function normaliseGame(input, existing = {}) {
  const platforms = Array.isArray(input.platforms)
    ? input.platforms
    : String(input.platforms || '')
      .split(',')
      .map((platform) => platform.trim())
      .filter(Boolean)

  return {
    ...existing,
    title: String(input.title || existing.title || '').trim(),
    genre: String(input.genre || existing.genre || '').trim(),
    developer: String(input.developer || existing.developer || '').trim(),
    year: Number(input.year || existing.year || new Date().getFullYear()),
    platforms,
    cover: String(input.cover || existing.cover || '').trim(),
    summary: String(input.summary || existing.summary || '').trim(),
    featured: Boolean(input.featured)
  }
}

// [Person 5] removing a review now also clears its reports
function removeReviewWithVotes(reviewId) {
  db().get('votes').remove({ reviewId }).write()
  db().get('reports').remove({ reviewId }).write()
  return db().get('reviews').remove({ id: reviewId }).write()
}

// [Person 5] allowed reasons a user can pick when flagging a review
const REPORT_REASONS = ['Spam or advertising', 'Harassment or hate', 'Off-topic', 'Spoilers', 'Other']

// Login handler — matches the same POST /users { action:'login' } the frontend sends.
// json-server's default POST /users would just create a record, so we intercept first.
server.post('/users', (req, res, next) => {
  if (req.body.action !== 'login') return next()

  const { username, password } = req.body
  if (!username || !password) {
    return res.status(400).json({ message: 'Username and password are required.' })
  }

  const user = db().get('users').find({ username }).value()
  if (!user || user.password !== password) {
    return res.status(401).json({ message: 'Invalid username or password.' })
  }

  const token = `tg_token_${user.id}`
  res.json({
    user: { id: user.id, username: user.username, role: user.role, createdAt: user.createdAt },
    token
  })
})

// Backend endpoint for the social feature. It prevents duplicate likes by the same user.
server.post('/votes', requireAuth, (req, res) => {
  const reviewId = Number(req.body.reviewId)
  const userId = Number(req.body.userId)

  if (!reviewId || !userId || userId !== Number(req.currentUser.id)) {
    return res.status(400).json({ message: 'Invalid vote request.' })
  }

  const review = db().get('reviews').find({ id: reviewId }).value()
  if (!review) return res.status(404).json({ message: 'Review not found.' })

  const existing = db().get('votes').find({ reviewId, userId }).value()
  if (existing) return res.status(409).json({ message: 'You have already liked this review.' })

  const vote = {
    id: nextId('votes'),
    reviewId,
    userId,
    createdAt: new Date().toISOString()
  }

  db().get('votes').push(vote).write()
  res.status(201).json(vote)
})

server.delete('/votes/:id', requireAuth, (req, res) => {
  const voteId = Number(req.params.id)
  const vote = db().get('votes').find({ id: voteId }).value()
  if (!vote) return res.status(404).json({ message: 'Vote not found.' })

  const isOwner = Number(vote.userId) === Number(req.currentUser.id)
  const isAdmin = req.currentUser.role === 'admin'
  if (!isOwner && !isAdmin) {
    return res.status(403).json({ message: 'You can only remove your own vote.' })
  }

  db().get('votes').remove({ id: voteId }).write()
  res.status(204).end()
})



server.post('/reports', requireAuth, (req, res) => {
  const reviewId = Number(req.body.reviewId)
  const userId = Number(req.currentUser.id)
  const reason = String(req.body.reason || '').trim()
  const note = String(req.body.note || '').trim().slice(0, 280)

  if (!reviewId) return res.status(400).json({ message: 'A review id is required.' })
  if (!REPORT_REASONS.includes(reason)) {
    return res.status(400).json({ message: 'Please choose a valid report reason.' })
  }

  const review = db().get('reviews').find({ id: reviewId }).value()
  if (!review) return res.status(404).json({ message: 'Review not found.' })

  const existing = db().get('reports').find({ reviewId, userId, status: 'open' }).value()
  if (existing) return res.status(409).json({ message: 'You have already reported this review.' })

  const report = {
    id: nextId('reports'),
    reviewId,
    userId,
    reason,
    note,
    status: 'open',
    createdAt: new Date().toISOString()
  }

  db().get('reports').push(report).write()
  res.status(201).json(report)
})


// Translate PHP-style /admin?action=X[&id=Y] → REST paths the route handlers expect.
// Must be a top-level middleware (no mount path) so it rewrites req.url before
// Express's router matches the route.
server.use((req, res, next) => {
  if (/^\/admin(\?|$)/.test(req.url)) {
    const action = req.query.action
    if (action) {
      const id = req.query.id
      req.url = action === 'dismiss'
        ? '/admin/reports/dismiss'
        : id ? `/admin/${action}/${id}` : `/admin/${action}`
    }
  }
  next()
})

server.get('/admin/stats', requireAdmin, (req, res) => {
  const games = db().get('games').value() || []
  const reviews = db().get('reviews').value() || []
  const votes = db().get('votes').value() || []
  const users = db().get('users').value() || []
  const reports = db().get('reports').value() || [] 

  res.json({
    games: games.length,
    reviews: reviews.length,
    votes: votes.length,
    users: users.length,
    featuredGames: games.filter((game) => game.featured).length,
    openReports: reports.filter((report) => report.status === 'open').length 
  })
})

server.get('/admin/games', requireAdmin, (req, res) => {
  res.json(db().get('games').value())
})

server.post('/admin/games', requireAdmin, (req, res) => {
  const game = normaliseGame(req.body, {
    id: nextId('games'),
    createdAt: new Date().toISOString()
  })

  if (!game.title || !game.genre || !game.developer || !game.summary || !game.platforms.length) {
    return res.status(400).json({ message: 'Title, genre, developer, summary, and platforms are required.' })
  }

  db().get('games').push(game).write()
  res.status(201).json(game)
})

server.patch('/admin/games/:id', requireAdmin, (req, res) => {
  const gameId = Number(req.params.id)
  const existing = db().get('games').find({ id: gameId }).value()
  if (!existing) return res.status(404).json({ message: 'Game not found.' })

  const updated = normaliseGame(req.body, existing)
  db().get('games').find({ id: gameId }).assign(updated).write()
  res.json(updated)
})

server.delete('/admin/games/:id', requireAdmin, (req, res) => {
  const gameId = Number(req.params.id)
  const existing = db().get('games').find({ id: gameId }).value()
  if (!existing) return res.status(404).json({ message: 'Game not found.' })

  const reviewIds = db().get('reviews').filter({ gameId }).map('id').value()
  reviewIds.forEach((reviewId) => removeReviewWithVotes(Number(reviewId)))

  const tierlists = db().get('tierlists').value() || []
  tierlists.forEach((tierlist) => {
    const tiers = tierlist.tiers || {}
    Object.keys(tiers).forEach((tierName) => {
      tiers[tierName] = tiers[tierName].filter((id) => Number(id) !== gameId)
    })
    db().get('tierlists').find({ id: tierlist.id }).assign({ tiers }).write()
  })

  db().get('games').remove({ id: gameId }).write()
  res.status(204).end()
})

server.get('/admin/reviews', requireAdmin, (req, res) => {
  res.json(db().get('reviews').sortBy('createdAt').reverse().value())
})

server.delete('/admin/reviews/:id', requireAdmin, (req, res) => {
  const reviewId = Number(req.params.id)
  const review = db().get('reviews').find({ id: reviewId }).value()
  if (!review) return res.status(404).json({ message: 'Review not found.' })

  removeReviewWithVotes(reviewId)
  res.status(204).end()
})


server.get('/admin/reports', requireAdmin, (req, res) => {
  let reports = db().get('reports').sortBy('createdAt').reverse().value()
  if (req.query.status) {
    reports = reports.filter((report) => report.status === req.query.status)
  }
  res.json(reports)
})


server.patch('/admin/reports/:id', requireAdmin, (req, res) => {
  const reportId = Number(req.params.id)
  const report = db().get('reports').find({ id: reportId }).value()
  if (!report) return res.status(404).json({ message: 'Report not found.' })

  const status = String(req.body.status || 'resolved')
  if (!['open', 'resolved'].includes(status)) {
    return res.status(400).json({ message: 'Status must be "open" or "resolved".' })
  }

  db().get('reports').find({ id: reportId }).assign({ status }).write()
  res.json({ ...report, status })
})

// [Person 5] dismiss every open report attached to one review in a single call
server.post('/admin/reports/dismiss', requireAdmin, (req, res) => {
  const reviewId = Number(req.body.reviewId)
  if (!reviewId) return res.status(400).json({ message: 'A review id is required.' })

  db().get('reports')
    .filter({ reviewId, status: 'open' })
    .each((report) => { report.status = 'resolved' })
    .write()

  res.json({ reviewId, dismissed: true })
})


server.delete('/admin/reports/:id', requireAdmin, (req, res) => {
  const reportId = Number(req.params.id)
  const report = db().get('reports').find({ id: reportId }).value()
  if (!report) return res.status(404).json({ message: 'Report not found.' })

  db().get('reports').remove({ id: reportId }).write()
  res.status(204).end()
})

server.use(router)

server.listen(PORT, () => {
  console.log(`TierGG API running at http://localhost:${PORT}`)
})
