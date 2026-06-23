import { defineStore } from "pinia";
import mockSocieties from "@/mock/societies.json";

// Mirrors the SOCIETY entity: id, name, faculty, advisor_id.
// Members/founded/coverUrl/logoColor are presentational extras carried
// over from the Figma prototype (not part of the core ER schema) — keep
// them here for now since the UI depends on them; drop or move them to a
// "society profile" concept later if the backend schema diverges.
//
// No dedicated GET /societies route is planned yet (see PR1 proposal,
// section 8) — society names are currently derived from event.societyName.
// This store stays mock-only until that decision is made with Hosam
// (Backend Lead); swap to an api/societies.js wrapper at that point,
// following the same fetchAll() pattern as stores/events.js.

export const useSocietiesStore = defineStore("societies", {
  state: () => ({
    societies: { ...mockSocieties },
  }),

  getters: {
    societyNames: (state) => Object.keys(state.societies),
    getSociety: (state) => (name) => state.societies[name],
  },
});
