<script setup>
import { ref, computed } from "vue";
import { CheckCircle2, XCircle, Eye, BarChart2, Users, Calendar, TrendingUp, Shield, Clock } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useEventsStore } from "@/stores/events";
import { useFeedbackStore } from "@/stores/feedback";
import Footer from "@/components/common/Footer.vue";

const auth = useAuthStore();
const eventsStore = useEventsStore();
const feedbackStore = useFeedbackStore();

const activeTab = ref("queue");
const previewId = ref(null);

const pending = computed(() => eventsStore.events.filter((e) => e.status === "pending"));
const approved = computed(() => eventsStore.events.filter((e) => e.status === "approved"));
const allPublic = computed(() => eventsStore.events.filter((e) => e.status === "approved" || e.status === "completed"));

const CATEGORIES = ["Academic", "Sports", "Cultural", "Religious", "Workshop", "Career"];
const CATEGORY_COLORS = ["#520000", "#7A1010", "#C17070", "#B45309", "#1A7A4A", "#3B82F6"];

const categoryData = computed(() =>
  CATEGORIES.map((cat) => ({
    name: cat,
    events: allPublic.value.filter((e) => e.category === cat).length,
    registrations: allPublic.value.filter((e) => e.category === cat).reduce((a, e) => a + (e.capacity - e.spotsLeft), 0),
  })).filter((d) => d.events > 0)
);
const categoryMax = computed(() => Math.max(...categoryData.value.map((d) => d.registrations), 1));

