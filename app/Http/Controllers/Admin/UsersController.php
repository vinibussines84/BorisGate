<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\User;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all'); // all | admin | nonadmin
        $digits = $q !== '' ? preg_replace('/\D+/', '', $q) : '';

        $users = User::query()
            ->select(['id','name','email','cpf_cnpj','is_admin','created_at'])
            ->when($q !== '', function ($qr) use ($q, $digits) {
                $qr->where(function ($w) use ($q, $digits) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('cpf_cnpj', 'like', "%{$q}%");
                    if ($digits !== '') {
                        $w->orWhere('cpf_cnpj', 'like', "%{$digits}%");
                    }
                });
            })
            ->when($filter === 'admin', fn($qr) => $qr->where('is_admin', true))
            ->when($filter === 'nonadmin', fn($qr) => $qr->where(fn($w) => $w->whereNull('is_admin')->orWhere('is_admin', false)))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'title'  => 'Usuários',
            'q'      => $q,
            'filter' => in_array($filter, ['all','admin','nonadmin'], true) ? $filter : 'all',
            'users'  => [
                'data'  => $users->items(),
                'total' => $users->total(),
                'links' => $users->linkCollection(),
            ],
        ]);
    }
}
