<script setup>
import { computed } from "vue";
import { Users, ChevronRight } from "lucide-vue-next";
import { useEventsStore } from "@/stores/events";
import { useSocietiesStore } from "@/stores/societies";

const props = defineProps({
  name: { type: String, required: true },
});
const emit = defineEmits(["select"]);

const eventsStore = useEventsStore();
const societiesStore = useSocietiesStore();

const meta = computed(() =>
  societiesStore.getSociety(props.name) ?? { desc: "", faculty: "UTM", members: 0, founded: "—", coverUrl: "", logoColor: "#520000" }
);
const upcomingCount = computed(() =>
  eventsStore.events.filter((e) => e.societyName === props.name && e.status === "approved").length
);
</script>

<template>
  <div @click="emit('select')" class="society-card">
    <!-- Cover -->
    <div :style="{ height:'130px', position:'relative', overflow:'hidden', background: meta.logoColor }">
      <img v-if="meta.coverUrl" :src="meta.coverUrl" :alt="name" style="width:100%;height:100%;object-fit:cover;opacity:0.7"/>
      <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.5) 0%,transparent 60%)"/>
      <div style="position:absolute;bottom:10px;left:12px;right:12px;display:flex;justify-content:space-between;align-items:center">
        <span style="background:rgba(82,0,0,0.9);color:#fff;font-size:10px;font-weight:600;padding:3px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:0.05em">
          {{ upcomingCount }} upcoming
        </span>
      </div>
    </div>

    <!-- Body -->
    <div style="padding:14px 16px;flex:1;display:flex;flex-direction:column">
      <h3 style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:3px;line-height:1.3">{{ name }}</h3>
      <p style="font-size:11px;color:var(--maroon);font-weight:500;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em">{{ meta.faculty }}</p>
      <p class="society-desc">{{ meta.desc }}</p>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;color:var(--text-secondary);display:flex;align-items:center;gap:3px">
          <Users :size="11"/> {{ meta.members.toLocaleString() }}
        </span>
        <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--maroon);font-weight:600">
          View <ChevronRight :size="13"/>
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.society-card {
  background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 10px;
  overflow: hidden; cursor: pointer; transition: all 200ms ease;
  display: flex; flex-direction: column;
}
.society-card:hover {
  box-shadow: 0 6px 20px rgba(82,0,0,0.12);
  border-color: var(--maroon-border);
  transform: translateY(-3px);
}
.society-desc {
  font-size: 12px; color: var(--text-secondary); line-height: 1.6; flex: 1;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; margin-bottom: 12px;
}
</style>
