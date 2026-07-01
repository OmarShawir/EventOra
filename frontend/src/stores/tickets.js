import { defineStore } from "pinia";
import { useEventsStore } from "./events";
import { useAuthStore } from "./auth";
import { fetchMyTickets as fetchMyTicketsRequest, registerForEvent, checkInRequest } from "@/api/tickets";
import mockCheckins from "@/mock/checkins.json";

// Mirrors the TICKET + CHECKIN entities from the ER diagram.
// Ticket.status: confirmed | checked_in | cancelled | waitlisted
// (kept as active | checked_in | cancelled here, matching the original
// Figma prototype; "waitlisted" can be added once the waitlist screens
// are wired up in a later phase)

function makeQR(eventId, userId) {
  return `EVORA-${String(eventId).toUpperCase()}-${String(userId).toUpperCase()}-${Math.random()
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
      // fetchMyTickets already returns only the current user's tickets
      // from the backend (GET /tickets/me is JWT-protected).
      // We keep the userId check as fallback for mock/offline tickets.
      return state.tickets.filter(
        (t) => !t.userId || String(t.userId) === String(auth.user.id)
      );
    },

    isCheckedIn: (state) => (eventId) => {
      const auth = useAuthStore();
      if (!auth.user) return false;
      return state.tickets.some(
        (t) => t.eventId === eventId && String(t.userId) === String(auth.user.id) && t.status === "checked_in"
      );
    },
  },

  actions: {
    async fetchMyTickets() {
      try {
        const data = await fetchMyTicketsRequest();
        this.tickets = data;
      } catch (err) {
        console.warn("[tickets store] Failed to fetch tickets from backend:", err.message);
      }
    },

    async registerFree(eventId) {
      const auth = useAuthStore();
      const eventsStore = useEventsStore();
      if (!auth.user) throw new Error("Not logged in");

      try {
        const saved = await registerForEvent(eventId);
        // The backend returns the ticket with event data already embedded
        // via the fetchPresented JOIN (TicketController::register → fetchPresented)
        this.tickets.push(saved);
        eventsStore.decrementSpots(eventId);
        return saved;
      } catch (err) {
        if (err.response && err.response.status >= 400 && err.response.status < 500) {
          throw err;
        }
        console.warn("[tickets store] registerFree API call failed, falling back to mock ticket:", err.message);
        const event = eventsStore.getEventById(eventId);
        const ticket = {
          id: `t_${Date.now()}`,
          eventId,
          userId: String(auth.user.id),
          qrCode: makeQR(eventId, auth.user.id),
          status: "confirmed",
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
      }
    },

    registerPaid(eventId) {
      return this.registerFree(eventId);
    },


    async checkIn(qrCode) {
      const normalizedCode = qrCode.trim();
      const localTicket = this.tickets.find((t) => t.qrCode === normalizedCode);

      try {
        const result = await checkInRequest(normalizedCode);
        if (localTicket) localTicket.status = "checked_in";

        this.checkins.unshift({
          id: `ci_${Date.now()}`,
          ticketId: localTicket?.id ?? normalizedCode,
          attendeeName: result.eventTitle || localTicket?.event?.title || "Event",
          checkedInAt: new Date().toLocaleString(),
        });

        return {
          ok: true,
          ticket: localTicket,
          message: result.message || "Check-in successful!",
          eventTitle: result.eventTitle || localTicket?.event?.title,
        };
      } catch (err) {
        const status = err.response?.status;
        const message = err.response?.data?.error;
        const responseTicket = err.response?.data?.ticket;

        if (status) {
          return {
            ok: false,
            ticket: responseTicket || localTicket,
            message: message || "QR code not found. Please verify the ticket.",
          };
        }

        console.warn("[tickets store] checkIn API call failed, using local fallback:", err.message);

        if (!localTicket) {
          return { ok: false, message: "QR code not found. Please verify the ticket." };
        }
        if (localTicket.status === "checked_in") {
          return { ok: false, ticket: localTicket, message: "Already checked in!" };
        }
        if (localTicket.status === "cancelled") {
          return { ok: false, ticket: localTicket, message: "This ticket has been cancelled." };
        }

        localTicket.status = "checked_in";
        this.checkins.unshift({
          id: `ci_${Date.now()}`,
          ticketId: localTicket.id,
          attendeeName: localTicket.event?.title || "Event",
          checkedInAt: new Date().toLocaleString(),
        });

        return {
          ok: true,
          ticket: localTicket,
          message: "Check-in successful!",
          eventTitle: localTicket.event?.title,
        };
      }
    },
  },
});
