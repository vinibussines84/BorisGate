<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
// use App\Jobs\RunKycAnalysis; // ⛔ Desabilitado
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        // Normalização leve
        $rawDoc        = $request->input('cpf_cnpj', $request->input('cpf'));
        $cpfCnpjDigits = preg_replace('/\D+/', '', (string) $rawDoc);
        $emailLower    = mb_strtolower((string) $request->input('email'));

        $request->merge([
            'cpf_cnpj' => $cpfCnpjDigits,
            'email'    => $emailLower,
        ]);

        // Validação simplificada
        $validated = $request->validate(
            [
                'nome_completo'   => ['nullable', 'string', 'max:255'],
                'data_nascimento' => ['nullable'],
                'cpf_cnpj'        => ['required', 'digits:11', 'unique:users,cpf_cnpj'],
                'email'           => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
                'password'        => ['required', 'confirmed'],
            ],
            [
                'cpf_cnpj.required'  => 'Informe o CPF.',
                'cpf_cnpj.digits'    => 'O CPF deve conter exatamente 11 dígitos.',
                'cpf_cnpj.unique'    => 'Este CPF já está em uso.',
                'email.required'     => 'Informe o e-mail.',
                'email.email'        => 'E-mail inválido.',
                'email.unique'       => 'Este e-mail já está em uso.',
                'password.required'  => 'Informe a senha.',
                'password.confirmed' => 'A confirmação de senha não confere.',
            ],
            [
                'attributes' => [
                    'nome_completo'   => 'nome completo',
                    'data_nascimento' => 'data de nascimento',
                    'cpf_cnpj'        => 'CPF',
                    'email'           => 'e-mail',
                    'password'        => 'senha',
                ],
            ]
        );

        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'nome_completo'   => $validated['nome_completo'] ?? null,
                    'data_nascimento' => $validated['data_nascimento'] ?? null,
                    'cpf_cnpj'        => $validated['cpf_cnpj'],
                    'email'           => $validated['email'],
                    'password'        => Hash::make($validated['password']),
                    'kyc_status'      => 'pending',
                ]);

                event(new Registered($user));
                return $user;
            });

            // ⛔ KYC desabilitado
            // dispatch(new RunKycAnalysis($user->id))->onQueue('kyc');

            Auth::login($user);
            $request->session()->regenerate();

            if ($request->header('X-Inertia')) {
                return to_route('dashboard', [], 303);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'        => true,
                    'user_id'   => $user->id,
                    'kycStatus' => $user->kyc_status,
                    'redirect'  => route('dashboard'),
                ]);
            }

            return redirect()->route('dashboard');

        } catch (\Throwable $e) {
            report($e);

            $errorMessage = 'Falha ao concluir o cadastro. Tente novamente.';

            if ($request->header('X-Inertia')) {
                return back()->withErrors(['register' => $errorMessage])->withInput();
            }

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => $errorMessage], 422);
            }

            return back()->withErrors(['register' => $errorMessage])->withInput();
        }
    }
}
