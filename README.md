# COS30043 Project — TierGG

A Vue 3 + Bootstrap web app for browsing games, writing reviews, and building tier lists.
Submitted for **COS30043 Interface Design and Development**, Swinburne University, Semester 1 2026.

---

## Quickstart

```bash
# 1. Install dependencies
npm install
# 2. Install vuedraggable@next
npm install vuedraggable@next
# 3. Start the API and dev server together
npm start
```

This launches:
- `json-server` REST API on **http://localhost:3001** (reads/writes `db.json`)
- Vite dev server on **http://localhost:5173**

Open http://localhost:5173 in your browser. The Vite dev server proxies `/api/*` to json-server, so the frontend talks to a single origin.

### Demo accounts
| Username | Password   | Role  |
|----------|------------|-------|
| `admin`  | `admin123` | admin |
| `demo`   | `demo1234` | user  |

### Available scripts
| Command            | What it does                                    |
|--------------------|-------------------------------------------------|
| `npm run dev`      | Vite dev server only                            |
| `npm run api`      | json-server REST API only                       |
| `npm start`        | Both, in one terminal (recommended)             |
| `npm run build`    | Production build to `dist/`                     |
| `npm run preview`  | Preview the production build                    |

---

## Tech stack

- **Vue 3** (Composition API, `<script setup>`)
- **Vite** — build tool & dev server
- **Vue Router 4** — SPA navigation
- **Pinia** — state management (auth)
- **Bootstrap 5** — grid + components (mobile-first)
- **json-server** — RESTful API over `db.json` (persistent storage)
- **Axios** — HTTP client

Maps cleanly to the unit syllabus: Layout & Grid (Wk 2), Data Binding (Wk 3), View/ViewModel (Wk 4), Components & Routes (Wk 5), Forms & Validation (Wk 6), Vite (Wk 7), Consuming External Services (Wk 8), API & Pagination (Wk 9), SPA (Wk 10), State Management (Wk 11).

---

## Project structure

```
project/
├── db.json                # json-server data (users, games, reviews, tierlists, votes)
├── src/
│   ├── main.js
│   ├── App.vue
│   ├── assets/main.css    # design tokens & shared styles
│   ├── components/
│   │   ├── NavBar.vue
│   │   ├── FooterBar.vue
│   │   └── GameCard.vue
│   ├── router/index.js    # all 12 routes + auth guards
│   ├── stores/auth.js     # Pinia auth store
│   ├── services/api.js    # axios + per-resource clients
│   └── views/             # one file per page
└── vite.config.js         # proxies /api → :3001
```

---

## Team allocation

| Member | Owns | Files / Routes |
|---|---|---|
| **1** | Auth & Profiles | `LoginView`, `RegisterView`, `ProfileView`, `stores/auth.js`, route guards in `router/index.js` |
| **2**  | Games browse & detail | `HomeView`, `GamesView`, `GameDetailView`, `components/GameCard.vue` |
| **3** | Reviews & Ratings | Review list & form inside `GameDetailView`, new `ReviewForm.vue`, `StarRating.vue` components, paginate reviews |
| **4** | Tier Lists | `TierlistsView`, `TierlistBuilderView`, `TierlistDetailView`, drag-and-drop component |
| **5** | Social & Admin | Upvote button on reviews (`votes` collection), `AdminView` (CRUD games, moderate reviews), polish `AboutView` / `NotFoundView` |

> **Person 2's work is done** — featured games on home, search/filter/sort/pagination on `/games`, full detail page with reviews placeholder. Everyone else can now plug their work into the existing routes.

---

## API endpoints (json-server)

All under `/api` (proxied to `http://localhost:3001`).

```
GET    /api/games?genre=RPG&_sort=year&_order=desc&_page=1&_limit=12
GET    /api/games/:id
POST   /api/games               (admin only by convention)
PATCH  /api/games/:id
DELETE /api/games/:id

GET    /api/users?username=foo
POST   /api/users               (register)
GET    /api/reviews?gameId=1&_sort=createdAt&_order=desc
POST   /api/reviews
PATCH  /api/reviews/:id
DELETE /api/reviews/:id
GET    /api/tierlists?userId=2
POST   /api/tierlists           ... etc.
GET    /api/votes?reviewId=1
POST   /api/votes
DELETE /api/votes/:id
```

json-server supports `_page`, `_limit`, `_sort`, `_order`, `q` for full-text search, and filter-by-field out of the box.

---

## Conventions

- **Branches**: `feat/p1-auth`, `feat/p3-reviews`, etc. PR to `main`.
- **Coding**: Composition API + `<script setup>`, 2-space indent, kebab-case file names for components.
- **Styles**: prefer Bootstrap utilities; put custom rules in `src/assets/main.css` using the existing `--tg-*` design tokens.
- **API**: always go through the per-resource clients in `services/api.js` — do not import axios in views.
- **Auth**: guard pages by adding `meta: { requiresAuth: true }` (or `requiresAdmin`) to the route.

---

## Advanced feature (TBD, worth 10 marks)

Pick one and document it in the report:
- Drag-and-drop tier list builder using the HTML5 DnD API (Person 4)
- Real-time review feed via `EventSource` / polling
- Dark / light theme toggle with CSS variables + Pinia persistence
- Charts (e.g. genre distribution on profile) via Chart.js
- Animated route transitions with `<transition>` + GSAP

---

## Deploying to Mercury

```bash
npm run build
# upload dist/* to mercury public_html
# for the REST API on mercury: replace json-server with a small PHP/Node endpoint,
# OR ship db.json as a static JSON file and switch to read-only mode for demo.
```

