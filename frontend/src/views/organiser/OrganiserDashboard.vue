<script setup>
import { ref, computed } from "vue";
import { Plus, Edit2, Download, BarChart2, Star, Users, Calendar, Clock, XCircle, CheckCircle2, X } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useEventsStore } from "@/stores/events";
import { useFeedbackStore } from "@/stores/feedback";
import Footer from "@/components/common/Footer.vue";

const auth = useAuthStore();
const eventsStore = useEventsStore();
const feedbackStore = useFeedbackStore();

const showCreate = ref(false);
const editEvent = ref(null);
const activeTab = ref("events");
const confirmCancel = ref(null);

// Filter to organiser's society events
const myEvents = computed(() =>
  eventsStore.events.filter((e) => e.societyName === (auth.user?.society ?? e.societyName))
);

const totalRegistrations = computed(() => myEvents.value.reduce((acc, e) => acc + (e.capacity - e.spotsLeft), 0));
const avgRating = computed(() => {
  const myFeedback = feedbackStore.feedback.filter((f) => myEvents.value.some((e) => e.id === f.eventId));
  if (!myFeedback.length) return null;
  return (myFeedback.reduce((a, f) => a + f.rating, 0) / myFeedback.length).toFixed(1);
});

const CATEGORIES = ["Academic", "Sports", "Cultural", "Religious", "Workshop", "Career"];

const statusMap = {
  approved: { bg: "#D1FAE5", color: "#065F46", label: "Approved" },
  pending: { bg: "#FEF3C7", color: "#92400E", label: "Pending" },
  cancelled: { bg: "#FEE2E2", color: "#991B1B", label: "Cancelled" },
  completed: { bg: "#F3F4F6", color: "#374151", label: "Completed" },
};
function statusChip(status) { return statusMap[status] ?? { bg: "#F9F9F9", color: "#555555", label: status }; }

function exportCSV(ev) {
  const rows = [["Ticket ID", "Event", "Status", "Issued At"], [`TKT-${ev.id}-001`, ev.title, "checked_in", "2026-06-21"], [`TKT-${ev.id}-002`, ev.title, "active", "2026-06-20"]];
  const csv = rows.map((r) => r.join(",")).join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url; a.download = `attendance_${ev.title.replace(/\s+/g, "_")}.csv`; a.click();
}

function confirmCancelEvent() {
  eventsStore.cancelEvent(confirmCancel.value);
  confirmCancel.value = null;
}

// Analytics: category breakdown
function categoryTotal(cat) {
  return myEvents.value.filter((e) => e.category === cat).reduce((a, e) => a + (e.capacity - e.spotsLeft), 0);
}
const categoryMax = computed(() => Math.max(...CATEGORIES.map(categoryTotal), 1));

function eventFeedback(eventId) { return feedbackStore.feedback.filter((f) => f.eventId === eventId); }
function eventAvg(eventId) {
  const fb = eventFeedback(eventId);
  return fb.length ? fb.reduce((a, f) => a + f.rating, 0) / fb.length : 0;
}
const hasAnyFeedback = computed(() => feedbackStore.feedback.some((f) => myEvents.value.some((e) => e.id === f.eventId)));

// ── Event Form Modal ──────────────────────────────────────────────────────────
const formState = ref(null); // null | { isEdit, ...fields }
const formSubmitting = ref(false);
const formDone = ref(false);

function openCreate() {
  formState.value = {
    isEdit: false, title: "", description: "", category: "Academic", venue: "",
    date: "", time: "", endsAt: "", capacity: "50", price: "0", imageUrl: "",
  };
  formDone.value = false;
}
function openEdit(ev) {
  formState.value = {
    isEdit: true, id: ev.id, title: ev.title, description: ev.description, category: ev.category,
    venue: ev.venue, date: ev.date, time: ev.time, endsAt: ev.endsAt,
    capacity: String(ev.capacity), price: String(ev.price), imageUrl: ev.imageUrl ?? "",
  };
  formDone.value = false;
}
function closeForm() { formState.value = null; formDone.value = false; }

async function handleFormSubmit(e) {
  e.preventDefault();
  formSubmitting.value = true;
  await new Promise((r) => setTimeout(r, 800));
  const f = formState.value;
  const payload = {
    title: f.title, description: f.description, category: f.category, venue: f.venue,
    date: f.date, time: f.time, endsAt: f.endsAt,
    capacity: Number(f.capacity), price: Number(f.price), imageUrl: f.imageUrl,
    societyId: "s1", societyName: auth.user?.society ?? "Unknown Society",
    tags: [], organiserName: auth.user?.name ?? "",
  };
  if (f.isEdit) eventsStore.updateEvent(f.id, payload);
  else eventsStore.addEvent(payload);
  formSubmitting.value = false;
  formDone.value = true;
  setTimeout(closeForm, 700);
}
</script>

