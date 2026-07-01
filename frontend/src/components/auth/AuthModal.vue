<script setup>
import { ref, watch, onMounted, onUnmounted, isRef } from "vue";
import {
  X, Eye, EyeOff, Mail, Lock, User, AlertCircle, CheckCircle2, ChevronRight, KeyRound,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  open: { type: Boolean, required: true },
  defaultTab: { type: String, default: "login" },
});

const emit = defineEmits(["close", "success"]);
const auth = useAuthStore();

// ── State ─────────────────────────────────────────────────────────────────────
// tabs: login | register | forgot
const tab = ref(props.defaultTab);
const showPw = ref(false);
const showConfirm = ref(false);
const submitting = ref(false);
// submitted: false | "login" | "register-verify" | "forgot"
const submitted = ref(false);
const generalError = ref("");

// Login fields
const loginEmail = ref({ value: "", error: "", touched: false });
const loginPw = ref({ value: "", error: "", touched: false });

// Register fields
const regName = ref({ value: "", error: "", touched: false });
const regMatric = ref({ value: "", error: "", touched: false });
const regEmail = ref({ value: "", error: "", touched: false });
const regPw = ref({ value: "", error: "", touched: false });
const regConfirm = ref({ value: "", error: "", touched: false });

// Forgot password fields
const forgotEmail = ref({ value: "", error: "", touched: false });

// Reset when modal opens/tab changes
watch(() => props.open, (open) => {
  if (open) {
    tab.value = props.defaultTab;
    submitted.value = false;
    showPw.value = false;
    showConfirm.value = false;
    generalError.value = "";
    document.body.style.overflow = "hidden";

    // Initialize Google Identity Services
    setTimeout(() => {
      if (window.google) {
        window.google.accounts.id.initialize({
          client_id: import.meta.env.VITE_GOOGLE_CLIENT_ID || "1016843513361-9k9h9g85b7g4112e434l18g9334o1p9c.apps.googleusercontent.com",
          callback: onGoogleResponse
        });
        
        window.google.accounts.id.renderButton(
          document.getElementById("google-hidden-btn-container"),
          { theme: "outline", size: "large" }
        );
      }
    }, 200);
  } else {
    document.body.style.overflow = "";
  }
});
watch(() => props.defaultTab, (dt) => {
  if (props.open) {
    tab.value = dt;
  }
});

// Escape key to close
function handleKey(e) {
  if (e.key === "Escape" && props.open) emit("close");
}
onMounted(() => document.addEventListener("keydown", handleKey));
onUnmounted(() => {
  document.removeEventListener("keydown", handleKey);
  document.body.style.overflow = "";
});

// ── Validation helpers ────────────────────────────────────────────────────────
function validateEmail(v) {
  if (!v) return "Email is required";
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return "Enter a valid email";
  return "";
}
function validatePassword(v) {
  if (!v) return "Password is required";
  if (v.length < 8) return "At least 8 characters";
  return "";
}

// ── Field helpers ─────────────────────────────────────────────────────────────
function getFieldObject(field) {
  return isRef(field) ? field.value : field;
}
function setVal(field, v) {
  const obj = getFieldObject(field);
  obj.value = v;
  obj.error = "";
}
function touch(field) {
  getFieldObject(field).touched = true;
}
function setErr(field, e) {
  getFieldObject(field).error = e;
}


async function onGoogleResponse(response) {
  submitting.value = true;
  generalError.value = "";
  try {
    await auth.loginWithGoogle(response.credential);
    submitting.value = false;
    submitted.value = "login";
    setTimeout(() => { emit("success", auth.user); emit("close"); }, 700);
  } catch (err) {
    submitting.value = false;
    generalError.value = err.response?.data?.error || err.message || "Google login failed";
  }
}

function handleGoogleLogin() {
  if (window.google) {
    window.google.accounts.id.prompt((notification) => {
      if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
        const btn = document.querySelector("#google-hidden-btn-container [role=button]");
        if (btn) {
          btn.click();
        } else {
          window.google.accounts.id.renderButton(
            document.getElementById("google-hidden-btn-container"),
            { theme: "outline", size: "large" }
          );
          setTimeout(() => {
            const b = document.querySelector("#google-hidden-btn-container [role=button]");
            if (b) b.click();
          }, 150);
        }
      }
    });
  } else {
    generalError.value = "Google identity services not loaded yet.";
  }
}

