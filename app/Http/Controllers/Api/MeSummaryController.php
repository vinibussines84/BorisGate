<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;

class MeSummaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();

        $doc = preg_replace('/\D+/', '', (string) $u->cpf_cnpj);
        $docMasked = $doc && strlen($doc) === 11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc)
            : '•••.•••.•••-••';

        return response()->json([
            'ok'   => true,
            'data' => [
                'id'        => $u->id,
                'nome'      => $u->nome_completo ?? $u->name,
                'cpf_cnpj'  => $u->cpf_cnpj,
                'cpf_mask'  => $docMasked,
                'email'     => $u->email,
                'created_hum' => optional($u->created_at)->timezone(config('app.timezone', 'America/Sao_Paulo'))
                    ? $u->created_at->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i')
                    : null,
            ],
        ]);
    }
}