<template>
  <div v-if="!auth.user" style="padding:64px;text-align:center;color:#555555">Please log in as an organiser.</div>

  <template v-else>
    <!-- Header -->
    <div style="background:linear-gradient(to bottom,#3A0000,#520000);padding:36px 24px 0">
      <div style="max-width:1280px;margin:0 auto">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:16px">
          <div>
            <p style="font-size:12px;color:rgba(255,255,255,0.6);margin-bottom:4px;text-transform:uppercase;letter-spacing:0.08em">Organiser Dashboard</p>
            <h1 style="font-size:24px;font-weight:700;color:#fff">{{ auth.user.society ?? 'My Society' }}</h1>
            <p style="font-size:14px;color:rgba(255,255,255,0.7);margin-top:4px">Managed by {{ auth.user.name }}</p>
          </div>
          <button @click="openCreate" style="display:flex;align-items:center;gap:8px;height:44px;padding:0 20px;background:#fff;color:#520000;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">
            <Plus :size="18"/> Create Event
          </button>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px">
          <div v-for="s in [
            { label:'Total Events', value: myEvents.length, icon: Calendar },
            { label:'Registrations', value: totalRegistrations, icon: Users },
            { label:'Pending Approval', value: myEvents.filter(e=>e.status==='pending').length, icon: Clock },
            { label:'Avg Rating', value: avgRating ?? '—', icon: Star },
          ]" :key="s.label" style="background:rgba(255,255,255,0.12);border-radius:8px;padding:14px 16px;backdrop-filter:blur(4px)">
            <component :is="s.icon" :size="16" style="color:rgba(255,255,255,0.6);margin-bottom:6px"/>
            <p style="font-size:24px;font-weight:700;color:#fff">{{ s.value }}</p>
            <p style="font-size:12px;color:rgba(255,255,255,0.65)">{{ s.label }}</p>
          </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex">
          <button v-for="t in [{key:'events',label:'My Events'},{key:'analytics',label:'Analytics & Feedback'}]" :key="t.key"
            @click="activeTab=t.key"
            :style="{ background:'none', border:'none', cursor:'pointer', fontSize:'14px', fontWeight:activeTab===t.key?600:400, color:activeTab===t.key?'#fff':'rgba(255,255,255,0.55)', padding:'10px 0', paddingRight:'24px', borderBottom:`2px solid ${activeTab===t.key?'#fff':'transparent'}`, marginBottom:'-1px', fontFamily:'inherit' }">
            {{ t.label }}
          </button>
        </div>
      </div>
    </div>

    <div style="max-width:1280px;margin:0 auto;padding:32px 24px 64px">

      <!-- Events table -->
      <div v-if="activeTab==='events'" style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;overflow:hidden">
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:700px">
            <thead>
              <tr style="background:#F9F9F9;border-bottom:1px solid #E5E5E5">
                <th v-for="h in ['Event','Date','Category','Capacity','Status','Actions']" :key="h" style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#555555;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="myEvents.length===0">
                <td colspan="6" style="padding:48px 16px;text-align:center;color:#555555;font-size:14px">No events yet. Create your first event!</td>
              </tr>
              <tr v-for="(ev,i) in myEvents" :key="ev.id" class="organiser-row" :style="{ borderBottom: i<myEvents.length-1 ? '1px solid #E5E5E5' : 'none' }">
                <td style="padding:14px 16px">
                  <p style="font-size:14px;font-weight:600;color:#1a1a1a">{{ ev.title }}</p>
                  <p style="font-size:12px;color:#555555;margin-top:2px">{{ ev.societyName }}</p>
                </td>
                <td style="padding:14px 16px;font-size:13px;color:#555555;white-space:nowrap">{{ ev.date }}</td>
                <td style="padding:14px 16px">
                  <span style="background:#F9F9F9;border:1px solid #E5E5E5;border-radius:20px;font-size:12px;color:#555555;padding:2px 8px">{{ ev.category }}</span>
                </td>
                <td style="padding:14px 16px">
                  <div style="font-size:13px;color:#1a1a1a;font-weight:500">{{ ev.capacity - ev.spotsLeft }} / {{ ev.capacity }}</div>
                  <div style="height:4px;background:#E5E5E5;border-radius:2px;margin-top:4px;width:80px">
                    <div :style="{ height:'100%', width:`${Math.round(((ev.capacity-ev.spotsLeft)/ev.capacity)*100)}%`, background:'#520000', borderRadius:'2px' }"/>
                  </div>
                </td>
                <td style="padding:14px 16px">
                  <span :style="{ background:statusChip(ev.status).bg, color:statusChip(ev.status).color, fontSize:'12px', fontWeight:600, padding:'3px 10px', borderRadius:'20px' }">{{ statusChip(ev.status).label }}</span>
                </td>
                <td style="padding:14px 16px">
                  <div style="display:flex;gap:6px">
                    <button @click="openEdit(ev)" title="Edit" class="organiser-icon-btn"><Edit2 :size="14"/></button>
                    <button @click="exportCSV(ev)" title="Export CSV" class="organiser-icon-btn"><Download :size="14"/></button>
                    <button v-if="ev.status!=='cancelled' && ev.status!=='completed'" @click="confirmCancel=ev.id" title="Cancel event" class="organiser-icon-btn organiser-icon-btn--danger"><XCircle :size="14"/></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Analytics -->
      <div v-if="activeTab==='analytics'" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:32px">

        <!-- Category breakdown -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px">
          <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <BarChart2 :size="16" style="color:#520000"/> Registrations by Category
          </h3>
          <template v-for="cat in CATEGORIES" :key="cat">
            <div v-if="categoryTotal(cat) > 0" style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:13px;color:#555555">{{ cat }}</span>
                <span style="font-size:13px;font-weight:600;color:#1a1a1a">{{ categoryTotal(cat) }}</span>
              </div>
              <div style="height:6px;background:#E5E5E5;border-radius:4px">
                <div :style="{ height:'100%', width:`${(categoryTotal(cat)/categoryMax)*100}%`, background:'#520000', borderRadius:'4px', transition:'width 500ms ease' }"/>
              </div>
            </div>
          </template>
        </div>

        <!-- Feedback summary -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px">
          <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <Star :size="16" style="color:#520000"/> Attendee Feedback
          </h3>
          <template v-for="ev in myEvents" :key="ev.id">
            <div v-if="eventFeedback(ev.id).length" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #E5E5E5">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <p style="font-size:14px;font-weight:600;color:#1a1a1a">{{ ev.title }}</p>
                <div style="display:flex;align-items:center;gap:4px">
                  <Star :size="14" fill="#B45309" style="color:#B45309"/>
                  <span style="font-size:14px;font-weight:700;color:#1a1a1a">{{ eventAvg(ev.id).toFixed(1) }}</span>
                  <span style="font-size:12px;color:#555555">({{ eventFeedback(ev.id).length }})</span>
                </div>
              </div>
              <div v-for="fb in eventFeedback(ev.id).slice(0,2)" :key="fb.id" style="background:#F9F9F9;border-radius:6px;padding:10px 12px;margin-bottom:6px">
                <div style="display:flex;gap:2px;margin-bottom:4px">
                  <Star v-for="s in 5" :key="s" :size="12" :fill="s<=fb.rating?'#B45309':'none'" :style="{ color: s<=fb.rating?'#B45309':'#E5E5E5' }"/>
                </div>
                <p style="font-size:13px;color:#555555;line-height:1.5">"{{ fb.comment }}"</p>
                <p style="font-size:11px;color:#AAAAAA;margin-top:4px">— {{ fb.userName }}</p>
              </div>
            </div>
          </template>
          <p v-if="!hasAnyFeedback" style="font-size:14px;color:#555555;text-align:center;padding:24px 0">No feedback yet for your events.</p>
        </div>
      </div>
    </div>

    <!-- Cancel confirmation -->
    <Teleport to="body">
      <div v-if="confirmCancel" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:400px;width:100%;padding:28px">
          <h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:8px">Cancel this event?</h2>
          <p style="font-size:14px;color:#555555;margin-bottom:24px">This will notify all registered attendees and cannot be undone.</p>
          <div style="display:flex;gap:10px">
            <button @click="confirmCancel=null" style="flex:1;height:44px;border:1px solid #E5E5E5;border-radius:8px;background:none;font-size:14px;cursor:pointer;font-family:inherit">Keep event</button>
            <button @click="confirmCancelEvent" style="flex:1;height:44px;border:none;border-radius:8px;background:#B91C1C;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Cancel Event</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Create/Edit Event modal -->
    <Teleport to="body">
      <div v-if="formState" @click.self="closeForm" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;overflow-y:auto">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:560px;padding:28px;box-shadow:0 24px 48px rgba(0,0,0,0.18);margin:auto">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
            <h2 style="font-size:18px;font-weight:700;color:#1a1a1a">{{ formState.isEdit ? 'Edit Event' : 'Create New Event' }}</h2>
            <button @click="closeForm" style="background:none;border:none;cursor:pointer;color:#555555"><X :size="20"/></button>
          </div>

          <div v-if="formDone" style="text-align:center;padding:24px 0">
            <div style="width:56px;height:56px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
              <CheckCircle2 :size="28" style="color:#1A7A4A"/>
            </div>
            <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:6px">{{ formState.isEdit ? 'Event updated!' : 'Event submitted for approval!' }}</h3>
            <p style="font-size:13px;color:#555555">The faculty admin will review and approve it shortly.</p>
          </div>

          <form v-else @submit="handleFormSubmit" novalidate>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Event title *</label>
              <input v-model="formState.title" required placeholder="e.g. Tech Symposium 2026" class="organiser-input"/>
            </div>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Description *</label>
              <textarea v-model="formState.description" required placeholder="Describe the event, agenda, what attendees can expect…" rows="4" class="organiser-input" style="height:auto;padding:12px;resize:vertical"/>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
              <div>
                <label class="organiser-label">Category *</label>
                <select v-model="formState.category" class="organiser-input" style="cursor:pointer">
                  <option v-for="c in CATEGORIES" :key="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="organiser-label">Price (RM)</label>
                <input v-model="formState.price" type="number" min="0" step="0.01" class="organiser-input"/>
              </div>
            </div>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Venue *</label>
              <input v-model="formState.venue" required placeholder="e.g. Dewan Sultan Iskandar, UTM" class="organiser-input"/>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
              <div>
                <label class="organiser-label">Date *</label>
                <input v-model="formState.date" placeholder="Sat, 21 Jun 2026" class="organiser-input"/>
              </div>
              <div>
                <label class="organiser-label">Start time</label>
                <input v-model="formState.time" placeholder="9:00 AM" class="organiser-input"/>
              </div>
              <div>
                <label class="organiser-label">End time</label>
                <input v-model="formState.endsAt" placeholder="5:00 PM" class="organiser-input"/>
              </div>
            </div>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Seat capacity *</label>
              <input v-model="formState.capacity" type="number" min="1" required class="organiser-input"/>
            </div>
            <div style="margin-bottom:24px">
              <label class="organiser-label">Cover image URL (optional)</label>
              <input v-model="formState.imageUrl" placeholder="https://images.unsplash.com/…" class="organiser-input"/>
            </div>
            <div style="display:flex;gap:10px">
              <button type="button" @click="closeForm" style="flex:1;height:44px;border:1px solid #E5E5E5;border-radius:8px;background:none;font-size:14px;font-weight:500;color:#555555;cursor:pointer;font-family:inherit">Cancel</button>
              <button type="submit" :disabled="formSubmitting" style="flex:2;height:44px;background:#520000;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit">
                <span v-if="formSubmitting" class="organiser-spinner"/>
                {{ formSubmitting ? (formState.isEdit ? 'Saving…' : 'Submitting…') : (formState.isEdit ? 'Save Changes' : 'Submit for Approval') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <Footer/>
  </template>
</template>

<style scoped>
.organiser-row { transition: background 150ms; background: #fff; }
.organiser-row:hover { background: #fff5f5; }

.organiser-icon-btn {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid #E5E5E5;
  background: none; cursor: pointer; display: flex; align-items: center;
  justify-content: center; color: #555555;
}
.organiser-icon-btn--danger { border-color: #FEE2E2; color: #B91C1C; }

.organiser-label { display: block; font-size: 13px; font-weight: 500; color: #1a1a1a; margin-bottom: 6px; }
.organiser-input {
  width: 100%; height: 44px; border: 1px solid #E5E5E5; border-radius: 6px;
  padding: 0 12px; font-size: 14px; outline: none; box-sizing: border-box; font-family: inherit;
}

@keyframes spin { to { transform: rotate(360deg); } }
.organiser-spinner {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite;
  display: inline-block;
}
</style>