// ── Login submit ──────────────────────────────────────────────────────────────
async function handleLogin(e) {
  e.preventDefault();
  touch(loginEmail); touch(loginPw);
  const emailErr = validateEmail(loginEmail.value.value);
  const pwErr = validatePassword(loginPw.value.value);
  if (emailErr) setErr(loginEmail, emailErr);
  if (pwErr) setErr(loginPw, pwErr);
  if (emailErr || pwErr) return;

  submitting.value = true;
  generalError.value = "";
  try {
    await auth.login(loginEmail.value.value, loginPw.value.value);
    submitting.value = false;
    submitted.value = "login";
    setTimeout(() => { emit("success", auth.user); emit("close"); }, 800);
  } catch (err) {
    submitting.value = false;
    // Special case: unverified account
    if (err.response?.data?.unverified) {
      generalError.value = err.response.data.error;
    } else {
      generalError.value = err.response?.data?.error || err.message || "Invalid email or password";
    }
  }
}

// ── Register submit ───────────────────────────────────────────────────────────
async function handleRegister(e) {
  e.preventDefault();
  touch(regName); touch(regMatric); touch(regEmail); touch(regPw); touch(regConfirm);

  const nameErr = !regName.value.value ? "Full name is required" : "";
  const raw = regMatric.value.value.toUpperCase();
  const matricErr = !raw ? "Matric number is required" : !/^A\d{2}[A-Z]{2}\d{4}$/.test(raw) ? "Format: A24AB1234" : "";
  const emailErr = validateEmail(regEmail.value.value);
  const pwErr = validatePassword(regPw.value.value);
  const confirmErr = !regConfirm.value.value ? "Please confirm your password" : regConfirm.value.value !== regPw.value.value ? "Passwords do not match" : "";

  if (nameErr) setErr(regName, nameErr);
  if (matricErr) setErr(regMatric, matricErr);
  if (emailErr) setErr(regEmail, emailErr);
  if (pwErr) setErr(regPw, pwErr);
  if (confirmErr) setErr(regConfirm, confirmErr);
  if (nameErr || matricErr || emailErr || pwErr || confirmErr) return;

  submitting.value = true;
  generalError.value = "";
  try {
    const msg = await auth.register(
      regName.value.value,
      regEmail.value.value,
      regPw.value.value,
      raw
    );
    submitting.value = false;
    if (msg === null) {
      // Demo/fallback mode — guest session was created
      submitted.value = "login";
      setTimeout(() => { emit("success", auth.user); emit("close"); }, 800);
    } else {
      // Real backend: show email verification notice
      submitted.value = "register-verify";
    }
  } catch (err) {
    submitting.value = false;
    generalError.value = err.response?.data?.error || err.message || "Registration failed";
  }
}

// ── Forgot password submit ────────────────────────────────────────────────────
async function handleForgot(e) {
  e.preventDefault();
  touch(forgotEmail);
  const emailErr = validateEmail(forgotEmail.value.value);
  if (emailErr) { setErr(forgotEmail, emailErr); return; }

  submitting.value = true;
  generalError.value = "";
  try {
    await auth.forgotPassword(forgotEmail.value.value);
    submitting.value = false;
    submitted.value = "forgot";
  } catch (err) {
    submitting.value = false;
    generalError.value = err.response?.data?.error || err.message || "Something went wrong";
  }
}

