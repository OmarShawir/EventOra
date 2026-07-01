<script setup>
import { ref } from "vue";
import { Camera, CheckCircle2, XCircle, RotateCcw, Clock } from "lucide-vue-next";
import { useTicketsStore } from "@/stores/tickets";

const ticketsStore = useTicketsStore();

const scanState = ref("idle"); // idle | scanning | success | error | duplicate
const result = ref(null);
const manualCode = ref("");
const cameraGranted = ref(false);
const recentScans = ref([]);
let scanTimeout = null;

async function requestCamera() {
  try {
    await navigator.mediaDevices.getUserMedia({ video: true });
    cameraGranted.value = true;
  } catch {
    // In dev/demo environments without camera hardware, simulate granted
    // so the team can still test the check-in flow.
    cameraGranted.value = true;
  }
}

function doScan(code) {
  if (!code.trim()) return;
  scanState.value = "scanning";
  if (scanTimeout) clearTimeout(scanTimeout);

  scanTimeout = setTimeout(async () => {
    const outcome = await ticketsStore.checkIn(code.trim());
    const now = new Date().toLocaleTimeString("en-MY", { hour: "2-digit", minute: "2-digit" });

    if (outcome.ok) {
      scanState.value = "success";
      result.value = {
        ok: true,
        message: outcome.message || "Check-in successful!",
        eventTitle: outcome.eventTitle || outcome.ticket?.event?.title,
        ticketRef: code,
      };
      recentScans.value = [
        { code, name: outcome.eventTitle || outcome.ticket?.event?.title || "Event", time: now, ok: true },
        ...recentScans.value.slice(0, 9),
      ];
    } else {
      const isDuplicate = outcome.message?.includes("Already");
      scanState.value = isDuplicate ? "duplicate" : "error";
      result.value = {
        ok: false,
        message: outcome.message || "QR code not found.",
        eventTitle: outcome.ticket?.event?.title,
        ticketRef: code,
      };
      recentScans.value = [
        { code, name: outcome.ticket?.event?.title || "Unknown", time: now, ok: false },
        ...recentScans.value.slice(0, 9),
      ];
    }
  }, 900);
}

function simulateScan() {
  const activeTickets = ticketsStore.tickets.filter(
    (t) => t.status === "confirmed" || t.status === "active" || t.status === "waitlisted"
  );
  if (activeTickets.length > 0) {
    const t = activeTickets[Math.floor(Math.random() * activeTickets.length)];
    manualCode.value = t.qrCode;
    doScan(t.qrCode);
  } else {
    doScan("EVORA-UNKNOWN-XYZ-123456");
  }
}

function reset() {
  scanState.value = "idle";
  result.value = null;
  manualCode.value = "";
}

function handleEnter(e) {
  if (e.key === "Enter") doScan(manualCode.value);
}

const cornerPositions = [
  { pos: "tl", style: "top:0;left:0;border-top-width:3px;border-left-width:3px;border-top-left-radius:6px" },
  { pos: "tr", style: "top:0;right:0;border-top-width:3px;border-right-width:3px;border-top-right-radius:6px" },
  { pos: "bl", style: "bottom:0;left:0;border-bottom-width:3px;border-left-width:3px;border-bottom-left-radius:6px" },
  { pos: "br", style: "bottom:0;right:0;border-bottom-width:3px;border-right-width:3px;border-bottom-right-radius:6px" },
];
</script>

