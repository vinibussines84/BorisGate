<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionsApiController extends Controller
{
    // Exemplo: endpoints públicos autenticados por headers (X-Auth-Key, X-Secret-Key etc.)
    public function index(Request $request)
    {
        // sua lógica específica desse controller aqui...
        return response()->json(['ok' => true, 'controller' => 'TransactionsApiController']);
    }
}
