import api from "./axios";

export function loginRequest(email, password) {
  return api.post("/auth/login", { email, password }).then((res) => res.data);
}

export function registerRequest(payload) {
  return api.post("/auth/register", payload).then((res) => res.data);
}

export function getMeRequest() {
  return api.get("/auth/me").then((res) => res.data);
}

export function forgotPasswordRequest(email) {
  return api.post("/auth/forgot-password", { email }).then((res) => res.data);
}

export function resetPasswordRequest(token, password) {
  return api.post("/auth/reset-password", { token, password }).then((res) => res.data);
}

export function googleLoginRequest(credential) {
  return api.post("/auth/google", { credential }).then((res) => res.data);
}

export function getBankDetailsRequest() {
  return api.get("/organiser/bank").then((res) => res.data);
}

export function updateBankDetailsRequest(payload) {
  return api.patch("/organiser/bank", payload).then((res) => res.data);
}

export function getOrganiserTransactionsRequest() {
  return api.get("/organiser/transactions").then((res) => res.data);
}

export function getAdminOrganisersRequest() {
  return api.get("/admin/organisers").then((res) => res.data);
}

export function getAdminTransactionsRequest() {
  return api.get("/admin/transactions").then((res) => res.data);
}
