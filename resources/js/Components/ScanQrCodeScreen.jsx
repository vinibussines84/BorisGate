import React, { useRef, useEffect, useState } from 'react';
import { ArrowLeft, Camera, QrCode } from 'lucide-react';

const ScanQrCodeScreen = ({ onBack }) => {
    const videoRef = useRef(null);
    const [error, setError] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    // Função para iniciar a stream de vídeo
    const startVideoStream = async (constraints) => {
        // Verifica se o navegador suporta a API de mídia
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setError("Seu navegador ou dispositivo não suporta o acesso à câmera.");
            setIsLoading(false);
            return null;
        }

        try {
            // Tenta obter a stream com as constraints fornecidas
            const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            if (videoRef.current) {
                videoRef.current.srcObject = mediaStream;
                await new Promise((resolve) => {
                    videoRef.current.onloadedmetadata = resolve;
                });
                videoRef.current.play();
                setIsLoading(false);
            }
            return mediaStream;
        } catch (err) {
            // Retorna o erro para ser tratado no nível superior (fallback)
            throw err;
        }
    };

    useEffect(() => {
        // 1. Tenta a câmera traseira (environment)
        const initialConstraints = {
            video: {
                facingMode: { exact: "environment" } 
            }
        };

        // 2. Fallback para qualquer câmera (geralmente a frontal ou a única disponível)
        const fallbackConstraints = {
            video: true 
        };

        let stream = null;

        const attemptStream = async () => {
            try {
                // Tenta a primeira opção (environment)
                stream = await startVideoStream(initialConstraints);
            } catch (err) {
                // Se falhar (ex: OverconstrainedError, NotReadableError), tenta a opção genérica
                if (err.name === "OverconstrainedError" || err.name === "NotFoundError" || err.name === "NotReadableError") {
                    console.warn("Falha ao obter 'environment' câmera. Tentando genérica.", err.message);
                    try {
                        stream = await startVideoStream(fallbackConstraints);
                    } catch (fallbackErr) {
                        handleError(fallbackErr);
                    }
                } else {
                    handleError(err);
                }
            }
        };
        
        const handleError = (err) => {
            if (err.name === "NotAllowedError") {
                setError("Permissão da câmera negada. Por favor, habilite nas configurações do seu navegador.");
            } else if (err.name === "NotFoundError") {
                setError("Nenhuma câmera encontrada que atenda aos requisitos.");
            } else {
                // Exibe a mensagem de erro que você estava vendo, mas de forma controlada
                setError(`Erro ao acessar a câmera: ${err.message || err.name}. Verifique permissões e HTTPS.`);
            }
            setIsLoading(false);
        };

        attemptStream();

        // Cleanup: interromper a câmera quando o componente for desmontado
        return () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        };
    }, []);

    return (
        <div className="p-0 bg-[#0B0B0B] min-h-screen text-white">
            
            {/* Header Fixo */}
            <div className="sticky top-0 z-10 flex items-center gap-4 p-4 bg-[#0B0B0B]/90 backdrop-blur-sm border-b border-gray-800">
                <button 
                    onClick={onBack} 
                    className="text-gray-400 hover:text-white transition p-2 rounded-full hover:bg-[#181818]"
                >
                    <ArrowLeft size={24} />
                </button>
                <h1 className="text-xl font-bold">Pagar com QR Code</h1>
            </div>

            {/* Corpo do Scanner */}
            <div className="relative w-full aspect-video md:aspect-auto md:h-full flex flex-col items-center justify-center">
                
                {/* AQUI A CÂMERA É EXIBIDA */}
                <video
                    ref={videoRef}
                    className="absolute inset-0 w-full h-full object-cover"
                    playsInline
                />

                {/* Overlay e Mensagens */}
                <div className="relative z-[2] w-full h-full flex flex-col items-center justify-center p-8 bg-black/30 backdrop-brightness-50">
                    
                    {/* Indicadores de Status */}
                    {(isLoading || error) && (
                        <div className="p-4 bg-red-600/70 text-white rounded-lg max-w-sm text-center">
                            {isLoading && (
                                <p className="flex items-center gap-2"><QrCode className='animate-pulse' size={20}/> Aguardando permissão da câmera...</p>
                            )}
                            {error && <p>{error}</p>}
                        </div>
                    )}

                    {/* Área de Foco do Scanner */}
                    {!isLoading && !error && (
                        <div className="w-64 h-64 border-4 border-pink-500/80 rounded-xl flex items-center justify-center shadow-[0_0_30px_0px_rgba(233,30,99,0.5)]">
                            <div className="w-full h-1 bg-pink-500/80 shadow-pink-500/50 animate-pulse" />
                        </div>
                    )}

                    <p className="mt-8 text-lg font-light text-gray-200">
                        Aponte para o QR Code Pix para escanear.
                    </p>
                </div>
            </div>
        </div>
    );
};

export default ScanQrCodeScreen;