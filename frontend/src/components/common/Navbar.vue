<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Bell,
  Menu,
  X,
  Ticket,
  Compass,
  Users,
  QrCode,
  LayoutDashboard,
  Shield,
  LogOut,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";

defineProps({
  user: { type: Object, default: null }, // { name, initials } | null
});

const emit = defineEmits(["login-click", "signup-click", "logout"]);

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const scrolled = ref(false);
const drawerOpen = ref(false);
const profileOpen = ref(false);

function handleScroll() {
  scrolled.value = window.scrollY > 4;
}

onMounted(() => window.addEventListener("scroll", handleScroll, { passive: true }));
onUnmounted(() => window.removeEventListener("scroll", handleScroll));

// Role-based nav links — identical logic to the React version.
const navLinks = computed(() => {
  if (auth.role === "organiser") {
    return [
      { label: "Discover", icon: Compass, path: "/" },
      { label: "Societies", icon: Users, path: "/societies" },
      { label: "My Events", icon: LayoutDashboard, path: "/organiser" },
      { label: "Check-In", icon: QrCode, path: "/checkin" },
    ];
  }
  if (auth.role === "admin") {
    return [
      { label: "Discover", icon: Compass, path: "/" },
      { label: "Societies", icon: Users, path: "/societies" },
      { label: "Admin Panel", icon: Shield, path: "/admin" },
    ];
  }
  return [
    { label: "Discover", icon: Compass, path: "/" },
    { label: "Societies", icon: Users, path: "/societies" },
    { label: "My Tickets", icon: Ticket, path: "/dashboard" },
  ];
});

const isActive = (path) => route.path === path;

function go(path) {
  router.push(path);
}

function goAndClose(path) {
  router.push(path);
  drawerOpen.value = false;
}

function goAndCloseProfile(path) {
  router.push(path);
  profileOpen.value = false;
}

function handleLogout() {
  profileOpen.value = false;
  emit("logout");
}

function handleLogoutMobile() {
  drawerOpen.value = false;
  emit("logout");
}
</script>

