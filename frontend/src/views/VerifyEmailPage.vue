<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { CheckCircle2, XCircle, Loader2 } from "lucide-vue-next";

const route  = useRoute();
const router = useRouter();
const auth   = useAuthStore();

// State: "loading" | "success" | "error"
const status = ref("loading");
const errorReason = ref("");

onMounted(async () => {
  const jwt   = route.query.token;
  const error = route.query.error;

  if (error) {
    errorReason.value =
      error === "invalid"
        ? "This verification link is invalid or has already been used."
        : "The verification link is missing. Please use the link from your email.";
    status.value = "error";
    return;
  }

  if (!jwt) {
    errorReason.value = "No token found. Please use the link from your email.";
    status.value = "error";
    return;
  }

  try {
    await auth.loginWithToken(jwt);
    status.value = "success";
    // Auto-redirect after 2.5 s
    setTimeout(() => router.push("/"), 2500);
  } catch {
    errorReason.value = "Could not verify your account. Please try again.";
    status.value = "error";
  }
});
</script>

<template>
  <div class="verify-page">
    <!-- Background decoration -->
    <div class="verify-bg-orb verify-bg-orb--1" />
    <div class="verify-bg-orb verify-bg-orb--2" />

    <div class="verify-card">
      <!-- Logo -->
      <div class="verify-logo">
        <span class="verify-logo__event">Event</span><span class="verify-logo__ora">Ora</span>
      </div>

      <!-- Loading state -->
      <div v-if="status === 'loading'" class="verify-state">
        <div class="verify-icon-wrap verify-icon-wrap--loading">
          <Loader2 :size="36" class="verify-spin" style="color: #6366f1" />
        </div>
        <h1 class="verify-title">Verifying your email…</h1>
        <p class="verify-sub">Please wait while we activate your account.</p>
      </div>

      <!-- Success state -->
      <div v-else-if="status === 'success'" class="verify-state">
        <div class="verify-icon-wrap verify-icon-wrap--success">
          <CheckCircle2 :size="40" style="color: #16a34a" />
        </div>
        <h1 class="verify-title">Email verified! 🎉</h1>
        <p class="verify-sub">
          Your UTM account is now active. Redirecting you to EventOra…
        </p>
        <div class="verify-progress">
          <div class="verify-progress__bar" />
        </div>
        <button class="verify-btn" @click="$router.push('/')">
          Go to EventOra now →
        </button>
      </div>

      <!-- Error state -->
      <div v-else class="verify-state">
        <div class="verify-icon-wrap verify-icon-wrap--error">
          <XCircle :size="40" style="color: #dc2626" />
        </div>
        <h1 class="verify-title" style="color: #dc2626">Verification failed</h1>
        <p class="verify-sub">{{ errorReason }}</p>
        <button class="verify-btn verify-btn--secondary" @click="$router.push('/')">
          Return to home
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.verify-page {
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

.verify-bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
}
.verify-bg-orb--1 {
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(99,102,241,.25) 0%, transparent 70%);
  top: -120px;
  right: -80px;
}
.verify-bg-orb--2 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(139,92,246,.2) 0%, transparent 70%);
  bottom: -100px;
  left: -60px;
}

.verify-card {
  background: linear-gradient(135deg, rgba(26,26,46,.95), rgba(22,33,62,.95));
  border: 1px solid rgba(99,102,241,.3);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 440px;
  text-align: center;
  backdrop-filter: blur(12px);
  box-shadow: 0 32px 64px rgba(0,0,0,.5);
  position: relative;
  z-index: 1;
}

.verify-logo {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 36px;
  letter-spacing: -0.5px;
}
.verify-logo__event { color: #e2e8f0; }
.verify-logo__ora   { color: #818cf8; }

.verify-state { display: flex; flex-direction: column; align-items: center; gap: 0; }

.verify-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}
.verify-icon-wrap--loading { background: rgba(99,102,241,.15); }
.verify-icon-wrap--success { background: rgba(22,163,74,.15); }
.verify-icon-wrap--error   { background: rgba(220,38,38,.15); }

.verify-title {
  font-size: 22px;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0 0 12px;
}

.verify-sub {
  font-size: 14px;
  color: #94a3b8;
  line-height: 1.7;
  margin: 0 0 28px;
}

.verify-progress {
  width: 100%;
  height: 4px;
  background: rgba(99,102,241,.2);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 28px;
}
.verify-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  border-radius: 2px;
  animation: progress 2.5s linear forwards;
}
@keyframes progress { from { width: 0 } to { width: 100% } }

@keyframes spin-anim { to { transform: rotate(360deg); } }
.verify-spin { animation: spin-anim 1s linear infinite; }

.verify-btn {
  width: 100%;
  height: 46px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 150ms;
}
.verify-btn:hover { opacity: 0.9; }

.verify-btn--secondary {
  background: transparent;
  border: 1px solid rgba(99,102,241,.4);
  color: #a5b4fc;
}
.verify-btn--secondary:hover { background: rgba(99,102,241,.1); opacity: 1; }
</style>
