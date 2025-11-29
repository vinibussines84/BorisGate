<?php

namespace App\Jobs;

use App\Models\KycReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class RunKycAnalysis implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        $payload = [
            'selfie'    => $user->selfie_path ? storage_path('app/public/'.$user->selfie_path) : null,
            'doc_front' => $user->doc_front_path ? storage_path('app/public/'.$user->doc_front_path) : null,
            'doc_back'  => $user->doc_back_path ? storage_path('app/public/'.$user->doc_back_path) : null,
            'cpf'       => $user->cpf,
            'full_name' => $user->nome_completo ?? $user->name ?? null,
        ];

        $endpoint = config('services.kyc.endpoint', env('KYC_ENDPOINT','http://127.0.0.1:8001'));
        $res = Http::timeout(120)->post(rtrim($endpoint,'/').'/analyze', $payload);

        if (!$res->ok()) {
            KycReport::create([
                'user_id' => $user->id,
                'status'  => 'rejected',
                'reasons' => ['Falha ao contatar serviço de IA'],
            ]);
            $user->update(['kyc_status'=>'rejected']);
            return;
        }

        $data = $res->json();
        KycReport::create([
            'user_id' => $user->id,
            'status'  => $data['status'] ?? 'rejected',
            'metrics' => $data['metrics'] ?? [],
            'reasons' => $data['reasons'] ?? [],
        ]);
        $user->update(['kyc_status' => $data['status'] ?? 'rejected']);
    }
}