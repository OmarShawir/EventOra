import { defineStore } from "pinia";
import { useAuthStore } from "./auth";

// Mirrors the FEEDBACK entity: id, event_id, user_id, rating, comment.
// Only users with a checked_in ticket for an event may submit feedback —
// that rule is enforced where submitFeedback is called (e.g. AttendeeDashboard),
// not inside the store itself, since it needs the tickets store's isCheckedIn check.

const MOCK_FEEDBACK = [
  { id: "f1", eventId: "e10", userId: "u2", userName: "Lim Wei Xian", rating: 5, comment: "Excellent challenges! The web exploitation category was particularly well-crafted.", submittedAt: "2025-11-16" },
  { id: "f2", eventId: "e10", userId: "u3", userName: "Priya Nair", rating: 4, comment: "Great event overall. Would love harder binary challenges next time.", submittedAt: "2025-11-16" },
  { id: "f3", eventId: "e10", userId: "u4", userName: "Muhammad Irfan", rating: 5, comment: "Best CTF I've attended at UTM. The hints system was very helpful for beginners.", submittedAt: "2025-11-17" },
  { id: "f4", eventId: "p1", userId: "u2", userName: "Lim Wei Xian", rating: 5, comment: "Amazing hackathon. Learnt more in 48 hours than an entire semester.", submittedAt: "2025-04-07" },
  { id: "f5", eventId: "p1", userId: "u5", userName: "Kavitha Raj", rating: 4, comment: "Great mentors throughout the night. The winning solution was genuinely impressive.", submittedAt: "2025-04-07" },
  { id: "f6", eventId: "p2", userId: "u6", userName: "Zul Hazwan", rating: 5, comment: "Best badminton event UTM has hosted. Excellent organisation.", submittedAt: "2025-03-23" },
  { id: "f7", eventId: "p3", userId: "u7", userName: "Siti Norehan", rating: 5, comment: "Citrawarna never disappoints. The Sabahan traditional dance segment was breathtaking.", submittedAt: "2025-02-09" },
  { id: "f8", eventId: "p4", userId: "u8", userName: "Farouk Idris", rating: 5, comment: "The fundraising talk alone was worth the trip. Very practical, no fluff.", submittedAt: "2025-02-20" },
  { id: "f9", eventId: "p5", userId: "u9", userName: "Chan Mei Ling", rating: 4, comment: "Really well-structured for beginners. Got my first bronze medal on Kaggle!", submittedAt: "2025-01-19" },
  { id: "f10", eventId: "p6", userId: "u10", userName: "Ahmad Khairul", rating: 5, comment: "Beautiful event. The community spirit was incredible. Will join every year.", submittedAt: "2025-03-21" },
  { id: "f11", eventId: "p7", userId: "u7", userName: "Siti Norehan", rating: 5, comment: "Kembara Jiwa made me tear up twice. The cast is incredibly talented.", submittedAt: "2025-03-08" },
  { id: "f12", eventId: "p8", userId: "u11", userName: "Nurul Huda", rating: 5, comment: "My CV went from zero to hero in one day. Got three interview calls the week after.", submittedAt: "2025-01-26" },
  { id: "f13", eventId: "p9", userId: "u6", userName: "Zul Hazwan", rating: 4, comment: "The route through the forest was beautiful. Hope they add a 10K next year.", submittedAt: "2025-02-17" },
];

export const useFeedbackStore = defineStore("feedback", {
  state: () => ({
    feedback: [...MOCK_FEEDBACK],
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
    submitFeedback(eventId, rating, comment) {
      const auth = useAuthStore();
      if (!auth.user) return;
      this.feedback.push({
        id: `fb_${Date.now()}`,
        eventId,
        userId: auth.user.id,
        userName: auth.user.name,
        rating,
        comment,
        submittedAt: new Date().toLocaleDateString("en-MY"),
      });
    },
  },
});
