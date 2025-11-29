<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DadosContaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        return Inertia::render('Conta/DadosConta', [
            'user' => $user,
            'rpnet_user' => session('rpnet_login'),
        ]);
    }
}
