// Crie um arquivo chamado UserActiveGlow.jsx
import React from 'react';

// Cor rosa vibrante
const GLOW_COLOR = "#E91E63"; 

const UserActiveGlow = ({ size = 40, children }) => {
    return (
        <div 
            style={{ width: size, height: size }}
            className="relative flex items-center justify-center"
        >
            {/* O SVG pulsante (Aura) */}
            <svg 
                className="absolute inset-0 w-full h-full" 
                viewBox="0 0 100 100" 
                xmlns="http://www.w3.org/2000/svg"
            >
                {/* Efeito de desfoque/sombra */}
                <defs>
                    <filter id="glow">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="3" result="blurred" />
                        <feMerge>
                            <feMergeNode in="blurred" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>
                {/* Círculo que pulsa */}
                <circle 
                    cx="50" cy="50" r="45" 
                    fill="none" 
                    stroke={GLOW_COLOR} 
                    strokeWidth="3"
                    className="opacity-75 animate-ping-slow" // 'animate-ping-slow' precisa ser definido no seu CSS global/Tailwind config
                    style={{ animationDuration: '4s' }}
                    filter="url(#glow)" 
                />
            </svg>
            
            {/* O Conteúdo (Iniciais do Usuário ou Ícone) */}
            <div className="relative z-10 w-10 h-10 rounded-full bg-gray-900/90 border border-gray-700/70 flex items-center justify-center text-gray-300 font-bold text-sm">
                {children}
            </div>
        </div>
    );
};

export default UserActiveGlow;