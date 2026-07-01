<script setup>
import { ref, watch, onMounted } from "vue";
import QRCode from "qrcode";

const props = defineProps({
  value: { type: String, required: true },
  size: { type: Number, default: 140 },
});

const dataUrl = ref("");
const hasError = ref(false);

async function generate() {
  if (!props.value) return;
  try {
    dataUrl.value = await QRCode.toDataURL(props.value, {
      width: props.size * 2,
      margin: 2,
      color: { dark: "#1A1A1A", light: "#FFFFFF" },
      errorCorrectionLevel: "M",
    });
    hasError.value = false;
  } catch (e) {
    console.error("[QRCodeDisplay] Failed to generate QR code:", e);
    hasError.value = true;
  }
}

onMounted(generate);
watch(() => props.value, generate);
</script>

<template>
  <div
    :style="{
      width: size + 'px',
      height: size + 'px',
      background: '#fff',
      borderRadius: '6px',
      overflow: 'hidden',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
    }"
  >
    <img
      v-if="dataUrl && !hasError"
      :src="dataUrl"
      :width="size"
      :height="size"
      :alt="'QR Code: ' + value"
      style="display: block"
    />
    <div v-else-if="hasError" style="font-size: 11px; color: #B91C1C; text-align: center; padding: 8px">
      QR error
    </div>
    <div v-else style="font-size: 11px; color: #AAAAAA">...</div>
  </div>
</template>
