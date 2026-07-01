<script setup>
import { ref, computed, onMounted } from "vue";
import { Plus, Edit2, Download, BarChart2, Star, Users, Calendar, Clock, XCircle, CheckCircle2, X, CreditCard } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useEventsStore } from "@/stores/events";
import { useFeedbackStore } from "@/stores/feedback";
import { uploadEventImage, fetchEventParticipants } from "@/api/events";
import { getBankDetailsRequest, updateBankDetailsRequest, getOrganiserTransactionsRequest } from "@/api/auth";
import Footer from "@/components/common/Footer.vue";

const auth = useAuthStore();
const eventsStore = useEventsStore();
const feedbackStore = useFeedbackStore();

const bankDetails = ref({
  bankName: "",
  bankAccountNo: "",
  bankAccountHolder: "",
  stripeConnectId: ""
});
const bankMessage = ref("");
const bankError = ref("");
const bankSubmitting = ref(false);

const transactions = ref([]);
const transactionsLoading = ref(false);

async function loadBankDetails() {
  try {
    const res = await getBankDetailsRequest();
    bankDetails.value = {
      bankName: res.bankName || "",
      bankAccountNo: res.bankAccountNo || "",
      bankAccountHolder: res.bankAccountHolder || "",
      stripeConnectId: res.stripeConnectId || ""
    };
  } catch (err) {
    console.error("Failed to load bank details", err);
  }
}

async function saveBankDetails() {
  bankSubmitting.value = true;
  bankMessage.value = "";
  bankError.value = "";
  try {
    await updateBankDetailsRequest(bankDetails.value);
    bankMessage.value = "Bank details updated successfully!";
  } catch (err) {
    bankError.value = err.response?.data?.error || err.message || "Failed to update bank details";
  } finally {
    bankSubmitting.value = false;
  }
}

async function loadTransactions() {
  transactionsLoading.value = true;
  try {
    const res = await getOrganiserTransactionsRequest();
    transactions.value = res.transactions || [];
  } catch (err) {
    console.error("Failed to load transactions", err);
  } finally {
    transactionsLoading.value = false;
  }
}

onMounted(() => {
  eventsStore.fetchMine();
  loadBankDetails();
  loadTransactions();
});

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

