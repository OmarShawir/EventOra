import { defineStore } from "pinia";
import {
  loginRequest,
  registerRequest,
  getMeRequest,
  forgotPasswordRequest,
  resetPasswordRequest,
  googleLoginRequest,
} from "@/api/auth";

// Demo accounts so the team can log in instantly during testing/demo
// without needing the real backend yet. Matches the USER entity roles:
// attendee | organiser | admin
const DEMO_USERS = {
  "attendee@utm.my": {
    id: "u1",
    name: "Omer Shawir",
    email: "attendee@utm.my",
    initials: "AS",
    role: "attendee",
  },
  "organiser@utm.my": {
    id: "u_org",
    name: "Organiser Account",
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
    token: localStorage.getItem("eventora_token") || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    role: (state) => state.user?.role ?? null,
    isAttendee: (state) => state.user?.role === "attendee",
    isOrganiser: (state) => state.user?.role === "organiser",
    isAdmin: (state) => state.user?.role === "admin",
  },

  actions: {
    /**
     * Tries the real Slim 4 /auth/login route first.
     * Falls back to local demo accounts if the API isn't reachable.
     */
    async login(email, password = "") {
      try {
        const data = await loginRequest(email, password);
        this.user = data.user;
        this.token = data.token;
        localStorage.setItem("eventora_token", data.token);
      } catch (err) {
        // If it's a real error from a running backend, propagate it
        if (err.response && err.response.status >= 400 && err.response.status < 500) {
          throw err;
        }
        // Otherwise, backend is down — fall back to demo accounts
        console.warn("[auth store] login API call failed, using demo account:", err.message);
        const matched = DEMO_USERS[email.toLowerCase()];
        if (matched) {
          this.user = matched;
          this.token = "mock-token-for-demo";
          localStorage.setItem("eventora_token", this.token);
        } else {
          throw err;
        }
      }
    },

    async loginWithGoogle(credential) {
      try {
        const data = await googleLoginRequest(credential);
        this.user = data.user;
        this.token = data.token;
        localStorage.setItem("eventora_token", data.token);
      } catch (err) {
        if (err.response && err.response.status >= 400 && err.response.status < 500) {
          throw err;
        }
        console.warn("[auth store] google login API call failed, using demo account");
        const matched = DEMO_USERS["attendee@utm.my"];
        this.user = matched;
        this.token = "mock-token-for-demo";
        localStorage.setItem("eventora_token", this.token);
      }
    },

    /**
     * Register a new account. On success the backend returns HTTP 202 with a
     * message (not a token) — the user must verify their email before logging in.
     * Returns the message string so the modal can display it.
     */
    async register(name, email, password, matricNo = "") {
      try {
        const data = await registerRequest({ name, email, password, matricNo });
        // data.message = "Check your UTM email…" — no user/token yet
        return data.message ?? "Account created! Please check your email.";
      } catch (err) {
        if (err.response && err.response.status >= 400 && err.response.status < 500) {
          throw err;
        }
        // Backend down — fall back to creating a guest session for demo purposes
        console.warn("[auth store] register API call failed, creating guest user:", err.message);
        this.user = deriveGuestUser(email, "attendee");
        this.token = "mock-token-for-demo";
        localStorage.setItem("eventora_token", this.token);
        return null; // null = guest session created (demo mode)
      }
    },

    /**
     * Called by VerifyEmailPage after the backend redirects with ?token=<jwt>.
     * Stores the JWT and fetches the user profile.
     */
    async loginWithToken(jwt) {
      this.token = jwt;
      localStorage.setItem("eventora_token", jwt);
      try {
        const data = await getMeRequest();
        this.user = data.user;
      } catch {
        this.logout();
      }
    },

    async forgotPassword(email) {
      const data = await forgotPasswordRequest(email);
      return data.message;
    },

    async resetPassword(token, password) {
      const data = await resetPasswordRequest(token, password);
      return data.message;
    },

    async restoreSession() {
      if (!this.token) return;
      try {
        const data = await getMeRequest();
        this.user = data.user;
      } catch (err) {
        console.warn("[auth store] Failed to restore session from token:", err.message);
        this.logout();
      }
    },

    logout() {
      this.user = null;
      this.token = null;
      localStorage.removeItem("eventora_token");
    },

    /**
     * Dev-only helper for the team to preview every role's screens during
     * testing or the PR4 demo without re-logging-in each time.
     */
    devSwitchRole(role) {
      const fallback = Object.values(DEMO_USERS).find((u) => u.role === role);
      if (fallback) this.user = fallback;
    },
  },
});
