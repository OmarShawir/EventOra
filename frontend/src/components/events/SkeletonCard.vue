<template>
  <div style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08)">
    <div class="shimmer" style="height: 160px" />
    <div style="padding: 16px">
      <div class="shimmer" style="height: 10px; width: 40%; border-radius: 4px; margin-bottom: 10px" />
      <div class="shimmer" style="height: 16px; width: 80%; border-radius: 4px; margin-bottom: 6px" />
      <div class="shimmer" style="height: 12px; width: 60%; border-radius: 4px; margin-bottom: 6px" />
      <div class="shimmer" style="height: 12px; width: 70%; border-radius: 4px" />
      <div style="height: 1px; background: #E5E5E5; margin: 12px 0" />
      <div style="display: flex; justify-content: space-between; align-items: center">
        <div class="shimmer" style="height: 12px; width: 30%; border-radius: 4px" />
        <div class="shimmer" style="height: 32px; width: 80px; border-radius: 8px" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* shimmer animation is defined globally in assets/main.css */
.shimmer {
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
</style>
