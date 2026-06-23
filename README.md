# EventOra

> **From idea to QR check-in — run your society events end-to-end.**

A full-stack campus event management and ticketing platform built for Universiti Teknologi Malaysia (UTM). Student societies can create and manage events, attendees register and receive unique QR tickets, and organisers check in attendees on the day via a mobile camera scanner.

---

## SCSM2223 — Cross-Platform Application Development

| | |
|---|---|
| **Course** | SCSM2223-02 Cross-Platform Application Development |
| **Lecturer** | Prof. Madya Dr. Hishammuddin bin Asmuni |
| **Problem** | P4 — EventOra |
| **Session** | Semester II, Academic Session 2025/2026 |

---

## Team

| Name | Matric | Role |
|---|---|---|
| Omar Nasreldin Mahgoub Shawir | A24CS4031 | Frontend Lead |
| Hosam Sayed Abdelaziz Eltalib | A24CS4015 | Backend & API Lead |
| Najmeldeen Mohamed Eltigani Salih | A24CS9001 | Database & Security Lead |
| Hebatulla Omer Abdulla Ali | A24CS4014 | DevOps, Mobile & Integration Lead |

---

## Repository Structure

```
eventora/
├── frontend/          # Vue 3 + Vite + Tailwind + Capacitor
│   ├── src/
│   │   ├── api/       # Axios instance + endpoint wrappers
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── mock/      # Fallback JSON data (dev mode)
│   │   ├── router/    # Vue Router + role-based guards
│   │   ├── stores/    # Pinia: auth, events, tickets, feedback, societies
│   │   └── views/
│   ├── capacitor.config.json
│   ├── package.json
│   └── README.md      ← frontend-specific setup guide
│
├── backend/           # PHP Slim 4 + PDO + MySQL + JWT
│   ├── migrations/    # SQL schema + seed script
│   ├── public/        # Entry point (index.php)
│   ├── src/
│   │   ├── Auth/
│   │   ├── Controllers/
│   │   ├── Database/
│   │   └── Middleware/
│   ├── vendor/
│   ├── composer.json
│   └── README.md      ← backend-specific setup guide
│
└── README.md          ← this file
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend framework | Vue 3 (Composition API) + Vue Router + Pinia |
| Styling | Tailwind CSS |
| HTTP client | Axios (JWT interceptor) |
| Backend framework | PHP Slim 4 |
| Database | MySQL 8 via PDO (prepared statements only) |
| Authentication | JSON Web Token (JWT) |
| Mobile | Capacitor — Android APK |
| QR scanning | @capacitor-community/barcode-scanner |
| Frontend deploy | Vercel |
| Backend deploy | Railway |

---

## Features

### For Attendees
- Browse and search campus events by category
- Register for free or paid events (mock payment)
- Receive a unique QR code ticket per event
- View upcoming and past tickets on a personal dashboard
- Export events to calendar (.ics)
- Submit feedback and ratings after attending
- Download digital attendance certificates

### For Society Organisers
- Create, edit, and cancel events via a dashboard
- Submit events for faculty approval
- Scan QR codes to check in attendees on the day
- Download attendance reports as CSV
- View feedback and ratings per event

### For Faculty Administrators
- Review and approve or reject pending events
- View participation analytics across all societies
- Monitor event categories, attendance counts, and ratings

---

## Roles & Access

| Route | Public | Attendee | Organiser | Admin |
|---|---|---|---|---|
| `/` Event discovery | ✅ | ✅ | ✅ | ✅ |
| `/events/:id` Event detail | ✅ | ✅ | ✅ | ✅ |
| `/societies` Society listing | ✅ | ✅ | ✅ | ✅ |
| `/dashboard` My tickets | — | ✅ | — | — |
| `/organiser` Organiser dashboard | — | — | ✅ | — |
| `/checkin` QR scanner | — | — | ✅ | — |
| `/admin` Admin panel | — | — | — | ✅ |

---

## Quick Start

### Frontend

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

Open [http://localhost:5173](http://localhost:5173)

**Demo accounts (mock mode — no backend needed):**

| Email | Role |
|---|---|
| attendee@utm.my | Attendee |
| organiser@utm.my | Organiser |
| admin@utm.my | Faculty Admin |

Password: `password123` (when backend is connected) — any value in mock mode.

### Backend

```bash
cd backend
composer install
cp .env.example .env
# Edit .env with your local MySQL credentials
mysql -u root eventora < migrations/001_create_schema.sql
php migrations/seed.php
composer start
```

API runs on [http://localhost:8080](http://localhost:8080)

### Connect them

In `frontend/.env.local`:

```
VITE_API_BASE_URL=http://localhost:8080
```

The frontend automatically falls back to mock JSON if this is not set, so each side can be developed independently.

---

## API Overview

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | — | Create attendee account |
| POST | `/auth/login` | — | Returns `{ token, user }` |
| GET | `/events` | — | Public event listing |
| GET | `/events/:id` | — | Single event |
| POST | `/events` | organiser | Create event |
| POST | `/events/:id/approve` | admin | Approve pending event |
| POST | `/events/:id/register` | any | Register + get QR ticket |
| GET | `/tickets/me` | attendee | My tickets |
| POST | `/checkin` | organiser | QR check-in |
| GET | `/events/:id/feedback` | — | Event feedback list |
| POST | `/events/:id/feedback` | any | Submit feedback |

Full API contract: see `backend/README.md`

---

## Deliverables

| | Deliverable | Due | Weight |
|---|---|---|---|
| PR1 | Project Proposal | End of Week 11 | 5% |
| PR2 | Interim Build | End of Week 13 | 10% |
| PR3 | Final Demo | End of Week 15 | 15% |
| PR4 | Presentation | End of Week 15 | 5% |

---

## Course Learning Outcomes

| CLO | Description | How EventOra covers it |
|---|---|---|
| CLO1 | Apply concepts of cross-platform web application development | Vue 3 SPA + Slim 4 REST API + MySQL + JWT + Capacitor Android |
| CLO2 | Develop an innovative solution using appropriate technologies | End-to-end event lifecycle: creation → approval → ticketing → QR check-in → feedback |
| CLO3 | Demonstrate entrepreneurial mindset | Revenue model (ticketing fees, analytics tier), adoption strategy for UTM faculty offices |
