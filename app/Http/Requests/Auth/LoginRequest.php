<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita { login, password } ou { email, password } (retrocompatível).
     * Normaliza espaços e, no caso de CPF, mantém apenas dígitos.
     */
    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email', ''));
        $login = trim((string) $this->input('login', $email));

        // Se login parece CPF, normaliza para apenas dígitos (sem pontos/traços)
        if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $login = preg_replace('/\D+/', '', $login ?? '');
        }

        $this->merge([
            'login' => $login,
        ]);
    }

    public function rules(): array
    {
        return [
            // Pode ser e-mail OU CPF (somente dígitos após normalização)
            'login' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:4',
                'max:64',
                // Permite letras, números e símbolos “básicos” comuns
                'regex:/^[A-Za-z0-9!@#\$%\^&\*\(\)_\-\+=\.\?]+$/',
            ],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'   => 'Informe seu CPF ou e-mail para continuar.',
            'password.regex'   => 'A senha deve conter apenas letras, números ou símbolos básicos (ex: !@#._-).',
            'password.min'     => 'A senha deve ter no mínimo 4 caracteres.',
        ];
    }

    /**
     * Autentica com e-mail ou CPF.
     * Importante: o throttle é feito via middleware "throttle:login" no web.php,
     * então não aplicamos um segundo limiter aqui para evitar 429 duplicado.
     */
    public function authenticate(): void
    {
        $login    = (string) $this->input('login');
        $password = (string) $this->input('password');

        // Detecta se é e-mail; caso contrário, usa CPF (somente dígitos)
        $isEmail        = filter_var($login, FILTER_VALIDATE_EMAIL);
        $emailToAttempt = $login;

        if (!$isEmail) {
            $document = preg_replace('/\D+/', '', $login);
            /** @var \App\Models\User|null $user */
            $user = $document ? User::where('cpf', $document)->first() : null;

            if ($user && $user->email) {
                $emailToAttempt = (string) $user->email;
            } else {
                // Previne user enumeration (tenta contra um e-mail inexistente)
                $emailToAttempt = 'nonexistent_' . Str::random(10) . '@example.com';
            }
        }

        $remember = $this->boolean('remember');

        if (!Auth::attempt(['email' => $emailToAttempt, 'password' => $password], $remember)) {
            // Mensagens genéricas para não vazar origem da falha
            throw ValidationException::withMessages([
                'login'    => ['Credenciais inválidas.'],
                'password' => ['Verifique seu e-mail/CPF e senha e tente novamente.'],
            ]);
        }

        // Sucesso: a regeneração da sessão é feita no controller após authenticate()
        // (em routes/web.php, no POST /login)
    }
}