async function exportCSV(ev) {
  try {
    const list = await fetchEventParticipants(ev.id);
    const headers = ["Name", "Matric Number", "Status", "Checked In At"];
    const rows = [
      headers,
      ...list.map(p => [p.name, p.matricNo, p.status, p.checkedInAt])
    ];
    const csv = rows.map((r) => r.map(val => `"${String(val).replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = `attendance_${ev.title.replace(/\s+/g, "_")}.csv`; a.click();
  } catch (err) {
    alert(err.response?.data?.error || err.message || "Failed to export participants.");
  }
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
const imageUploading = ref(false);

async function handleImageUpload(e) {
  const file = e.target.files[0];
  if (!file) return;

  imageUploading.value = true;
  try {
    const res = await uploadEventImage(file);
    if (res && res.imageUrl) {
      formState.value.imageUrl = res.imageUrl;
    }
  } catch (err) {
    alert(err.response?.data?.error || err.message || "Failed to upload image.");
  } finally {
    imageUploading.value = false;
  }
}

function toInputDateFormat(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) {
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
    return "";
  }
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function toInputTimeFormat(timeStr) {
  if (!timeStr) return "";
  if (/^\d{2}:\d{2}$/.test(timeStr)) return timeStr;
  const match = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (match) {
    let hours = parseInt(match[1]);
    const minutes = match[2];
    const ampm = match[3].toUpperCase();
    if (ampm === "PM" && hours < 12) hours += 12;
    if (ampm === "AM" && hours === 12) hours = 0;
    return `${String(hours).padStart(2, "0")}:${minutes}`;
  }
  return timeStr;
}

function openCreate() {
  formState.value = {
    isEdit: false, title: "", description: "", category: "Academic", venue: "",
    date: "", time: "09:00", endsAt: "17:00", capacity: "50", price: "0", imageUrl: "",
  };
  formDone.value = false;
}
function openEdit(ev) {
  formState.value = {
    isEdit: true, id: ev.id, title: ev.title, description: ev.description, category: ev.category,
    venue: ev.venue, date: toInputDateFormat(ev.date), time: toInputTimeFormat(ev.time), endsAt: toInputTimeFormat(ev.endsAt),
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
  
  const startsAt = `${f.date} ${f.time}:00`;
  const endsAt = `${f.date} ${f.endsAt}:00`;

  const payload = {
    title: f.title, description: f.description, category: f.category, venue: f.venue,
    date: f.date, time: f.time, endsAt: f.endsAt,
    startsAt, endsAt,
    capacity: Number(f.capacity), price: Number(f.price), imageUrl: f.imageUrl,
    societyId: "s1", societyName: auth.user?.society ?? "Unknown Society",
    tags: [], organiserName: auth.user?.name ?? "",
  };
  try {
    if (f.isEdit) await eventsStore.updateEvent(f.id, payload);
    else await eventsStore.addEvent(payload);
    formDone.value = true;
    setTimeout(closeForm, 700);
  } catch (err) {
    let errorMsg = err.response?.data?.error || err.message || "Failed to save event.";
    if (err.response?.data?.fields) {
      const fieldErrors = Object.values(err.response.data.fields).join("\n");
      errorMsg += "\n\n" + fieldErrors;
    }
    alert(errorMsg);
  } finally {
    formSubmitting.value = false;
  }
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
          <button v-for="t in [
            {key:'events',label:'My Events'},
            {key:'analytics',label:'Analytics & Feedback'},
            {key:'transactions',label:'Transactions Log'},
            {key:'payout',label:'Payout Settings'}
          ]" :key="t.key"
            @click="activeTab=t.key"
            :style="{ background:'none', border:'none', cursor:'pointer', fontSize:'14px', fontWeight:activeTab===t.key?600:400, color:activeTab===t.key?'#fff':'rgba(255,255,255,0.55)', padding:'10px 0', paddingRight:'24px', borderBottom:`2px solid ${activeTab===t.key?'#fff':'transparent'}`, marginBottom:'-1px', fontFamily:'inherit' }">
            {{ t.label }}
          </button>
        </div>
      </div>
    </div>

    <div style="max-width:1280px;margin:0 auto;padding:32px 24px 64px">

      <!-- Events table -->
      <div v-if="activeTab==='events'" style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;overflow:hidden">
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:700px">
            <thead>
              <tr style="background:var(--bg-pill);border-bottom:1px solid var(--border-color)">
                <th v-for="h in ['Event','Date','Category','Capacity','Status','Actions']" :key="h" style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="myEvents.length===0">
                <td colspan="6" style="padding:48px 16px;text-align:center;color:var(--text-secondary);font-size:14px">No events yet. Create your first event!</td>
              </tr>
              <tr v-for="(ev,i) in myEvents" :key="ev.id" class="organiser-row" :style="{ borderBottom: i<myEvents.length-1 ? '1px solid var(--border-color)' : 'none' }">
                <td style="padding:14px 16px">
                  <p style="font-size:14px;font-weight:600;color:var(--text-primary)">{{ ev.title }}</p>
                  <p style="font-size:12px;color:var(--text-secondary);margin-top:2px">{{ ev.societyName }}</p>
                </td>
                <td style="padding:14px 16px;font-size:13px;color:var(--text-secondary);white-space:nowrap">{{ ev.date }}</td>
                <td style="padding:14px 16px">
                  <span style="background:var(--bg-pill);border:1px solid var(--border-card);border-radius:20px;font-size:12px;color:var(--text-secondary);padding:2px 8px">{{ ev.category }}</span>
                </td>
                <td style="padding:14px 16px">
                  <div style="font-size:13px;color:var(--text-primary);font-weight:500">{{ ev.capacity - ev.spotsLeft }} / {{ ev.capacity }}</div>
                  <div style="height:4px;background:var(--border-color);border-radius:2px;margin-top:4px;width:80px">
                    <div :style="{ height:'100%', width:`${Math.round(((ev.capacity-ev.spotsLeft)/ev.capacity)*100)}%`, background:'var(--maroon)', borderRadius:'2px' }"/>
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
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;padding:24px">
          <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <BarChart2 :size="16" style="color:var(--maroon)"/> Registrations by Category
          </h3>
          <template v-for="cat in CATEGORIES" :key="cat">
            <div v-if="categoryTotal(cat) > 0" style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:13px;color:var(--text-secondary)">{{ cat }}</span>
                <span style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ categoryTotal(cat) }}</span>
              </div>
              <div style="height:6px;background:var(--border-color);border-radius:4px">
                <div :style="{ height:'100%', width:`${(categoryTotal(cat)/categoryMax)*100}%`, background:'var(--maroon)', borderRadius:'4px', transition:'width 500ms ease' }"/>
              </div>
            </div>
          </template>
        </div>

        <!-- Feedback summary -->
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;padding:24px">
          <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <Star :size="16" style="color:var(--maroon)"/> Attendee Feedback
          </h3>
          <template v-for="ev in myEvents" :key="ev.id">
            <div v-if="eventFeedback(ev.id).length" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border-color)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <p style="font-size:14px;font-weight:600;color:var(--text-primary)">{{ ev.title }}</p>
                <div style="display:flex;align-items:center;gap:4px">
                  <Star :size="14" fill="#B45309" style="color:#B45309"/>
                  <span style="font-size:14px;font-weight:700;color:var(--text-primary)">{{ eventAvg(ev.id).toFixed(1) }}</span>
                  <span style="font-size:12px;color:var(--text-secondary)">({{ eventFeedback(ev.id).length }})</span>
                </div>
              </div>
              <div v-for="fb in eventFeedback(ev.id).slice(0,2)" :key="fb.id" style="background:var(--bg-pill);border-radius:6px;padding:10px 12px;margin-bottom:6px">
                <div style="display:flex;gap:2px;margin-bottom:4px">
                  <Star v-for="s in 5" :key="s" :size="12" :fill="s<=fb.rating?'#B45309':'none'" :style="{ color: s<=fb.rating?'#B45309':'var(--border-color)' }"/>
                </div>
                <p style="font-size:13px;color:var(--text-secondary);line-height:1.5">"{{ fb.comment }}"</p>
                <p style="font-size:11px;color:#AAAAAA;margin-top:4px">— {{ fb.userName }}</p>
              </div>
            </div>
          </template>
          <p v-if="!hasAnyFeedback" style="font-size:14px;color:var(--text-secondary);text-align:center;padding:24px 0">No feedback yet for your events.</p>
        </div>
      </div>

      <!-- Transactions Log -->
      <div v-if="activeTab==='transactions'" style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
          <h3 style="font-size:16px;font-weight:700;color:var(--text-primary)">Tickets Revenue & Payments</h3>
          <span style="font-size:13px;font-weight:600;color:var(--maroon);background:var(--maroon-light);padding:4px 10px;border-radius:20px">
            Total Revenue: RM {{ transactions.reduce((sum, t) => sum + Number(t.amount), 0).toFixed(2) }}
          </span>
        </div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:700px">
            <thead>
              <tr style="background:var(--bg-pill);border-bottom:1px solid var(--border-color)">
                <th v-for="h in ['Event','Attendee','Amount','Date','Status']" :key="h" style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="transactionsLoading">
                <td colspan="5" style="padding:48px 16px;text-align:center;color:var(--text-secondary)">
                  <span class="organiser-spinner" style="border-top-color:var(--maroon);margin-right:8px"></span> Loading transactions...
                </td>
              </tr>
              <tr v-else-if="transactions.length===0">
                <td colspan="5" style="padding:48px 16px;text-align:center;color:var(--text-secondary);font-size:14px">No payments recorded yet.</td>
              </tr>
              <tr v-for="(tx,i) in transactions" :key="tx.transaction_id" class="organiser-row" :style="{ borderBottom: i<transactions.length-1 ? '1px solid var(--border-color)' : 'none' }">
                <td style="padding:14px 16px">
                  <p style="font-size:14px;font-weight:600;color:var(--text-primary)">{{ tx.event_title }}</p>
                  <p style="font-size:11px;color:var(--text-secondary)">ID: #{{ tx.event_id }}</p>
                </td>
                <td style="padding:14px 16px">
                  <p style="font-size:14px;font-weight:500;color:var(--text-primary)">{{ tx.attendee_name }}</p>
                  <p style="font-size:12px;color:var(--text-secondary)">{{ tx.attendee_email }}</p>
                </td>
                <td style="padding:14px 16px;font-weight:600;color:var(--text-primary)">RM {{ Number(tx.amount).toFixed(2) }}</td>
                <td style="padding:14px 16px;font-size:13px;color:var(--text-secondary)">{{ tx.payment_date }}</td>
                <td style="padding:14px 16px">
                  <span style="background:#D1FAE5;color:#065F46;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:capitalize">{{ tx.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Payout Settings -->
      <div v-if="activeTab==='payout'" style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;padding:24px;max-width:600px;margin:0 auto">
        <h3 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:8px;display:flex;align-items:center;gap:8px">
          <CreditCard :size="20" style="color:var(--maroon)"/> Bank Account Setup
        </h3>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">Configure your society's bank account to receive registration fee payouts automatically.</p>
        
        <div v-if="bankMessage" style="background:#D1FAE5;border:1px solid #065F46;border-radius:8px;padding:12px;margin-bottom:20px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:8px">
          <CheckCircle2 :size="16" />
          <span>{{ bankMessage }}</span>
        </div>
        <div v-if="bankError" style="background:#FFF5F5;border:1px solid #B91C1C;border-radius:8px;padding:12px;margin-bottom:20px;color:#B91C1C;font-size:13px;display:flex;align-items:center;gap:8px">
          <XCircle :size="16" />
          <span>{{ bankError }}</span>
        </div>

        <form @submit.prevent="saveBankDetails">
          <div style="margin-bottom:16px">
            <label class="organiser-label">Bank Name *</label>
            <select v-model="bankDetails.bankName" required class="organiser-input" style="cursor:pointer">
              <option value="">Select Bank</option>
              <option value="Maybank">Maybank</option>
              <option value="CIMB Bank">CIMB Bank</option>
              <option value="Public Bank">Public Bank</option>
              <option value="RHB Bank">RHB Bank</option>
              <option value="Hong Leong Bank">Hong Leong Bank</option>
              <option value="AmBank">AmBank</option>
              <option value="Bank Islam">Bank Islam</option>
              <option value="Affin Bank">Affin Bank</option>
            </select>
          </div>
          <div style="margin-bottom:16px">
            <label class="organiser-label">Account Number *</label>
            <input v-model="bankDetails.bankAccountNo" type="text" required placeholder="e.g. 164012345678" class="organiser-input"/>
          </div>
          <div style="margin-bottom:20px">
            <label class="organiser-label">Account Holder Name *</label>
            <input v-model="bankDetails.bankAccountHolder" type="text" required placeholder="e.g. PERSATUAN MAHASISWA FAKULTI KOMPUTER" class="organiser-input"/>
          </div>
          <div style="margin-bottom:24px">
            <label class="organiser-label">Stripe Connect Account ID (Optional)</label>
            <input v-model="bankDetails.stripeConnectId" type="text" placeholder="e.g. acct_1234567890" class="organiser-input"/>
            <p style="font-size:11px;color:var(--text-secondary);margin-top:4px">For real-time ticket splits. Leave empty if using standard direct local bank transfer.</p>
          </div>

          <button type="submit" :disabled="bankSubmitting" style="width:100%;height:44px;background:var(--maroon);color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit">
            <span v-if="bankSubmitting" class="organiser-spinner" style="margin-right:8px"></span>
            {{ bankSubmitting ? 'Saving...' : 'Save Payout Details' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Cancel confirmation -->
    <Teleport to="body">
      <div v-if="confirmCancel" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:12px;max-width:400px;width:100%;padding:28px">
          <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:8px">Cancel this event?</h2>
          <p style="font-size:14px;color:var(--text-secondary);margin-bottom:24px">This will notify all registered attendees and cannot be undone.</p>
          <div style="display:flex;gap:10px">
            <button @click="confirmCancel=null" style="flex:1;height:44px;border:1px solid var(--border-color);border-radius:8px;background:none;font-size:14px;color:var(--text-secondary);cursor:pointer;font-family:inherit">Keep event</button>
            <button @click="confirmCancelEvent" style="flex:1;height:44px;border:none;border-radius:8px;background:#B91C1C;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Cancel Event</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Create/Edit Event modal -->
    <Teleport to="body">
      <div v-if="formState" @click.self="closeForm" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;overflow-y:auto">
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:12px;width:100%;max-width:560px;padding:28px;box-shadow:0 24px 48px rgba(0,0,0,0.18);margin:auto">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
            <h2 style="font-size:18px;font-weight:700;color:var(--text-primary)">{{ formState.isEdit ? 'Edit Event' : 'Create New Event' }}</h2>
            <button @click="closeForm" style="background:none;border:none;cursor:pointer;color:var(--text-secondary)"><X :size="20"/></button>
          </div>

          <div v-if="formDone" style="text-align:center;padding:24px 0">
            <div style="width:56px;height:56px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
              <CheckCircle2 :size="28" style="color:#1A7A4A"/>
            </div>
            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:6px">{{ formState.isEdit ? 'Event updated!' : 'Event submitted for approval!' }}</h3>
            <p style="font-size:13px;color:var(--text-secondary)">The faculty admin will review and approve it shortly.</p>
          </div>

          <form v-else @submit="handleFormSubmit" novalidate>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Event title *</label>
              <input v-model="formState.title" required placeholder="e.g. Tech Symposium 2026" class="organiser-input"/>
            </div>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Description *</label>
              <textarea v-model="formState.description" required minlength="10" placeholder="Describe the event, agenda, what attendees can expect… (min 10 characters)" rows="4" class="organiser-input" style="height:auto;padding:12px;resize:vertical"/>
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
                <input v-model="formState.date" type="date" required class="organiser-input" style="cursor:pointer"/>
              </div>
              <div>
                <label class="organiser-label">Start time *</label>
                <input v-model="formState.time" type="time" required class="organiser-input" style="cursor:pointer"/>
              </div>
              <div>
                <label class="organiser-label">End time *</label>
                <input v-model="formState.endsAt" type="time" required class="organiser-input" style="cursor:pointer"/>
              </div>
            </div>
            <div style="margin-bottom:16px">
              <label class="organiser-label">Seat capacity *</label>
              <input v-model="formState.capacity" type="number" min="1" required class="organiser-input"/>
            </div>
            <div style="margin-bottom:24px">
              <label class="organiser-label">Cover image (optional)</label>
              <div style="display:flex; gap:12px; align-items:center">
                <!-- Custom Upload Button -->
                <button
                  type="button"
                  @click="$refs.fileInput.click()"
                  style="display:flex; align-items:center; gap:8px; height:44px; padding:0 16px; border:1px dashed var(--maroon); border-radius:6px; background:var(--maroon-light); color:var(--maroon); font-size:14px; font-weight:600; cursor:pointer; font-family:inherit; transition:all 150ms; flex:1; justify-content:center"
                  onmouseover="this.style.background='var(--bg-hover)'"
                  onmouseout="this.style.background='var(--maroon-light)'"
                >
                  <Plus :size="16"/> Upload Image File
                </button>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*"
                  @change="handleImageUpload"
                  style="display:none"
                />
                <div v-if="imageUploading" class="organiser-spinner" style="border-top-color:var(--maroon); width:20px; height:20px"/>
              </div>
              <div v-if="formState.imageUrl" style="margin-top:10px; display:flex; align-items:center; gap:8px">
                <img :src="formState.imageUrl" style="width:48px; height:48px; object-fit:cover; border-radius:4px; border:1px solid var(--border-card)"/>
                <span style="font-size:12px; color:#1A7A4A; font-weight:500">✓ Image uploaded</span>
              </div>
            </div>
            <div style="display:flex;gap:10px">
              <button type="button" @click="closeForm" style="flex:1;height:44px;border:1px solid var(--border-color);border-radius:8px;background:none;font-size:14px;font-weight:500;color:var(--text-secondary);cursor:pointer;font-family:inherit">Cancel</button>
              <button type="submit" :disabled="formSubmitting" style="flex:2;height:44px;background:var(--maroon);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit">
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
.organiser-row { transition: background 150ms; background: var(--bg-card); }
.organiser-row:hover { background: var(--maroon-light); }

.organiser-icon-btn {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
  background: none; cursor: pointer; display: flex; align-items: center;
  justify-content: center; color: var(--text-secondary);
}
.organiser-icon-btn--danger { border-color: #FEE2E2; color: #B91C1C; }

.organiser-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px; }
.organiser-input {
  width: 100%; height: 44px; border: 1px solid var(--border-color); border-radius: 6px;
  background: var(--bg-card); color: var(--text-primary);
  padding: 0 12px; font-size: 14px; outline: none; box-sizing: border-box; font-family: inherit;
}

@keyframes spin { to { transform: rotate(360deg); } }
.organiser-spinner {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite;
  display: inline-block;
}
</style>
