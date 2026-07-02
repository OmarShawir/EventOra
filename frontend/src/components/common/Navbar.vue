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
  Sun,
  Moon,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useEventsStore } from "@/stores/events";
import { useTicketsStore } from "@/stores/tickets";

defineProps({
  user: { type: Object, default: null }, // { name, initials } | null
});

const emit = defineEmits(["login-click", "signup-click", "logout"]);

const auth = useAuthStore();
const eventsStore = useEventsStore();
const ticketsStore = useTicketsStore();
const router = useRouter();
const route = useRoute();

const scrolled = ref(false);
const drawerOpen = ref(false);
const profileOpen = ref(false);
const notificationsOpen = ref(false);

function handleScroll() {
  scrolled.value = window.scrollY > 4;
}

const notifications = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return [];
  const list = [];

  if (auth.role === "attendee") {
    // Show notification for upcoming registered events within 2 days
    ticketsStore.myTickets.forEach((ticket) => {
      if (ticket.event) {
        // Handle mock string date "Sat, 27 Jun 2026" or real date
        const dateStr = ticket.event.starts_at || ticket.event.date;
        const evDate = new Date(dateStr);
        const now = new Date();
        const diffTime = evDate.getTime() - now.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays >= 0 && diffDays <= 2) {
          list.push({
            id: `att-soon-${ticket.id}`,
            title: "Event Starting Soon! ⏰",
            message: `"${ticket.event.title}" starts soon on ${ticket.event.date} at ${ticket.event.time}.`,
            time: diffDays === 0 ? "Today" : diffDays === 1 ? "Tomorrow" : "In 2 days",
            path: `/events/${ticket.eventId}`,
          });
        }
      }
    });
  }

  if (auth.role === "organiser") {
    // Show events that are high capacity (85% or above)
    eventsStore.events.forEach((event) => {
      if (event.societyName === auth.user.society) {
        const filled = event.capacity - event.spotsLeft;
        const pct = Math.round((filled / event.capacity) * 100);
        if (pct >= 85) {
          list.push({
            id: `org-cap-${event.id}`,
            title: "High Event Capacity 🔥",
            message: `"${event.title}" is at ${pct}% capacity (${filled}/${event.capacity} registered).`,
            time: "Capacity alert",
            path: `/events/${event.id}`,
          });
        }
      }
    });
  }

  if (auth.role === "admin") {
    // Show events that are pending approval
    eventsStore.events.forEach((event) => {
      if (event.status === "pending") {
        list.push({
          id: `admin-new-${event.id}`,
          title: "New Event Proposal 📥",
          message: `"${event.title}" by ${event.societyName} is pending your approval.`,
          time: "New request",
          path: "/admin",
        });
      }
    });
  }

  return list;
});

onMounted(() => {
  window.addEventListener("scroll", handleScroll, { passive: true });
  isDark.value = document.documentElement.classList.contains("dark");
});
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
  
  const links = [
    { label: "Discover", icon: Compass, path: "/" },
    { label: "Societies", icon: Users, path: "/societies" },
  ];
  
  if (auth.isAuthenticated) {
    links.push({ label: "My Tickets", icon: Ticket, path: "/dashboard" });
  }
  
  return links;
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
  notificationsOpen.value = false;
}

function handleLogout() {
  profileOpen.value = false;
  notificationsOpen.value = false;
  emit("logout");
}

function handleLogoutMobile() {
  drawerOpen.value = false;
  emit("logout");
}

const isDark = ref(false);

function toggleDarkMode() {
  const nextDark = !isDark.value;
  isDark.value = nextDark;
  if (nextDark) {
    document.documentElement.classList.add("dark");
    localStorage.setItem("eventora_dark_mode", "enabled");
  } else {
    document.documentElement.classList.remove("dark");
    localStorage.setItem("eventora_dark_mode", "disabled");
  }
}
</script>

