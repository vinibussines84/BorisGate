/* ===========================================================================
   🧱 Bootstrap JS – TrustGate (Inertia + Axios + NProgress)
   Totalmente otimizado, sem duplicações e sem sobrecarga.
=========================================================================== */

import axios from "axios";
import NProgress from "nprogress";
import "nprogress/nprogress.css";

/* ===========================================================================
   🌈 NProgress — barra de carregamento bonita e leve
=========================================================================== */

NProgress.configure({
  showSpinner: false,
  trickleSpeed: 140,
  minimum: 0.08,
});

// Evita flicker → só mostra se demorar
let inertiaTimeout = null;

document.addEventListener("inertia:start", () => {
  if (inertiaTimeout) clearTimeout(inertiaTimeout);
  inertiaTimeout = setTimeout(() => NProgress.start(), 120);
});

document.addEventListener("inertia:finish", (event) => {
  if (inertiaTimeout) {
    clearTimeout(inertiaTimeout);
    inertiaTimeout = null;
  }

  const v = event.detail?.visit;
  if (v?.completed || v?.cancelled) {
    NProgress.done();
  }
});

/* ===========================================================================
   🌐 Axios Configuração Global
=========================================================================== */

window.axios = axios;

axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
axios.defaults.withCredentials = true;

// Puxa CSRF automaticamente do <head>
const csrfToken = document
  .querySelector('meta[name="csrf-token"]')
  ?.getAttribute("content");

if (csrfToken) {
  axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken;
} else {
  console.warn("⚠️ CSRF token não encontrado no meta[name='csrf-token'].");
}

/* ===========================================================================
   🧩 Sistema Global de Pollers (para auto-refresh)
=========================================================================== */

window.__POLLERS__ = [];
window.registerPoller = (id) => (window.__POLLERS__.push(id), id);
window.stopAllPollers = () => {
  window.__POLLERS__.forEach(clearInterval);
  window.__POLLERS__ = [];
};

/* ===========================================================================
   🔐 Controle de Sessão / Expiração / Rate-Limit
=========================================================================== */

let redirectedOnce = false;
let activeRequests = 0; // barra global de loading para Axios

/* -----------------------------
   Requisição ⟶ inicia NProgress
------------------------------ */
axios.interceptors.request.use((config) => {
  activeRequests++;
  if (activeRequests === 1) {
    NProgress.start();
  }

  // Garantir que CSRF esteja sempre definido
  const token = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");

  if (token) {
    config.headers["X-CSRF-TOKEN"] = token;
  }

  return config;
});

/* -----------------------------
   Resposta ⟶ finaliza NProgress
------------------------------ */
axios.interceptors.response.use(
  (response) => {
    activeRequests = Math.max(activeRequests - 1, 0);
    if (activeRequests === 0) NProgress.done();
    return response;
  },

  async (error) => {
    activeRequests = Math.max(activeRequests - 1, 0);
    if (activeRequests === 0) NProgress.done();

    const res = error?.response;
    const status = res?.status;
    const headers = res?.headers || {};
    const data = res?.data || {};

    /* =====================================================
       1) Sessão expirada (401 + x-auth-expired)
    ===================================================== */
    if (status === 401 && headers["x-auth-expired"] === "1") {
      window.stopAllPollers();

      if (!redirectedOnce) {
        redirectedOnce = true;
        const redirectTo = data?.redirect || "/login";
        console.warn("🔒 Sessão expirada. Redirecionando para", redirectTo);
        window.location.assign(redirectTo);
      }
    }

    /* =====================================================
       2) Token CSRF expirou (419)
    ===================================================== */
    if (status === 419) {
      console.warn("⚠️ CSRF expirou. Tentando renovar cookie...");

      try {
        await axios.get("/sanctum/csrf-cookie");
        console.info("✅ CSRF renovado. Reenvie a requisição.");
      } catch (err) {
        console.error("❌ Falha ao renovar CSRF:", err);
      }
    }

    /* =====================================================
       3) Limite de requisições (429)
    ===================================================== */
    if (status === 429) {
      window.stopAllPollers();

      const retry = parseInt(headers["retry-after"] || "15", 10);
      console.warn(`⏳ Muitas requisições. Aguarde ~${retry}s.`);
    }

    return Promise.reject(error);
  }
);

/* ===========================================================================
   🚀 Bootstrap Finalizado
=========================================================================== */

console.log("🌐 bootstrap.js carregado com sucesso (Axios + CSRF + NProgress otimizado).");