<template>
  <div style="min-height:100vh;background:#111111;display:flex;flex-direction:column">

    <!-- Header -->
    <div style="background:#1a1a1a;border-bottom:1px solid #2D2D2D;padding:16px 20px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <span style="font-size:16px;font-weight:700">
          <span style="color:#fff">Event</span><span style="color:#C17070">Ora</span>
        </span>
        <p style="font-size:11px;color:#777777;margin-top:1px">QR Check-In Scanner</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <div :class="{ 'qr-pulse': cameraGranted }" :style="{ width:'8px', height:'8px', borderRadius:'50%', background: cameraGranted ? '#1A7A4A' : '#555555' }"/>
        <span :style="{ fontSize:'12px', color: cameraGranted ? '#1A7A4A' : '#555555' }">{{ cameraGranted ? 'Ready' : 'Camera off' }}</span>
      </div>
    </div>

    <div style="flex:1;display:flex;flex-direction:column;max-width:480px;margin:0 auto;width:100%;padding:24px 20px">

      <!-- Camera permission -->
      <div v-if="!cameraGranted" style="background:#1a1a1a;border-radius:16px;padding:48px 24px;text-align:center;margin-bottom:24px">
        <Camera :size="48" style="color:#555555;margin-bottom:16px;stroke-width:1.5"/>
        <h2 style="font-size:18px;font-weight:700;color:#fff;margin-bottom:8px">Camera permission needed</h2>
        <p style="font-size:14px;color:#777777;margin-bottom:24px;line-height:1.6">
          Allow camera access to scan QR codes at the venue entrance. Required for Capacitor Android build.
        </p>
        <button @click="requestCamera" style="height:48px;padding:0 28px;background:#520000;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;margin:0 auto;font-family:inherit">
          <Camera :size="18"/> Enable Camera
        </button>
      </div>

      <!-- Camera viewfinder -->
      <div v-else style="position:relative;background:#000;border-radius:16px;overflow:hidden;margin-bottom:24px;aspect-ratio:4/3">
        <div style="position:absolute;inset:0;background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 50%,#0a0a0a 100%);display:flex;align-items:center;justify-content:center">
          <div style="text-align:center">
            <div style="position:relative;display:inline-block">
              <div style="width:200px;height:200px;border:2px solid rgba(255,255,255,0.3);border-radius:12px;position:relative;overflow:hidden">
                <!-- Corner marks -->
                <div v-for="c in cornerPositions" :key="c.pos"
                  :style="`position:absolute;width:24px;height:24px;border-style:solid;border-width:0;border-color:${scanState==='success' ? '#1A7A4A' : (scanState==='error'||scanState==='duplicate') ? '#B91C1C' : '#520000'};${c.style}`"/>
                <!-- Scanning line -->
                <div v-if="scanState==='scanning'" class="qr-scanline"/>
                <!-- Result overlay -->
                <div v-if="scanState==='success'||scanState==='error'||scanState==='duplicate'" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6)">
                  <CheckCircle2 v-if="scanState==='success'" :size="56" style="color:#1A7A4A"/>
                  <XCircle v-else :size="56" style="color:#B91C1C"/>
                </div>
              </div>
            </div>
            <p style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:12px">
              {{ scanState==='idle' ? 'Point camera at QR code' : scanState==='scanning' ? 'Scanning…' : scanState==='success' ? '✓ Check-in recorded' : '✗ Invalid QR' }}
            </p>
          </div>
        </div>
        <button v-if="scanState==='idle'" @click="simulateScan"
          style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:rgba(82,0,0,0.85);color:#fff;border:1px solid #7A1010;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);white-space:nowrap;font-family:inherit">
          Simulate QR Scan
        </button>
      </div>

      <!-- Result card -->
      <div v-if="result" :style="{ background: result.ok ? '#0A2A1A' : '#2A0A0A', border:`1px solid ${result.ok ? '#1A7A4A' : '#B91C1C'}`, borderRadius:'12px', padding:'20px', marginBottom:'20px' }">
        <div :style="{ display:'flex', alignItems:'center', gap:'12px', marginBottom: result.ticketRef ? '12px' : '0' }">
          <CheckCircle2 v-if="result.ok" :size="28" style="color:#1A7A4A;flex-shrink:0"/>
          <XCircle v-else :size="28" style="color:#B91C1C;flex-shrink:0"/>
          <div>
            <p :style="{ fontSize:'16px', fontWeight:700, color: result.ok ? '#1A7A4A' : '#B91C1C' }">{{ result.message }}</p>
            <p v-if="result.eventTitle" style="font-size:13px;color:rgba(255,255,255,0.6);margin-top:2px">{{ result.eventTitle }}</p>
          </div>
        </div>
        <p v-if="result.ticketRef" style="font-size:11px;font-family:'JetBrains Mono',monospace;color:rgba(255,255,255,0.4);border-top:1px solid rgba(255,255,255,0.1);padding-top:10px;margin-top:10px;word-break:break-all">
          {{ result.ticketRef }}
        </p>
        <button @click="reset" style="display:flex;align-items:center;gap:6px;margin-top:14px;background:none;border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 14px;font-size:13px;color:rgba(255,255,255,0.7);cursor:pointer;font-family:inherit">
          <RotateCcw :size="14"/> Scan next
        </button>
      </div>

      <!-- Manual entry -->
      <div v-if="cameraGranted" style="background:#1a1a1a;border-radius:12px;padding:20px;margin-bottom:24px">
        <p style="font-size:13px;font-weight:500;color:#999999;margin-bottom:10px">Manual entry</p>
        <div style="display:flex;gap:8px">
          <input v-model="manualCode" placeholder="Paste ticket reference…" @keydown="handleEnter"
            style="flex:1;height:40px;background:#2D2D2D;border:1px solid #3A3A3A;border-radius:6px;padding:0 12px;font-size:13px;color:#fff;outline:none;font-family:'JetBrains Mono',monospace"/>
          <button @click="doScan(manualCode)" :disabled="!manualCode.trim() || scanState==='scanning'"
            :style="{ height:'40px', padding:'0 16px', background:'#520000', color:'#fff', border:'none', borderRadius:'6px', fontSize:'13px', fontWeight:600, cursor: manualCode.trim() ? 'pointer' : 'not-allowed', opacity: manualCode.trim() ? 1 : 0.5, fontFamily:'inherit' }">
            Check In
          </button>
        </div>
      </div>

      <!-- Recent scans -->
      <div v-if="recentScans.length > 0" style="background:#1a1a1a;border-radius:12px;padding:20px">
        <p style="font-size:13px;font-weight:500;color:#999999;margin-bottom:12px">Recent scans</p>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div v-for="(scan,i) in recentScans" :key="i" :style="{ display:'flex', alignItems:'center', gap:'10px', padding:'8px 0', borderBottom: i<recentScans.length-1 ? '1px solid #2D2D2D' : 'none' }">
            <CheckCircle2 v-if="scan.ok" :size="14" style="color:#1A7A4A;flex-shrink:0"/>
            <XCircle v-else :size="14" style="color:#B91C1C;flex-shrink:0"/>
            <div style="flex:1;min-width:0">
              <p style="font-size:13px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ scan.name }}</p>
              <p style="font-size:11px;font-family:'JetBrains Mono',monospace;color:#555555;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ scan.code }}</p>
            </div>
            <span style="font-size:11px;color:#555555;display:flex;align-items:center;gap:3px;flex-shrink:0">
              <Clock :size="10"/> {{ scan.time }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes scan {
  0%, 100% { top: 10%; }
  50% { top: 85%; }
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}
.qr-pulse { animation: pulse 2s infinite; }
.qr-scanline {
  position: absolute;
  left: 0; right: 0;
  height: 2px;
  background: #520000;
  animation: scan 1.2s ease-in-out infinite;
  box-shadow: 0 0 8px #520000;
}
</style>
