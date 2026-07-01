import { defineStore } from "pinia";
import {
  fetchEvents,
  createEvent as createEventRequest,
  updateEventRequest,
  approveEventRequest,
  rejectEventRequest,
  cancelEventRequest,
  fetchPendingEvents,
  fetchMyEvents,
} from "@/api/events";
import mockEvents from "@/mock/events.json";

// Mirrors the EVENT entity from the ER diagram:
// id, society_id, title, description, venue, starts_at, ends_at, capacity, price, status
// status flow: draft > pending_approval > approved > ongoing > completed / cancelled
// (here simplified to: pending | approved | cancelled | completed)

export const useEventsStore = defineStore("events", {
  state: () => ({
    events: [],
    loading: false,
    error: null,
    loaded: false,
  }),

  getters: {
    getEventById: (state) => (id) => state.events.find((e) => e.id === id),
    approvedEvents: (state) => state.events.filter((e) => e.status === "approved"),
    pendingEvents: (state) => state.events.filter((e) => e.status === "pending"),
  },

  actions: {
    /**
     * Loads events from the Slim 4 API. Until that route exists (Week 4 —
     * Backend Core), the request fails and we transparently fall back to
     * local mock JSON so the rest of the app keeps working unmodified.
     * Call this once from App.vue/RootLayout on mount.
     */
    async fetchAll() {
      if (this.loaded) return;
      this.loading = true;
      this.error = null;
      try {
        this.events = await fetchEvents();
      } catch (err) {
        console.warn("[events store] API unavailable, using mock data:", err.message);
        this.events = [...mockEvents];
      } finally {
        this.loading = false;
        this.loaded = true;
      }
    },

    async approveEvent(id) {
      const ev = this.events.find((e) => e.id === id);
      if (!ev) return;
      ev.status = "approved"; // optimistic update
      try {
        await approveEventRequest(id);
      } catch (err) {
        console.warn("[events store] approveEvent API call failed, kept optimistic update:", err.message);
      }
    },

    async rejectEvent(id) {
      const ev = this.events.find((e) => e.id === id);
      if (!ev) return;
      ev.status = "cancelled";
      try {
        await rejectEventRequest(id);
      } catch (err) {
        console.warn("[events store] rejectEvent API call failed, kept optimistic update:", err.message);
      }
    },

    async addEvent(ev) {
      const optimistic = {
        ...ev,
        id: `e${Date.now()}`,
        spotsLeft: ev.capacity,
        status: "pending",
      };
      this.events.unshift(optimistic);
      try {
        const saved = await createEventRequest(ev);
        const idx = this.events.findIndex((e) => e.id === optimistic.id);
        if (idx !== -1 && saved) {
          this.events[idx] = saved;
          return saved;
        }
      } catch (err) {
        console.warn("[events store] addEvent API call failed:", err.message);
        const idx = this.events.findIndex((e) => e.id === optimistic.id);
        if (idx !== -1) this.events.splice(idx, 1);
        throw err;
      }
      return optimistic;
    },

    async updateEvent(id, patch) {
      const idx = this.events.findIndex((e) => e.id === id);
      if (idx === -1) return;
      const original = { ...this.events[idx] };
      this.events[idx] = { ...this.events[idx], ...patch };
      try {
        await updateEventRequest(id, patch);
      } catch (err) {
        console.warn("[events store] updateEvent API call failed:", err.message);
        this.events[idx] = original;
        throw err;
      }
    },

    async cancelEvent(id) {
      const ev = this.events.find((e) => e.id === id);
      if (!ev) return;
      ev.status = "cancelled";
      try {
        await cancelEventRequest(id);
      } catch (err) {
        console.warn("[events store] cancelEvent API call failed, kept optimistic update:", err.message);
      }
    },

    // Local-only state change (no dedicated endpoint — spotsLeft is derived
    // server-side from ticket count once the backend is live).
    decrementSpots(id) {
      const ev = this.events.find((e) => e.id === id);
      if (ev) ev.spotsLeft = Math.max(0, ev.spotsLeft - 1);
    },

    async fetchPending() {
      try {
        const pending = await fetchPendingEvents();
        this.mergeEvents(pending);
      } catch (err) {
        console.warn("[events store] fetchPending API call failed:", err.message);
      }
    },

    async fetchMine() {
      try {
        const mine = await fetchMyEvents();
        this.mergeEvents(mine);
      } catch (err) {
        console.warn("[events store] fetchMine API call failed:", err.message);
      }
    },

    mergeEvents(newEvents) {
      newEvents.forEach((newEvent) => {
        const idx = this.events.findIndex((e) => e.id === newEvent.id);
        if (idx !== -1) {
          this.events[idx] = newEvent;
        } else {
          this.events.push(newEvent);
        }
      });
    },
  },
});
