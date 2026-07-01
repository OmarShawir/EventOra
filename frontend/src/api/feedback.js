import api from "./axios";

export function submitFeedbackRequest(eventId, rating, comment) {
  return api.post(`/events/${eventId}/feedback`, { rating, comment }).then((res) => res.data);
}
