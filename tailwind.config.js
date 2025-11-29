import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
        "./resources/js/**/*.tsx",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },

            // 🔹 Configurações de Animação e Keyframes
            keyframes: {
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                shake: {
                    "0%, 100%": { transform: "translateX(0)" },
                    "25%": { transform: "translateX(-5px)" },
                    "75%": { transform: "translateX(5px)" },
                },
                // MODIFICADO: ping-slow reconfigurado para um efeito de aura mais suave
                pingSlow: {
                    "0%": { transform: "scale(1)", opacity: "0.7" }, // Inicia com opacidade visível
                    "50%": { transform: "scale(1.2)", opacity: "0.1" }, // Pulsa de forma mais sutil
                    "100%": { transform: "scale(1)", opacity: "0.7" },
                },
            },
            animation: {
                fadeIn: "fadeIn 0.6s ease-in-out",
                shake: "shake 0.4s ease-in-out",
                // MODIFICADO: A duração agora é de 3 segundos para um ciclo lento
                pingSlow: "pingSlow 3s ease-in-out infinite", 
            },
        },
    },

    plugins: [forms],
};