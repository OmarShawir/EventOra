import axios from "axios";

// Centralised Axios instance. Right now VITE_API_BASE_URL is unset, so every
// request 404s against nothing real — each store catches that and falls
// back to local mock JSON (see src/mock/*.json). Once Hosam's Slim 4 API is
// live (Week 4 — Backend Core), set VITE_API_BASE_URL in .env.local and the
// stores below will start hitting the real endpoints with no further
// changes needed here.
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || "/api",
  headers: { "Content-Type": "application/json" },
});

// Attach the JWT (once auth.js stores a real one from POST /auth/login) to
// every outgoing request's Authorization header.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("eventora_token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Centralised 401 handling: once the backend is live, an expired/invalid
// JWT should log the user out instead of leaving the app in a broken state.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem("eventora_token");
      // Full reload back to "/" clears all Pinia state cleanly. Swap for a
      // router.push("/") + auth.logout() once the router is wired in here.
      if (typeof window !== "undefined") window.location.href = "/";
    }
    return Promise.reject(error);
  }
);

export default api;
