<script setup>
import { ref } from "vue";
import { Search } from "lucide-vue-next";

const emit = defineEmits(["search"]);

const query = ref("");
const focused = ref(false);

function handleSubmit(e) {
  e.preventDefault();
  emit("search", query.value);
}
</script>

<template>
  <section style="background: linear-gradient(to bottom, var(--maroon-light), var(--bg-app)); padding: 64px 24px 48px; width: 100%">
    <div style="max-width: 680px; margin: 0 auto; text-align: center">
      <p style="font-size: 11px; font-weight: 500; color: var(--maroon); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 16px">
        Upcoming Events
      </p>
      <h1 style="font-size: clamp(28px, 5vw, 40px); font-weight: 700; color: var(--text-primary); line-height: 1.2; margin-bottom: 16px">
        Find your next campus experience
      </h1>
      <p style="font-size: 18px; font-weight: 400; color: var(--text-secondary); line-height: 1.6; margin-bottom: 32px">
        Browse events from all UTM societies, register, get your QR ticket, and show up.
      </p>

      <form @submit="handleSubmit" style="display: flex; max-width: 600px; margin: 0 auto">
        <div style="flex: 1; position: relative; display: flex; align-items: center">
          <Search :size="18" style="position: absolute; left: 14px; color: var(--text-secondary); pointer-events: none" />
          <input
            v-model="query"
            type="text"
            placeholder="Search events, societies, or keywords..."
            @focus="focused = true"
            @blur="focused = false"
            :style="{
              width: '100%',
              height: '48px',
              paddingLeft: '44px',
              paddingRight: '16px',
              border: `1px solid ${focused ? 'var(--maroon)' : 'var(--border-color)'}`,
              borderRight: 'none',
              borderRadius: '8px 0 0 8px',
              fontSize: '15px',
              color: 'var(--text-primary)',
              background: focused ? 'var(--maroon-light)' : 'var(--bg-card)',
              outline: 'none',
              transition: 'border-color 150ms, background 150ms',
              boxShadow: focused ? '0 0 0 3px rgba(82,0,0,0.08)' : 'none',
            }"
          />
        </div>
        <button type="submit" class="hero-search-btn">Search</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.hero-search-btn {
  height: 48px;
  padding: 0 20px;
  background: var(--maroon);
  color: #ffffff;
  border: none;
  border-radius: 0 8px 8px 0;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: background 150ms;
  font-family: inherit;
}
.hero-search-btn:hover { background: var(--maroon-hover); }
</style>
