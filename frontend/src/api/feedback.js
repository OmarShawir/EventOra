import api from "./axios";

export function fetchFeedbackForEvent(eventId) {
  return api.get(`/events/${eventId}/feedback`).then((res) => res.data);
}

export function submitFeedbackRequest(eventId, rating, comment) {
  return api.post(`/events/${eventId}/feedback`, { rating, comment }).then((res) => res.data);
}