<template>
  <nav
    :style="{
      height: '64px',
      background: 'var(--bg-surface)',
      backdropFilter: 'blur(12px)',
      webkitBackdropFilter: 'blur(12px)',
      borderBottom: '1px solid var(--border-color)',
      boxShadow: scrolled ? '0 4px 20px rgba(0,0,0,0.05)' : 'none',
      transition: 'box-shadow 200ms ease, background 200ms ease',
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
          <span style="font-weight: 700; color: var(--text-primary)">Event</span>
          <span style="font-weight: 700; color: var(--maroon)">Ora</span>
        </span>
        <span class="hidden md:block" style="font-size: 11px; color: var(--text-secondary); line-height: 1.2">
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
            color: isActive(link.path) ? 'var(--maroon)' : 'var(--text-secondary)',
            paddingBottom: '4px',
            borderBottom: `2px solid ${isActive(link.path) ? 'var(--maroon)' : 'transparent'}`,
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
        <!-- Theme Toggle -->
        <button
          @click="toggleDarkMode"
          style="background: none; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; transition: all 150ms ease"
          onmouseover="this.style.borderColor='var(--maroon)'; this.style.color='var(--text-primary)'"
          onmouseout="this.style.borderColor='var(--border-color)'; this.style.color='var(--text-secondary)'"
          aria-label="Toggle Dark Mode"
        >
          <Sun v-if="isDark" :size="18" />
          <Moon v-else :size="18" />
        </button>
        <template v-if="user">
          <!-- Notification Bell and Dropdown -->
          <div style="position: relative">
            <button
              @click="notificationsOpen = !notificationsOpen; profileOpen = false"
              class="navbar-bell"
              style="background: none; border: none; cursor: pointer; color: #555555; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; position: relative"
            >
              <Bell :size="20" />
              <span v-if="notifications.length > 0" style="position: absolute; top: 8px; right: 8px; width: 7px; height: 7px; background: #520000; border-radius: 50%; border: 1.5px solid #fff" />
            </button>
            
            <div
              v-if="notificationsOpen"
              style="position: absolute; right: 0; top: calc(100% + 8px); background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 8px; padding: 12px; min-width: 280px; max-width: 320px; box-shadow: var(--shadow-dropdown); z-index: 60"
            >
              <p style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px">
                Notifications ({{ notifications.length }})
              </p>
              
              <div v-if="notifications.length === 0" style="padding: 16px 0; text-align: center; color: var(--text-secondary); font-size: 13px">
                No new notifications
              </div>
              
              <div v-else style="max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px">
                <button
                  v-for="notif in notifications"
                  :key="notif.id"
                  @click="goAndCloseProfile(notif.path)"
                  style="width: 100%; padding: 10px; border-radius: 6px; background: var(--bg-pill); border: none; border-left: 3px solid var(--maroon); font-size: 12px; line-height: 1.4; text-align: left; cursor: pointer; display: block; font-family: inherit; transition: background 150ms"
                  onmouseover="this.style.background='var(--bg-hover)'"
                  onmouseout="this.style.background='var(--bg-pill)'"
                >
                  <p style="font-weight: 600; color: var(--text-primary); margin-bottom: 2px; margin-top: 0">{{ notif.title }}</p>
                  <p style="color: var(--text-secondary); margin-bottom: 4px; margin-top: 0">{{ notif.message }}</p>
                  <span style="font-size: 10px; color: var(--text-secondary); font-weight: 500">{{ notif.time }}</span>
                </button>
              </div>
            </div>
          </div>

          <div style="position: relative">
            <button
              @click="profileOpen = !profileOpen; notificationsOpen = false"
              class="navbar-avatar"
              style="width: 36px; height: 36px; border-radius: 50%; background: var(--maroon); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; cursor: pointer; border: 2px solid transparent; transition: border-color 150ms"
            >
              {{ user.initials }}
            </button>

            <div
              v-if="profileOpen"
              style="position: absolute; right: 0; top: calc(100% + 8px); background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 8px; padding: 8px 0; min-width: 180px; box-shadow: var(--shadow-dropdown); z-index: 60"
            >
              <div style="padding: 8px 16px 12px; border-bottom: 1px solid var(--border-color); margin-bottom: 4px">
                <p style="font-size: 14px; font-weight: 600; color: var(--text-primary)">{{ user.name }}</p>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px; text-transform: capitalize">
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

      <!-- Mobile hamburger and theme toggle -->
      <div class="flex md:hidden" style="align-items: center; gap: 8px">
        <!-- Theme Toggle -->
        <button
          @click="toggleDarkMode"
          style="background: none; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; transition: all 150ms ease"
          aria-label="Toggle Dark Mode"
        >
          <Sun v-if="isDark" :size="18" />
          <Moon v-else :size="18" />
        </button>
        <button
          @click="drawerOpen = true"
          style="background: none; border: none; cursor: pointer; color: var(--text-primary); display: flex; align-items: center; padding: 8px"
        >
          <Menu :size="22" />
        </button>
      </div>
    </div>
  </nav>

  <!-- Mobile drawer -->
  <div v-if="drawerOpen" style="position: fixed; inset: 0; z-index: 100">
    <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.4)" @click="drawerOpen = false" />
    <div style="position: absolute; top: 0; left: 0; bottom: 0; width: 280px; background: var(--bg-surface); border-right: 1px solid var(--border-color); padding: 24px; display: flex; flex-direction: column">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px">
        <span style="font-size: 20px; font-weight: 700">
          <span style="color: var(--text-primary)">Event</span><span style="color: var(--maroon)">Ora</span>
        </span>
        <button @click="drawerOpen = false" style="background: none; border: none; cursor: pointer; color: var(--text-secondary)">
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
          color: isActive(link.path) ? 'var(--maroon)' : 'var(--text-secondary)',
          borderBottom: '1px solid var(--border-color)',
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
          style="height: 44px; border: 1px solid var(--border-color); border-radius: 6px; background: none; font-size: 14px; font-weight: 500; color: #B91C1C; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px"
        >
          <LogOut :size="16" /> Sign out
        </button>
        <template v-else>
          <button
            @click="drawerOpen = false; emit('login-click')"
            style="height: 44px; border: 1px solid var(--border-color); border-radius: 6px; background: none; font-size: 14px; font-weight: 500; color: var(--text-primary); cursor: pointer"
          >
            Log in
          </button>
          <button
            @click="drawerOpen = false; emit('signup-click')"
            style="height: 44px; border: none; border-radius: 6px; background: var(--maroon); font-size: 14px; font-weight: 500; color: #fff; cursor: pointer"
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
  border-color: var(--maroon-border) !important;
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
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
}
.navbar-menu-item:hover {
  background: var(--bg-hover);
}
.navbar-menu-item--danger {
  color: #ef5350;
}
.navbar-menu-item--danger:hover {
  background: var(--maroon-light);
}
.navbar-btn-outline {
  background: none;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 0 16px;
  height: 44px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
  cursor: pointer;
  transition: border-color 150ms, color 150ms;
}
.navbar-btn-outline:hover {
  border-color: var(--maroon);
  color: var(--maroon);
}
.navbar-btn-solid {
  background: var(--maroon);
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
  background: var(--maroon-hover);
}
</style>
