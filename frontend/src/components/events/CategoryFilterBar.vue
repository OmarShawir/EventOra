<script setup>
import { ref } from "vue";
import { ChevronDown } from "lucide-vue-next";

const props = defineProps({
  activeCategory: { type: String, default: "All" },
});

const emit = defineEmits(["category-change", "sort-change"]);

const CATEGORIES = ["All", "Academic", "Sports", "Cultural", "Religious", "Workshop", "Career"];
const SORT_OPTIONS = ["Soonest", "Most Popular", "Recently Added"];

const sort = ref("Soonest");
const showSort = ref(false);

function handleSort(opt) {
  sort.value = opt;
  emit("sort-change", opt);
  showSort.value = false;
}
</script>

<template>
  <div style="background: #ffffff; border-bottom: 1px solid #E5E5E5; position: sticky; top: 64px; z-index: 40">
    <div style="max-width: 1280px; margin: 0 auto; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px">

      <!-- Category pills -->
      <div style="display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; flex: 1">
        <button
          v-for="cat in CATEGORIES"
          :key="cat"
          @click="emit('category-change', cat)"
          class="cat-pill"
          :class="{ 'cat-pill--active': activeCategory === cat }"
          :style="{
            height: '28px', padding: '0 16px', borderRadius: '20px',
            border: `1px solid ${activeCategory === cat ? '#520000' : '#E5E5E5'}`,
            background: activeCategory === cat ? '#FFF5F5' : '#F9F9F9',
            color: activeCategory === cat ? '#520000' : '#555555',
            fontSize: '13px', fontWeight: activeCategory === cat ? 500 : 400,
            cursor: 'pointer', whiteSpace: 'nowrap', transition: 'all 150ms ease', flexShrink: 0,
          }"
        >
          {{ cat }}
        </button>
      </div>

      <!-- Sort dropdown -->
      <div class="hidden md:block" style="position: relative; flex-shrink: 0">
        <button
          @click="showSort = !showSort"
          style="display: flex; align-items: center; gap: 6px; background: none; border: 1px solid #E5E5E5; border-radius: 6px; padding: 0 12px; height: 32px; font-size: 13px; color: #555555; cursor: pointer; white-space: nowrap; font-family: inherit"
        >
          Sort by: <span style="color: #1a1a1a; font-weight: 500">{{ sort }}</span>
          <ChevronDown :size="14" />
        </button>

        <div
          v-if="showSort"
          style="position: absolute; right: 0; top: calc(100% + 4px); background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 4px 0; min-width: 160px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); z-index: 50"
        >
          <button
            v-for="opt in SORT_OPTIONS"
            :key="opt"
            @click="handleSort(opt)"
            class="sort-option"
            :class="{ 'sort-option--active': sort === opt }"
            :style="{
              display: 'block', width: '100%', padding: '8px 16px',
              background: sort === opt ? '#FFF5F5' : 'none',
              border: 'none', fontSize: '13px',
              color: sort === opt ? '#520000' : '#1a1a1a',
              textAlign: 'left', cursor: 'pointer',
              fontWeight: sort === opt ? 500 : 400,
              fontFamily: 'inherit',
            }"
          >
            {{ opt }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cat-pill:not(.cat-pill--active):hover { border-color: #7a1010 !important; }
.sort-option:not(.sort-option--active):hover { background: #f9f9f9 !important; }
</style>
