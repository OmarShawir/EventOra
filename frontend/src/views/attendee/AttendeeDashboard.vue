<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { QrCode, Calendar, MapPin, Star, Download, CheckCircle2, Clock, Sparkles, Award, ChevronRight, X } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useTicketsStore } from "@/stores/tickets";
import { useFeedbackStore } from "@/stores/feedback";
import { useEventsStore } from "@/stores/events";
import Footer from "@/components/common/Footer.vue";

const router = useRouter();
const auth = useAuthStore();
const ticketsStore = useTicketsStore();
const feedbackStore = useFeedbackStore();
const eventsStore = useEventsStore();

const activeTab = ref("upcoming");

const myTickets = computed(() => ticketsStore.myTickets);
const upcomingTickets = computed(() => myTickets.value.filter((t) => t.event?.status !== "completed" && t.status !== "cancelled"));
const pastTickets = computed(() => myTickets.value.filter((t) => t.event?.status === "completed"));

const attendedCategories = computed(() => new Set(myTickets.value.map((t) => t.event?.category).filter(Boolean)));
const registeredIds = computed(() => new Set(myTickets.value.map((t) => t.eventId)));
const recommended = computed(() =>
  eventsStore.events.filter((e) =>
    e.status === "approved" &&
    !registeredIds.value.has(e.id) &&
    (attendedCategories.value.has(e.category) || attendedCategories.value.size === 0)
  ).slice(0, 3)
);

const displayTickets = computed(() => activeTab.value === "upcoming" ? upcomingTickets.value : pastTickets.value);

// QR SVG data modules
function qrRects(qrCode) {
  const rects = [];
  for (let r = 0; r < 11; r++) {
    for (let c = 0; c < 11; c++) {
      const hash = (qrCode.charCodeAt((r * 11 + c) % qrCode.length) + r * 5 + c * 7) % 3;
      if (hash !== 0) rects.push({ x: 9 + c, y: 9 + r });
    }
  }
  return rects;
}

// Per-ticket state (expanded QR, feedback modal, cert modal)
const expandedTickets = ref(new Set());
const feedbackTicket = ref(null);
const certTicket = ref(null);

// Feedback form state
const fbRating = ref(0);
const fbHover = ref(0);
const fbComment = ref("");
const fbDone = ref(false);

function openFeedback(ticket) {
  feedbackTicket.value = ticket;
  fbRating.value = 0;
  fbHover.value = 0;
  fbComment.value = "";
  fbDone.value = feedbackStore.hasFeedback(ticket.eventId);
}

function submitFeedback(e) {
  e.preventDefault();
  if (!fbRating.value) return;
  feedbackStore.submitFeedback(feedbackTicket.value.eventId, fbRating.value, fbComment.value);
  fbDone.value = true;
}

