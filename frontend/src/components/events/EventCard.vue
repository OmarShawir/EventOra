<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { Calendar, MapPin } from "lucide-vue-next";
import { useTicketsStore } from "@/stores/tickets";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  event: { type: Object, required: true },
});

const router = useRouter();
const tickets = useTicketsStore();
const auth = useAuthStore();

// Matches the original "registered" local state logic — derive from the
// tickets store once the user is logged in, so the card reflects real state.
const registered = computed(() =>
  auth.isAuthenticated
    ? tickets.myTickets.some((t) => t.eventId === props.event.id)
    : false
);

const spotsPercent = computed(() =>
  props.event.capacity > 0 ? (props.event.spotsLeft / props.event.capacity) * 100 : 0
);
const almostFull = computed(() => spotsPercent.value <= 10 && props.event.spotsLeft > 0);
const isFull = computed(() => props.event.spotsLeft === 0);

const spotsColor = computed(() => {
  if (registered.value) return "#1A7A4A";
  if (isFull.value) return "#520000";
  if (almostFull.value) return "#B45309";
  return "#1A7A4A";
});

const spotsText = computed(() => {
  if (registered.value) return "Registered ✓";
  if (isFull.value) return "Waitlist open";
  return `${props.event.spotsLeft} spots left`;
});

function handleCardClick() {
  router.push(`/events/${props.event.id}`);
}

function handleRegister(e) {
  e.stopPropagation();
  if (!auth.isAuthenticated) {
    router.push({ query: { authRequired: "1" } });
    return;
  }
  if (props.event.price > 0) {
    tickets.registerPaid(props.event.id);
  } else {
    tickets.registerFree(props.event.id);
  }
}

function handleWaitlist(e) {
  e.stopPropagation();
  // TODO (Week 4): wire to a POST /waitlist/:eventId endpoint
  alert("Added to waitlist! You'll be notified if a spot opens.");
}
</script>

<template>
  <div class="event-card" @click="handleCardClick">
    <!-- Cover image -->
    <div style="position: relative; height: 160px; flex-shrink: 0">
      <img
        v-if="event.imageUrl"
        :src="event.imageUrl"
        :alt="event.title"
        style="width: 100%; height: 100%; object-fit: cover"
      />
      <div
        v-else
        style="width: 100%; height: 100%; background: linear-gradient(135deg, #520000, #7A1010); display: flex; align-items: center; justify-content: center"
      >
        <span style="font-size: 40px; font-weight: 700; color: rgba(255,255,255,0.9)">E</span>
      </div>
      <span style="position: absolute; bottom: 10px; left: 10px; background: #520000; color: #ffffff; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 10px; border-radius: 20px">
        {{ event.category }}
      </span>
    </div>

    <!-- Card body -->
    <div style="padding: 16px 16px 0; flex: 1">
      <p style="font-size: 11px; font-weight: 500; color: #520000; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px">
        {{ event.societyName }}
      </p>
      <h3 class="event-card__title">{{ event.title }}</h3>
      <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px">
        <Calendar :size="16" style="color: #555555; flex-shrink: 0" />
        <span style="font-size: 13px; color: #555555">{{ event.date }} · {{ event.time }}</span>
      </div>
      <div style="display: flex; align-items: center; gap: 6px">
        <MapPin :size="16" style="color: #555555; flex-shrink: 0" />
        <span style="font-size: 13px; color: #555555; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
          {{ event.venue }}
        </span>
      </div>
      <div style="height: 1px; background: #E5E5E5; margin: 12px 0 8px" />
    </div>

    <!-- Card footer -->
    <div style="padding: 0 16px 16px; display: flex; align-items: center; justify-content: space-between">
      <span :style="{ fontSize: '13px', fontWeight: 500, color: spotsColor }">
        {{ spotsText }}
      </span>

      <button
        v-if="registered"
        disabled
        style="background: #1A7A4A; color: #fff; border: none; border-radius: 8px; padding: 0 12px; height: 32px; font-size: 13px; font-weight: 500; cursor: not-allowed; opacity: 0.9; font-family: inherit"
      >
        Registered ✓
      </button>
      <button
        v-else-if="isFull"
        @click="handleWaitlist"
        class="event-card__btn event-card__btn--outline"
      >
        Join Waitlist
      </button>
      <button
        v-else
        @click="handleRegister"
        class="event-card__btn event-card__btn--solid"
      >
        Register
      </button>
    </div>
  </div>
</template>

<style scoped>
.event-card {
  background: #ffffff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  transform: translateY(0);
  transition: all 200ms ease;
  cursor: pointer;
  display: flex;
  flex-direction: column;
}
.event-card:hover {
  border-color: #c17070;
  box-shadow: 0 4px 16px rgba(82, 0, 0, 0.1);
  transform: translateY(-2px);
}
.event-card__title {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 1.35;
  margin-bottom: 10px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.event-card__btn {
  border-radius: 8px;
  padding: 0 12px;
  height: 32px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 150ms, border-color 150ms;
  font-family: inherit;
}
.event-card__btn--solid {
  background: #520000;
  color: #fff;
  border: none;
}
.event-card__btn--solid:hover { background: #3a0000; }
.event-card__btn--outline {
  background: none;
  color: #520000;
  border: 1px solid #520000;
}
.event-card__btn--outline:hover { background: #fff5f5; }
</style>
