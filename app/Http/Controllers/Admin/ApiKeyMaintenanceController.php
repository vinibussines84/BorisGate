<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyMaintenanceController extends Controller
{
    /**
     * POST /api/admin/api-keys/backfill
     *
     * Headers obrigatórios:
     *  - authkey: string
     *  - secretkey: string
     *
     * Apenas administradores podem executar.
     * Gera novas chaves apenas para usuários sem authkey/secretkey.
     */
    public function backfillMissing(Request $request): JsonResponse
    {
        $auth = (string) $request->header('authkey', '');
        $secret = (string) $request->header('secretkey', '');

        if ($auth === '' || $secret === '') {
            return response()->json([
                'ok' => false,
                'error' => 'missing_headers',
                'message' => 'Headers authkey e secretkey são obrigatórios.',
            ], 401);
        }

        // Autentica o usuário
        $admin = User::query()
            ->where('authkey', $auth)
            ->where('secretkey', $secret)
            ->first();

        if (! $admin) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_credentials',
                'message' => 'Par authkey/secretkey inválido.',
            ], 403);
        }

        if (! $admin->is_admin) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'Acesso restrito a administradores.',
            ], 403);
        }

        // Faz o backfill de chaves faltantes
        $updated = 0;
        $ids = [];

        DB::transaction(function () use (&$updated, &$ids) {
            User::query()
                ->whereNull('authkey')
                ->orWhereNull('secretkey')
                ->chunkById(200, function ($users) use (&$updated, &$ids) {
                    foreach ($users as $u) {
                        $changed = false;

                        if (! $u->authkey) {
                            do {
                                $key = User::generateHex(16);
                            } while (User::where('authkey', $key)->exists());
                            $u->authkey = $key;
                            $changed = true;
                        }

                        if (! $u->secretkey) {
                            $u->secretkey = User::generateHex(32);
                            $changed = true;
                        }

                        if ($changed) {
                            $u->save();
                            $updated++;
                            $ids[] = $u->id;
                        }
                    }
                });
        });

        return response()->json([
            'ok' => true,
            'action' => 'backfill_missing_api_keys',
            'updated_count' => $updated,
            'updated_user_ids' => $ids,
        ]);
    }
}
