<?php

namespace App\Services\Pix;

class KeyValidator
{
    public static function validate(string $key, string $type): bool
    {
        return match (strtoupper($type)) {
            'CPF'   => self::validateCpf($key),
            'CNPJ'  => self::validateCnpj($key),
            'EMAIL' => filter_var($key, FILTER_VALIDATE_EMAIL) !== false,
            'PHONE' => self::validatePhone($key),
            'EVP'   => self::validateEvp($key),
            default => false,
        };
    }

    private static function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;

            if ($cpf[$t] != $d) {
                return false;
            }
        }
        return true;
    }

    private static function validateCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) return false;

        $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];

        for ($i = 0, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }

        $digit1 = $sum % 11;
        $digit1 = $digit1 < 2 ? 0 : 11 - $digit1;

        if ($cnpj[12] != $digit1) return false;

        for ($i = 0, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }

        $digit2 = $sum % 11;
        $digit2 = $digit2 < 2 ? 0 : 11 - $digit2;

        return $cnpj[13] == $digit2;
    }

    private static function validatePhone(string $phone): bool
    {
        $phone = preg_replace('/\D/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 13;
    }

    private static function validateEvp(string $evp): bool
    {
        return preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
            $evp
        );
    }
}