const societyData = computed(() => {
  const map = {};
  allPublic.value.forEach((e) => { map[e.societyName] = (map[e.societyName] ?? 0) + (e.capacity - e.spotsLeft); });
  const entries = Object.entries(map)
    .map(([name, value]) => ({ name: name.replace("UTM ", "").replace(" Society", "").replace(" Club", ""), value }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 5);
  const total = entries.reduce((a, e) => a + e.value, 0) || 1;
  return entries.map((e, i) => ({ ...e, pct: Math.round((e.value / total) * 100), color: CATEGORY_COLORS[i % CATEGORY_COLORS.length] }));
});

const avgRating = computed(() => {
  if (!feedbackStore.feedback.length) return null;
  return (feedbackStore.feedback.reduce((a, f) => a + f.rating, 0) / feedbackStore.feedback.length).toFixed(1);
});
const totalRegistrations = computed(() => allPublic.value.reduce((a, e) => a + (e.capacity - e.spotsLeft), 0));

const statusMap = {
  approved: { bg: "#D1FAE5", color: "#065F46" },
  pending: { bg: "#FEF3C7", color: "#92400E" },
  cancelled: { bg: "#FEE2E2", color: "#991B1B" },
  completed: { bg: "#F3F4F6", color: "#374151" },
};
function statusChip(status) { return statusMap[status] ?? { bg: "#F9F9F9", color: "#555555" }; }

const previewEvent = computed(() => previewId.value ? eventsStore.getEventById(previewId.value) : null);

function approve(id) { eventsStore.approveEvent(id); }
function reject(id) { eventsStore.rejectEvent(id); }

// SVG donut chart geometry for societyData
const donutSegments = computed(() => {
  let cumulative = 0;
  return societyData.value.map((s) => {
    const start = cumulative;
    cumulative += s.pct;
    return { ...s, start, end: cumulative };
  });
});
function arcPath(start, end, r = 70, cx = 90, cy = 90) {
  const a0 = (start / 100) * 2 * Math.PI - Math.PI / 2;
  const a1 = (end / 100) * 2 * Math.PI - Math.PI / 2;
  const x0 = cx + r * Math.cos(a0), y0 = cy + r * Math.sin(a0);
  const x1 = cx + r * Math.cos(a1), y1 = cy + r * Math.sin(a1);
  const largeArc = end - start > 50 ? 1 : 0;
  return `M ${cx} ${cy} L ${x0} ${y0} A ${r} ${r} 0 ${largeArc} 1 ${x1} ${y1} Z`;
}
</script>

<template>
  <div v-if="!auth.user" style="padding:64px;text-align:center;color:#555555">Please log in as a Faculty Admin.</div>

  <template v-else>
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#3A0000 0%,#520000 60%,#7A1010 100%);padding:36px 24px 0">
      <div style="max-width:1280px;margin:0 auto">
        <div style="margin-bottom:24px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <Shield :size="16" style="color:rgba(255,255,255,0.5)"/>
            <p style="font-size:12px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.08em">Faculty Administrator Panel</p>
          </div>
          <h1 style="font-size:24px;font-weight:700;color:#fff">Faculty of Computing, UTM</h1>
          <p style="font-size:14px;color:rgba(255,255,255,0.6);margin-top:4px">Welcome, {{ auth.user.name }}</p>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:24px">
          <div v-for="s in [
            { label:'Pending Review', value: pending.length, icon: Clock, accent:'#C17070' },
            { label:'Active Events', value: approved.length, icon: Calendar, accent:'#fff' },
            { label:'Total Registrations', value: totalRegistrations, icon: Users, accent:'#fff' },
            { label:'Avg Rating', value: avgRating ?? '—', icon: TrendingUp, accent:'#C17070' },
          ]" :key="s.label" style="background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:14px 16px;backdrop-filter:blur(4px)">
            <component :is="s.icon" :size="16" :style="{ color: s.accent, marginBottom: '8px', opacity: 0.85 }"/>
            <p style="font-size:26px;font-weight:700;color:#fff;line-height:1">{{ s.value }}</p>
            <p style="font-size:12px;color:rgba(255,255,255,0.55);margin-top:4px">{{ s.label }}</p>
          </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex">
          <button v-for="t in [{key:'queue',label:'Approval Queue',count:pending.length},{key:'analytics',label:'Analytics Dashboard',count:null}]" :key="t.key"
            @click="activeTab=t.key"
            :style="{ background:'none', border:'none', cursor:'pointer', fontSize:'14px', fontWeight:activeTab===t.key?600:400, color:activeTab===t.key?'#fff':'rgba(255,255,255,0.5)', padding:'10px 0', paddingRight:'24px', borderBottom:`2px solid ${activeTab===t.key?'#fff':'transparent'}`, marginBottom:'-1px', display:'flex', alignItems:'center', gap:'6px', fontFamily:'inherit' }">
            {{ t.label }}
            <span v-if="t.count !== null && t.count > 0" style="background:#B91C1C;color:#fff;border-radius:10px;font-size:11px;padding:1px 7px;font-weight:700">{{ t.count }}</span>
          </button>
        </div>
      </div>
    </div>

    <div style="max-width:1280px;margin:0 auto;padding:32px 24px 64px">

      <!-- Approval Queue -->
      <template v-if="activeTab==='queue'">
        <div v-if="pending.length===0" style="text-align:center;padding:64px 0">
          <CheckCircle2 :size="56" style="color:#1A7A4A;margin-bottom:16px;stroke-width:1.5"/>
          <h3 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:8px">All caught up!</h3>
          <p style="font-size:14px;color:#555555">No events pending review.</p>
        </div>
        <template v-else>
          <p style="font-size:14px;color:#555555;margin-bottom:20px">{{ pending.length }} event{{ pending.length!==1?'s':'' }} awaiting your review</p>
          <div style="display:flex;flex-direction:column;gap:12px">
            <div v-for="ev in pending" :key="ev.id" style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
              <img v-if="ev.imageUrl" :src="ev.imageUrl" :alt="ev.title" style="width:64px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0"/>
              <div style="flex:1;min-width:200px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                  <h3 style="font-size:15px;font-weight:700;color:#1a1a1a">{{ ev.title }}</h3>
                  <span :style="{ background:statusChip(ev.status).bg, color:statusChip(ev.status).color, fontSize:'12px', fontWeight:600, padding:'3px 10px', borderRadius:'20px', textTransform:'capitalize' }">{{ ev.status }}</span>
                </div>
                <p style="font-size:13px;color:#520000;font-weight:500;margin-bottom:2px">{{ ev.societyName }}</p>
                <div style="display:flex;gap:16px;flex-wrap:wrap">
                  <span style="font-size:12px;color:#555555">📅 {{ ev.date }} · {{ ev.time }}</span>
                  <span style="font-size:12px;color:#555555">📍 {{ ev.venue }}</span>
                  <span style="font-size:12px;color:#555555">👥 Capacity: {{ ev.capacity }}</span>
                  <span :style="{ fontSize:'12px', color: ev.price===0?'#1A7A4A':'#555555', fontWeight: ev.price===0?600:400 }">{{ ev.price===0 ? 'FREE' : `RM ${ev.price}` }}</span>
                </div>
              </div>
              <div style="display:flex;gap:8px;flex-shrink:0">
                <button @click="previewId=ev.id" class="admin-action-btn admin-action-btn--ghost"><Eye :size="14"/> Preview</button>
                <button @click="reject(ev.id)" class="admin-action-btn admin-action-btn--reject"><XCircle :size="14"/> Reject</button>
                <button @click="approve(ev.id)" class="admin-action-btn admin-action-btn--approve"><CheckCircle2 :size="14"/> Approve</button>
              </div>
            </div>
          </div>
        </template>

        <!-- Recent approvals -->
        <div style="margin-top:40px">
          <h2 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:16px">Recent Approvals</h2>
          <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;overflow:hidden">
            <table style="width:100%;border-collapse:collapse">
              <thead>
                <tr style="background:#F9F9F9;border-bottom:1px solid #E5E5E5">
                  <th v-for="h in ['Event','Society','Date','Category','Status']" :key="h" style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#555555;text-transform:uppercase;letter-spacing:0.05em">{{ h }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(ev,i,arr) in eventsStore.events.filter(e=>e.status==='approved'||e.status==='completed').slice(0,6)" :key="ev.id" style="border-bottom:1px solid #F0F0F0">
                  <td style="padding:12px 16px;font-size:14px;font-weight:500;color:#1a1a1a">{{ ev.title }}</td>
                  <td style="padding:12px 16px;font-size:13px;color:#555555">{{ ev.societyName }}</td>
                  <td style="padding:12px 16px;font-size:13px;color:#555555;white-space:nowrap">{{ ev.date }}</td>
                  <td style="padding:12px 16px"><span style="background:#F9F9F9;border:1px solid #E5E5E5;border-radius:20px;font-size:12px;color:#555555;padding:2px 8px">{{ ev.category }}</span></td>
                  <td style="padding:12px 16px"><span :style="{ background:statusChip(ev.status).bg, color:statusChip(ev.status).color, fontSize:'12px', fontWeight:600, padding:'3px 10px', borderRadius:'20px', textTransform:'capitalize' }">{{ ev.status }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- Analytics Dashboard -->
      <template v-if="activeTab==='analytics'">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;margin-bottom:32px">

          <!-- Bar chart: registrations by category (built with plain divs — see note below) -->
          <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px">
            <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:20px;display:flex;align-items:center;gap:8px">
              <BarChart2 :size="16" style="color:#520000"/> Registrations by Category
            </h3>
            <div style="display:flex;align-items:flex-end;gap:16px;height:220px;padding-top:20px">
              <div v-for="d in categoryData" :key="d.name" style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end">
                <span style="font-size:12px;font-weight:600;color:#1a1a1a;margin-bottom:6px">{{ d.registrations }}</span>
                <div :style="{ width:'100%', maxWidth:'36px', height:`${(d.registrations/categoryMax)*160}px`, background:'#520000', borderRadius:'4px 4px 0 0', transition:'height 400ms ease' }"/>
                <span style="font-size:11px;color:#555555;margin-top:8px;text-align:center">{{ d.name }}</span>
              </div>
              <p v-if="categoryData.length===0" style="font-size:13px;color:#AAAAAA;margin:auto">No data yet</p>
            </div>
          </div>

          <!-- Donut chart: top societies (plain SVG — see note below) -->
          <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;padding:24px">
            <h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:20px;display:flex;align-items:center;gap:8px">
              <Users :size="16" style="color:#520000"/> Top Societies by Attendance
            </h3>
            <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
              <svg width="180" height="180" viewBox="0 0 180 180">
                <path v-for="seg in donutSegments" :key="seg.name" :d="arcPath(seg.start, seg.end)" :fill="seg.color"/>
                <circle cx="90" cy="90" r="40" fill="#fff"/>
              </svg>
              <div style="display:flex;flex-direction:column;gap:8px">
                <div v-for="seg in donutSegments" :key="seg.name" style="display:flex;align-items:center;gap:8px;font-size:12px">
                  <span :style="{ width:'10px', height:'10px', borderRadius:'2px', background:seg.color, display:'inline-block' }"/>
                  <span style="color:#1a1a1a">{{ seg.name }}</span>
                  <span style="color:#555555">{{ seg.pct }}%</span>
                </div>
                <p v-if="donutSegments.length===0" style="font-size:13px;color:#AAAAAA">No data yet</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Participation table -->
        <div style="background:#fff;border:1px solid #E5E5E5;border-radius:8px;overflow:hidden">
          <div style="padding:16px 20px;border-bottom:1px solid #E5E5E5">
            <h3 style="font-size:16px;font-weight:700;color:#1a1a1a">All Events — Participation Overview</h3>
          </div>
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:600px">
              <thead>
                <tr style="background:#F9F9F9;border-bottom:1px solid #E5E5E5">
                  <th v-for="h in ['Event','Society','Category','Registrations','Capacity %','Avg Rating','Status']" :key="h" style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#555555;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap">{{ h }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ev in eventsStore.events" :key="ev.id" style="border-bottom:1px solid #F0F0F0">
                  <td style="padding:12px 16px;font-size:14px;font-weight:500;color:#1a1a1a;max-width:220px">
                    <p style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ ev.title }}</p>
                  </td>
                  <td style="padding:12px 16px;font-size:12px;color:#555555;white-space:nowrap">{{ ev.societyName.replace('UTM ', '') }}</td>
                  <td style="padding:12px 16px"><span style="background:#F9F9F9;font-size:11px;color:#555555;padding:2px 8px;border-radius:20px;border:1px solid #E5E5E5">{{ ev.category }}</span></td>
                  <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1a1a1a">{{ ev.capacity - ev.spotsLeft }}</td>
                  <td style="padding:12px 16px">
                    <div style="display:flex;align-items:center;gap:8px">
                      <div style="height:6px;width:60px;background:#E5E5E5;border-radius:3px">
                        <div :style="{ height:'100%', width:`${Math.round(((ev.capacity-ev.spotsLeft)/ev.capacity)*100)}%`, background: Math.round(((ev.capacity-ev.spotsLeft)/ev.capacity)*100)>90 ? '#B91C1C' : '#520000', borderRadius:'3px' }"/>
                      </div>
                      <span style="font-size:12px;color:#555555">{{ Math.round(((ev.capacity-ev.spotsLeft)/ev.capacity)*100) }}%</span>
                    </div>
                  </td>
                  <td :style="{ padding:'12px 16px', fontSize:'13px', color: feedbackStore.feedbackForEvent(ev.id).length ? '#B45309' : '#AAAAAA', fontWeight: feedbackStore.feedbackForEvent(ev.id).length ? 600 : 400 }">
                    {{ feedbackStore.feedbackForEvent(ev.id).length ? `★ ${feedbackStore.averageRating(ev.id).toFixed(1)}` : '—' }}
                  </td>
                  <td style="padding:12px 16px"><span :style="{ background:statusChip(ev.status).bg, color:statusChip(ev.status).color, fontSize:'12px', fontWeight:600, padding:'3px 10px', borderRadius:'20px', textTransform:'capitalize' }">{{ ev.status }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Event preview modal -->
    <Teleport to="body">
      <div v-if="previewEvent" @click.self="previewId=null" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:520px;padding:28px;box-shadow:0 24px 48px rgba(0,0,0,0.18);max-height:90vh;overflow-y:auto">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2 style="font-size:18px;font-weight:700;color:#1a1a1a">Event Review</h2>
            <button @click="previewId=null" style="background:none;border:none;cursor:pointer;color:#555555;font-size:20px">×</button>
          </div>
          <img v-if="previewEvent.imageUrl" :src="previewEvent.imageUrl" :alt="previewEvent.title" style="width:100%;height:180px;object-fit:cover;border-radius:8px;margin-bottom:16px"/>
          <span style="background:#FFF5F5;border:1px solid #C17070;border-radius:20px;font-size:11px;color:#520000;padding:3px 10px;display:inline-block;margin-bottom:12px">{{ previewEvent.category }}</span>
          <h3 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:4px">{{ previewEvent.title }}</h3>
          <p style="font-size:13px;color:#520000;font-weight:500;margin-bottom:12px">{{ previewEvent.societyName }}</p>
          <p style="font-size:14px;color:#555555;line-height:1.7;margin-bottom:16px">{{ previewEvent.description }}</p>
          <div v-for="row in [
            ['Date', previewEvent.date], ['Time', `${previewEvent.time} – ${previewEvent.endsAt}`],
            ['Venue', previewEvent.venue], ['Capacity', String(previewEvent.capacity)],
            ['Price', previewEvent.price===0 ? 'Free' : `RM ${previewEvent.price}`],
          ]" :key="row[0]" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F0F0">
            <span style="font-size:13px;color:#555555">{{ row[0] }}</span>
            <span style="font-size:13px;font-weight:500;color:#1a1a1a">{{ row[1] }}</span>
          </div>
          <div v-if="previewEvent.status==='pending'" style="display:flex;gap:10px;margin-top:24px">
            <button @click="reject(previewEvent.id); previewId=null" style="flex:1;height:44px;border:1px solid #FEE2E2;border-radius:8px;background:#FEE2E2;color:#991B1B;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Reject</button>
            <button @click="approve(previewEvent.id); previewId=null" style="flex:2;height:44px;border:none;border-radius:8px;background:#1A7A4A;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Approve & Publish</button>
          </div>
        </div>
      </div>
    </Teleport>

    <Footer/>
  </template>
</template>

<!--
  Chart note: the original React prototype used `recharts` (BarChart + PieChart).
  recharts is React-only, so the bar chart and pie/donut chart here are
  rebuilt with plain CSS bars + an SVG arc-path donut instead of pulling in
  a new charting dependency. Visually equivalent, zero extra deps. If the
  team later wants animated/interactive charts, Chart.js (already an
  approved library) is the natural swap-in.
-->

<style scoped>
.admin-action-btn {
  display: flex; align-items: center; gap: 5px; height: 36px; padding: 0 14px;
  border-radius: 6px; font-size: 13px; cursor: pointer; font-family: inherit;
}
.admin-action-btn--ghost { border: 1px solid #E5E5E5; background: none; color: #555555; }
.admin-action-btn--reject { border: 1px solid #FEE2E2; background: #FEE2E2; color: #991B1B; font-weight: 500; }
.admin-action-btn--approve { border: none; background: #1A7A4A; color: #fff; font-weight: 600; }
</style>
