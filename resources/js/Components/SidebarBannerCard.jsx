// resources/js/Components/SidebarBannerCard.jsx
import React, { useMemo } from "react";

/**
 * Card de banner lateral (1 imagem de /public/images) com overlay gradiente e CTA de WhatsApp.
 * Controle de enquadramento: use focusX / focusY (0..100) para ajustar o object-position.
 */
export default function SidebarBannerCard({
  src,                                   // se vazio, usa defaultSrc
  alt = "Banner",
  title = "Suporte",
  phone = "+55",
  message = "Olá! Preciso de ajuda com minha conta.",
  minHeight = 150,
  rounded = "rounded-2xl",
  defaultSrc = "/images/portrait-smiling-woman-making-notes.jpg",
  aspect = "16 / 6",
  // Overlay
  darken = 0.35,                         // 0..1 camada preta extra
  gradientFrom = "rgba(0,0,0,0.70)",
  gradientVia  = "rgba(0,0,0,0.40)",
  gradientTo   = "rgba(16,185,129,0.22)", // verde translúcido
  // Enquadramento da imagem (0..100). ↑ aumente focusY para “descer” a imagem.
  focusX = 50,
  focusY = 58,
}) {
  const finalSrc = src || defaultSrc;

  const wa = useMemo(() => {
    const digits = (phone || "").replace(/[^\d]/g, ""); // limpa para wa.me
    const txt = encodeURIComponent(message || "");
    return `https://wa.me/${digits}${txt ? `?text=${txt}` : ""}`;
  }, [phone, message]);

  const veil = `rgba(0,0,0,${Math.min(Math.max(darken, 0), 1)})`;

  return (
    <section
      className={[
        rounded,
        "border border-zinc-800 bg-[#0E0E0E]/80 backdrop-blur",
        "shadow-[0_8px_30px_-20px_rgba(0,0,0,0.6)]",
        "overflow-hidden relative",
      ].join(" ")}
      style={{ minHeight }}
      aria-label="Banner lateral"
    >
      {/* Imagem */}
      <div className="relative w-full" style={{ aspectRatio: aspect }}>
        <img
          src={finalSrc}
          alt={alt}
          className="absolute inset-0 h-full w-full object-cover"
          style={{ objectPosition: `${focusX}% ${focusY}%` }}   // 👈 controla "alto/baixo"
          loading="lazy"
          decoding="async"
        />

        {/* Overlay gradiente preto -> verde + escurecimento extra */}
        <div
          aria-hidden
          className="absolute inset-0"
          style={{
            background: `
              linear-gradient(90deg, ${gradientFrom} 0%, ${gradientVia} 45%, ${gradientTo} 100%),
              ${veil}
            `,
          }}
        />

        {/* Conteúdo sobre a imagem */}
        <div className="absolute inset-0 flex items-end">
          <div className="p-4 sm:p-5 w-full">
            <div className="flex flex-col gap-1.5">
              <h3 className="text-sm sm:text-base font-semibold text-white/95 drop-shadow">
                {title}
              </h3>

              {/* Linha com número clicável */}
              <div className="flex items-center justify-between gap-2">
                <p className="text-xs sm:text-sm text-white/80">
                  Atendimento via WhatsApp
                </p>
                <a
                  href={wa}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={[
                    "inline-flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-medium",
                    "text-emerald-100 bg-emerald-500/15 border border-emerald-500/30",
                    "hover:bg-emerald-500/20 hover:border-emerald-500/40",
                    "transition focus:outline-none focus:ring focus:ring-emerald-600/30",
                    "shadow-[0_8px_20px_-12px_rgba(16,185,129,0.35)]",
                  ].join(" ")}
                  aria-label={`Chamar no WhatsApp ${phone}`}
                  title={`Chamar no WhatsApp ${phone}`}
                >
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30" />
                  <span className="whitespace-nowrap">{phone}</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        {/* Borda interna sutil */}
        <div className="pointer-events-none absolute inset-0 ring-1 ring-white/10 rounded-[inherit]" />
      </div>
    </section>
  );
}

