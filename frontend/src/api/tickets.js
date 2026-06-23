import api from "./axios";

export function fetchMyTickets() {
  return api.get("/tickets/me").then((res) => res.data);
}

export function registerForEvent(eventId) {
  return api.post(`/events/${eventId}/register`).then((res) => res.data);
}

export function checkInRequest(qrCode) {
  return api.post("/checkin", { qrCode }).then((res) => res.data);
}
