import { defineStore } from "pinia";

// Demo accounts so the team can log in instantly during testing/demo
// without needing the real backend yet. Matches the USER entity roles:
// attendee | organiser | admin
const DEMO_USERS = {
  "attendee@utm.my": {
    id: "u1",
    name: "Ahmad Syafiq",
    email: "attendee@utm.my",
    initials: "AS",
    role: "attendee",
  },
  "organiser@utm.my": {
    id: "u_org",
    name: "Ahmad Faris",
    email: "organiser@utm.my",
    initials: "AF",
    role: "organiser",
    society: "IEEE UTM Student Branch",
  },
  "admin@utm.my": {
    id: "u_admin",
    name: "Prof. Dr. Razali",
    email: "admin@utm.my",
    initials: "PR",
    role: "admin",
  },
};

function deriveGuestUser(email, role) {
  const namePart = email.split("@")[0].replace(/[._]/g, " ");
  const parts = namePart.split(" ");
  const initials = (
    (parts[0]?.[0] ?? "") + (parts[1]?.[0] ?? parts[0]?.[1] ?? "")
  ).toUpperCase();
  return {
    id: `u_${Date.now()}`,
    name: namePart,
    email,
    initials,
    role,
  };
}

export const useAuthStore = defineStore("auth", {
  state: () => ({
    user: null,
    // TODO (Week 4 — Backend Core): replace with a real JWT received from
    // POST /auth/login and persist it (e.g. via Pinia + an Axios interceptor)
    token: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    role: (state) => state.user?.role ?? null,
    isAttendee: (state) => state.user?.role === "attendee",
    isOrganiser: (state) => state.user?.role === "organiser",
    isAdmin: (state) => state.user?.role === "admin",
  },

  actions: {
    login(email, role) {
      const matched = DEMO_USERS[email.toLowerCase()];
      this.user = matched ?? deriveGuestUser(email, role);
      // TODO: set this.token from the real API response once Slim 4 auth is live
    },

    logout() {
      this.user = null;
      this.token = null;
    },

    /**
     * Dev-only helper for the team to preview every role's screens during
     * testing or the PR4 demo without re-logging-in each time. Wire this to
     * a small role-switcher control that should be hidden/removed before
     * the final production build.
     */
    devSwitchRole(role) {
      const fallback = Object.values(DEMO_USERS).find((u) => u.role === role);
      if (fallback) this.user = fallback;
    },
  },
});