// ── Password strength ─────────────────────────────────────────────────────────
function pwChecks(pw) {
  return [
    { label: "At least 8 characters", ok: pw.length >= 8 },
    { label: "Uppercase letter", ok: /[A-Z]/.test(pw) },
    { label: "Number or symbol", ok: /[0-9!@#$%^&*]/.test(pw) },
  ];
}
function pwScore(pw) { return pwChecks(pw).filter((c) => c.ok).length; }
const pwColors = ["#E5E5E5", "#B91C1C", "#B45309", "#1A7A4A"];
const pwLabels = ["", "Weak", "Fair", "Strong"];
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      @click.self="emit('close')"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 16px"
    >
      <div class="modal-no-scrollbar" style="background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; width: 100%; max-width: 440px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 24px 48px rgba(0,0,0,0.18)">

        <!-- Header -->
        <div style="padding: 24px 24px 0; display: flex; justify-content: space-between; align-items: flex-start">
          <div>
            <span style="font-size: 18px; font-weight: 700">
              <span style="color: var(--text-primary)">Event</span>
              <span style="color: var(--maroon)">Ora</span>
            </span>
            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 2px">UTM Campus Events</p>
          </div>
          <button @click="emit('close')" class="auth-close-btn">
            <X :size="18" />
          </button>
        </div>

        <!-- Tabs -->
        <div style="padding: 20px 24px 0; display: flex; border-bottom: 1px solid var(--border-color)">
          <button
            v-for="t in ['login', 'register']"
            :key="t"
            @click="tab = t; submitted = false; generalError = ''"
            :style="{
              background: 'none', border: 'none', cursor: 'pointer',
              fontSize: '15px', fontWeight: tab === t ? 600 : 400,
              color: tab === t ? 'var(--maroon)' : 'var(--text-secondary)',
              paddingBottom: '12px', paddingRight: '20px',
              borderBottom: `2px solid ${tab === t ? 'var(--maroon)' : 'transparent'}`,
              marginBottom: '-1px', transition: 'color 150ms, border-color 150ms',
              display: tab === 'forgot' ? 'none' : 'block',
            }"
          >
            {{ t === "login" ? "Log in" : "Create account" }}
          </button>
          <span
            v-if="tab === 'forgot'"
            style="font-size: 15px; font-weight: 600; color: var(--maroon); padding-bottom: 12px; border-bottom: 2px solid var(--maroon); margin-bottom: -1px"
          >
            Forgot password
          </span>
        </div>

        <!-- ── SUCCESS: Login ── -->
        <div v-if="submitted === 'login'" style="padding: 48px 24px; text-align: center">
          <div style="width: 64px; height: 64px; border-radius: 50%; background: #D1FAE5; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px">
            <CheckCircle2 :size="32" style="color: #1A7A4A" />
          </div>
          <h2 style="font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px">Welcome back!</h2>
          <p style="font-size: 14px; color: #555555">Signing you in…</p>
        </div>

        <!-- ── SUCCESS: Registration — email verification sent ── -->
        <div v-else-if="submitted === 'register-verify'" style="padding: 40px 24px; text-align: center">
          <div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #ede9fe, #ddd6fe); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px">
            <Mail :size="32" style="color: #7c3aed" />
          </div>
          <h2 style="font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 10px">Check your email! 📧</h2>
          <p style="font-size: 14px; color: #555555; line-height: 1.7; margin-bottom: 8px">
            We've sent a verification link to
          </p>
          <p style="font-size: 14px; font-weight: 600; color: #520000; margin-bottom: 16px">{{ regEmail.value }}</p>
          <p style="font-size: 13px; color: #888; line-height: 1.6; margin-bottom: 24px">
            Click the link in the email to activate your account. The link expires in <strong>24 hours</strong>.
          </p>
          <div style="background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #555; text-align: left">
            💡 <strong>Didn't receive it?</strong> Check your spam folder or
            <button
              type="button"
              class="auth-link"
              style="font-size: 12px"
              @click="handleRegister({ preventDefault: () => {} })"
            >
              click here to resend
            </button>.
          </div>
          <button type="button" @click="emit('close')" class="auth-submit-btn" style="margin-top: 24px">
            Done
          </button>
        </div>

        <!-- ── SUCCESS: Forgot password sent ── -->
        <div v-else-if="submitted === 'forgot'" style="padding: 40px 24px; text-align: center">
          <div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #fee2e2, #fecaca); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px">
            <Mail :size="32" style="color: #dc2626" />
          </div>
          <h2 style="font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 10px">Reset link sent! 🔐</h2>
          <p style="font-size: 14px; color: #555555; line-height: 1.7; margin-bottom: 8px">
            If <strong style="color: #520000">{{ forgotEmail.value }}</strong> is registered and verified, we've sent a password reset link.
          </p>
          <p style="font-size: 13px; color: #888; line-height: 1.6; margin-bottom: 24px">
            The link expires in <strong>1 hour</strong>. Check your spam folder if you don't see it.
          </p>
          <button type="button" @click="tab = 'login'; submitted = false; generalError = ''" class="auth-submit-btn">
            Back to log in
          </button>
        </div>

        <!-- ── LOGIN FORM ── -->
        <form v-else-if="tab === 'login'" @submit="handleLogin" style="padding: 24px" novalidate>

          <!-- General Error Alert -->
          <div v-if="generalError" style="background: #FFF5F5; border: 1px solid #B91C1C; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #B91C1C; font-size: 13px; display: flex; align-items: center; gap: 8px">
            <AlertCircle :size="16" />
            <span>{{ generalError }}</span>
          </div>

          <!-- Email -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px">Email address</label>
            <AuthInputWrap :error="loginEmail.error" :touched="loginEmail.touched">
              <Mail :size="16" class="auth-input-icon" :class="{ 'auth-input-icon--error': loginEmail.touched && loginEmail.error }" />
              <input
                type="email" placeholder="you@graduate.utm.my"
                :value="loginEmail.value"
                @input="setVal(loginEmail, $event.target.value)"
                @blur="touch(loginEmail); if (loginEmail.value) setErr(loginEmail, validateEmail(loginEmail.value))"
                class="auth-input"
              />
            </AuthInputWrap>
            <p v-if="loginEmail.touched && loginEmail.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ loginEmail.error }}
            </p>
          </div>

          <!-- Password -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #1a1a1a; margin-bottom: 6px">Password</label>
            <AuthInputWrap :error="loginPw.error" :touched="loginPw.touched">
              <Lock :size="16" class="auth-input-icon" :class="{ 'auth-input-icon--error': loginPw.touched && loginPw.error }" />
              <input
                :type="showPw ? 'text' : 'password'" placeholder="Enter your password"
                :value="loginPw.value"
                @input="setVal(loginPw, $event.target.value)"
                @blur="touch(loginPw); if (loginPw.value) setErr(loginPw, validatePassword(loginPw.value))"
                class="auth-input"
                style="padding-right: 44px"
              />
              <button type="button" @click="showPw = !showPw" class="auth-pw-toggle">
                <component :is="showPw ? EyeOff : Eye" :size="16" />
              </button>
            </AuthInputWrap>
            <p v-if="loginPw.touched && loginPw.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ loginPw.error }}
            </p>
          </div>

          <div style="text-align: right; margin-top: -8px; margin-bottom: 20px">
            <button
              type="button"
              class="auth-link"
              @click="tab = 'forgot'; submitted = false; generalError = ''; forgotEmail.value = loginEmail.value; forgotEmail.error = ''; forgotEmail.touched = false"
            >
              Forgot password?
            </button>
          </div>

          <button type="submit" :disabled="submitting" class="auth-submit-btn" :class="{ 'auth-submit-btn--loading': submitting }">
            <span v-if="submitting" class="auth-spinner" /> {{ submitting ? "Signing in…" : "" }}
            <template v-if="!submitting">Log in <ChevronRight :size="16" /></template>
          </button>

          <p style="text-align: center; font-size: 13px; color: #555555; margin-top: 20px">
            Don't have an account?
            <button type="button" @click="tab = 'register'" class="auth-link auth-link--bold">Sign up free</button>
          </p>

          <div style="margin: 20px 0; display: flex; align-items: center; gap: 12px">
            <div style="flex: 1; height: 1px; background: var(--border-color)" />
            <span style="font-size: 12px; color: #AAAAAA">or continue with</span>
            <div style="flex: 1; height: 1px; background: var(--border-color)" />
          </div>

          <button type="button" @click="handleGoogleLogin" class="auth-google-btn">
            <svg width="18" height="18" viewBox="0 0 18 18">
              <path d="M16.51 8H8.98v3h4.3c-.18 1-.74 1.48-1.6 2.04v2.01h2.6a7.8 7.8 0 0 0 2.38-5.88c0-.57-.05-.66-.15-1.18z" fill="#4285F4"/>
              <path d="M8.98 17c2.16 0 3.97-.72 5.3-1.94l-2.6-2.01a4.8 4.8 0 0 1-7.18-2.54H1.83v2.07A8 8 0 0 0 8.98 17z" fill="#34A853"/>
              <path d="M4.5 10.52a4.8 4.8 0 0 1 0-3.04V5.41H1.83a8 8 0 0 0 0 7.18z" fill="#FBBC05"/>
              <path d="M8.98 4.18c1.17 0 2.23.4 3.06 1.2l2.3-2.3A8 8 0 0 0 1.83 5.4L4.5 7.49a4.77 4.77 0 0 1 4.48-3.3z" fill="#EA4335"/>
            </svg>
            Continue with Google
          </button>
        </form>

        <!-- ── REGISTER FORM ── -->
        <form v-else-if="tab === 'register'" @submit="handleRegister" style="padding: 24px" novalidate>
          
          <!-- General Error Alert -->
          <div v-if="generalError" style="background: #FFF5F5; border: 1px solid #B91C1C; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #B91C1C; font-size: 13px; display: flex; align-items: center; gap: 8px">
            <AlertCircle :size="16" />
            <span>{{ generalError }}</span>
          </div>

          <p style="font-size: 15px; color: #1a1a1a; margin-bottom: 24px; font-weight: 500">
            Create your UTM student account to register for events.
          </p>

          <!-- Full name -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #1a1a1a; margin-bottom: 6px">Full name</label>
            <AuthInputWrap :error="regName.error" :touched="regName.touched">
              <User :size="16" class="auth-input-icon" />
              <input
                type="text" placeholder="Ahmad Syafiq bin Abdullah"
                :value="regName.value"
                @input="setVal(regName, $event.target.value)"
                @blur="touch(regName); if (!regName.value) setErr(regName, 'Full name is required')"
                class="auth-input"
              />
            </AuthInputWrap>
            <p v-if="regName.touched && regName.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ regName.error }}
            </p>
          </div>

          <!-- Matric number -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px">Matric number</label>
            <div
              :style="{
                display: 'flex', alignItems: 'center',
                border: `1px solid ${regMatric.touched && regMatric.error ? '#B91C1C' : 'var(--border-color)'}`,
                borderRadius: '6px', background: 'var(--bg-card)', overflow: 'hidden',
              }"
            >
              <span style="padding: 0 12px; height: 44px; display: flex; align-items: center; background: var(--bg-pill); border-right: 1px solid var(--border-color); font-size: 13px; color: var(--text-secondary); white-space: nowrap; font-family: 'JetBrains Mono', monospace">
                UTM
              </span>
              <input
                type="text" placeholder="A24AB1234"
                :value="regMatric.value"
                @input="setVal(regMatric, $event.target.value.toUpperCase())"
                @blur="touch(regMatric); if (!regMatric.value) setErr(regMatric, 'Matric number is required'); else if (!/^A\d{2}[A-Z]{2}\d{4}$/.test(regMatric.value)) setErr(regMatric, 'Format: A24AB1234')"
                style="flex: 1; height: 44px; padding: 0 12px; background: transparent; border: none; outline: none; font-size: 14px; font-family: 'JetBrains Mono', monospace; letter-spacing: 0.05em; color: var(--text-primary)"
              />
            </div>
            <p v-if="regMatric.touched && regMatric.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ regMatric.error }}
            </p>
            <p style="font-size: 11px; color: #AAAAAA; margin-top: 4px">Found on your UTM student card</p>
          </div>

          <!-- Email -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px">Email address</label>
            <AuthInputWrap :error="regEmail.error" :touched="regEmail.touched">
              <Mail :size="16" class="auth-input-icon" />
              <input
                type="email" placeholder="you@graduate.utm.my"
                :value="regEmail.value"
                @input="setVal(regEmail, $event.target.value)"
                @blur="touch(regEmail); if (regEmail.value) setErr(regEmail, validateEmail(regEmail.value))"
                class="auth-input"
              />
            </AuthInputWrap>
            <p v-if="regEmail.touched && regEmail.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ regEmail.error }}
            </p>
          </div>

          <!-- Password -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px">Password</label>
            <AuthInputWrap :error="regPw.error" :touched="regPw.touched">
              <Lock :size="16" class="auth-input-icon" />
              <input
                :type="showPw ? 'text' : 'password'" placeholder="Create a strong password"
                :value="regPw.value"
                @input="setVal(regPw, $event.target.value)"
                @blur="touch(regPw); if (regPw.value) setErr(regPw, validatePassword(regPw.value))"
                class="auth-input"
                style="padding-right: 44px"
              />
              <button type="button" @click="showPw = !showPw" class="auth-pw-toggle">
                <component :is="showPw ? EyeOff : Eye" :size="16" />
              </button>
            </AuthInputWrap>
            <p v-if="regPw.touched && regPw.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ regPw.error }}
            </p>
          </div>

          <!-- Password strength indicator -->
          <div v-if="regPw.value" style="margin-bottom: 16px">
            <div style="display: flex; gap: 4px; margin-bottom: 6px">
              <div
                v-for="i in 3" :key="i"
                :style="{
                  flex: 1, height: '3px', borderRadius: '2px',
                  background: (i - 1) < pwScore(regPw.value) ? pwColors[pwScore(regPw.value)] : 'var(--border-color)',
                  transition: 'background 200ms',
                }"
              />
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start">
              <div style="display: flex; flex-direction: column; gap: 2px">
                <span
                  v-for="c in pwChecks(regPw.value)" :key="c.label"
                  :style="{ fontSize: '11px', color: c.ok ? '#1A7A4A' : '#AAAAAA', display: 'flex', alignItems: 'center', gap: '4px' }"
                >
                  <CheckCircle2 :size="10" :style="{ opacity: c.ok ? 1 : 0.3 }" /> {{ c.label }}
                </span>
              </div>
              <span :style="{ fontSize: '11px', fontWeight: 600, color: pwColors[pwScore(regPw.value)] }">
                {{ pwLabels[pwScore(regPw.value)] }}
              </span>
            </div>
          </div>

          <!-- Confirm password -->
          <div style="margin-bottom: 16px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 6px">Confirm password</label>
            <AuthInputWrap :error="regConfirm.error" :touched="regConfirm.touched">
              <Lock :size="16" class="auth-input-icon" />
              <input
                :type="showConfirm ? 'text' : 'password'" placeholder="Repeat your password"
                :value="regConfirm.value"
                @input="setVal(regConfirm, $event.target.value)"
                @blur="touch(regConfirm); if (regConfirm.value && regConfirm.value !== regPw.value) setErr(regConfirm, 'Passwords do not match')"
                class="auth-input"
                style="padding-right: 44px"
              />
              <button type="button" @click="showConfirm = !showConfirm" class="auth-pw-toggle">
                <component :is="showConfirm ? EyeOff : Eye" :size="16" />
              </button>
            </AuthInputWrap>
            <p v-if="regConfirm.touched && regConfirm.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ regConfirm.error }}
            </p>
          </div>

          <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.6">
            By creating an account you agree to EventOra's
            <button type="button" class="auth-link">Terms of Service</button>
            and
            <button type="button" class="auth-link">Privacy Policy</button>.
          </p>

          <button type="submit" :disabled="submitting" class="auth-submit-btn" :class="{ 'auth-submit-btn--loading': submitting }">
            <span v-if="submitting" class="auth-spinner" />
            {{ submitting ? "Creating account…" : "" }}
            <template v-if="!submitting">Create account <ChevronRight :size="16" /></template>
          </button>

          <p style="text-align: center; font-size: 13px; color: #555555; margin-top: 20px">
            Already have an account?
            <button type="button" @click="tab = 'login'" class="auth-link auth-link--bold">Log in</button>
          </p>
        </form>

        <!-- ── FORGOT PASSWORD FORM ── -->
        <form v-else-if="tab === 'forgot'" @submit="handleForgot" style="padding: 24px" novalidate>

          <!-- General Error Alert -->
          <div v-if="generalError" style="background: #FFF5F5; border: 1px solid #B91C1C; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #B91C1C; font-size: 13px; display: flex; align-items: center; gap: 8px">
            <AlertCircle :size="16" />
            <span>{{ generalError }}</span>
          </div>

          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, #fee2e2, #fecaca); display: flex; align-items: center; justify-content: center; flex-shrink: 0">
              <KeyRound :size="20" style="color: #dc2626" />
            </div>
            <div>
              <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #1a1a1a">Reset your password</h3>
              <p style="margin: 2px 0 0; font-size: 13px; color: #555">Enter your UTM email and we'll send a reset link.</p>
            </div>
          </div>

          <!-- Email -->
          <div style="margin-bottom: 20px">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #1a1a1a; margin-bottom: 6px">Email address</label>
            <AuthInputWrap :error="forgotEmail.error" :touched="forgotEmail.touched">
              <Mail :size="16" class="auth-input-icon" :class="{ 'auth-input-icon--error': forgotEmail.touched && forgotEmail.error }" />
              <input
                type="email" placeholder="you@graduate.utm.my"
                :value="forgotEmail.value"
                @input="setVal(forgotEmail, $event.target.value)"
                @blur="touch(forgotEmail); if (forgotEmail.value) setErr(forgotEmail, validateEmail(forgotEmail.value))"
                class="auth-input"
              />
            </AuthInputWrap>
            <p v-if="forgotEmail.touched && forgotEmail.error" class="auth-field-error">
              <AlertCircle :size="12" /> {{ forgotEmail.error }}
            </p>
          </div>

          <button type="submit" :disabled="submitting" class="auth-submit-btn" :class="{ 'auth-submit-btn--loading': submitting }">
            <span v-if="submitting" class="auth-spinner" /> {{ submitting ? "Sending…" : "" }}
            <template v-if="!submitting">Send reset link <ChevronRight :size="16" /></template>
          </button>

          <p style="text-align: center; font-size: 13px; color: #555555; margin-top: 20px">
            Remembered your password?
            <button type="button" @click="tab = 'login'; submitted = false; generalError = ''" class="auth-link auth-link--bold">Back to log in</button>
          </p>
        </form>

        <!-- Google hidden sign-in iframe trigger container -->
        <div id="google-hidden-btn-container" style="display: none !important;"></div>
      </div>
    </div>
  </Teleport>
