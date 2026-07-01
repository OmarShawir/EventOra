import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: "/",
      component: () => import("@/layouts/RootLayout.vue"),
      children: [
        { path: "", name: "home", component: () => import("@/views/HomePage.vue") },
        {
          path: "events/:id",
          name: "event-detail",
          component: () => import("@/views/EventDetailPage.vue"),
        },
        {
          path: "societies",
          name: "societies",
          component: () => import("@/views/societies/SocietiesPage.vue"),
        },
        {
          path: "dashboard",
          name: "attendee-dashboard",
          component: () => import("@/views/attendee/AttendeeDashboard.vue"),
          meta: { requiresRole: "attendee" },
        },
        {
          path: "organiser",
          name: "organiser-dashboard",
          component: () => import("@/views/organiser/OrganiserDashboard.vue"),
          meta: { requiresRole: "organiser" },
        },
        {
          path: "admin",
          name: "admin-panel",
          component: () => import("@/views/admin/AdminPanel.vue"),
          meta: { requiresRole: "admin" },
        },
      ],
    },
    // QR Check-In is a standalone fullscreen page (no navbar), same as the
    // original React routes.tsx.
    {
      path: "/checkin",
      name: "checkin",
      component: () => import("@/views/checkin/QRCheckInPage.vue"),
      meta: { requiresRole: "organiser" },
    },
    // Email verification landing page — the backend redirects here after
    // validating the one-time token (/auth/verify-email?token=...).
    {
      path: "/auth/verified",
      name: "verify-email",
      component: () => import("@/views/VerifyEmailPage.vue"),
    },
    // Password reset page — linked from the reset email (/reset-password?token=...).
    {
      path: "/reset-password",
      name: "reset-password",
      component: () => import("@/views/ResetPasswordPage.vue"),
    },
    // Stripe payment success landing page
    {
      path: "/payment-success",
      name: "payment-success",
      component: () => import("@/views/PaymentSuccessPage.vue"),
      meta: { requiresRole: "attendee" },
    },
  ],
  scrollBehavior() {
    return { top: 0 };
  },
});

// Role-based access guard. Each role only sees the routes meant for it
// (per the PR1 proposal's "no role able to access another role's sensitive
// data or functions" requirement). During testing/demo, use the dev role
// switcher (see components/common/DevRoleSwitcher.vue) instead of editing
// this guard.
router.beforeEach(async (to) => {
  const auth = useAuthStore();

  // If a token exists but user profile is not loaded yet, restore the session first
  if (auth.token && !auth.user) {
    try {
      await auth.restoreSession();
    } catch (err) {
      console.warn("Failed to restore session in router guard:", err);
    }
  }

  const requiredRole = to.meta.requiresRole;
  if (!requiredRole) return true;

  if (!auth.isAuthenticated) {
    return { name: "home", query: { authRequired: "1" } };
  }
  if (auth.role !== requiredRole) {
    return { name: "home", query: { forbidden: "1" } };
  }
  return true;
});

export default router;
