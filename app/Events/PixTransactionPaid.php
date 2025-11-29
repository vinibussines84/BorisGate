<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * Evento disparado sempre que uma transação é confirmada como paga.
 * Transmitido via canal privado para o usuário dono da transação.
 */
class PixTransactionPaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Canal privado do usuário autenticado.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->transaction->user_id);
    }

    /**
     * Nome do evento no front-end.
     */
    public function broadcastAs(): string
    {
        return 'PixTransactionPaid';
    }

    /**
     * Dados transmitidos para o front-end.
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->transaction->id,
            'amount'     => $this->transaction->amount,
            'status'     => $this->transaction->status,
            'reference'  => $this->transaction->external_reference,
            'created_at' => optional($this->transaction->created_at)->toDateTimeString(),
        ];
    }
}
