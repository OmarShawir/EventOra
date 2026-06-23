import api from "./axios";

export function loginRequest(email, password) {
  return api.post("/auth/login", { email, password }).then((res) => res.data);
}

export function registerRequest(payload) {
  return api.post("/auth/register", payload).then((res) => res.data);
}
