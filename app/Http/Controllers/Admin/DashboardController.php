<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // --- métricas básicas
        $totalUsers  = User::count();
        $totalAdmins = User::where('is_admin', true)->count();

        // --- busca + filtro
        $q       = trim((string) $request->query('q', ''));
        $filter  = $request->query('filter', 'all'); // all | admin | nonadmin
        $digits  = $q !== '' ? preg_replace('/\D+/', '', $q) : '';

        // Só consulta quando há busca ou filtro != all
        $users = null;
        if ($q !== '' || $filter !== 'all') {
            $users = User::query()
                // campos retornados (evita vazar colunas sensíveis)
                ->select(['id','name','email','cpf_cnpj','is_admin'])
                // busca
                ->when($q !== '', function ($qr) use ($q, $digits) {
                    $qr->where(function ($w) use ($q, $digits) {
                        $w->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%")
                          ->orWhere('cpf_cnpj', 'like', "%{$q}%");
                        // se houver versões sem máscara de CPF/CNPJ, tenta também pelos dígitos
                        if ($digits !== '') {
                            $w->orWhere('cpf_cnpj', 'like', "%{$digits}%");
                        }
                    });
                })
                // filtro admin
                ->when($filter === 'admin', fn ($qr) => $qr->where('is_admin', true))
                ->when($filter === 'nonadmin', fn ($qr) =>
                    $qr->where(function ($w) {
                        $w->whereNull('is_admin')->orWhere('is_admin', false);
                    })
                )
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(); // mantém ?q=&filter= na paginação
        }

        return Inertia::render('Admin/Index', [
            'title'  => 'Admin',
            'q'      => $q,
            'filter' => in_array($filter, ['all','admin','nonadmin'], true) ? $filter : 'all',

            'metrics' => [
                'totalUsers'        => $totalUsers,
                'totalAdmins'       => $totalAdmins,
                'totalTransactions' => null, // ajuste se tiver
            ],

            // Normaliza a paginação para o front (leve e previsível)
            'users' => $users ? [
                'data'  => $users->items(),
                'total' => $users->total(),
                'links' => $users->linkCollection(),
            ] : null,
        ]);
    }
}
