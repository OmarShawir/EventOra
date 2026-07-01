<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { Calendar, Users } from "lucide-vue-next";

const props = defineProps({
  event: { type: Object, required: true },
  feedbackList: { type: Array, default: () => [] },
});

const router = useRouter();
const expanded = ref(false);

const avgRating = computed(() =>
  props.feedbackList.length
    ? (props.feedbackList.reduce((a, f) => a + f.rating, 0) / props.feedbackList.length).toFixed(1)
    : null
);
</script>

<template>
  <div style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:8px;overflow:hidden">
    <div style="height:3px;background:linear-gradient(to right,var(--text-secondary),#AAAAAA)"/>

    <div style="display:flex;gap:16px;padding:16px;align-items:flex-start">
      <!-- Thumbnail -->
      <div style="width:68px;height:68px;border-radius:6px;overflow:hidden;flex-shrink:0;background:var(--border-color);filter:grayscale(0.5)">
        <img v-if="event.imageUrl" :src="event.imageUrl" :alt="event.title" style="width:100%;height:100%;object-fit:cover"/>
      </div>

      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap">
          <span style="background:var(--bg-pill);border:1px solid var(--border-card);border-radius:20px;font-size:10px;color:var(--text-secondary);padding:2px 8px;font-weight:500;text-transform:uppercase;letter-spacing:0.04em">{{ event.category }}</span>
          <span style="background:var(--bg-pill);color:var(--text-secondary);font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px">Completed</span>
          <span v-if="avgRating" style="display:flex;align-items:center;gap:3px;font-size:12px;color:#B45309;font-weight:600">
            ★ {{ avgRating }} <span style="color:#AAAAAA;font-weight:400">({{ feedbackList.length }})</span>
          </span>
        </div>

        <h4 @click="router.push(`/events/${event.id}`)" class="past-event-title">{{ event.title }}</h4>

        <div style="display:flex;gap:14px;flex-wrap:wrap">
          <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:4px"><Calendar :size="12"/> {{ event.date }}</span>
          <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:4px"><Users :size="12"/> {{ event.capacity }} attended</span>
        </div>
      </div>

      <button v-if="feedbackList.length > 0" @click="expanded = !expanded" class="past-event-toggle">
        {{ expanded ? "Hide" : "Reviews" }} ({{ feedbackList.length }})
      </button>
    </div>

    <!-- Expandable reviews -->
    <div v-if="expanded && feedbackList.length > 0" style="border-top:1px solid var(--border-color);padding:12px 16px 16px;background:var(--bg-pill)">
      <p style="font-size:11px;font-weight:600;color:#AAAAAA;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px">Attendee Reviews</p>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div v-for="(fb, i) in feedbackList" :key="i" style="background:var(--bg-card);border:1px solid var(--border-card);border-radius:6px;padding:10px 12px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
            <span style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ fb.userName }}</span>
            <div style="display:flex;gap:2px">
              <span v-for="s in 5" :key="s" :style="{ fontSize:'12px', color: s<=fb.rating ? '#B45309' : 'var(--border-color)' }">★</span>
            </div>
          </div>
          <p style="font-size:13px;color:var(--text-secondary);line-height:1.55">"{{ fb.comment }}"</p>
          <p style="font-size:11px;color:#AAAAAA;margin-top:4px">{{ fb.submittedAt }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.past-event-title {
  font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px;
  cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  transition: color 150ms;
}
.past-event-title:hover { color: var(--maroon); }

.past-event-toggle {
  flex-shrink: 0; background: none; border: 1px solid var(--border-color); border-radius: 6px;
  padding: 5px 10px; font-size: 12px; color: var(--text-secondary); cursor: pointer;
  display: flex; align-items: center; gap: 4px; white-space: nowrap; font-family: inherit;
}
</style>
