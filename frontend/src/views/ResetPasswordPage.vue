<script setup>
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { Eye, EyeOff, Lock, AlertCircle, CheckCircle2, ChevronRight } from "lucide-vue-next";

const route  = useRoute();
const router = useRouter();
const auth   = useAuthStore();

const token       = ref(route.query.token || "");
const password    = ref("");
const confirm     = ref("");
const showPw      = ref(false);
const showConfirm = ref(false);
const submitting  = ref(false);
const success     = ref(false);
const error       = ref("");
const pwError     = ref("");
const confirmError= ref("");

// If no token in URL, show an error immediately
const missingToken = !token.value;

function pwChecks(pw) {
  return [
    { label: "At least 8 characters", ok: pw.length >= 8 },
    { label: "Uppercase letter",       ok: /[A-Z]/.test(pw) },
    { label: "Number or symbol",       ok: /[0-9!@#$%^&*]/.test(pw) },
  ];
}
function pwScore(pw) { return pwChecks(pw).filter((c) => c.ok).length; }
const pwColors = ["#E5E5E5", "#B91C1C", "#B45309", "#1A7A4A"];
const pwLabels = ["", "Weak", "Fair", "Strong"];

async function handleSubmit(e) {
  e.preventDefault();
  pwError.value = "";
  confirmError.value = "";
  error.value = "";

  if (!password.value || password.value.length < 8) {
    pwError.value = "Password must be at least 8 characters.";
    return;
  }
  if (password.value !== confirm.value) {
    confirmError.value = "Passwords do not match.";
    return;
  }

  submitting.value = true;
  try {
    await auth.resetPassword(token.value, password.value);
    success.value = true;
    setTimeout(() => router.push("/"), 2500);
  } catch (err) {
    error.value = err.response?.data?.error || err.message || "Something went wrong. Please try again.";
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="reset-page">
    <div class="reset-bg-orb reset-bg-orb--1" />
    <div class="reset-bg-orb reset-bg-orb--2" />

    <div class="reset-card">
      <!-- Logo -->
      <div class="reset-logo">
        <span style="color: #e2e8f0">Event</span><span style="color: #f87171">Ora</span>
      </div>

      <!-- Missing token error -->
      <div v-if="missingToken" class="reset-state">
        <div class="reset-icon-wrap reset-icon-wrap--error">
          <Lock :size="36" style="color: #dc2626" />
        </div>
        <h1 class="reset-title">Invalid reset link</h1>
        <p class="reset-sub">This password reset link is invalid or has already been used. Please request a new one.</p>
        <button class="reset-btn reset-btn--secondary" @click="$router.push('/')">Back to home</button>
      </div>

      <!-- Success state -->
      <div v-else-if="success" class="reset-state">
        <div class="reset-icon-wrap reset-icon-wrap--success">
          <CheckCircle2 :size="40" style="color: #16a34a" />
        </div>
        <h1 class="reset-title">Password updated! 🔑</h1>
        <p class="reset-sub">Your password has been changed successfully. Redirecting you to EventOra…</p>
        <div class="reset-progress"><div class="reset-progress__bar" /></div>
        <button class="reset-btn" @click="$router.push('/')">Go to EventOra →</button>
      </div>

      <!-- Reset form -->
      <div v-else>
        <div class="reset-icon-wrap reset-icon-wrap--default" style="margin: 0 auto 24px">
          <Lock :size="32" style="color: #f87171" />
        </div>
        <h1 class="reset-title">Choose a new password</h1>
        <p class="reset-sub">Enter a strong new password for your EventOra account.</p>

        <!-- General error -->
        <div v-if="error" class="reset-error">
          <AlertCircle :size="16" />
          <span>{{ error }}</span>
        </div>

        <form @submit="handleSubmit" novalidate>
          <!-- New password -->
          <div class="reset-field">
            <label class="reset-label">New password</label>
            <div class="reset-input-wrap" :class="{ 'reset-input-wrap--error': pwError }">
              <Lock :size="16" class="reset-input-icon" />
              <input
                :type="showPw ? 'text' : 'password'"
                v-model="password"
                placeholder="Create a strong password"
                class="reset-input"
                style="padding-right: 44px"
              />
              <button type="button" @click="showPw = !showPw" class="reset-pw-toggle">
                <component :is="showPw ? EyeOff : Eye" :size="16" />
              </button>
            </div>
            <p v-if="pwError" class="reset-field-error"><AlertCircle :size="12" /> {{ pwError }}</p>
          </div>

          <!-- Password strength -->
          <div v-if="password" class="reset-strength">
            <div class="reset-strength__bars">
              <div
                v-for="i in 3" :key="i"
                class="reset-strength__bar"
                :style="{ background: (i - 1) < pwScore(password) ? pwColors[pwScore(password)] : 'rgba(255,255,255,0.1)' }"
              />
            </div>
            <div class="reset-strength__checks">
              <span v-for="c in pwChecks(password)" :key="c.label" :style="{ color: c.ok ? '#4ade80' : '#64748b' }">
                <CheckCircle2 :size="10" :style="{ opacity: c.ok ? 1 : 0.3 }" /> {{ c.label }}
              </span>
              <span :style="{ fontWeight: 600, color: pwColors[pwScore(password)] }">{{ pwLabels[pwScore(password)] }}</span>
            </div>
          </div>

          <!-- Confirm password -->
          <div class="reset-field">
            <label class="reset-label">Confirm new password</label>
            <div class="reset-input-wrap" :class="{ 'reset-input-wrap--error': confirmError }">
              <Lock :size="16" class="reset-input-icon" />
              <input
                :type="showConfirm ? 'text' : 'password'"
                v-model="confirm"
                placeholder="Repeat your new password"
                class="reset-input"
                style="padding-right: 44px"
              />
              <button type="button" @click="showConfirm = !showConfirm" class="reset-pw-toggle">
                <component :is="showConfirm ? EyeOff : Eye" :size="16" />
              </button>
            </div>
            <p v-if="confirmError" class="reset-field-error"><AlertCircle :size="12" /> {{ confirmError }}</p>
          </div>

          <button type="submit" :disabled="submitting" class="reset-btn" :class="{ 'reset-btn--loading': submitting }">
            <span v-if="submitting" class="reset-spinner" />
            {{ submitting ? "Updating…" : "" }}
            <template v-if="!submitting">Set new password <ChevronRight :size="16" /></template>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.reset-page {
  min-height: 100vh;
  background: #0f0f1a;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Inter', sans-serif;
  position: relative;
  overflow: hidden;
  padding: 24px;
}

.reset-bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
}
.reset-bg-orb--1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(220,38,38,.2) 0%, transparent 70%);
  top: -120px; right: -80px;
}
.reset-bg-orb--2 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(159,18,57,.2) 0%, transparent 70%);
  bottom: -100px; left: -60px;
}