function downloadICS(ticket) {
  const ics = `BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:${ticket.event.title}\nLOCATION:${ticket.event.venue}\nDTSTART:20260621T090000\nDTEND:20260621T170000\nDESCRIPTION:${ticket.event.societyName}\nEND:VEVENT\nEND:VCALENDAR`;
  const blob = new Blob([ics], { type: "text/calendar" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url; a.download = `${ticket.event.title.replace(/\s+/g, "_")}.ics`; a.click();
}

const ratingLabels = ["", "Poor", "Fair", "Good", "Very good", "Excellent!"];
</script>

<template>
  <!-- Not logged in -->
  <div v-if="!auth.user" style="max-width:480px;margin:80px auto;padding:0 24px;text-align:center">
    <div style="width:64px;height:64px;border-radius:50%;background:#FFF5F5;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <QrCode :size="28" style="color:#520000"/>
    </div>
    <h2 style="font-size:22px;font-weight:700;color:#1a1a1a;margin-bottom:8px">Sign in to view your tickets</h2>
    <p style="font-size:15px;color:#555555;margin-bottom:24px">Your registered events and QR tickets will appear here.</p>
    <button @click="router.push('/')" style="height:44px;padding:0 24px;background:#520000;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;font-family:inherit">Browse Events</button>
  </div>

  <template v-else>
    <!-- Header -->
    <div style="background:linear-gradient(to bottom,#FFF5F5,#F9F9F9);padding:40px 24px 0">
      <div style="max-width:1280px;margin:0 auto">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:32px">
          <div style="width:52px;height:52px;border-radius:50%;background:#520000;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700">{{ auth.user.initials }}</div>
          <div>
            <h1 style="font-size:22px;font-weight:700;color:#1a1a1a">Welcome back, {{ auth.user.name.split(' ')[0] }}!</h1>
            <p style="font-size:14px;color:#555555">{{ myTickets.length }} registered event{{ myTickets.length !== 1 ? 's' : '' }} total</p>
          </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px">
          <div v-for="s in [
            { label:'Upcoming', value:upcomingTickets.length, color:'#520000' },
            { label:'Attended', value:pastTickets.filter(t=>t.status==='checked_in').length, color:'#1A7A4A' },
            { label:'Certificates', value:pastTickets.filter(t=>t.status==='checked_in').length, color:'#B45309' },
          ]" :key="s.label" style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:16px 20px">
            <p :style="{ fontSize:'26px', fontWeight:700, color:s.color }">{{ s.value }}</p>
            <p style="font-size:13px;color:#555555">{{ s.label }}</p>
          </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex;border-bottom:1px solid #E5E5E5">
          <button v-for="tab in [
            { key:'upcoming', label:'Upcoming', count:upcomingTickets.length },
            { key:'past', label:'Past Events', count:pastTickets.length },
            { key:'recommendations', label:'For You', count:recommended.length },
          ]" :key="tab.key" @click="activeTab=tab.key"
            :style="{ background:'none', border:'none', cursor:'pointer', fontSize:'14px', fontWeight:activeTab===tab.key?600:400, color:activeTab===tab.key?'#520000':'#555555', padding:'10px 0', paddingRight:'24px', borderBottom:`2px solid ${activeTab===tab.key?'#520000':'transparent'}`, marginBottom:'-1px', display:'flex', alignItems:'center', gap:'6px', fontFamily:'inherit' }">
            <Sparkles v-if="tab.key==='recommendations'" :size="14"/>
            {{ tab.label }}
            <span :style="{ background:activeTab===tab.key?'#FFF5F5':'#F0F0F0', color:activeTab===tab.key?'#520000':'#555555', borderRadius:'10px', fontSize:'11px', padding:'1px 7px', fontWeight:600 }">{{ tab.count }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div style="max-width:1280px;margin:0 auto;padding:32px 24px 64px">

      <!-- Ticket list -->
      <template v-if="activeTab !== 'recommendations'">
        <div v-if="displayTickets.length === 0" style="text-align:center;padding:64px 0">
          <QrCode :size="56" style="color:#C17070;margin-bottom:16px;stroke-width:1.5"/>
          <h3 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:8px">
            {{ activeTab==='upcoming' ? 'No upcoming events' : 'No past events yet' }}
          </h3>
          <p style="font-size:14px;color:#555555;margin-bottom:20px">
            {{ activeTab==='upcoming' ? 'Register for events to see them here.' : 'Attend events to build your history.' }}
          </p>
          <button @click="router.push('/')" style="height:44px;padding:0 24px;background:#520000;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit">Discover Events</button>
        </div>

        <div v-else style="display:flex;flex-direction:column;gap:16px">
          <!-- Ticket card -->
          <div v-for="t in displayTickets" :key="t.id" style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
            <div :style="{ height:'4px', background: t.status==='checked_in' ? '#1A7A4A' : t.event?.status==='completed' ? '#555555' : '#520000' }"/>
            <div style="padding:20px">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                <div style="flex:1;min-width:0">
                  <p style="font-size:11px;font-weight:500;color:#520000;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px">{{ t.event?.societyName }}</p>
                  <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;line-height:1.3;margin-bottom:8px">{{ t.event?.title }}</h3>
                  <div style="display:flex;flex-wrap:wrap;gap:12px">
                    <span style="display:flex;align-items:center;gap:5px;font-size:13px;color:#555555"><Calendar :size="13"/>{{ t.event?.date }} · {{ t.event?.time }}</span>
                    <span style="display:flex;align-items:center;gap:5px;font-size:13px;color:#555555"><MapPin :size="13"/>{{ t.event?.venue }}</span>
                  </div>
                </div>
                <div style="margin-left:16px;flex-shrink:0">
                  <span v-if="t.status==='checked_in'" style="background:#D1FAE5;color:#065F46;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;display:flex;align-items:center;gap:4px">
                    <CheckCircle2 :size="12"/> Checked In
                  </span>
                  <span v-else-if="t.event?.status==='completed'" style="background:#F9F9F9;color:#555555;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px">Completed</span>
                  <span v-else style="background:#FFF5F5;color:#520000;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;display:flex;align-items:center;gap:4px">
                    <Clock :size="12"/> Upcoming
                  </span>
                </div>
              </div>

              <!-- QR toggle -->
              <button @click="expandedTickets.has(t.id) ? expandedTickets.delete(t.id) : expandedTickets.add(t.id)"
                style="display:flex;align-items:center;gap:6px;background:none;border:1px solid #E5E5E5;border-radius:6px;padding:6px 12px;font-size:13px;color:#555555;cursor:pointer;margin-bottom:expandedTickets.has(t.id)?16:0;font-family:inherit">
                <QrCode :size="14"/> {{ expandedTickets.has(t.id) ? 'Hide QR Code' : 'Show QR Code' }}
              </button>

              <div v-if="expandedTickets.has(t.id)" style="display:flex;gap:20px;align-items:flex-start;padding:16px 0 8px;border-top:1px solid #E5E5E5;margin-top:16px">
                <!-- QR SVG -->
                <div style="background:#1a1a1a;border-radius:6px;padding:10px;display:inline-block;flex-shrink:0">
                  <svg width="100" height="100" viewBox="0 0 29 29" style="image-rendering:pixelated;display:block">
                    <rect width="29" height="29" fill="#fff"/>
                    <rect x="1" y="1" width="7" height="7" fill="#1A1A1A"/>
                    <rect x="2" y="2" width="5" height="5" fill="#fff"/>
                    <rect x="3" y="3" width="3" height="3" fill="#1A1A1A"/>
                    <rect x="21" y="1" width="7" height="7" fill="#1A1A1A"/>
                    <rect x="22" y="2" width="5" height="5" fill="#fff"/>
                    <rect x="23" y="3" width="3" height="3" fill="#1A1A1A"/>
                    <rect x="1" y="21" width="7" height="7" fill="#1A1A1A"/>
                    <rect x="2" y="22" width="5" height="5" fill="#fff"/>
                    <rect x="3" y="23" width="3" height="3" fill="#1A1A1A"/>
                    <rect v-for="(r,i) in qrRects(t.qrCode)" :key="i" :x="r.x" :y="r.y" width="1" height="1" fill="#1A1A1A"/>
                    <rect x="13" y="13" width="3" height="3" fill="#520000"/>
                  </svg>
                </div>
                <div>
                  <p style="font-size:11px;color:#AAAAAA;font-weight:500;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em">Ticket Reference</p>
                  <p style="font-size:12px;font-family:'JetBrains Mono',monospace;color:#520000;font-weight:600;letter-spacing:0.06em;margin-bottom:12px;word-break:break-all">{{ t.qrCode }}</p>
                  <p style="font-size:11px;color:#AAAAAA;margin-bottom:2px">Issued: {{ t.issuedAt }}</p>
                  <p style="font-size:11px;color:#AAAAAA">Show this QR at the venue entrance</p>
                </div>
              </div>

              <!-- Action row -->
              <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                <button v-if="t.event?.status!=='completed'" @click="downloadICS(t)" style="display:flex;align-items:center;gap:5px;height:32px;padding:0 12px;border:1px solid #E5E5E5;border-radius:6px;background:none;font-size:12px;color:#555555;cursor:pointer;font-family:inherit">
                  <Calendar :size="13"/> Add to Calendar
                </button>
                <button v-if="t.event?.status==='completed' && t.status==='checked_in' && !feedbackStore.hasFeedback(t.eventId)" @click="openFeedback(t)" style="display:flex;align-items:center;gap:5px;height:32px;padding:0 12px;border:1px solid #B45309;border-radius:6px;background:#FEF3C7;font-size:12px;color:#B45309;font-weight:500;cursor:pointer;font-family:inherit">
                  <Star :size="13"/> Rate this event
                </button>
                <span v-if="feedbackStore.hasFeedback(t.eventId)" style="display:flex;align-items:center;gap:5px;height:32px;padding:0 12px;font-size:12px;color:#1A7A4A">
                  <CheckCircle2 :size="13"/> Feedback submitted
                </span>
                <button v-if="t.event?.status==='completed' && t.status==='checked_in'" @click="certTicket=t" style="display:flex;align-items:center;gap:5px;height:32px;padding:0 12px;border:none;border-radius:6px;background:#520000;font-size:12px;color:#fff;font-weight:500;cursor:pointer;font-family:inherit">
                  <Award :size="13"/> Certificate
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Recommendations -->
      <template v-if="activeTab === 'recommendations'">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
          <Sparkles :size="16" style="color:#520000"/>
          <p style="font-size:14px;color:#555555">
            {{ attendedCategories.size > 0 ? `Based on your interest in ${[...attendedCategories].join(', ')} events` : 'Curated events just for you' }}
          </p>
        </div>
        <p v-if="recommended.length===0" style="color:#555555;font-size:14px">No new recommendations right now — check back after attending more events!</p>
        <div v-else style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
          <div v-for="ev in recommended" :key="ev.id" @click="router.push(`/events/${ev.id}`)" class="rec-card">
            <img v-if="ev.imageUrl" :src="ev.imageUrl" :alt="ev.title" style="width:100%;height:120px;object-fit:cover"/>
            <div style="padding:16px">
              <span style="background:#FFF5F5;border:1px solid #C17070;border-radius:20px;font-size:11px;color:#520000;padding:2px 8px">{{ ev.category }}</span>
              <h3 style="font-size:15px;font-weight:700;color:#1a1a1a;margin:8px 0 4px">{{ ev.title }}</h3>
              <p style="font-size:13px;color:#555555;margin-bottom:12px">{{ ev.date }} · {{ ev.societyName }}</p>
              <button @click.stop="router.push(`/events/${ev.id}`)" style="display:flex;align-items:center;gap:4px;background:none;border:none;color:#520000;font-size:13px;font-weight:600;cursor:pointer;padding:0;font-family:inherit">
                View event <ChevronRight :size="14"/>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <Footer/>

    <!-- Feedback modal -->
    <Teleport to="body">
      <div v-if="feedbackTicket" @click.self="feedbackTicket=null" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:440px;padding:24px;box-shadow:0 24px 48px rgba(0,0,0,0.18)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
            <div>
              <h2 style="font-size:18px;font-weight:700;color:#1a1a1a">Rate this event</h2>
              <p style="font-size:13px;color:#555555;margin-top:2px">{{ feedbackTicket.event.title }}</p>
            </div>
            <button @click="feedbackTicket=null" style="background:none;border:none;cursor:pointer;color:#555555"><X :size="20"/></button>
          </div>

          <div v-if="fbDone" style="text-align:center;padding:24px 0">
            <div style="width:56px;height:56px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
              <CheckCircle2 :size="28" style="color:#1A7A4A"/>
            </div>
            <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:6px">Thank you for your feedback!</h3>
            <p style="font-size:13px;color:#555555">Your response helps organisers improve future events.</p>
            <button @click="feedbackTicket=null" style="margin-top:20px;height:40px;padding:0 24px;background:#520000;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit">Close</button>
          </div>

          <form v-else @submit="submitFeedback">
            <div style="text-align:center;margin-bottom:20px">
              <p style="font-size:14px;color:#555555;margin-bottom:12px">How would you rate your experience?</p>
              <div style="display:flex;justify-content:center;gap:8px">
                <button v-for="s in 5" :key="s" type="button" @click="fbRating=s" @mouseenter="fbHover=s" @mouseleave="fbHover=0"
                  style="background:none;border:none;cursor:pointer;padding:4px;transition:transform 150ms;font-family:inherit">
                  <Star :size="32" :fill="(fbHover||fbRating)>=s?'#B45309':'none'" :style="{ color:(fbHover||fbRating)>=s?'#B45309':'#E5E5E5', transition:'color 150ms, fill 150ms' }"/>
                </button>
              </div>
              <p style="font-size:13px;color:#B45309;font-weight:500;margin-top:8px;min-height:20px">{{ ratingLabels[fbHover||fbRating]??'' }}</p>
            </div>
            <div style="margin-bottom:20px">
              <label style="font-size:13px;font-weight:500;color:#1a1a1a;display:block;margin-bottom:6px">Comments (optional)</label>
              <textarea v-model="fbComment" placeholder="Share your experience — what went well, what could be better?" rows="4"
                style="width:100%;border:1px solid #E5E5E5;border-radius:6px;padding:12px;font-size:14px;color:#1a1a1a;resize:vertical;outline:none;box-sizing:border-box;font-family:inherit"/>
            </div>
            <button type="submit" :disabled="!fbRating"
              :style="{ width:'100%', height:'44px', background:fbRating?'#520000':'#E5E5E5', color:fbRating?'#fff':'#AAAAAA', border:'none', borderRadius:'8px', fontSize:'15px', fontWeight:600, cursor:fbRating?'pointer':'not-allowed', transition:'background 150ms', fontFamily:'inherit' }">
              Submit Feedback
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Certificate modal -->
    <Teleport to="body">
      <div v-if="certTicket" @click.self="certTicket=null" style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:560px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.22);text-align:center">
          <div style="border:3px solid #520000;border-radius:8px;padding:28px 32px;position:relative;margin-bottom:20px">
            <div style="position:absolute;inset:6px;border:1px solid #C17070;border-radius:4px;pointer-events:none"/>
            <p style="font-size:11px;font-weight:500;color:#520000;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:8px">Universiti Teknologi Malaysia</p>
            <div style="width:48px;height:48px;border-radius:50%;background:#520000;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
              <span style="font-size:22px;font-weight:700;color:#fff">E</span>
            </div>
            <p style="font-size:13px;color:#555555;margin-bottom:6px">This is to certify that</p>
            <h2 style="font-size:22px;font-weight:700;color:#1a1a1a;margin-bottom:6px;font-style:italic">{{ auth.user.name }}</h2>
            <p style="font-size:13px;color:#555555;margin-bottom:6px">has successfully attended</p>
            <h3 style="font-size:17px;font-weight:700;color:#520000;margin-bottom:6px">{{ certTicket.event.title }}</h3>
            <p style="font-size:13px;color:#555555;margin-bottom:16px">organised by {{ certTicket.event.societyName }}</p>
            <p style="font-size:12px;color:#AAAAAA">{{ certTicket.event.date }} · {{ certTicket.event.venue }}</p>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:24px">
              <div style="text-align:left">
                <div style="width:80px;height:1px;background:#1a1a1a;margin-bottom:4px"/>
                <p style="font-size:11px;color:#555555">Organiser Signature</p>
              </div>
              <p style="font-size:10px;font-family:'JetBrains Mono',monospace;color:#AAAAAA">{{ certTicket.qrCode.slice(0,20) }}</p>
              <div style="text-align:right">
                <div style="width:80px;height:1px;background:#1a1a1a;margin-bottom:4px;margin-left:auto"/>
                <p style="font-size:11px;color:#555555">Faculty Advisor</p>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:10px">
            <button @click="certTicket=null" style="flex:1;height:44px;border:1px solid #E5E5E5;border-radius:8px;background:none;font-size:14px;font-weight:500;color:#555555;cursor:pointer;font-family:inherit">Close</button>
            <button @click="window.print()" style="flex:1;height:44px;border:none;border-radius:8px;background:#520000;color:#fff;font-size:14px;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit">
              <Download :size="16"/> Download Certificate
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </template>
</template>

<style scoped>
.rec-card {
  background: #fff;
  border: 1px solid #E5E5E5;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 200ms, transform 200ms;
}
.rec-card:hover {
  box-shadow: 0 4px 16px rgba(82,0,0,0.10);
  transform: translateY(-2px);
}
</style>
