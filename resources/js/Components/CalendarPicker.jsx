import React, { useState } from 'react';
import { ArrowRight, ArrowLeft } from 'lucide-react';

const CalendarPicker = ({ isOpen, onClose, onApplyFilter }) => { // ✅ Adicionada a prop onApplyFilter
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Zera hora para comparação de data

    // Estados para a navegação do calendário e seleção de datas
    const [viewDate, setViewDate] = useState(new Date(today.getFullYear(), today.getMonth(), 1)); // Mês que estamos visualizando
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);

    if (!isOpen) return null;

    const currentMonth = viewDate.getMonth();
    const currentYear = viewDate.getFullYear();

    const daysInMonth = (month, year) => new Date(year, month + 1, 0).getDate();
    const firstDayOfMonth = (month, year) => new Date(year, month, 1).getDay(); 

    const weekdays = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
    const monthNames = [
        "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho",
        "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
    ];

    // --- Funções de Navegação ---
    const changeMonth = (delta) => {
        setViewDate(prevDate => {
            const newDate = new Date(prevDate.getFullYear(), prevDate.getMonth() + delta, 1);
            return newDate;
        });
    };

    // --- Funções de Seleção de Datas ---
    const handleDateSelect = (day) => {
        const selectedDate = new Date(currentYear, currentMonth, day);
        selectedDate.setHours(0, 0, 0, 0); // Zera hora para garantir comparação correta

        if (!startDate || (startDate && endDate)) {
            // Se nenhuma data foi selecionada ou se o intervalo já está completo, reinicia
            setStartDate(selectedDate);
            setEndDate(null);
        } else if (selectedDate.getTime() < startDate.getTime()) {
            // Se a nova data for anterior ao start, inverte
            setEndDate(startDate);
            setStartDate(selectedDate);
        } else {
            // Se a nova data for posterior ou igual ao start, define como end
            setEndDate(selectedDate);
        }
    };

    // --- Funções de Ação ---
    const handleApply = () => {
        if (onApplyFilter && startDate) {
            // Formata as datas para o componente pai
            const start = startDate.toLocaleDateString('pt-BR');
            const end = endDate ? endDate.toLocaleDateString('pt-BR') : start;

            onApplyFilter({ startDate: start, endDate: end });
        }
        onClose();
    };

    // --- Renderização dos Dias ---
    const renderDays = (month, year) => {
        const totalDays = daysInMonth(month, year);
        let startDay = (firstDayOfMonth(month, year) + 6) % 7; 
        
        const days = [];
        for (let i = 0; i < startDay; i++) {
            days.push(<div key={`empty-${i}`} className="h-8"></div>);
        }

        for (let day = 1; day <= totalDays; day++) {
            const date = new Date(year, month, day);
            date.setHours(0, 0, 0, 0);

            const isToday = date.getTime() === today.getTime();
            const isSelectedStart = startDate && date.getTime() === startDate.getTime();
            const isSelectedEnd = endDate && date.getTime() === endDate.getTime();
            
            // Verifica se está no intervalo (apenas se ambas as datas estiverem selecionadas)
            const isInRange = startDate && endDate && date.getTime() > startDate.getTime() && date.getTime() < endDate.getTime();

            let classes = 'w-8 h-8 flex items-center justify-center text-sm rounded-full cursor-pointer transition-all duration-100';
            
            if (isSelectedStart || isSelectedEnd) {
                // Estilo para a data de Início ou Fim
                classes += ' bg-emerald-600 font-bold text-white shadow-md';
                // Adiciona bordas arredondadas corretas
                if (isSelectedStart && !endDate) {
                    classes += ' rounded-full';
                } else if (isSelectedStart) {
                    classes += ' rounded-r-none';
                } else if (isSelectedEnd) {
                    classes += ' rounded-l-none';
                }

            } else if (isInRange) {
                // Estilo para datas dentro do intervalo
                classes += ' bg-emerald-900/50 text-white rounded-none';
            } else if (isToday) {
                // Estilo para o dia de hoje (sem seleção de intervalo)
                classes += ' border border-emerald-500 text-emerald-500 hover:bg-gray-800';
            } else {
                // Estilo padrão
                classes += ' text-gray-300 hover:bg-gray-800';
            }
            
            // Define o estilo de hover se não estiver selecionado
            if (!isSelectedStart && !isSelectedEnd && !isInRange) {
                 classes += ' hover:bg-gray-800';
            }


            days.push(
                <div 
                    key={day} 
                    onClick={() => handleDateSelect(day)}
                    className={classes}
                >
                    {day}
                </div>
            );
        }
        return days;
    };

    // Determina se o botão Aplicar deve estar ativo
    const isApplyDisabled = !startDate;

    return (
        <div 
            className="absolute top-[85px] right-0 w-[280px] bg-[#1a1a1a] border border-gray-700 rounded-xl shadow-2xl z-20 p-4 transition-opacity duration-300"
            onClick={(e) => e.stopPropagation()}
        >
            {/* Header do Calendário */}
            <div className="flex justify-between items-center mb-4">
                <button 
                    onClick={() => changeMonth(-1)}
                    className="p-1 text-gray-400 hover:bg-gray-700 rounded-full transition"
                >
                    <ArrowLeft size={16} />
                </button>
                <span className="font-semibold text-gray-200">
                    {monthNames[currentMonth]} {currentYear}
                </span>
                <button 
                    onClick={() => changeMonth(1)}
                    className="p-1 text-gray-400 hover:bg-gray-700 rounded-full transition"
                >
                    <ArrowRight size={16} />
                </button>
            </div>

            {/* Dias da semana */}
            <div className="grid grid-cols-7 text-xs font-medium text-gray-500 mb-2">
                {weekdays.map(day => (
                    <span key={day} className="text-center">
                        {day}
                    </span>
                ))}
            </div>

            {/* Dias do mês */}
            <div className="grid grid-cols-7 gap-y-1">
                {renderDays(currentMonth, currentYear)}
            </div>
            
            {/* Footer / Ações */}
            <div className="mt-4 pt-3 border-t border-gray-700 flex justify-between items-center">
                <div className='text-xs text-gray-400'>
                    {startDate ? `${startDate.toLocaleDateString('pt-BR')} ${endDate ? ` - ${endDate.toLocaleDateString('pt-BR')}` : ''}` : 'Selecione um período'}
                </div>
                <div className='flex gap-2'>
                    <button
                        onClick={onClose}
                        className="px-3 py-1.5 text-sm rounded-lg text-gray-400 hover:bg-gray-800 transition"
                    >
                        Fechar
                    </button>
                    <button
                        onClick={handleApply} // Conectado à função de aplicar
                        disabled={isApplyDisabled} // Desativa se nenhuma data foi selecionada
                        className={`px-3 py-1.5 text-sm rounded-lg font-semibold transition ${
                            isApplyDisabled 
                                ? 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                : 'bg-emerald-600 hover:bg-emerald-700 text-white'
                        }`}
                    >
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default CalendarPicker;