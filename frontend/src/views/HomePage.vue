<script setup>
import { ref, computed, onMounted } from "vue";
import { CalendarX } from "lucide-vue-next";
import { useEventsStore } from "@/stores/events";
import HeroSearch from "@/components/events/HeroSearch.vue";
import CategoryFilterBar from "@/components/events/CategoryFilterBar.vue";
import EventCard from "@/components/events/EventCard.vue";
import SkeletonCard from "@/components/events/SkeletonCard.vue";
import Footer from "@/components/common/Footer.vue";

const PAGE_SIZE = 6;
const eventsStore = useEventsStore();

const searchQuery = ref("");
const activeCategory = ref("All");
const visibleCount = ref(PAGE_SIZE);
const loading = ref(true);

// Simulate the 1-second loading delay from the original, which shows
// skeleton cards and gives a realistic feel before showing real data.
onMounted(() => {
  setTimeout(() => { loading.value = false; }, 1000);
});

const filtered = computed(() =>
  eventsStore.events.filter((e) => {
    if (e.status !== "approved") return false;
    const matchCat = activeCategory.value === "All" || e.category === activeCategory.value;
    const q = searchQuery.value.toLowerCase();
    return matchCat && (!q ||
      e.title.toLowerCase().includes(q) ||
      e.societyName.toLowerCase().includes(q) ||
      e.category.toLowerCase().includes(q) ||
      e.venue.toLowerCase().includes(q)
    );
  })
);

const visible = computed(() => filtered.value.slice(0, visibleCount.value));
const hasMore = computed(() => visibleCount.value < filtered.value.length);

function handleSearch(q) {
  searchQuery.value = q;
  visibleCount.value = PAGE_SIZE;
}

function handleCategoryChange(cat) {
  activeCategory.value = cat;
  visibleCount.value = PAGE_SIZE;
}

function clearSearch() {
  searchQuery.value = "";
  activeCategory.value = "All";
}

function loadMore() {
  visibleCount.value += PAGE_SIZE;
}
</script>

<template>
  <HeroSearch @search="handleSearch" />
  <CategoryFilterBar
    :active-category="activeCategory"
    @category-change="handleCategoryChange"
    @sort-change="() => {}"
  />

  <section style="max-width: 1280px; margin: 0 auto; padding: 32px 24px 64px">

    <p v-if="!loading" style="font-size: 13px; color: #555555; margin-bottom: 20px">
      Showing {{ visible.length }} event{{ visible.length !== 1 ? "s" : "" }}
    </p>

    <!-- Empty state -->
    <div
      v-if="!loading && filtered.length === 0"
      style="display: flex; flex-direction: column; align-items: center; padding: 64px 24px; text-align: center"
    >
      <CalendarX :size="72" style="color: #C17070; margin-bottom: 20px; stroke-width: 1.5" />
      <h2 style="font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px">No events found</h2>
      <p style="font-size: 15px; color: #555555; margin-bottom: 20px">Try a different keyword or category.</p>
      <button @click="clearSearch" style="background: none; border: none; color: #520000; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: underline; font-family: inherit">
        Clear search
      </button>
    </div>

    <!-- Events grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px">
      <SkeletonCard v-if="loading" v-for="i in 6" :key="i" />
      <EventCard v-else v-for="ev in visible" :key="ev.id" :event="ev" />
    </div>

    <!-- Load more -->
    <div v-if="!loading && hasMore" style="display: flex; justify-content: center; margin-top: 48px">
      <button @click="loadMore" class="load-more-btn">Load more events</button>
    </div>

  </section>

  <Footer />
</template>

<style scoped>
.load-more-btn {
  height: 44px;
  width: 180px;
  border-radius: 8px;
  border: 1px solid #520000;
  background: none;
  color: #520000;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: background 150ms;
}
.load-more-btn:hover { background: #fff5f5; }
</style>
