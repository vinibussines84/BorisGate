<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardFlowController extends Controller
{
    public function dailyFlow(Request $request)
    {
        $user = $request->user();

        $days = max(3, min((int) $request->integer('days', 7), 31));
        $tz   = 'America/Sao_Paulo';

        // Datas no TZ local
        $endTz   = Carbon::now($tz)->endOfDay();
        $startTz = $endTz->copy()->subDays($days - 1)->startOfDay();

        // Faixa em UTC para filtrar
        $startUtc = $startTz->copy()->utc();
        $endUtc   = $endTz->copy()->utc();

        // Query super enxuta
        $txs = Transaction::query()
            ->select('amount', 'fee', 'net_amount', 'created_at')
            ->where('user_id', $user->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->get();

        /**
         * Cria prévia das datas no formato Y-m-d no TZ local
         * Isso evita conversões dentro do foreach.
         */
        $buckets = [];
        $cursor = $startTz->copy();
        while ($cursor->lte($endTz)) {
            $buckets[$cursor->format('Y-m-d')] = 0.0;
            $cursor->addDay();
        }

        // Conversor minimizado (muito mais rápido que Carbon::parse repetido)
        foreach ($txs as $t) {
            $ts = $t->created_at?->getTimestamp();
            if (!$ts) continue;

            // Já pega a data local diretamente com PHP nativo → mais rápido que Carbon
            $localDate = gmdate('Y-m-d', $ts + $endTz->offset);

            if (!isset($buckets[$localDate])) continue;

            // líquido rápido e seguro
            $net = $t->net_amount !== null
                ? (float) $t->net_amount
                : max(0, (float) $t->amount - (float) $t->fee);

            $buckets[$localDate] += $net;
        }

        // Formatar saída final (Carbon só 1 vez por dia)
        $out = [];
        foreach ($buckets as $ymd => $valor) {
            $d = Carbon::createFromFormat('Y-m-d', $ymd, $tz);
            $out[] = [
                'name'  => $d->format('d/m'),
                'valor' => round($valor, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $out,
            'meta'    => [
                'days'     => $days,
                'timezone' => $tz,
                'range'    => [
                    'start' => $startTz->toIso8601String(),
                    'end'   => $endTz->toIso8601String(),
                ],
            ],
        ]);
    }
}
