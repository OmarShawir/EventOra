<script setup>
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Calendar, MapPin, Users, Clock, ArrowLeft, CreditCard, CheckCircle2, X, Tag, Share2 } from "lucide-vue-next";
import { useEventsStore } from "@/stores/events";
import { useTicketsStore } from "@/stores/tickets";
import { useAuthStore } from "@/stores/auth";
import Footer from "@/components/common/Footer.vue";

const route = useRoute();
const router = useRouter();
const eventsStore = useEventsStore();
const ticketsStore = useTicketsStore();
const auth = useAuthStore();

const event = computed(() => eventsStore.getEventById(route.params.id));
const myTicket = computed(() => ticketsStore.myTickets.find((t) => t.eventId === route.params.id));
const registered = computed(() => !!myTicket.value);
const almostFull = computed(() => event.value && event.value.spotsLeft > 0 && event.value.spotsLeft / event.value.capacity <= 0.1);
const capacityPct = computed(() => event.value ? Math.round(((event.value.capacity - event.value.spotsLeft) / event.value.capacity) * 100) : 0);

// Modal state
const showPayment = ref(false);
const newTicket = ref(null);

// Payment form
const payStep = ref("form"); // form | processing | done
const cardNum = ref("");
const expiry = ref("");
const cvv = ref("");
const cardName = ref("");

function formatCard(v) { return v.replace(/\D/g, "").slice(0, 16).replace(/(.{4})/g, "$1 ").trim(); }
function formatExpiry(v) { const d = v.replace(/\D/g, "").slice(0, 4); return d.length >= 3 ? `${d.slice(0,2)}/${d.slice(2)}` : d; }

function handleRegister() {
  if (!auth.isAuthenticated) { router.push({ query: { authRequired: "1" } }); return; }
  if (event.value.price > 0) { showPayment.value = true; return; }
  const ticket = ticketsStore.registerFree(event.value.id);
  newTicket.value = ticket;
}

async function handlePay(e) {
  e.preventDefault();
  payStep.value = "processing";
  await new Promise((r) => setTimeout(r, 1800));
  payStep.value = "done";
  setTimeout(() => {
    showPayment.value = false;
    payStep.value = "form";
    const ticket = ticketsStore.registerPaid(event.value.id);
    newTicket.value = ticket;
  }, 1200);
}

