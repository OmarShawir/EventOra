import { defineStore } from "pinia";
import { useEventsStore } from "./events";
import { useAuthStore } from "./auth";
import { registerForEvent, checkInRequest } from "@/api/tickets";
import mockCheckins from "@/mock/checkins.json";

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

export const useTicketsStore = defineStore("tickets", {
  state: () => ({
    tickets: [],
    // Seed check-ins matching the original prototype's mock data, useful
    // for the Organiser Dashboard's "recent scans" preview before real
    // tickets have been issued in this session.
    checkins: [...mockCheckins],
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
    async registerFree(eventId) {
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

      try {
        const saved = await registerForEvent(eventId);
        // Once the backend is live, prefer its qrCode/ticket id (server is
        // the source of truth for ticket issuance).
        if (saved) Object.assign(ticket, saved);
      } catch (err) {
        console.warn("[tickets store] registerFree API call failed, kept optimistic ticket:", err.message);
      }

      return ticket;
    },

    registerPaid(eventId) {
      // TODO (Week 4 — Backend Core): swap for a mock-payment confirmation
      // step before issuing the ticket, per the "mock payment" requirement.
      return this.registerFree(eventId);
    },

    async checkIn(qrCode) {
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

      try {
        await checkInRequest(qrCode);
      } catch (err) {
        console.warn("[tickets store] checkIn API call failed, kept optimistic update:", err.message);
      }

      return { ok: true, ticket, message: "Check-in successful!" };
    },
  },
});
