<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import Navbar from "@/components/common/Navbar.vue";
import Footer from "@/components/common/Footer.vue";
import AuthModal from "@/components/auth/AuthModal.vue";
import DevRoleSwitcher from "@/components/common/DevRoleSwitcher.vue";

const auth = useAuthStore();
const router = useRouter();

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

function handleLogout() {
  auth.logout();
  router.push("/");
}

// Detect role from email for demo accounts, same heuristic as the
// original RootLayout.tsx, then route the user to their dashboard.
function handleAuthSuccess(u) {
  const email = u.email.toLowerCase();
  const role = email.includes("organiser")
    ? "organiser"
    : email.includes("admin")
    ? "admin"
    : "attendee";
  auth.login(u.email, role);
  authOpen.value = false;

  if (role === "organiser") router.push("/organiser");
  else if (role === "admin") router.push("/admin");
  else router.push("/dashboard");
}
</script>

<template>
  <div style="min-height: 100vh; background: #f9f9f9; font-family: 'Inter', sans-serif">
    <Navbar
      :user="auth.user ? { name: auth.user.name, initials: auth.user.initials } : null"
      @login-click="openLogin"
      @signup-click="openSignup"
      @logout="handleLogout"
    />

    <RouterView />

    <AuthModal
      :open="authOpen"
      :default-tab="authTab"
      @close="authOpen = false"
      @success="handleAuthSuccess"
    />

    <!-- Dev-only helper: lets the team preview every role's screens during
         testing/demo without re-authenticating. Remove before final build. -->
    <DevRoleSwitcher />
  </div>
</template>
