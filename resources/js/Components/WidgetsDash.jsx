import { Send, QrCode, Banknote } from "lucide-react";
import PixIcon from "./PixIcon";
import { Link } from "@inertiajs/react";

const widgets = [
  { name: "Pix",        icon: <PixIcon size={26} className="text-gray-300 group-hover:text-white transition-colors" />, href: "/pix" },
  { name: "Pagar",      icon: <QrCode   size={26} strokeWidth={1.8} className="text-gray-300 group-hover:text-white transition-colors" />, href: "/pagamentos" },
  { name: "Transferir", icon: <Send     size={26} strokeWidth={1.8} className="text-gray-300 group-hover:text-white transition-colors" />, href: "/transferencia" },
  { name: "Cobrar",     icon: <Banknote size={26} strokeWidth={1.8} className="text-gray-300 group-hover:text-white transition-colors" />, href: "/pix" },
];

export default function WidgetsDash() {
  return (
    <div className="w-full">
      {/* MOBILE: linha única rolável (arrastar) */}
      <div
        className="
          md:hidden
          flex gap-3 px-1
          overflow-x-auto touch-pan-x
          snap-x snap-mandatory
          [-webkit-overflow-scrolling:touch]
          scrollbar-none
        "
        /* opcional: inibir bounce em iOS
        onTouchMove={(e) => e.stopPropagation()}
        */
      >
        {widgets.map((w, i) => (
          <Link
            key={i}
            href={w.href}
            className={`
              group relative rounded-3xl
              flex-none w-[72vw] max-w-[320px] h-[120px]
              bg-[#111111]/90 backdrop-blur-xl
              border border-gray-800/80
              shadow-xl shadow-black/25
              transition-all duration-300
              hover:scale-[1.03] active:scale-[0.99]
              hover:border-gray-600/60
              hover:shadow-[0_0_22px_-6px_rgba(255,255,255,0.18)]
              flex flex-col items-center justify-center
              overflow-hidden snap-center
            `}
          >
            <span className="pointer-events-none absolute inset-x-6 top-0 h-px bg-gradient-to-r from-transparent via-white/15 to-transparent" />
            <span className="pointer-events-none absolute -bottom-10 -right-10 h-28 w-28 rounded-full bg-white/5 blur-2xl" />

            <div className="flex items-center justify-center w-[50px] h-[50px] rounded-full border border-gray-700/60 bg-gray-600/10 transition-all duration-300 group-hover:bg-gray-500/15 group-hover:border-gray-500/60">
              {w.icon}
            </div>

            <h3 className="mt-2 text-[14px] font-medium tracking-tight text-gray-200 transition-colors duration-300 group-hover:text-white">
              {w.name}
            </h3>
          </Link>
        ))}
      </div>

      {/* DESKTOP/TABLET: grid 4 colunas */}
      <div className="hidden md:grid grid-cols-4 gap-6">
        {widgets.map((w, i) => (
          <Link
            key={i}
            href={w.href}
            className={`
              group relative w-full h-[135px] rounded-3xl
              bg-[#111111]/90 backdrop-blur-xl
              border border-gray-800/80
              shadow-xl shadow-black/25
              transition-all duration-300
              hover:scale-[1.03] active:scale-[0.99]
              hover:border-gray-600/60
              hover:shadow-[0_0_22px_-6px_rgba(255,255,255,0.18)]
              flex flex-col items-center justify-center overflow-hidden
            `}
          >
            <span className="pointer-events-none absolute inset-x-6 top-0 h-px bg-gradient-to-r from-transparent via-white/15 to-transparent" />
            <span className="pointer-events-none absolute -bottom-10 -right-10 h-28 w-28 rounded-full bg-white/5 blur-2xl" />

            <div className="flex items-center justify-center w-[58px] h-[58px] rounded-full border border-gray-700/60 bg-gray-600/10 transition-all duration-300 group-hover:bg-gray-500/15 group-hover:border-gray-500/60">
              {w.icon}
            </div>

            <h3 className="mt-3 text-[15px] font-medium tracking-tight text-gray-200 transition-colors duration-300 group-hover:text-white">
              {w.name}
            </h3>
          </Link>
        ))}
      </div>
    </div>
  );
}