</template>

<!-- AuthInputWrap is used inline as a layout wrapper in the template above. -->
<script>
import { defineComponent, h } from "vue";

const AuthInputWrap = defineComponent({
  name: "AuthInputWrap",
  props: {
    error: String,
    touched: Boolean,
  },
  setup(props, { slots }) {
    return () => {
      const hasError = props.touched && props.error;
      return h(
        "div",
        {
          style: {
            position: "relative",
            display: "flex",
            alignItems: "center",
            border: `1px solid ${hasError ? "#B91C1C" : "var(--border-color)"}`,
            borderRadius: "6px",
            background: hasError ? "var(--maroon-light)" : "var(--bg-card)",
            boxShadow: hasError ? "0 0 0 3px rgba(185,28,28,0.08)" : "none",
            transition: "all 150ms ease",
          },
        },
        slots.default?.()
      );
    };
  },
});

export default { components: { AuthInputWrap } };
</script>

<style scoped>
.auth-close-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  transition: background 150ms;
}
.auth-close-btn:hover { background: var(--bg-hover); }

.auth-input {
  flex: 1;
  height: 44px;
  padding-left: 40px;
  padding-right: 12px;
  background: transparent;
  border: none;
  outline: none;
  font-size: 14px;
  color: var(--text-primary);
}
.auth-input-icon {
  position: absolute;
  left: 12px;
  color: var(--text-secondary);
}
.auth-input-icon--error { color: #b91c1c; }

.auth-pw-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-secondary);
  display: flex;
  padding: 0;
}

.auth-field-error {
  font-size: 12px;
  color: #b91c1c;
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.auth-link {
  background: none;
  border: none;
  font-size: 13px;
  color: var(--maroon);
  cursor: pointer;
  font-weight: 500;
  padding: 0;
}
.auth-link--bold { font-weight: 600; }

.auth-submit-btn {
  width: 100%;
  height: 44px;
  background: var(--maroon);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 150ms;
  font-family: inherit;
}
.auth-submit-btn:hover:not(:disabled) { background: var(--maroon-hover); }
.auth-submit-btn--loading { background: var(--maroon-hover); cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.auth-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  display: inline-block;
  animation: spin 0.7s linear infinite;
}

.auth-google-btn {
  width: 100%;
  height: 44px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-card);
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: border-color 150ms, background 150ms;
  font-family: inherit;
}
.auth-google-btn:hover { background: var(--bg-hover); }

/* Hide scrollbars for clean aesthetics */
.modal-no-scrollbar::-webkit-scrollbar {
  display: none;
}
.modal-no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