.reset-card {
  background: linear-gradient(135deg, rgba(26,26,46,.95), rgba(22,33,62,.95));
  border: 1px solid rgba(220,38,38,.25);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 440px;
  backdrop-filter: blur(12px);
  box-shadow: 0 32px 64px rgba(0,0,0,.5);
  position: relative;
  z-index: 1;
}

.reset-logo {
  font-size: 24px;
  font-weight: 800;
  text-align: center;
  margin-bottom: 32px;
  letter-spacing: -0.5px;
}

.reset-state { display: flex; flex-direction: column; align-items: center; text-align: center; }

.reset-icon-wrap {
  width: 72px; height: 72px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px;
}
.reset-icon-wrap--default { background: rgba(220,38,38,.15); }
.reset-icon-wrap--success  { background: rgba(22,163,74,.15); }
.reset-icon-wrap--error    { background: rgba(220,38,38,.15); }

.reset-title {
  font-size: 22px; font-weight: 700; color: #e2e8f0;
  margin: 0 0 10px; text-align: center;
}
.reset-sub {
  font-size: 14px; color: #94a3b8; line-height: 1.7;
  margin: 0 0 28px; text-align: center;
}

.reset-error {
  background: rgba(220,38,38,.1);
  border: 1px solid rgba(220,38,38,.3);
  border-radius: 8px; padding: 12px;
  margin-bottom: 20px; color: #fca5a5;
  font-size: 13px; display: flex; align-items: center; gap: 8px;
}

.reset-field { margin-bottom: 16px; }
.reset-label {
  display: block; font-size: 13px; font-weight: 500;
  color: #cbd5e1; margin-bottom: 6px;
}

.reset-input-wrap {
  position: relative; display: flex; align-items: center;
  border: 1px solid rgba(255,255,255,.12); border-radius: 8px;
  background: rgba(255,255,255,.05);
  transition: border-color 150ms;
}
.reset-input-wrap:focus-within { border-color: rgba(248,113,113,.5); }
.reset-input-wrap--error { border-color: rgba(220,38,38,.5); background: rgba(220,38,38,.05); }

.reset-input {
  flex: 1; height: 46px; padding-left: 40px; padding-right: 12px;
  background: transparent; border: none; outline: none;
  font-size: 14px; color: #e2e8f0;
  font-family: inherit;
}
.reset-input::placeholder { color: #475569; }

.reset-input-icon {
  position: absolute; left: 12px; color: #64748b; pointer-events: none;
}
.reset-pw-toggle {
  position: absolute; right: 12px;
  background: none; border: none; cursor: pointer;
  color: #64748b; display: flex; padding: 0;
}

.reset-field-error {
  font-size: 12px; color: #f87171;
  margin-top: 4px; display: flex; align-items: center; gap: 4px;
}

.reset-strength { margin-bottom: 16px; }
.reset-strength__bars {
  display: flex; gap: 4px; margin-bottom: 8px;
}
.reset-strength__bar {
  flex: 1; height: 3px; border-radius: 2px;
  transition: background 200ms;
}
.reset-strength__checks {
  display: flex; flex-wrap: wrap; gap: 6px 16px;
  font-size: 11px; align-items: center;
}
.reset-strength__checks span {
  display: flex; align-items: center; gap: 4px;
}

.reset-progress {
  width: 100%; height: 4px;
  background: rgba(22,163,74,.2);
  border-radius: 2px; overflow: hidden; margin-bottom: 28px;
}
.reset-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #16a34a, #4ade80);
  border-radius: 2px;
  animation: progress 2.5s linear forwards;
}
@keyframes progress { from { width: 0 } to { width: 100% } }

.reset-btn {
  width: 100%; height: 46px;
  background: linear-gradient(135deg, #dc2626, #9f1239);
  color: #fff; border: none; border-radius: 10px;
  font-size: 15px; font-weight: 600; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-family: inherit; transition: opacity 150ms;
  margin-top: 8px;
}
.reset-btn:hover:not(:disabled) { opacity: 0.9; }
.reset-btn--loading { opacity: 0.7; cursor: not-allowed; }

.reset-btn--secondary {
  background: transparent;
  border: 1px solid rgba(220,38,38,.3);
  color: #fca5a5;
}
.reset-btn--secondary:hover { background: rgba(220,38,38,.1); opacity: 1; }

@keyframes spin-anim { to { transform: rotate(360deg); } }
.reset-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff; border-radius: 50%;
  display: inline-block;
  animation: spin-anim 0.7s linear infinite;
}
</style>
