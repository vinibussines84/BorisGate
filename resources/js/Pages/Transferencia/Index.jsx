// resources/js/Pages/Pix/Transferencia.jsx
import React, { useState } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Send,
  Wallet,
  QrCode,
  Copy,
  KeyRound,
  Gauge,
  Link as LinkIcon,
  ArrowRight,
  MessageSquare,
  DollarSign,
  User,
  Repeat,
  ArrowLeft,
} from "lucide-react";
import { motion } from "framer-motion";
import ScanQrCodeScreen from "@/Components/ScanQrCodeScreen";

/* ===========================
   UI helpers
=========================== */
const SectionHeadline = ({ title, accent = "emerald" }) => (
  <h2
    className={`text-2xl font-light text-white pl-4 border-l-2 ${
      accent === "emerald" ? "border-emerald-400/60" : "border-white/50"
    }`}
  >
    {title}
  </h2>
);

const Glow = ({ className = "" }) => (
  <div className={`pointer-events-none absolute rounded-full blur-3xl ${className}`} />
);

const Card = ({ children, className = "" }) => (
  <div
    className={`rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-md
                shadow-[0_10px_35px_-18px_rgba(0,0,0,0.6)] hover:bg-white/[0.06] transition-colors ${className}`}
  >
    {children}
  </div>
);

