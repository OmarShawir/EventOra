<script setup>
import { ref, computed } from "vue";
import { Search, Users } from "lucide-vue-next";
import { useEventsStore } from "@/stores/events";
import { useSocietiesStore } from "@/stores/societies";
import SocietyCard from "@/components/societies/SocietyCard.vue";
import SocietyDetail from "@/components/societies/SocietyDetail.vue";
import Footer from "@/components/common/Footer.vue";

const eventsStore = useEventsStore();
const societiesStore = useSocietiesStore();

const search = ref("");
const selected = ref(null);
const focused = ref(false);

const societies = computed(() => [...new Set(eventsStore.events.map((e) => e.societyName))]);

const filtered = computed(() =>
  societies.value.filter((name) =>
    !search.value ||
    name.toLowerCase().includes(search.value.toLowerCase()) ||
    (societiesStore.getSociety(name)?.faculty ?? "").toLowerCase().includes(search.value.toLowerCase())
  )
);

function selectSociety(name) {
  selected.value = name;
  window.scrollTo({ top: 0, behavior: "smooth" });
}
function backToList() {
  selected.value = null;
  window.scrollTo({ top: 0 });
}
</script>

<template>
  <!-- Detail view -->
  <template v-if="selected">
    <SocietyDetail :name="selected" @back="backToList" />
    <Footer />
  </template>

  <!-- List view -->
  <template v-else>
    <div style="background:linear-gradient(to bottom,var(--maroon-light),var(--bg-app));padding:52px 24px 44px">
      <div style="max-width:680px;margin:0 auto;text-align:center">
        <p style="font-size:11px;font-weight:500;color:var(--maroon);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:12px">
          Universiti Teknologi Malaysia
        </p>
        <h1 style="font-size:clamp(26px,5vw,40px);font-weight:700;color:var(--text-primary);line-height:1.2;margin-bottom:12px">
          Student Societies
        </h1>
        <p style="font-size:16px;color:var(--text-secondary);line-height:1.65;margin-bottom:28px">
          {{ societies.length }} active societies on campus. Click any society to browse their events and learn more.
        </p>

        <!-- Search -->
        <div style="position:relative;max-width:480px;margin:0 auto">
          <Search :size="17" :style="{ position:'absolute', left:'14px', top:'50%', transform:'translateY(-50%)', color: focused ? 'var(--maroon)' : 'var(--text-secondary)', pointerEvents:'none' }"/>
          <input
            v-model="search"
            @focus="focused=true"
            @blur="focused=false"
            placeholder="Search societies or faculties…"
            :style="{
              width:'100%', height:'48px', paddingLeft:'44px', paddingRight:'16px',
              border:`1px solid ${focused ? 'var(--maroon)' : 'var(--border-color)'}`, borderRadius:'8px',
              fontSize:'15px', background: focused ? 'var(--maroon-light)' : 'var(--bg-card)', outline:'none',
              color: 'var(--text-primary)',
              boxShadow: focused ? '0 0 0 3px rgba(82,0,0,0.08)' : 'none',
              transition:'all 150ms', boxSizing:'border-box',
            }"
          />
        </div>
      </div>
    </div>

    <div style="max-width:1280px;margin:0 auto;padding:28px 24px 64px">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">
        {{ filtered.length }} societ{{ filtered.length !== 1 ? 'ies' : 'y' }} — click to view events
      </p>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
        <SocietyCard v-for="name in filtered" :key="name" :name="name" @select="selectSociety(name)" />
      </div>

      <div v-if="filtered.length===0" style="text-align:center;padding:64px 0">
        <Users :size="48" style="color:var(--accent);margin-bottom:12px;stroke-width:1.5"/>
        <p style="font-size:16px;font-weight:600;color:var(--text-primary);margin-bottom:6px">No societies found</p>
        <p style="font-size:14px;color:var(--text-secondary)">Try a different search term.</p>
      </div>
    </div>

    <Footer/>
  </template>
</template>
