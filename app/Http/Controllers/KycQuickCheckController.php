<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class KycQuickCheckController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'type' => 'required|in:selfie,doc_front,doc_back',
            'file' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif|max:6144',
        ]);

        $type = $request->input('type');
        $file = $request->file('file');
        $path = $file->storePublicly('kyc/tmp', 'public');
        $abs  = storage_path('app/public/'.$path);

        $python = base_path('./kyc_ai/.venv/bin/python');
        $script = base_path('./kyc_ai/kyc_check.py');

        $args = [$python, $script, '--mode', $type];
        if ($type === 'selfie')    $args += ['--selfie', $abs];
        if ($type === 'doc_front') $args += ['--doc_front', $abs];
        if ($type === 'doc_back')  $args += ['--doc_back', $abs];

        $env = ['PYTHONWARNINGS' => 'ignore::urllib3.exceptions.NotOpenSSLWarning'];
        $process = new Process($args, base_path(), $env, null, 60);
        $process->run();

        Storage::disk('public')->delete($path);

        if (!$process->isSuccessful()) {
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'reason' => 'Erro ao processar imagem.'
            ], 422);
        }

        $json = json_decode($process->getOutput(), true);
        return response()->json([
            'ok' => true,
            'status' => $json['status'] ?? 'rejected',
            'reasons' => $json['reasons'] ?? [],
        ]);
    }
}