/* ===========================
   Receive Screen
=========================== */
const ReceiveScreen = ({ onBack }) => {
  const goToQrPage = () => (window.location.href = "/pix/qrcode");

  const options = [
    {
      label: "Gerar QR Code Pix",
      description:
        "Crie um QR Code estático ou com valor definido e receba em instantes.",
      Icon: QrCode,
      action: goToQrPage,
    },
    {
      label: "Compartilhar Chave Pix",
      description:
        "Visualize e compartilhe suas chaves (CPF, e-mail, telefone).",
      Icon: User,
      action: () => alert("Avançar para Chaves Cadastradas."),
    },
    {
      label: "Consultar Agendamentos",
      description: "Veja recebimentos futuros e cobranças programadas.",
      Icon: Repeat,
      action: () => alert("Avançar para Agendamentos de Recebimento."),
    },
  ];

  return (
    <div className="min-h-screen bg-[#0B0B0B] text-white relative">
      {/* glows */}
      <Glow className="-top-24 -left-10 h-64 w-64 bg-emerald-500/10" />
      <Glow className="-bottom-28 -right-14 h-64 w-64 bg-sky-500/10" />

      {/* header */}
      <div className="sticky top-0 z-20 flex items-center gap-4 p-4 bg-[#0B0B0B]/90 backdrop-blur-sm border-b border-white/10">
        <button
          onClick={onBack}
          className="text-zinc-400 hover:text-white transition p-2 rounded-full hover:bg-white/5"
          aria-label="Voltar"
        >
          <ArrowLeft size={22} />
        </button>
        <div className="h-9 w-9 rounded-lg border border-white/10 bg-white/[0.06] flex items-center justify-center">
          <DollarSign size={18} className="text-emerald-400" />
        </div>
        <h1 className="text-xl font-semibold tracking-tight">Receber Dinheiro</h1>
      </div>

      <div className="p-5 sm:p-8 lg:p-10">
        <header className="pb-6 pt-2">
          <h2 className="text-4xl sm:text-5xl font-extralight tracking-tight">
            Escolha como{" "}
            <span className="font-light text-white/80">receber</span>
          </h2>
          <p className="mt-2 text-lg text-zinc-400 max-w-xl">
            Selecione o método para compartilhar seus dados de recebimento.
          </p>
        </header>

        <div className="max-w-2xl space-y-3">
          {options.map(({ Icon, label, description, action }, i) => (
            <button
              key={i}
              onClick={action}
              className="group w-full text-left p-4 rounded-xl border border-white/10 bg-white/[0.04]
                         hover:bg-white/[0.08] transition-colors duration-200"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-4">
                  <div className="p-2 rounded-lg border border-emerald-500/25 bg-emerald-500/10">
                    <Icon size={20} className="text-emerald-400" />
                  </div>
                  <div>
                    <span className="block text-base font-medium text-white">
                      {label}
                    </span>
                    <span className="block text-xs text-zinc-400 mt-1">
                      {description}
                    </span>
                  </div>
                </div>
                <ArrowRight
                  size={18}
                  className="text-zinc-500 mt-1 group-hover:translate-x-0.5 transition-transform"
                />
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};

/* ===========================
   Help Card (com imagem ancorada no TOPO)
=========================== */
const HelpCard = ({ title, text, buttonLabel, onClick, imageSrc, imageAlt = "" }) => (
  <Card className="relative overflow-hidden p-5 bg-gradient-to-br from-emerald-900/40 to-emerald-800/20 border-emerald-700/30">
    {/* Imagem de fundo: cobre o card e mostra o TOPO da foto */}
    {imageSrc && (
      <img
        src={imageSrc}
        alt={imageAlt}
        className="absolute inset-0 h-full w-full object-cover object-top opacity-40 pointer-events-none select-none"
        loading="lazy"
        style={{ objectPosition: "top" }}
      />
    )}

    {/* Overlay sutil para legibilidade (opcional) */}
    <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/35 via-black/10 to-transparent" />

    {/* conteúdo acima da imagem */}
    <div className="relative">
      <div className="flex items-center gap-2 mb-3">
        <div className="p-1.5 rounded-full border border-white/20 bg-white/10">
          <MessageSquare size={18} className="text-white" />
        </div>
        <h3 className="text-lg font-light text-white">{title}</h3>
      </div>
      <p className="text-zinc-200/90 text-sm leading-relaxed mb-4">{text}</p>
      <button
        onClick={onClick}
        className="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium transition-colors"
      >
        {buttonLabel}
      </button>
    </div>
  </Card>
);

/* ===========================
   Cards de ação (swap de ícone → seta + reveal)
=========================== */
function ActionCard({
  label,
  icon,
  action,
  isLink = false,
  featured = false,
  className = "",
}) {
  const Component = isLink ? "a" : "button";

  const base =
    "group rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-md " +
    "shadow-[0_10px_30px_-18px_rgba(0,0,0,0.5)] hover:bg-white/[0.08] " +
    "hover:border-white/20 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-white/10";

  const featuredCls =
    "w-full p-4 md:p-6 min-h-[96px] md:min-h-[112px] " +
    "grid grid-cols-[auto,1fr,auto] items-center gap-3 md:gap-6";

  const compactCls =
    "w-full p-4 h-full text-center flex flex-col items-center justify-center gap-3";

  const props = {
    className: `${base} ${featured ? featuredCls : compactCls} ${className}`,
    href: isLink ? action : undefined,
    onClick: !isLink ? action : undefined,
  };

  const IconSwap = ({ sizeBox = "h-12 w-12", sizeIcon = 24 }) => (
    <div
      className={`relative ${sizeBox} rounded-xl border border-white/15 bg-white/5 flex items-center justify-center`}
    >
      <span className="absolute inset-0 flex items-center justify-center transition-all duration-300 opacity-100 scale-100 group-hover:opacity-0 group-hover:scale-75">
        {React.cloneElement(icon, { size: sizeIcon, className: "text-white" })}
      </span>
      <span className="absolute inset-0 flex items-center justify-center transition-all duration-300 opacity-0 scale-75 group-hover:opacity-100 group-hover:scale-110">
        <ArrowRight size={sizeIcon} className="text-emerald-400" />
      </span>
    </div>
  );

  if (featured) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 14 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, amount: 0.25 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
      >
        <Component {...props}>
          <div className="h-10 w-10 md:h-14 md:w-14">
            <IconSwap sizeBox="h-full w-full" sizeIcon={22} />
          </div>
          <div className="text-left">
            <div className="text-white font-light tracking-wide text-[17px] md:text-xl leading-tight">
              {label}
            </div>
            <p className="text-[13px] md:text-sm text-zinc-400 mt-1 leading-snug">
              Rápido, seguro e com confirmação em tempo real.
            </p>
          </div>
          <div className="flex items-center justify-end pr-1 md:pr-2">
            <ArrowRight
              size={18}
              className="text-zinc-400 opacity-70 transition-transform duration-300 group-hover:translate-x-0.5"
            />
          </div>
        </Component>
      </motion.div>
    );
  }

  // compact (mobile 2x2): fonte menor + largura máxima para quebrar melhor
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.2 }}
      transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
    >
      <Component {...props}>
        <div className="flex flex-col items-center gap-3">
          <IconSwap sizeBox="h-11 w-11" sizeIcon={22} />
          <div className="text-center">
            <div className="text-white font-light tracking-wide text-[13px] sm:text-sm leading-tight whitespace-normal break-words max-w-[9.5rem] mx-auto [text-wrap:balance]">
              {label}
            </div>
          </div>
        </div>
      </Component>
    </motion.div>
  );
}

