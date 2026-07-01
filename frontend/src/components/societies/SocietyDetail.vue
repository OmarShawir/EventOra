<script setup>
import { ref, computed } from "vue";
import { Users, Calendar, Clock, ArrowLeft } from "lucide-vue-next";
import { useEventsStore } from "@/stores/events";
import { useFeedbackStore } from "@/stores/feedback";
import { useSocietiesStore } from "@/stores/societies";
import EventRow from "./EventRow.vue";
import PastEventCard from "./PastEventCard.vue";

const props = defineProps({
  name: { type: String, required: true },
});
const emit = defineEmits(["back"]);

const eventsStore = useEventsStore();
const feedbackStore = useFeedbackStore();
const societiesStore = useSocietiesStore();

const activeTab = ref("upcoming");

const meta = computed(() =>
  societiesStore.getSociety(props.name) ?? { desc: "", faculty: "UTM", members: 0, founded: "—", coverUrl: "", logoColor: "#520000" }
);

const societyEvents = computed(() => eventsStore.events.filter((e) => e.societyName === props.name));
const upcomingEvents = computed(() => societyEvents.value.filter((e) => e.status === "approved" || e.status === "pending"));
const pastEvents = computed(() =>
  societyEvents.value.filter((e) => e.status === "completed").sort((a, b) => b.date.localeCompare(a.date))
);

function getFeedback(eventId) {
  return feedbackStore.feedback
    .filter((f) => f.eventId === eventId)
    .map((f) => ({ rating: f.rating, comment: f.comment, userName: f.userName, submittedAt: f.submittedAt }));
}

const overallAvgRating = computed(() => {
  const evFeedback = feedbackStore.feedback.filter((f) => societyEvents.value.some((e) => e.id === f.eventId));
  if (!evFeedback.length) return null;
  return (evFeedback.reduce((a, f) => a + f.rating, 0) / evFeedback.length).toFixed(1);
});

const initials = computed(() => {
  const words = props.name.split(" ").filter((w) => w.length > 2 && !["UTM", "and", "the", "of"].includes(w));
  return words.slice(0, 2).map((w) => w[0]).join("").toUpperCase();
});

const statsRow = computed(() => {
  const items = [
    { icon: Users, label: `${meta.value.members.toLocaleString()} members` },
    { icon: Calendar, label: `${societyEvents.value.length} events run` },
    { icon: Clock, label: `Est. ${meta.value.founded}` },
  ];
  if (overallAvgRating.value) items.push({ icon: null, label: `★ ${overallAvgRating.value} avg rating` });
  return items;
});

const infoRows = computed(() => [
  { label: "Faculty", value: meta.value.faculty },
  { label: "Members", value: meta.value.members.toLocaleString() },
  { label: "Founded", value: meta.value.founded },
  { label: "Events run", value: String(societyEvents.value.length) },
  { label: "Upcoming", value: String(upcomingEvents.value.length) },
  { label: "Avg rating", value: overallAvgRating.value ? `★ ${overallAvgRating.value}` : "No data" },
]);
</script>

