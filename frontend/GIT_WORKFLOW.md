# Git Workflow — EventOra Frontend

## Branch Strategy

```
main          ← production-ready only, never commit directly
dev       ← integration branch, merge features here first
feature/*     ← one branch per feature or page
fix/*         ← bug fixes
```

---

## Setup (First Time)

```bash
# 1. Clone the repo
git clone https://github.com/YOUR_USERNAME/EventOra.git
cd eventora-frontend

# 2. Install dependencies
npm install

# 3. Set up environment
cp .env.example .env.local
# Edit .env.local and set VITE_API_BASE_URL if the backend is ready
# If not set, the app falls back to mock JSON automatically

# 4. Run the dev server
npm run dev
```

---

## Daily Workflow

### Starting a new feature

```bash
# Always branch off develop, never off main
git checkout dev
git pull origin dev
git checkout -b feature/your-feature-name

# Examples:
git checkout -b feature/navbar
git checkout -b feature/home-page
git checkout -b feature/attendee-dashboard
```

### Committing your work

```bash
# Stage specific files (never use git add . blindly)
git add src/components/common/Navbar.vue

# Commit with a clear message
git commit -m "feat: add Navbar component with role-based links"

# Push your branch
git push origin feature/navbar
```

### Merging into develop

```bash
# When your feature is done
git checkout dev
git pull origin dev
git merge feature/navbar

# Resolve any conflicts, then
git push origin dev

# Clean up the feature branch
git branch -d feature/navbar
git push origin --delete feature/navbar
```

### Merging develop into main (only for deliverable submissions)

```bash
git checkout main
git pull origin main
git merge dev
git push origin main

# Tag the submission
git tag -a v1.0-PR2 -m "PR2 Interim Build submission"
git push origin --tags
```

---

## Commit Message Format

Follow this pattern for a clean, readable history:

```
type: short description (max 72 chars)
```

| Type | When to use |
|---|---|
| `feat` | Adding a new component, page, or feature |
| `fix` | Fixing a bug |
| `refactor` | Restructuring code without changing behaviour |
| `style` | CSS/styling changes only |
| `chore` | Config changes, dependency updates, tooling |
| `docs` | README or comment changes only |

### Examples

```bash
git commit -m "feat: add EventCard component with register button"
git commit -m "feat: add HomePage with search and category filter"
git commit -m "feat: add role-based route guards in router"
git commit -m "fix: correct spotsLeft calculation in events store"
git commit -m "refactor: move QR generation logic to tickets store"
git commit -m "chore: add Capacitor Android platform"
git commit -m "docs: update README with setup instructions"
```

---

## Planned Commit History

These are the commits that should appear in the repo history in order. Each one maps to a logical step in the build, showing the examiner a clean, progressive development story.

### Phase 1 — Project Setup

```
chore: scaffold Vue 3 + Vite + Tailwind project
feat: add Pinia stores for auth, events, tickets, feedback, societies
feat: add Vue Router with role-based guards and root layout
```

### Phase 2 — Shared Components

```
feat: add Navbar, Footer, and dev role-switcher components
feat: add authentication modal with login/register flows
```

### Phase 3 — Event Discovery

```
feat: add event discovery page with search and filtering
feat: add event detail page with registration and payment flow
```

### Phase 4 — Societies

```
feat: add societies listing and detail pages
```

### Phase 5 — Dashboards

```
feat: add attendee dashboard with tickets, feedback, certificates
feat: add organiser dashboard with event management and analytics
feat: add faculty admin panel with approval queue and analytics
feat: add QR check-in scanner page
```

### Phase 6 — API Layer

```
feat: add centralized Axios instance with JWT interceptor
feat: add API endpoint wrappers for auth, events, tickets, feedback
refactor: extract mock data from stores into JSON files
refactor: convert stores to async with API-first fallback to mock data
feat: fetch events on app mount, await async auth login
```

### Phase 7 — Documentation & CI

```
docs: add project README
chore: add GitHub Actions CI workflow
```

---

## Rules

- **Never** commit directly to `main` or `develop`
- **Never** commit `node_modules/`, `dist/`, or `.env.local` (covered by `.gitignore`)
- **Always** pull from `develop` before creating a new feature branch
- **One logical change per commit** — if you changed three unrelated things, make three commits
- **Every member must have commits** — the examiner checks contribution history per member

---

## Conflict Resolution

```bash
# If you get a merge conflict
git status                    # see which files conflict
# Open the conflicting file, resolve the <<<<< ===== >>>>> markers
git add src/the-fixed-file.vue
git commit -m "fix: resolve merge conflict in Navbar"
```

---

## Submission Tags

Tag each deliverable submission so it's easy to find in the history:

```bash
git tag -a v1.0-PR1 -m "PR1 Project Proposal"
git tag -a v1.0-PR2 -m "PR2 Interim Build"
git tag -a v1.0-PR3 -m "PR3 Final Demo"
git push origin --tags
```
