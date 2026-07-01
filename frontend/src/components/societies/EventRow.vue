<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { Calendar, MapPin, ChevronRight } from "lucide-vue-next";

const props = defineProps({
  event: { type: Object, required: true },
});

const router = useRouter();

const almostFull = computed(() => props.event.spotsLeft > 0 && props.event.spotsLeft / props.event.capacity <= 0.1);
const spotsColor = computed(() => props.event.spotsLeft === 0 ? "#520000" : almostFull.value ? "#B45309" : "#1A7A4A");
const spotsText = computed(() =>
  props.event.spotsLeft === 0 ? "Fully booked" : almostFull.value ? `${props.event.spotsLeft} spots left!` : `${props.event.spotsLeft} spots left`
);
</script>

<template>
  <div @click="router.push(`/events/${event.id}`)" class="event-row">
    <!-- Cover thumbnail -->
    <div style="width:72px;height:72px;border-radius:6px;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,var(--maroon),var(--maroon-hover))">
      <img v-if="event.imageUrl" :src="event.imageUrl" :alt="event.title" style="width:100%;height:100%;object-fit:cover"/>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
        <span style="background:var(--maroon-light);border:1px solid var(--maroon-border);border-radius:20px;font-size:10px;color:var(--maroon);padding:2px 8px;font-weight:500;text-transform:uppercase;letter-spacing:0.04em">
          {{ event.category }}
        </span>
        <span v-if="event.price > 0" style="background:var(--text-primary);color:var(--bg-card);font-size:11px;font-weight:700;padding:2px 7px;border-radius:5px">RM {{ event.price }}</span>
        <span v-else style="color:#1A7A4A;font-size:11px;font-weight:600">FREE</span>
      </div>
      <h4 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ event.title }}</h4>
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:4px"><Calendar :size="12"/> {{ event.date }} · {{ event.time }}</span>
        <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:4px"><MapPin :size="12"/> {{ event.venue }}</span>
      </div>
    </div>

    <!-- Right side -->
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
      <span :style="{ fontSize:'12px', fontWeight:500, color:spotsColor }">{{ spotsText }}</span>
      <ChevronRight :size="16" style="color:var(--maroon)"/>
    </div>
  </div>
</template>

<style scoped>
.event-row {
  display: flex; gap: 16px; padding: 16px;
  border: 1px solid var(--border-card); border-radius: 8px;
  cursor: pointer; align-items: flex-start;
  background: var(--bg-card); transition: all 180ms ease;
}
.event-row:hover {
  background: var(--maroon-light);
  border-color: var(--maroon-border);
  transform: translateX(4px);
}
</style>
