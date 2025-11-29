// ------------------------------------------------------------
// 🧱 app.jsx — Versão oficial e otimizada para Inertia + React
// ------------------------------------------------------------

import "../css/app.css";        // estilos globais
import "./bootstrap";           // inicialização global (axios, csrf, nprogress)
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

// Nome do app (meta tag ou .env)
const appName =
  document.querySelector('meta[name="app-name"]')?.content ||
  import.meta.env.VITE_APP_NAME ||
  "TrustGate";

// ------------------------------------------------------------
// 🚀 Inicialização da Aplicação Inertia
// ------------------------------------------------------------
createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),

  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.jsx`,
      import.meta.glob("./Pages/**/*.jsx")
    ),

  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});

// ------------------------------------------------------------
// 🧩 Observação
// ------------------------------------------------------------
// Nada além da inicialização do Inertia deve ser configurado
// aqui. Axios, interceptors e CSRF já estão no bootstrap.js.
// O Echo é importado acima apenas para inicializar o WebSocket.
// ------------------------------------------------------------