<template>
  <nav
    :style="{
      height: '64px',
      background: '#ffffff',
      borderBottom: '1px solid #E5E5E5',
      boxShadow: scrolled ? '0 2px 12px rgba(0,0,0,0.08)' : 'none',
      transition: 'box-shadow 200ms ease',
      position: 'sticky',
      top: 0,
      zIndex: 50,
    }"
  >
    <div
      style="
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
      "
    >
      <!-- Wordmark -->
      <button
        @click="go('/')"
        style="background: none; border: none; cursor: pointer; display: flex; flex-direction: column; gap: 0; padding: 0"
      >
        <span style="font-size: 20px; line-height: 1.2">
          <span style="font-weight: 700; color: #1a1a1a">Event</span>
          <span style="font-weight: 700; color: #520000">Ora</span>
        </span>
        <span class="hidden md:block" style="font-size: 11px; color: #555555; line-height: 1.2">
          UTM Campus Events
        </span>
      </button>

      <!-- Desktop nav links -->
      <div class="hidden md:flex" style="gap: 32px; align-items: center">
        <button
          v-for="link in navLinks"
          :key="link.label"
          @click="go(link.path)"
          class="navbar-link"
          :class="{ 'navbar-link--active': isActive(link.path) }"
          :style="{
            background: 'none',
            border: 'none',
            cursor: 'pointer',
            fontSize: '15px',
            fontWeight: isActive(link.path) ? 500 : 400,
            color: isActive(link.path) ? '#520000' : '#555555',
            paddingBottom: '4px',
            borderBottom: `2px solid ${isActive(link.path) ? '#520000' : 'transparent'}`,
            transition: 'color 150ms, border-color 150ms',
            minHeight: '44px',
            display: 'flex',
            alignItems: 'center',
          }"
        >
          {{ link.label }}
        </button>
      </div>

      <!-- Right side -->
      <div class="hidden md:flex" style="gap: 8px; align-items: center">
        <template v-if="user">
          <button class="navbar-bell" style="background: none; border: none; cursor: pointer; color: #555555; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; position: relative">
            <Bell :size="20" />
            <span style="position: absolute; top: 8px; right: 8px; width: 7px; height: 7px; background: #520000; border-radius: 50%; border: 1.5px solid #fff" />
          </button>

          <div style="position: relative">
            <button
              @click="profileOpen = !profileOpen"
              class="navbar-avatar"
              style="width: 36px; height: 36px; border-radius: 50%; background: #520000; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; cursor: pointer; border: 2px solid transparent; transition: border-color 150ms"
            >
              {{ user.initials }}
            </button>

            <div
              v-if="profileOpen"
              style="position: absolute; right: 0; top: calc(100% + 8px); background: #fff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 8px 0; min-width: 180px; box-shadow: 0 4px 16px rgba(0,0,0,0.10); z-index: 60"
            >
              <div style="padding: 8px 16px 12px; border-bottom: 1px solid #E5E5E5; margin-bottom: 4px">
                <p style="font-size: 14px; font-weight: 600; color: #1a1a1a">{{ user.name }}</p>
                <p style="font-size: 12px; color: #555555; margin-top: 2px; text-transform: capitalize">
                  {{ auth.role ?? "Student" }}
                </p>
              </div>

              <button v-if="auth.role === 'attendee'" @click="goAndCloseProfile('/dashboard')" class="navbar-menu-item">
                <Ticket :size="14" /> My Tickets
              </button>

              <template v-if="auth.role === 'organiser'">
                <button @click="goAndCloseProfile('/organiser')" class="navbar-menu-item">
                  <LayoutDashboard :size="14" /> My Events
                </button>
                <button @click="goAndCloseProfile('/checkin')" class="navbar-menu-item">
                  <QrCode :size="14" /> QR Check-In
                </button>
              </template>

              <button v-if="auth.role === 'admin'" @click="goAndCloseProfile('/admin')" class="navbar-menu-item">
                <Shield :size="14" /> Admin Panel
              </button>

              <div style="height: 1px; background: #E5E5E5; margin: 4px 0" />

              <button @click="handleLogout" class="navbar-menu-item navbar-menu-item--danger">
                <LogOut :size="14" /> Sign out
              </button>
            </div>
          </div>
        </template>

        <template v-else>
          <button @click="emit('login-click')" class="navbar-btn-outline">Log in</button>
          <button @click="emit('signup-click')" class="navbar-btn-solid">Sign up</button>
        </template>
      </div>

      <!-- Mobile hamburger -->
      <button
        class="md:hidden"
        @click="drawerOpen = true"
        style="background: none; border: none; cursor: pointer; color: #1a1a1a; display: flex; align-items: center; padding: 8px"
      >
        <Menu :size="22" />
      </button>
    </div>
  </nav>

  <!-- Mobile drawer -->
  <div v-if="drawerOpen" style="position: fixed; inset: 0; z-index: 100">
    <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.4)" @click="drawerOpen = false" />
    <div style="position: absolute; top: 0; left: 0; bottom: 0; width: 280px; background: #fff; padding: 24px; display: flex; flex-direction: column">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px">
        <span style="font-size: 20px; font-weight: 700">
          <span style="color: #1a1a1a">Event</span><span style="color: #520000">Ora</span>
        </span>
        <button @click="drawerOpen = false" style="background: none; border: none; cursor: pointer; color: #555555">
          <X :size="22" />
        </button>
      </div>

      <button
        v-for="link in navLinks"
        :key="link.label"
        @click="goAndClose(link.path)"
        :style="{
          background: 'none',
          border: 'none',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          padding: '14px 0',
          fontSize: '16px',
          fontWeight: isActive(link.path) ? 600 : 400,
          color: isActive(link.path) ? '#520000' : '#555555',
          borderBottom: '1px solid #E5E5E5',
          width: '100%',
          textAlign: 'left',
        }"
      >
        <component :is="link.icon" :size="18" />{{ link.label }}
      </button>

      <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px">
        <button
          v-if="user"
          @click="handleLogoutMobile"
          style="height: 44px; border: 1px solid #E5E5E5; border-radius: 6px; background: none; font-size: 14px; font-weight: 500; color: #B91C1C; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px"
        >
          <LogOut :size="16" /> Sign out
        </button>
        <template v-else>
          <button
            @click="drawerOpen = false; emit('login-click')"
            style="height: 44px; border: 1px solid #E5E5E5; border-radius: 6px; background: none; font-size: 14px; font-weight: 500; color: #1a1a1a; cursor: pointer"
          >
            Log in
          </button>
          <button
            @click="drawerOpen = false; emit('signup-click')"
            style="height: 44px; border: none; border-radius: 6px; background: #520000; font-size: 14px; font-weight: 500; color: #fff; cursor: pointer"
          >
            Sign up
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Hover effects ported from JS onMouseEnter/onMouseLeave handlers in the
   original Navbar.tsx — same colors, expressed as CSS :hover instead. */
.navbar-link:not(.navbar-link--active):hover {
  color: #1a1a1a !important;
}
.navbar-avatar:hover {
  border-color: #c17070 !important;
}
.navbar-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 16px;
  background: none;
  border: none;
  font-size: 13px;
  color: #1a1a1a;
  text-align: left;
  cursor: pointer;
}
.navbar-menu-item:hover {
  background: #f9f9f9;
}
.navbar-menu-item--danger {
  color: #b91c1c;
}
.navbar-menu-item--danger:hover {
  background: #fff5f5;
}
.navbar-btn-outline {
  background: none;
  border: 1px solid #e5e5e5;
  border-radius: 6px;
  padding: 0 16px;
  height: 44px;
  font-size: 14px;
  font-weight: 500;
  color: #1a1a1a;
  cursor: pointer;
  transition: border-color 150ms, color 150ms;
}
.navbar-btn-outline:hover {
  border-color: #520000;
  color: #520000;
}
.navbar-btn-solid {
  background: #520000;
  border: none;
  border-radius: 6px;
  padding: 0 16px;
  height: 44px;
  font-size: 14px;
  font-weight: 500;
  color: #fff;
  cursor: pointer;
  transition: background 150ms;
}
.navbar-btn-solid:hover {
  background: #3a0000;
}
</style>
