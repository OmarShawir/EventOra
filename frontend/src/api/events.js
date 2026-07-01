import api from "./axios";

// Each function maps 1:1 to a planned Slim 4 route (see PR1 proposal,
// section 8 "Week 4: Backend Core"). Until that route exists, the
// corresponding Pinia action catches the failed request and falls back to
// local mock JSON — see stores/events.js.

export function fetchEvents() {
  return api.get("/events").then((res) => res.data);
}

export function fetchEventById(id) {
  return api.get(`/events/${id}`).then((res) => res.data);
}

export function createEvent(payload) {
  return api.post("/events", payload).then((res) => res.data);
}

export function updateEventRequest(id, payload) {
  return api.patch(`/events/${id}`, payload).then((res) => res.data);
}

export function approveEventRequest(id) {
  return api.post(`/events/${id}/approve`).then((res) => res.data);
}

export function rejectEventRequest(id) {
  return api.post(`/events/${id}/reject`).then((res) => res.data);
}

export function cancelEventRequest(id) {
  return api.post(`/events/${id}/cancel`).then((res) => res.data);
}

export function uploadEventImage(file) {
  const formData = new FormData();
  formData.append("image", file);
  return api.post("/upload", formData, {
    headers: {
      "Content-Type": "multipart/form-data",
    },
  }).then((res) => res.data);
}

export function fetchEventParticipants(id) {
  return api.get(`/events/${id}/participants`).then((res) => res.data);
}

export function fetchPendingEvents() {
  return api.get("/events/pending").then((res) => res.data);
}

export function fetchMyEvents() {
  return api.get("/events/mine").then((res) => res.data);
}

