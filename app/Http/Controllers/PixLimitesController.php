<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class PixLimitesController extends Controller
{
    public function index()
    {
        return Inertia::render('Pix/Limites', [
            'title' => 'Alterar Limite PIX'
        ]);
    }
}
