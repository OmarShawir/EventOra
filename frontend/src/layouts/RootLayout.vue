<script setup>
import { ref, onMounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useEventsStore } from "@/stores/events";
import { useTicketsStore } from "@/stores/tickets";
import Navbar from "@/components/common/Navbar.vue";
import AuthModal from "@/components/auth/AuthModal.vue";

const auth = useAuthStore();
const eventsStore = useEventsStore();
const ticketsStore = useTicketsStore();
const router = useRouter();
const route = useRoute();

// Load events once when the app shell mounts (tries the real API first,
// falls back to mock JSON — see stores/events.js). Every page that reads
// from eventsStore.events shares this single fetch.
onMounted(() => {
  auth.restoreSession();
  eventsStore.fetchAll();

  const isDark = localStorage.getItem("eventora_dark_mode") === "enabled";
  if (isDark) {
    document.documentElement.classList.add("dark");
  } else {
    document.documentElement.classList.remove("dark");
  }
});

// Fetch tickets when user gets authenticated
watch(
  () => auth.isAuthenticated,
  (val) => {
    if (val) {
      ticketsStore.fetchMyTickets();
    }
  },
  { immediate: true }
);

// Watch for authentication requirements on navigation or actions
watch(
  () => route.query.authRequired,
  (val) => {
    if (val === "1") {
      openLogin();
      // Clean up query parameters from the URL
      router.replace({ query: { ...route.query, authRequired: undefined } });
    }
  },
  { immediate: true }
);

const authOpen = ref(false);
const authTab = ref("login");

function openLogin() {
  authTab.value = "login";
  authOpen.value = true;
}

function openSignup() {
  authTab.value = "register";
  authOpen.value = true;
}

const loggingOut = ref(false);
const loggingIn = ref(false);

async function handleLogout() {
  loggingOut.value = true;
  await new Promise((resolve) => setTimeout(resolve, 600));
  auth.logout();
  loggingOut.value = false;
  router.push("/");
}

// Route the user to the discover page after a successful login.
async function handleAuthSuccess(u) {
  authOpen.value = false;
  loggingIn.value = true;
  await new Promise((resolve) => setTimeout(resolve, 800));
  loggingIn.value = false;
  router.push("/");
}

</script>

<template>
  <div style="min-height: 100vh; background: var(--bg-app); font-family: 'Inter', sans-serif">
    <Navbar
      :user="auth.user ? { name: auth.user.name, initials: auth.user.initials } : null"
      @login-click="openLogin"
      @signup-click="openSignup"
      @logout="handleLogout"
    />

    <RouterView v-slot="{ Component }">
      <transition name="fade-slide" mode="out-in">
        <div :key="route.path + '_' + (auth.isAuthenticated ? 'auth' : 'anon')" class="page-transition-wrapper">
          <component :is="Component" />
        </div>
      </transition>
    </RouterView>

    <AuthModal
      :open="authOpen"
      :default-tab="authTab"
      @close="authOpen = false"
      @success="handleAuthSuccess"
    />

    <!-- Logout Overlay -->
    <transition name="fade">
      <div v-if="loggingOut" style="position: fixed; inset: 0; background: var(--bg-modal); z-index: 999; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; backdrop-filter: blur(8px)">
        <div class="logout-spinner" />
        <p style="font-size: 15px; font-weight: 500; color: var(--text-primary)">Logging you out safely…</p>
      </div>
    </transition>

    <!-- Login Overlay -->
    <transition name="fade">
      <div v-if="loggingIn" style="position: fixed; inset: 0; background: var(--bg-modal); z-index: 999; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; backdrop-filter: blur(8px)">
        <div class="login-spinner" />
        <p style="font-size: 15px; font-weight: 500; color: var(--text-primary)">Authenticating securely…</p>
      </div>
    </transition>
  </div>
</template>

<style>
/* Global page transition classes — hardware-accelerated for 60fps performance */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: opacity, transform;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* Fade transition for overlay */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }
.logout-spinner,
.login-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--maroon-light);
  border-top-color: var(--maroon);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
</style>
