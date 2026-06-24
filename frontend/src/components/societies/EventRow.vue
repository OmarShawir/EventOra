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
    <div style="width:72px;height:72px;border-radius:6px;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#520000,#7A1010)">
      <img v-if="event.imageUrl" :src="event.imageUrl" :alt="event.title" style="width:100%;height:100%;object-fit:cover"/>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
        <span style="background:#FFF5F5;border:1px solid #C17070;border-radius:20px;font-size:10px;color:#520000;padding:2px 8px;font-weight:500;text-transform:uppercase;letter-spacing:0.04em">
          {{ event.category }}
        </span>
        <span v-if="event.price > 0" style="background:#1a1a1a;color:#fff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:5px">RM {{ event.price }}</span>
        <span v-else style="color:#1A7A4A;font-size:11px;font-weight:600">FREE</span>
      </div>
      <h4 style="font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ event.title }}</h4>
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <span style="font-size:12px;color:#555555;display:flex;align-items:center;gap:4px"><Calendar :size="12"/> {{ event.date }} · {{ event.time }}</span>
        <span style="font-size:12px;color:#555555;display:flex;align-items:center;gap:4px"><MapPin :size="12"/> {{ event.venue }}</span>
      </div>
    </div>

    <!-- Right side -->
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
      <span :style="{ fontSize:'12px', fontWeight:500, color:spotsColor }">{{ spotsText }}</span>
      <ChevronRight :size="16" style="color:#C17070"/>
    </div>
  </div>
</template>

<style scoped>
.event-row {
  display: flex; gap: 16px; padding: 16px;
  border: 1px solid #E5E5E5; border-radius: 8px;
  cursor: pointer; align-items: flex-start;
  background: #fff; transition: all 180ms ease;
}
.event-row:hover {
  background: #fff5f5;
  border-color: #c17070;
  transform: translateX(4px);
}
</style>