/* ===========================
   Main Page
=========================== */
export default function Transferencia() {
  const [isReceiveScreenOpen, setIsReceiveScreenOpen] = useState(false);
  const [isScanScreenOpen, setIsScanScreenOpen] = useState(false);

  const openReceiveScreen = () => setIsReceiveScreenOpen(true);
  const closeReceiveScreen = () => setIsReceiveScreenOpen(false);

  const openScanScreen = () => setIsScanScreenOpen(true);
  const closeScanScreen = () => setIsScanScreenOpen(false);

  const PIX_OPTIONS = [
    { label: "Enviar Dinheiro", icon: <Send />, action: "/pix/send", isLink: true },
    { label: "Pagar com QR Code", icon: <QrCode />, action: openScanScreen, isLink: false },
    { label: "Receber (Gerar QR)", icon: <Wallet />, action: openReceiveScreen, isLink: false },
    { label: "Pix Copia e Cola", icon: <Copy />, action: () => alert("Copia e Cola em breve."), isLink: false },
    { label: "Gerenciar Chaves", icon: <KeyRound />, action: "/pix/chaves", isLink: true },
    { label: "Alterar Limites", icon: <Gauge />, action: "/pix/limites", isLink: true },
    { label: "Extrato Pix", icon: <LinkIcon />, action: "/extrato?tipo=pix", isLink: true },
  ];

  const QR_TEXT_OPTION = {
    label: "Pix QRCode",
    icon: <QrCode />,
    action: "/pix/qrcode",
    isLink: true,
  };

  const displayActions = [
    PIX_OPTIONS[0],
    PIX_OPTIONS[1],
    PIX_OPTIONS[2],
    PIX_OPTIONS[3],
    PIX_OPTIONS[6],
  ];

  const movedOptions = [PIX_OPTIONS[4], QR_TEXT_OPTION, PIX_OPTIONS[5]];

  if (isReceiveScreenOpen) {
    return (
      <AuthenticatedLayout>
        <Head title="Receber Dinheiro" />
        <ReceiveScreen onBack={closeReceiveScreen} />
      </AuthenticatedLayout>
    );
  }

  if (isScanScreenOpen) {
    return (
      <AuthenticatedLayout>
        <Head title="Pagar com QR Code" />
        <ScanQrCodeScreen onBack={closeScanScreen} />
      </AuthenticatedLayout>
    );
  }

  const [featured, ...rest] = displayActions;
  const row2 = rest.slice(0, 2);
  const row3 = rest.slice(2, 4);

  return (
    <AuthenticatedLayout>
      <Head title="Pix Profissional" />

      {/* estilos: scrollbar custom + smooth + edge fade do carrossel */}
      <style>{`
        html { scroll-behavior: smooth; }
        .fancy-scroll { scrollbar-color: rgba(52,211,153,.5) transparent; }
        .fancy-scroll::-webkit-scrollbar { height: 10px; width: 10px; }
        .fancy-scroll::-webkit-scrollbar-track { background: transparent; }
        .fancy-scroll::-webkit-scrollbar-thumb {
          background: linear-gradient(180deg, rgba(52,211,153,.35), rgba(56,189,248,.35));
          border-radius: 9999px;
          border: 2px solid transparent;
          background-clip: padding-box;
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .edge-fade {
          -webkit-mask-image: linear-gradient(to right, transparent 0, black 28px, black calc(100% - 28px), transparent 100%);
                  mask-image: linear-gradient(to right, transparent 0, black 28px, black calc(100% - 28px), transparent 100%);
        }
      `}</style>

      <div className="min-h-screen bg-[#0A0A0A] text-white relative py-12 px-4 sm:px-6 lg:px-8 fancy-scroll">
        {/* glows */}
        <Glow className="-top-24 -left-12 h-72 w-72 bg-emerald-500/10" />
        <Glow className="-bottom-32 -right-10 h-72 w-72 bg-sky-500/10" />

        <div className="max-w-5xl mx-auto space-y-12">
          {/* Header */}
          <motion.header
            className="text-left"
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          >
            <div className="flex items-center gap-3 mb-3">
              <div className="h-11 w-11 rounded-xl border border-white/10 bg-white/[0.06] flex items-center justify-center">
                <Send size={20} className="text-emerald-400" />
              </div>
              <div>
                <h1 className="text-4xl sm:text-5xl font-extralight tracking-tight">
                  Serviços <span className="font-light text-white/80">Pix</span>
                </h1>
                <p className="text-sm text-zinc-400 mt-1">
                  Transações instantâneas, seguras e com controle total.
                </p>
              </div>
            </div>
          </motion.header>

          {/* Ações essenciais */}
          <div className="space-y-6">
            <SectionHeadline title="Ações Essenciais" />

            {/* MOBILE: carrossel arrastável com edge-fade e snap */}
            <div
              className="sm:hidden -mx-4 px-4 overflow-x-auto hide-scrollbar scroll-smooth snap-x snap-mandatory edge-fade"
              aria-label="Ações essenciais (arraste lateralmente)"
            >
              <div className="flex gap-4 pr-6">
                <ActionCard {...featured} featured className="min-w-[90%] snap-center" />
                {rest.map((opt, i) => (
                  <ActionCard key={`m-${i}`} {...opt} className="min-w-[78%] snap-center" />
                ))}
              </div>
            </div>

            {/* DESKTOP: 1 + 2 + 2 */}
            <div className="hidden sm:grid grid-cols-1">
              <ActionCard {...featured} featured />
            </div>

            <div className="hidden sm:grid grid-cols-1 sm:grid-cols-2 gap-5">
              {row2.map((opt, i) => (
                <ActionCard key={`r2-${i}`} {...opt} />
              ))}
            </div>

            <div className="hidden sm:grid grid-cols-1 sm:grid-cols-2 gap-5">
              {row3.map((opt, i) => (
                <ActionCard key={`r3-${i}`} {...opt} />
              ))}
            </div>
          </div>

          {/* Configurações rápidas */}
          <motion.div
            className="space-y-6 pt-2"
            initial={{ opacity: 0, y: 12 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
          >
            <SectionHeadline title="Configurações Rápidas" />
            <div className="flex flex-col sm:flex-row sm:space-x-8 space-y-3 sm:space-y-0 pl-4">
              {movedOptions.map((opt, i) => (
                <a
                  key={i}
                  href={opt.action}
                  className="flex items-center gap-2 text-sm font-light text-white/80 hover:text-emerald-400 transition-colors group"
                >
                  {React.cloneElement(opt.icon, {
                    size: 18,
                    className: "text-white/60 group-hover:text-emerald-400 transition-colors",
                  })}
                  <span className="group-hover:tracking-wide transition-all">{opt.label}</span>
                  <ArrowRight
                    size={18}
                    className="text-emerald-400 opacity-0 group-hover:opacity-100 -translate-x-1 group-hover:translate-x-0 transition-all"
                  />
                </a>
              ))}
            </div>
          </motion.div>

          {/* Atalhos + Ajuda */}
          <motion.div
            className="space-y-6 pt-2"
            initial={{ opacity: 0, y: 12 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
          >
            <SectionHeadline title="Atalhos Rápidos" accent="black" />
            <HelpCard
              title="Central de Ajuda"
              text="A JC Bank oferece suporte rápido e humanizado para suas transações, dúvidas e autenticações. Tecnologia e confiança lado a lado."
              buttonLabel="Clique aqui"
              onClick={() => (window.location.href = "/dashboard")}
              imageSrc="/images/Close Up Flower Photo.jpg"   // caminho com espaço codificado
              imageAlt="Central de Ajuda JC Bank"
            />
          </motion.div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
