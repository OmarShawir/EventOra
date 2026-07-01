import { defineStore } from "pinia";
import { submitFeedbackRequest } from "@/api/feedback";
import { useAuthStore } from "./auth";
import mockFeedback from "@/mock/feedback.json";

// Mirrors the FEEDBACK entity: id, event_id, user_id, rating, comment.
// Only users with a checked_in ticket for an event may submit feedback —
// that rule is enforced where submitFeedback is called (e.g. AttendeeDashboard),
// not inside the store itself, since it needs the tickets store's isCheckedIn check.

export const useFeedbackStore = defineStore("feedback", {
  state: () => ({
    feedback: [...mockFeedback],
  }),

  getters: {
    feedbackForEvent: (state) => (eventId) =>
      state.feedback.filter((f) => f.eventId === eventId),

    hasFeedback: (state) => (eventId) => {
      const auth = useAuthStore();
      if (!auth.user) return false;
      return state.feedback.some((f) => f.eventId === eventId && f.userId === auth.user.id);
    },

    averageRating: (state) => (eventId) => {
      const items = state.feedback.filter((f) => f.eventId === eventId);
      if (items.length === 0) return 0;
      return items.reduce((sum, f) => sum + f.rating, 0) / items.length;
    },
  },

  actions: {
    async submitFeedback(eventId, rating, comment) {
      const auth = useAuthStore();
      if (!auth.user) return;

      const optimistic = {
        id: `fb_${Date.now()}`,
        eventId,
        userId: auth.user.id,
        userName: auth.user.name,
        rating,
        comment,
        submittedAt: new Date().toLocaleDateString("en-MY"),
      };
      this.feedback.push(optimistic);

      try {
        await submitFeedbackRequest(eventId, rating, comment);
      } catch (err) {
        console.warn("[feedback store] submitFeedback API call failed, kept optimistic record:", err.message);
      }
    },
  },
});
