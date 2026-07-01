<script setup>
import { ref } from "vue";
import { ChevronDown } from "lucide-vue-next";

const props = defineProps({
  activeCategory: { type: String, default: "All" },
});

const emit = defineEmits(["category-change", "sort-change"]);

const CATEGORIES = ["All", "Academic", "Sports", "Cultural", "Religious", "Workshop", "Career"];
const SORT_OPTIONS = ["Soonest", "Most Popular", "Recently Added", "Free First", "Paid First"];

const sort = ref("Soonest");
const showSort = ref(false);

function handleSort(opt) {
  sort.value = opt;
  emit("sort-change", opt);
  showSort.value = false;
}
</script>

<template>
  <div style="background: var(--bg-surface); border-bottom: 1px solid var(--border-color); position: sticky; top: 64px; z-index: 40">
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
            border: `1px solid ${activeCategory === cat ? 'var(--maroon)' : 'var(--border-color)'}`,
            background: activeCategory === cat ? 'var(--maroon-light)' : 'var(--bg-pill)',
            color: activeCategory === cat ? 'var(--maroon)' : 'var(--text-secondary)',
            fontSize: '13px', fontWeight: activeCategory === cat ? 500 : 400,
            cursor: 'pointer', whiteSpace: 'nowrap', transition: 'all 150ms ease', flexShrink: 0,
          }"
        >
          {{ cat }}
        </button>
      </div>

      <!-- Sort dropdown -->
      <div style="position: relative; flex-shrink: 0">
        <button
          @click="showSort = !showSort"
          style="display: flex; align-items: center; gap: 6px; background: none; border: 1px solid var(--border-color); border-radius: 6px; padding: 0 12px; height: 32px; font-size: 13px; color: var(--text-secondary); cursor: pointer; white-space: nowrap; font-family: inherit"
        >
          Sort by: <span style="color: var(--text-primary); font-weight: 500">{{ sort }}</span>
          <ChevronDown :size="14" />
        </button>

        <div
          v-if="showSort"
          style="position: absolute; right: 0; top: calc(100% + 4px); background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 8px; padding: 4px 0; min-width: 160px; box-shadow: var(--shadow-dropdown); z-index: 50"
        >
          <button
            v-for="opt in SORT_OPTIONS"
            :key="opt"
            @click="handleSort(opt)"
            class="sort-option"
            :class="{ 'sort-option--active': sort === opt }"
            :style="{
              display: 'block', width: '100%', padding: '8px 16px',
              background: sort === opt ? 'var(--maroon-light)' : 'none',
              border: 'none', fontSize: '13px',
              color: sort === opt ? 'var(--maroon)' : 'var(--text-primary)',
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
.cat-pill:not(.cat-pill--active):hover { border-color: var(--maroon-border) !important; }
.sort-option:not(.sort-option--active):hover { background: var(--bg-hover) !important; }
</style>