<template>
  <div>
    <!-- Hero -->
    <div style="position:relative;height:260px;overflow:hidden;background:#1a1a1a">
      <img v-if="meta.coverUrl" :src="meta.coverUrl" :alt="name" style="width:100%;height:100%;object-fit:cover;opacity:0.55"/>
      <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.8) 0%,rgba(0,0,0,0.2) 60%,transparent 100%)"/>
      <button @click="emit('back')" style="position:absolute;top:20px;left:20px;display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:8px;color:#fff;padding:7px 14px;cursor:pointer;font-size:14px;backdrop-filter:blur(8px);font-family:inherit">
        <ArrowLeft :size="15"/> All Societies
      </button>
      <div style="position:absolute;bottom:24px;left:24px;right:24px">
        <p style="font-size:11px;color:rgba(255,255,255,0.65);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:6px">{{ meta.faculty }}</p>
        <h1 style="font-size:clamp(20px,4vw,30px);font-weight:700;color:#fff;line-height:1.2">{{ name }}</h1>
      </div>
    </div>

    <!-- Stats bar -->
    <div style="background:#520000;padding:14px 24px">
      <div style="max-width:1280px;margin:0 auto;display:flex;gap:32px;flex-wrap:wrap">
        <span v-for="item in statsRow" :key="item.label" style="display:flex;align-items:center;gap:7px;font-size:13px;color:rgba(255,255,255,0.85);font-weight:500">
          <component v-if="item.icon" :is="item.icon" :size="14" style="color:#C17070"/>
          <span v-else style="color:#C17070">★</span>
          {{ item.label }}
        </span>
      </div>
    </div>

    <!-- Content -->
    <div class="society-detail-grid" style="max-width:1280px;margin:0 auto;padding:32px 24px 64px;display:grid;grid-template-columns:1fr min(360px,100%);gap:32px;align-items:start">

      <!-- Left: events -->
      <div>
        <div style="display:flex;border-bottom:1px solid var(--border-color);margin-bottom:20px">
          <button v-for="t in [{key:'upcoming',label:'Upcoming Events',count:upcomingEvents.length},{key:'past',label:'Past Events',count:pastEvents.length}]" :key="t.key"
            @click="activeTab=t.key"
            :style="{ background:'none', border:'none', cursor:'pointer', fontSize:'14px', fontWeight:activeTab===t.key?600:400, color:activeTab===t.key?'var(--maroon)':'var(--text-secondary)', padding:'8px 0', paddingRight:'24px', borderBottom:`2px solid ${activeTab===t.key?'var(--maroon)':'transparent'}`, marginBottom:'-1px', display:'flex', alignItems:'center', gap:'6px', fontFamily:'inherit' }">
            {{ t.label }}
            <span :style="{ background:activeTab===t.key?'var(--maroon-light)':'var(--bg-pill)', color:activeTab===t.key?'var(--maroon)':'var(--text-secondary)', borderRadius:'10px', fontSize:'11px', padding:'1px 7px', fontWeight:600 }">{{ t.count }}</span>
          </button>
        </div>

        <!-- Upcoming -->
        <template v-if="activeTab==='upcoming'">
          <div v-if="upcomingEvents.length===0" style="text-align:center;padding:48px 0">
            <Calendar :size="48" style="color:var(--accent);margin-bottom:12px;stroke-width:1.5"/>
            <p style="font-size:16px;font-weight:600;color:var(--text-primary);margin-bottom:6px">No upcoming events</p>
            <p style="font-size:14px;color:var(--text-secondary)">Check back soon or browse their event history.</p>
            <button @click="activeTab='past'" style="margin-top:12px;background:none;border:none;color:var(--maroon);font-size:14px;font-weight:500;cursor:pointer;text-decoration:underline;font-family:inherit">View past events →</button>
          </div>
          <div v-else style="display:flex;flex-direction:column;gap:12px">
            <EventRow v-for="ev in upcomingEvents" :key="ev.id" :event="ev"/>
          </div>
        </template>

        <!-- Past -->
        <template v-if="activeTab==='past'">
          <div v-if="pastEvents.length===0" style="text-align:center;padding:48px 0">
            <Clock :size="48" style="color:var(--accent);margin-bottom:12px;stroke-width:1.5"/>
            <p style="font-size:16px;font-weight:600;color:var(--text-primary);margin-bottom:6px">No past events yet</p>
            <p style="font-size:14px;color:var(--text-secondary)">This society's history will appear here after events are completed.</p>
          </div>
          <template v-else>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">{{ pastEvents.length }} completed event{{ pastEvents.length!==1?'s':'' }} — click "Reviews" to see attendee feedback</p>
            <div style="display:flex;flex-direction:column;gap:12px">
              <PastEventCard v-for="ev in pastEvents" :key="ev.id" :event="ev" :feedback-list="getFeedback(ev.id)"/>
            </div>
          </template>
        </template>
      </div>

      <!-- Right: about card -->
      <div style="position:sticky;top:88px">
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);margin-bottom:16px">
          <div :style="{ background: meta.logoColor, padding:'20px 24px', display:'flex', alignItems:'center', gap:'14px' }">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0">
              {{ initials || name[0] }}
            </div>
            <div>
              <h3 style="font-size:14px;font-weight:700;color:#fff;line-height:1.3">{{ name }}</h3>
              <p style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:2px">{{ meta.faculty }}</p>
            </div>
          </div>
          <div style="padding:20px">
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.7;margin-bottom:16px">{{ meta.desc }}</p>
            <div style="display:flex;flex-direction:column">
              <div v-for="row in infoRows" :key="row.label" style="display:flex;justify-content:space-between;font-size:13px;padding:9px 0;border-bottom:1px solid var(--border-color)">
                <span style="color:var(--text-secondary)">{{ row.label }}</span>
                <span :style="{ fontWeight:500, color: row.value.startsWith('★') ? '#B45309' : 'var(--text-primary)' }">{{ row.value }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick switch -->
        <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;padding:14px 16px">
          <p style="font-size:12px;font-weight:600;color:#AAAAAA;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px">Browse</p>
          <button @click="activeTab = activeTab==='upcoming' ? 'past' : 'upcoming'" class="quick-switch-btn">
            <template v-if="activeTab==='upcoming'"><Clock :size="14"/> View Past Events</template>
            <template v-else><Calendar :size="14"/> View Upcoming</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  .society-detail-grid { grid-template-columns: 1fr !important; }
}
.quick-switch-btn {
  width: 100%; height: 38px; border: 1px solid var(--maroon); border-radius: 6px;
  background: none; color: var(--maroon); font-size: 13px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  gap: 6px; transition: background 150ms; font-family: inherit;
}
.quick-switch-btn:hover { background: var(--maroon-light); }
</style>
