<script setup>
// lucide-vue-next mirrors lucide-react's API 1:1
import { Instagram, Linkedin } from "lucide-vue-next";

const links = ["About", "How it Works", "For Societies", "Contact"];
</script>

<template>
  <footer style="background: #1a1a1a; color: #f9f9f9; padding: 48px 24px 32px">
    <div
      style="
        max-width: 1280px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 40px;
        padding-bottom: 32px;
      "
    >
      <!-- Col 1 -->
      <div>
        <div style="margin-bottom: 12px">
          <span style="font-size: 20px; font-weight: 700; color: #ffffff">Event</span>
          <span style="font-size: 20px; font-weight: 700; color: #c17070">Ora</span>
        </div>
        <p style="font-size: 14px; color: #aaaaaa; line-height: 1.6; margin-bottom: 20px; max-width: 240px">
          The official event platform for UTM students.
        </p>
        <div style="display: flex; gap: 12px">
          <a href="#" class="footer-social-link">
            <Instagram :size="16" />
          </a>
          <a href="#" class="footer-social-link">
            <Linkedin :size="16" />
          </a>
        </div>
      </div>

      <!-- Col 2 -->
      <div>
        <p style="font-size: 11px; font-weight: 500; color: #777777; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px">
          Links
        </p>
        <a v-for="link in links" :key="link" href="#" class="footer-link">
          {{ link }}
        </a>
      </div>

      <!-- Col 3 -->
      <div>
        <p style="font-size: 11px; font-weight: 500; color: #777777; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px">
          Institution
        </p>
        <p style="font-size: 14px; color: #aaaaaa; margin-bottom: 8px">Faculty of Computing, UTM</p>
        <p style="font-size: 14px; color: #aaaaaa">Universiti Teknologi Malaysia</p>
        <p style="font-size: 14px; color: #aaaaaa; margin-top: 4px">81310 Johor Bahru, Malaysia</p>
      </div>
    </div>

    <div style="border-top: 1px solid #333333; padding-top: 24px">
      <div style="max-width: 1280px; margin: 0 auto">
        <p style="font-size: 13px; color: #777777; text-align: center">
          © 2026 EventOra. All rights reserved.
        </p>
      </div>
    </div>
  </footer>
</template>

<style scoped>
/* These hover effects were JS onMouseEnter/onMouseLeave handlers in the
   original React version. Plain inline styles can't express :hover, so
   they're reproduced here with scoped CSS — same visual result. */
.footer-social-link {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #333333;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #aaaaaa;
  transition: border-color 150ms, color 150ms;
  text-decoration: none;
}
.footer-social-link:hover {
  border-color: #c17070;
  color: #c17070;
}

.footer-link {
  display: block;
  font-size: 14px;
  color: #aaaaaa;
  text-decoration: none;
  margin-bottom: 12px;
  transition: color 150ms;
}
.footer-link:hover {
  color: #f9f9f9;
}
</style>
