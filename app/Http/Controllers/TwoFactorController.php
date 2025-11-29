<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    /**
     * GET /setup-pin — Página para configurar PIN
     */
    public function setupPin(Request $request)
    {
        $user = $request->user();

        // Se já tiver PIN, pode redirecionar
        if (!empty($user->pin_encrypted)) {
            return redirect()->route('saques.index');
        }

        return Inertia::render('Auth/SetupPin', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * POST /setup-pin — Salvar PIN
     */
    public function storePin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:6'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $user = $request->user();
        $user->pin_encrypted = Crypt::encryptString($request->pin);
        $user->save();

        return redirect()->route('saques.index')->with('success', 'PIN configurado com sucesso.');
    }
}
