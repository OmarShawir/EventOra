<script setup>
// App.vue stays minimal — RootLayout.vue (rendered via router) owns the
// Navbar / Footer / AuthModal shell. QRCheckInPage is a standalone route
// with no shell, matching the original React routes.tsx structure.
</script>

<template>
  <RouterView />
</template>
