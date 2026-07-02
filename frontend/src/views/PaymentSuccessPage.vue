<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useTicketsStore } from "@/stores/tickets";
import { useEventsStore } from "@/stores/events";
import { CheckCircle2, Ticket, ArrowRight, Loader2 } from "lucide-vue-next";
import QRCodeDisplay from "@/components/tickets/QRCodeDisplay.vue";
import api from "@/api/axios";

const route = useRoute();
const router = useRouter();
const ticketsStore = useTicketsStore();
const eventsStore = useEventsStore();

const eventId = computed(() => route.query.event_id);
const sessionId = computed(() => route.query.session_id);
const event = ref(null);
const ticket = ref(null);
const loading = ref(true);
let pollInterval = null;

async function checkTicket() {
  await ticketsStore.fetchMyTickets();
  const found = ticketsStore.myTickets.find(
    (t) => String(t.eventId) === String(eventId.value)
  );
  if (found) {
    ticket.value = found;
    loading.value = false;
    if (pollInterval) clearInterval(pollInterval);
  }
}

onMounted(async () => {
  if (!eventId.value) {
    router.push("/");
    return;
  }

  // Load event details
  try {
    await eventsStore.fetchEvents(); // Make sure events are loaded
    event.value = eventsStore.getEventById(eventId.value);
  } catch (err) {
    console.error("Failed to load event:", err);
  }

  // Issue the ticket synchronously instead of waiting on the async Stripe
  // webhook, which can be delayed or (if misconfigured) never arrive.
  if (sessionId.value) {
    try {
      await api.get("/payment/verify-session", { params: { session_id: sessionId.value } });
    } catch (err) {
      console.warn("[payment-success] verify-session failed, falling back to webhook polling:", err.message);
    }
  }

  // First check
  await checkTicket();

  // If not found yet (waiting for webhook), poll
  if (loading.value) {
    let attempts = 0;
    pollInterval = setInterval(async () => {
      attempts++;
      await checkTicket();
      if (attempts >= 6) {
        // Stop polling after 9 seconds, let them go to dashboard
        clearInterval(pollInterval);
        loading.value = false;
      }
    }, 1500);
  }
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <div class="success-page">
    <div class="success-bg-orb success-bg-orb--1" />
    <div class="success-bg-orb success-bg-orb--2" />

    <div class="success-card">
      <div class="success-icon-wrap animate-pop">
        <CheckCircle2 :size="48" style="color: #1a7a4a" />
      </div>

      <h1 class="success-title">Payment Successful!</h1>
      <p class="success-subtitle">
        Your registration for <strong v-if="event">{{ event.title }}</strong><span v-else>the event</span> is confirmed.
      </p>

      <!-- Loading ticket state -->
      <div v-if="loading" class="ticket-loading">
        <Loader2 :size="24" class="spinner" style="color: #520000" />
        <p>Generating your secure entry ticket...</p>
      </div>

      <!-- Ticket display -->
      <div v-else-if="ticket" class="ticket-display animate-slide-up">
        <div class="ticket-qr-container">
          <QRCodeDisplay :value="ticket.qrCode" :size="160" />
        </div>
        <p class="ticket-ref-label">TICKET REFERENCE</p>
        <p class="ticket-ref">{{ ticket.qrCode }}</p>

        <div class="ticket-details">
          <div class="detail-row">
            <span class="detail-label">Event</span>
            <span class="detail-val">{{ ticket.event?.title || event?.title }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Venue</span>
            <span class="detail-val">{{ ticket.event?.venue || event?.venue }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Date & Time</span>
            <span class="detail-val">{{ ticket.event?.date || event?.date }} at {{ ticket.event?.time || event?.time }}</span>
          </div>
        </div>
      </div>

      <!-- Ticket not found (delay/timeout) -->
      <div v-else class="ticket-timeout animate-slide-up">
        <p class="timeout-text">
          Your payment was processed, but ticket generation is taking slightly longer than expected.
        </p>
      </div>

      <div class="actions-container">
        <button @click="router.push('/dashboard')" class="btn-primary">
          <Ticket :size="16" /> Go to My Tickets
        </button>
        <button @click="router.push('/')" class="btn-secondary">
          Browse Events <ArrowRight :size="16" />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.success-page {
  position: relative;
  min-height: calc(100vh - 72px);
  background: #fdfdfd;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 24px;
  overflow: hidden;
}

/* Decorative Orbs */
.success-bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  z-index: 0;
  opacity: 0.4;
}
.success-bg-orb--1 {
  width: 350px;
  height: 350px;
  background: #fff5f5;
  top: -100px;
  right: -50px;
}
.success-bg-orb--2 {
  width: 400px;
  height: 400px;
  background: #fdf2f8;
  bottom: -150px;
  left: -100px;
}

.success-card {
  position: relative;
  z-index: 10;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(229, 229, 229, 0.6);
  border-radius: 20px;
  width: 100%;
  max-width: 440px;
  padding: 40px 32px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.04);
  text-align: center;
}

.success-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #e8f8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}

.success-title {
  font-size: 24px;
  font-weight: 800;
  color: #1a1a1a;
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}

.success-subtitle {
  font-size: 15px;
  color: #555555;
  line-height: 1.5;
  margin-bottom: 32px;
}

/* Loading/Timeout */
.ticket-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 24px;
  background: #f9f9f9;
  border-radius: 12px;
  border: 1px dashed #e5e5e5;
  margin-bottom: 24px;
}
.ticket-loading p {
  font-size: 14px;
  color: #555555;
}

.ticket-timeout {
  padding: 20px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 12px;
  margin-bottom: 24px;
  text-align: left;
}
.timeout-text {
  font-size: 14px;
  color: #b45309;
  line-height: 1.5;
}

/* Ticket Display styling */
.ticket-display {
  background: #ffffff;
  border: 1px solid #e5e5e5;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 28px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.ticket-qr-container {
  background: #1a1a1a;
  padding: 16px;
  border-radius: 12px;
  display: inline-block;
  margin-bottom: 16px;
}

.ticket-ref-label {
  font-size: 11px;
  font-weight: 600;
  color: #aaaaaa;
  letter-spacing: 0.08em;
  margin-bottom: 4px;
}

.ticket-ref {
  font-size: 14px;
  font-family: 'JetBrains Mono', monospace;
  color: #520000;
  font-weight: 700;
  letter-spacing: 0.05em;
  margin-bottom: 20px;
}

.ticket-details {
  border-top: 1px solid #f0f0f0;
  padding-top: 16px;
  text-align: left;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
  font-size: 13px;
}
.detail-row:last-child {
  margin-bottom: 0;
}

.detail-label {
  color: #777777;
}

.detail-val {
  font-weight: 600;
  color: #1a1a1a;
  text-align: right;
  max-width: 220px;
}

/* Buttons */
.actions-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-primary, .btn-secondary {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 150ms ease;
  font-family: inherit;
}

.btn-primary {
  background: #520000;
  color: #ffffff;
  border: none;
}
.btn-primary:hover {
  background: #3d0000;
}

.btn-secondary {
  background: #ffffff;
  color: #555555;
  border: 1px solid #e5e5e5;
}
.btn-secondary:hover {
  background: #f9f9f9;
  border-color: #d1d1d1;
}

/* Animations */
.animate-pop {
  animation: pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
}
.animate-slide-up {
  animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.spinner {
  animation: spin 1s linear infinite;
}

@keyframes pop {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes slideUp {
  0% { transform: translateY(16px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