function downloadICS() {
  const ev = event.value;
  const ics = `BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:${ev.title}\nLOCATION:${ev.venue}\nDTSTART:20260621T090000\nDTEND:20260621T170000\nDESCRIPTION:${ev.societyName}\nEND:VEVENT\nEND:VCALENDAR`;
  const blob = new Blob([ics], { type: "text/calendar" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url; a.download = `${ev.title.replace(/\s+/g, "_")}.ics`; a.click();
}

// Deterministic QR pattern from qrCode string (same algorithm as the original)
function qrRects(qrData) {
  const rects = [];
  for (let r = 0; r < 13; r++) {
    for (let c = 0; c < 13; c++) {
      const hash = (qrData.charCodeAt((r * 13 + c) % qrData.length) + r * 7 + c * 3) % 3;
      if (hash !== 0) rects.push({ x: 9 + c * 1.5, y: 9 + r * 1.5 });
    }
  }
  return rects;
}
</script>

<template>
  <!-- Not found -->
  <div v-if="!event" style="max-width:1280px;margin:0 auto;padding:64px 24px;text-align:center">
    <h2 style="font-size:24px;font-weight:700;color:#1a1a1a">Event not found</h2>
    <button @click="router.push('/')" style="margin-top:16px;background:#520000;color:#fff;border:none;border-radius:8px;padding:10px 20px;cursor:pointer;font-family:inherit">
      Back to Discover
    </button>
  </div>

  <template v-else>
    <!-- Hero -->
    <div style="position:relative;height:340px;background:#1a1a1a;overflow:hidden">
      <img v-if="event.imageUrl" :src="event.imageUrl" :alt="event.title" style="width:100%;height:100%;object-fit:cover;opacity:0.65"/>
      <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.7) 0%,transparent 60%)"/>
      <button @click="router.go(-1)" style="position:absolute;top:24px;left:24px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:8px;color:#fff;display:flex;align-items:center;gap:6px;padding:8px 14px;cursor:pointer;backdrop-filter:blur(8px);font-size:14px;font-family:inherit">
        <ArrowLeft :size="16"/> Back
      </button>
      <div style="position:absolute;bottom:28px;left:0;right:0;padding:0 24px;max-width:1280px;margin:0 auto">
        <div style="max-width:1280px;margin:0 auto">
          <span style="background:#520000;color:#fff;font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:0.05em;padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:10px">{{ event.category }}</span>
          <span v-if="event.status === 'completed'" style="background:rgba(0,0,0,0.5);color:rgba(255,255,255,0.85);font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:10px;margin-left:8px">🏁 Event Ended</span>
          <h1 style="font-size:clamp(22px,4vw,36px);font-weight:700;color:#fff;line-height:1.2">{{ event.title }}</h1>
          <p style="font-size:14px;color:rgba(255,255,255,0.75);margin-top:6px">{{ event.societyName }}</p>
        </div>
      </div>
    </div>

    <!-- Body grid -->
    <div class="event-detail-grid" style="max-width:1280px;margin:0 auto;padding:32px 24px 64px;display:grid;grid-template-columns:1fr min(360px,100%);gap:32px;align-items:start">

      <!-- Left column -->
      <div>
        <!-- About -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px;margin-bottom:24px">
          <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:16px">About this event</h2>
          <p style="font-size:15px;color:#555555;line-height:1.75">{{ event.description }}</p>
          <div v-if="event.tags && event.tags.length" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
            <span v-for="tag in event.tags" :key="tag" style="background:#FFF5F5;border:1px solid #C17070;border-radius:20px;font-size:12px;color:#520000;padding:3px 10px;display:flex;align-items:center;gap:4px">
              <Tag :size="11"/> {{ tag }}
            </span>
          </div>
        </div>

        <!-- Event details -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px;margin-bottom:24px">
          <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:16px">Event details</h2>
          <div v-for="item in [
            { icon: Calendar, label: 'Date', value: event.date },
            { icon: Clock, label: 'Time', value: `${event.time} – ${event.endsAt}` },
            { icon: MapPin, label: 'Venue', value: event.venue },
            { icon: Users, label: 'Capacity', value: `${event.capacity - event.spotsLeft} / ${event.capacity} registered` },
          ]" :key="item.label" style="display:flex;gap:16px;margin-bottom:16px;align-items:flex-start">
            <div style="width:36px;height:36px;border-radius:8px;background:#FFF5F5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <component :is="item.icon" :size="16" style="color:#520000"/>
            </div>
            <div>
              <p style="font-size:12px;color:#555555;margin-bottom:2px">{{ item.label }}</p>
              <p style="font-size:14px;font-weight:500;color:#1a1a1a">{{ item.value }}</p>
            </div>
          </div>
          <!-- Capacity bar -->
          <div style="margin-top:8px">
            <div style="height:6px;background:#E5E5E5;border-radius:4px;overflow:hidden">
              <div :style="{ height:'100%', width:`${capacityPct}%`, background: almostFull ? '#B45309' : event.spotsLeft===0 ? '#520000' : '#1A7A4A', borderRadius:'4px', transition:'width 300ms' }"/>
            </div>
            <p style="font-size:12px;color:#555555;margin-top:4px">{{ capacityPct }}% capacity filled</p>
          </div>
        </div>

        <!-- Organised by -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px">
          <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:4px">Organised by</h2>
          <p style="font-size:14px;color:#520000;font-weight:500">{{ event.societyName }}</p>
          <p style="font-size:13px;color:#555555;margin-top:4px">Contact: {{ event.organiserName }}</p>
        </div>
      </div>

      <!-- Right: sticky registration panel -->
      <div style="position:sticky;top:88px">
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.06)">

          <!-- COMPLETED -->
          <template v-if="event.status === 'completed'">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
              <span style="background:#F3F4F6;color:#555555;font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px">Event Ended</span>
            </div>
            <div style="background:#F9F9F9;border:1px solid #E5E5E5;border-radius:8px;padding:16px;text-align:center;margin-bottom:16px">
              <p style="font-size:22px;margin-bottom:6px">🏁</p>
              <p style="font-size:14px;font-weight:600;color:#1a1a1a;margin-bottom:4px">This event has ended</p>
              <p style="font-size:13px;color:#555555;line-height:1.55">Registration is now closed. {{ event.capacity - event.spotsLeft }} people attended this event.</p>
            </div>
            <button @click="router.push('/societies')" class="detail-btn detail-btn--outline">
              Browse more from {{ event.societyName.replace('UTM ', '') }}
            </button>
          </template>

          <!-- ACTIVE / UPCOMING -->
          <template v-else>
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px">
              <span :style="{ fontSize:'26px', fontWeight:700, color: event.price===0 ? '#1A7A4A' : '#1a1a1a' }">
                {{ event.price === 0 ? 'FREE' : `RM ${event.price.toFixed(2)}` }}
              </span>
              <span v-if="event.price > 0" style="font-size:13px;color:#555555">per person</span>
            </div>
            <p :style="{ fontSize:'13px', fontWeight:500, marginBottom:'20px', color: event.spotsLeft===0 ? '#520000' : almostFull ? '#B45309' : '#1A7A4A' }">
              {{ event.spotsLeft===0 ? 'Fully booked — waitlist available' : almostFull ? `Only ${event.spotsLeft} spots left!` : `${event.spotsLeft} spots available` }}
            </p>

            <div v-if="registered" style="background:#D1FAE5;border-radius:8px;padding:14px;text-align:center;margin-bottom:16px">
              <CheckCircle2 :size="20" style="color:#1A7A4A;margin-bottom:4px"/>
              <p style="font-size:14px;font-weight:600;color:#065F46">You're registered!</p>
              <button @click="router.push('/dashboard')" style="margin-top:8px;background:none;border:none;color:#520000;font-size:13px;font-weight:500;cursor:pointer;text-decoration:underline;font-family:inherit">View in My Tickets</button>
            </div>
            <button v-else @click="handleRegister" class="detail-btn detail-btn--register" :style="{ background: event.spotsLeft===0 ? 'none' : '#520000', color: event.spotsLeft===0 ? '#520000' : '#fff', border: event.spotsLeft===0 ? '1px solid #520000' : 'none' }">
              {{ event.spotsLeft===0 ? 'Join Waitlist' : event.price>0 ? `Pay RM ${event.price.toFixed(2)} & Register` : 'Register for Free' }}
            </button>

            <button @click="downloadICS" class="detail-btn detail-btn--ghost" style="margin-bottom:8px">
              <Calendar :size="14"/> Add to Calendar (.ics)
            </button>
            <button class="detail-btn detail-btn--ghost">
              <Share2 :size="14"/> Share event
            </button>
          </template>
        </div>
      </div>
    </div>

    <Footer />

    <!-- Payment modal -->
    <Teleport to="body">
      <div v-if="showPayment" @click.self="showPayment=false" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:300;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:440px;padding:24px;box-shadow:0 24px 48px rgba(0,0,0,0.2)">

          <!-- Done -->
          <div v-if="payStep==='done'" style="text-align:center;padding:24px 0">
            <div style="width:64px;height:64px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
              <CheckCircle2 :size="32" style="color:#1A7A4A"/>
            </div>
            <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:8px">Payment successful!</h2>
            <p style="font-size:14px;color:#555555">Issuing your QR ticket…</p>
          </div>

          <!-- Processing -->
          <div v-else-if="payStep==='processing'" style="text-align:center;padding:32px 0">
            <div class="pay-spinner" style="margin:0 auto 16px"/>
            <p style="font-size:15px;font-weight:500;color:#1a1a1a">Processing payment…</p>
            <p style="font-size:13px;color:#555555;margin-top:4px">Do not close this window</p>
          </div>

          <!-- Form -->
          <template v-else>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
              <div>
                <h2 style="font-size:18px;font-weight:700;color:#1a1a1a">Secure Payment</h2>
                <p style="font-size:13px;color:#555555">{{ event.title }}</p>
              </div>
              <button @click="showPayment=false" style="background:none;border:none;cursor:pointer;color:#555555"><X :size="20"/></button>
            </div>
            <div style="background:#FFF5F5;border:1px solid #C17070;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <p style="font-size:12px;color:#555555">Total to pay</p>
                <p style="font-size:22px;font-weight:700;color:#520000">RM {{ event.price.toFixed(2) }}</p>
              </div>
              <CreditCard :size="32" style="color:#C17070"/>
            </div>
            <form @submit="handlePay">
              <div style="margin-bottom:14px">
                <label style="font-size:13px;font-weight:500;color:#1a1a1a;display:block;margin-bottom:6px">Cardholder name</label>
                <input v-model="cardName" placeholder="Ahmad Syafiq" required style="width:100%;height:44px;border:1px solid #E5E5E5;border-radius:6px;padding:0 12px;font-size:14px;outline:none;box-sizing:border-box;font-family:inherit"/>
              </div>
              <div style="margin-bottom:14px">
                <label style="font-size:13px;font-weight:500;color:#1a1a1a;display:block;margin-bottom:6px">Card number</label>
                <input :value="cardNum" @input="cardNum=formatCard($event.target.value)" placeholder="1234 5678 9012 3456" required maxlength="19" style="width:100%;height:44px;border:1px solid #E5E5E5;border-radius:6px;padding:0 12px;font-size:14px;font-family:'JetBrains Mono',monospace;outline:none;box-sizing:border-box"/>
              </div>
              <div style="display:flex;gap:12px;margin-bottom:20px">
                <div style="flex:1">
                  <label style="font-size:13px;font-weight:500;color:#1a1a1a;display:block;margin-bottom:6px">Expiry</label>
                  <input :value="expiry" @input="expiry=formatExpiry($event.target.value)" placeholder="MM/YY" required maxlength="5" style="width:100%;height:44px;border:1px solid #E5E5E5;border-radius:6px;padding:0 12px;font-size:14px;font-family:'JetBrains Mono',monospace;outline:none;box-sizing:border-box"/>
                </div>
                <div style="flex:1">
                  <label style="font-size:13px;font-weight:500;color:#1a1a1a;display:block;margin-bottom:6px">CVV</label>
                  <input :value="cvv" @input="cvv=$event.target.value.replace(/\D/g,'').slice(0,3)" placeholder="•••" required maxlength="3" style="width:100%;height:44px;border:1px solid #E5E5E5;border-radius:6px;padding:0 12px;font-size:14px;font-family:'JetBrains Mono',monospace;outline:none;box-sizing:border-box"/>
                </div>
              </div>
              <button type="submit" style="width:100%;height:44px;background:#520000;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit">
                Pay RM {{ event.price.toFixed(2) }}
              </button>
              <p style="font-size:11px;color:#AAAAAA;text-align:center;margin-top:12px">🔒 256-bit SSL encrypted · Mock payment for demo purposes</p>
            </form>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- QR Ticket modal -->
    <Teleport to="body">
      <div v-if="newTicket" @click.self="newTicket=null; router.push('/dashboard')" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:300;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:360px;padding:24px;box-shadow:0 24px 48px rgba(0,0,0,0.2);text-align:center">
          <div style="background:#520000;height:6px;border-radius:8px 8px 0 0;margin:-24px -24px 20px"/>
          <div style="width:48px;height:48px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
            <CheckCircle2 :size="28" style="color:#1A7A4A"/>
          </div>
          <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:4px">You're registered!</h2>
          <p style="font-size:13px;color:#555555;margin-bottom:20px">{{ newTicket.event.title }}</p>

          <!-- SVG QR visual -->
          <div style="background:#1a1a1a;border-radius:8px;padding:16px;display:inline-block;margin-bottom:16px">
            <div style="width:140px;height:140px;background:#fff;border-radius:4px;display:grid;place-items:center;position:relative;overflow:hidden">
              <svg width="140" height="140" viewBox="0 0 29 29" style="image-rendering:pixelated">
                <rect x="1" y="1" width="7" height="7" fill="#1A1A1A"/>
                <rect x="2" y="2" width="5" height="5" fill="#fff"/>
                <rect x="3" y="3" width="3" height="3" fill="#1A1A1A"/>
                <rect x="21" y="1" width="7" height="7" fill="#1A1A1A"/>
                <rect x="22" y="2" width="5" height="5" fill="#fff"/>
                <rect x="23" y="3" width="3" height="3" fill="#1A1A1A"/>
                <rect x="1" y="21" width="7" height="7" fill="#1A1A1A"/>
                <rect x="2" y="22" width="5" height="5" fill="#fff"/>
                <rect x="3" y="23" width="3" height="3" fill="#1A1A1A"/>
                <rect v-for="(r,i) in qrRects(newTicket.qrCode)" :key="i" :x="r.x" :y="r.y" width="1" height="1" fill="#1A1A1A"/>
                <rect x="13" y="13" width="3" height="3" fill="#520000"/>
              </svg>
            </div>
          </div>

          <p style="font-size:11px;font-weight:500;color:#AAAAAA;letter-spacing:0.05em;margin-bottom:4px">TICKET REFERENCE</p>
          <p style="font-size:13px;font-family:'JetBrains Mono',monospace;color:#520000;font-weight:600;letter-spacing:0.08em;margin-bottom:20px">{{ newTicket.qrCode }}</p>

          <div style="background:#F9F9F9;border-radius:8px;padding:12px 16px;margin-bottom:20px;text-align:left">
            <div v-for="row in [['Date', newTicket.event.date], ['Time', newTicket.event.time], ['Venue', newTicket.event.venue]]" :key="row[0]" style="display:flex;justify-content:space-between;margin-bottom:6px">
              <span style="font-size:12px;color:#555555">{{ row[0] }}</span>
              <span style="font-size:12px;font-weight:500;color:#1a1a1a;text-align:right;max-width:160px">{{ row[1] }}</span>
            </div>
          </div>

          <div style="display:flex;gap:8px">
            <button @click="newTicket=null" style="flex:1;height:44px;border:1px solid #E5E5E5;border-radius:8px;background:none;font-size:14px;font-weight:500;color:#555555;cursor:pointer;font-family:inherit">Close</button>
            <button @click="newTicket=null; router.push('/dashboard')" style="flex:1;height:44px;border:none;border-radius:8px;background:#520000;color:#fff;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit">View in My Tickets</button>
          </div>
        </div>
      </div>
    </Teleport>
  </template>
</template>

<style scoped>
.event-detail-grid { grid-template-columns: 1fr min(360px, 100%) !important; }
@media (max-width: 768px) { .event-detail-grid { grid-template-columns: 1fr !important; } }

.detail-btn {
  width: 100%; height: 44px; border-radius: 8px;
  font-size: 14px; font-weight: 600; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  gap: 6px; margin-bottom: 8px; font-family: inherit;
  transition: background 150ms;
}
.detail-btn--register { height: 48px; font-size: 15px; }
.detail-btn--outline { background: none; border: 1px solid #520000; color: #520000; }
.detail-btn--outline:hover { background: #fff5f5; }
.detail-btn--ghost { background: none; border: 1px solid #E5E5E5; color: #555555; }
.detail-btn--ghost:hover { background: #f9f9f9; }

@keyframes spin { to { transform: rotate(360deg); } }
.pay-spinner {
  width: 48px; height: 48px;
  border: 3px solid #FFF5F5; border-top-color: #520000;
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
</style>
