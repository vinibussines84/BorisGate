<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Exception;

class RpnetService
{
    /**
     * 🌐 Endpoint base da API Stric (produção)
     */
    protected string $baseUrl = 'https://global.stric.com.br';

    /**
     * 🏢 Tenant ID da sua empresa no ecossistema Stric
     */
    protected string $tenantKey = '94b5c08d-54ed-45d5-b5f1-4b8cdf63adac';

    /**
     * 🔐 Autentica o usuário/empresa na Stric API e retorna token JWT e dados da conta.
     */
    public function authenticate(string $login, string $password, int $expiration = 3600): array
    {
        try {
            Log::info('📤 RPNet(Stric) Enviando requisição de autenticação', [
                'url'       => "{$this->baseUrl}/authenticate",
                'tenant_id' => $this->tenantKey,
                'document'  => $login,
            ]);

            // 🔹 Requisição HTTP com timeout e verificação de SSL
            $response = Http::timeout(10)
                ->retry(2, 200)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-tenant-id'  => $this->tenantKey,
                ])
                ->post("{$this->baseUrl}/authenticate", [
                    'document' => $login,
                    'password' => $password,
                ]);

            $status = $response->status();
            $body   = $response->body();

            Log::info('📥 RPNet(Stric) Resposta recebida', [
                'status' => $status,
                'excerpt' => mb_substr($body, 0, 200),
            ]);

            // 🚫 Bloqueio HTML (ex: firewall Cloudflare)
            if ($status === 403 && str_contains($body, '<html')) {
                Log::error('🚫 RPNet(Stric) Bloqueio HTML 403 detectado', [
                    'document' => $login,
                    'body_excerpt' => mb_substr($body, 0, 150),
                ]);

                return [
                    'success' => false,
                    'message' => 'Acesso bloqueado pela Stric (403 Forbidden). Verifique Tenant ID, domínio ou IP autorizado.',
                    'status'  => 403,
                ];
            }

            // 🔍 Tenta decodificar JSON
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::error('❌ RPNet(Stric) Resposta não é JSON válido', [
                    'document' => $login,
                    'body_excerpt' => mb_substr($body, 0, 150),
                ]);

                return [
                    'success' => false,
                    'message' => 'Retorno inválido da Stric API. Tente novamente.',
                    'status'  => $status,
                ];
            }

            // ✅ Sucesso — token JWT retornado
            if ($response->successful() && !empty($data['token'])) {
                Log::info('✅ RPNet(Stric) Autenticação bem-sucedida', [
                    'document'     => $login,
                    'entity'       => $data['entity']['name'] ?? 'Desconhecido',
                    'entityType'   => $data['entityType'] ?? 'N/D',
                    'accounts'     => count($data['accounts'] ?? []),
                ]);

                return [
                    'success'     => true,
                    'token'       => $data['token'],
                    'entity'      => $data['entity'] ?? null,
                    'entityType'  => $data['entityType'] ?? null,
                    'accounts'    => $data['accounts'] ?? [],
                    'expires'     => Carbon::now()->addSeconds($expiration),
                ];
            }

            // ⚠️ Falha (credenciais inválidas ou erro de negócio)
            Log::warning('⚠️ RPNet(Stric) Falha na autenticação', [
                'document' => $login,
                'status'   => $status,
                'message'  => $data['message'] ?? 'Sem mensagem retornada pela API',
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Falha ao autenticar na Stric API',
                'status'  => $status,
            ];

        } catch (Exception $e) {
            // ❌ Timeout, DNS, SSL ou falha geral
            Log::error('❌ RPNet(Stric) Erro de conexão', [
                'document' => $login,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao conectar à Stric API. Tente novamente mais tarde.',
                'error'   => $e->getMessage(),
            ];
        }
    }
}
