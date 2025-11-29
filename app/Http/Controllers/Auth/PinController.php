<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class PinController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        if (!empty($user->pin)) {
            return redirect()->route('saques.index')
                ->with('info', 'Seu PIN já está configurado.');
        }

        return Inertia::render('Auth/SetupPin', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pin'              => ['required', 'regex:/^\d{4,6}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $u = $request->user();
        $u->pin = Hash::make($data['pin']);
        $u->save();

        return redirect()->route('saques.create')
            ->with('success', 'PIN configurado com sucesso. Agora você pode solicitar saques.');
    }
}
