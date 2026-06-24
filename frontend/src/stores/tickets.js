import { defineStore } from "pinia";
import { useEventsStore } from "./events";
import { useAuthStore } from "./auth";

// Mirrors the TICKET + CHECKIN entities from the ER diagram.
// Ticket.status: confirmed | checked_in | cancelled | waitlisted
// (kept as active | checked_in | cancelled here, matching the original
// Figma prototype; "waitlisted" can be added once the waitlist screens
// are wired up in a later phase)

function makeQR(eventId, userId) {
  return `EVORA-${eventId.toUpperCase()}-${userId.toUpperCase()}-${Math.random()
    .toString(36)
    .slice(2, 8)
    .toUpperCase()}`;
}

// Seed check-ins matching the original prototype's MOCK_CHECKINS, useful
// for the Organiser Dashboard's "recent scans" preview before real tickets
// have been issued in this session.
const MOCK_CHECKINS = [
  { id: "ci1", ticketId: "t3", attendeeName: "Lim Wei Xian", checkedInAt: "2025-11-15 08:52 AM" },
  { id: "ci2", ticketId: "t4", attendeeName: "Priya Nair", checkedInAt: "2025-11-15 09:01 AM" },
  { id: "ci3", ticketId: "t5", attendeeName: "Muhammad Irfan", checkedInAt: "2025-11-15 09:14 AM" },
];

export const useTicketsStore = defineStore("tickets", {
  state: () => ({
    tickets: [],
    checkins: [...MOCK_CHECKINS],
  }),

  getters: {
    myTickets: (state) => {
      const auth = useAuthStore();
      if (!auth.user) return [];
      return state.tickets.filter((t) => t.userId === auth.user.id);
    },

    isCheckedIn: (state) => (eventId) => {
      const auth = useAuthStore();
      if (!auth.user) return false;
      return state.tickets.some(
        (t) => t.eventId === eventId && t.userId === auth.user.id && t.status === "checked_in"
      );
    },
  },

  actions: {
    registerFree(eventId) {
      const auth = useAuthStore();
      const eventsStore = useEventsStore();
      if (!auth.user) throw new Error("Not logged in");

      const event = eventsStore.getEventById(eventId);
      const ticket = {
        id: `t_${Date.now()}`,
        eventId,
        userId: auth.user.id,
        qrCode: makeQR(eventId, auth.user.id),
        status: "active",
        issuedAt: new Date().toLocaleDateString("en-MY", {
          day: "2-digit",
          month: "short",
          year: "numeric",
        }),
        event,
      };
      this.tickets.push(ticket);
      eventsStore.decrementSpots(eventId);
      return ticket;
    },

    registerPaid(eventId) {
      // TODO (Week 4 — Backend Core): swap for a mock-payment confirmation
      // step before issuing the ticket, per the "mock payment" requirement.
      return this.registerFree(eventId);
    },

    checkIn(qrCode) {
      const ticket = this.tickets.find((t) => t.qrCode === qrCode);
      if (!ticket) {
        return { ok: false, message: "QR code not found. Please verify the ticket." };
      }
      if (ticket.status === "checked_in") {
        return { ok: false, ticket, message: "Already checked in!" };
      }
      if (ticket.status === "cancelled") {
        return { ok: false, ticket, message: "This ticket has been cancelled." };
      }

      ticket.status = "checked_in";
      this.checkins.push({
        id: `ci_${Date.now()}`,
        ticketId: ticket.id,
        attendeeName: ticket.event.title,
        checkedInAt: new Date().toLocaleString(),
      });
      return { ok: true, ticket, message: "Check-in successful!" };
    },
  },
});
