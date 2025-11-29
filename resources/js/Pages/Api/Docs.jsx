// resources/js/Pages/Api/Docs.jsx
import React, { useMemo, useState, useRef, useEffect, useContext } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  BookOpenText,
  ShieldCheck,
  Link2,
  Copy,
  Check,
  Server,
  Zap,
  Code2,
  KeyRound,
  Shield,
  AlertTriangle,
  Webhook,
  ChevronDown,
  Banknote,
} from "lucide-react";

/* ================================
   Helpers
================================ */
function cls(...a) { return a.filter(Boolean).join(" "); }
async function copyToClipboard(text) {
  try { await navigator.clipboard.writeText(text); return true; } catch { return false; }
}

/* ================================
   UI bits
================================ */
function Badge({ children, tone = "neutral", className = "" }) {
  const tones = {
    neutral: "border-white/10 bg-white/[0.03] text-zinc-300",
    green: "border-emerald-700/30 bg-emerald-600/10 text-emerald-300",
    amber: "border-amber-700/30 bg-amber-600/10 text-amber-300",
    red: "border-rose-700/30 bg-rose-600/10 text-rose-300",
    blue: "border-sky-700/30 bg-sky-600/10 text-sky-300",
    violet: "border-violet-700/30 bg-violet-600/10 text-violet-300",
  };
  return (
    <span className={cls("inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs", tones[tone], className)}>
      {children}
    </span>
  );
}

function CodeBlock({ title, code, compact = false }) {
  const [copied, setCopied] = useState(false);
  const doCopy = async () => {
    const ok = await copyToClipboard(code);
    setCopied(ok);
    setTimeout(() => setCopied(false), 1200);
  };
  return (
    <div className="rounded-xl border border-white/10 bg-black/30">
      <div className="flex items-center justify-between px-3 py-2 border-b border-white/10">
        <div className="flex items-center gap-2">
          <Code2 size={14} className="text-zinc-400" />
          <span className="text-xs text-zinc-300">{title}</span>
        </div>
        <button
          type="button"
          onClick={doCopy}
          className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] border border-white/10 text-zinc-300 hover:bg-white/10"
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? "Copiado" : "Copiar"}
        </button>
      </div>
      <pre className={cls("overflow-x-auto text-xs text-zinc-200", compact ? "p-3" : "p-4")}>{code}</pre>
    </div>
  );
}

/* ================================
   Collapsible (animação confiável)
================================ */
function Collapsible({ open, children }) {
  const ref = useRef(null);
  const [maxH, setMaxH] = useState(0);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const h = el.scrollHeight;
    requestAnimationFrame(() => setMaxH(open ? h : 0));
  }, [open, children]);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const ro = new ResizeObserver(() => {
      if (open) setMaxH(el.scrollHeight);
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, [open]);

  return (
    <div
      style={{ maxHeight: `${maxH}px` }}
      className="transition-[max-height] duration-300 ease-out overflow-hidden"
      aria-hidden={!open}
    >
      <div ref={ref}>{children}</div>
    </div>
  );
}

/* ================================
   Accordion Group (apenas 1 aberto por grupo)
================================ */
const AccordionCtx = React.createContext(null);

function AccordionGroup({ children, defaultId = null }) {
  const [active, setActive] = useState(defaultId);
  const value = useMemo(() => ({ active, setActive }), [active]);
  return <AccordionCtx.Provider value={value}>{children}</AccordionCtx.Provider>;
}

/* ================================
   Painel de introdução (controlado por grupo)
================================ */
function InfoPanel({ id, title, icon: Icon, children, tone = "neutral" }) {
  const ctx = useContext(AccordionCtx); // pode ser null
  const isControlled = !!ctx;
  const openControlled = isControlled ? ctx.active === id : undefined;

  const [openUncontrolled, setOpenUncontrolled] = useState(false);
  const open = isControlled ? openControlled : openUncontrolled;

  const toggle = () => {
    if (isControlled) {
      ctx.setActive(open ? null : id);
    } else {
      setOpenUncontrolled(v => !v);
    }
  };

  const toneRing =
    tone === "green" ? "border-emerald-700/30 bg-emerald-600/10"
    : tone === "blue" ? "border-sky-700/30 bg-sky-600/10"
    : tone === "amber" ? "border-amber-700/30 bg-amber-600/10"
    : "border-white/10 bg-white/[0.03]";

  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03]">
      <button
        type="button"
        onClick={toggle}
        className={cls(
          "w-full flex items-center justify-between gap-3 px-4 sm:px-5 py-4",
          "hover:bg-white/[0.04] transition"
        )}
      >
        <div className="flex items-center gap-3">
          <div className={cls("inline-flex h-8 w-8 items-center justify-center rounded-lg border", toneRing)}>
            {Icon ? <Icon size={16} className={
              tone === "green" ? "text-emerald-300" :
              tone === "blue" ? "text-sky-300" :
              tone === "amber" ? "text-amber-300" :
              "text-zinc-300"
            } /> : null}
          </div>
          <span className="text-white font-medium">{title}</span>
        </div>
        <ChevronDown
          size={18}
          className={cls("text-zinc-300 transition-transform", open ? "rotate-180" : "rotate-0")}
        />
      </button>
      <Collapsible open={!!open}>
        <div className="px-4 sm:px-5 pb-5">{children}</div>
      </Collapsible>
    </div>
  );
}

/* ================================
   Página
================================ */
export default function ApiDocs({
  title = "Documentação da API",
  version = "v1",
  base_url = "https://app.closedpay.com.br/api",
}) {
  const createEndpoint   = `${base_url}/transaction/pix`;
  const withdrawEndpoint = `${base_url}/withdraw/out`;

  /* ------- Criar Pagamento ------- */
  const exampleRequestCreate = useMemo(
    () => `POST ${createEndpoint}
Content-Type: application/json
X-Auth-Key: <sua_auth_key>
X-Secret-Key: <sua_secret_key>

{"amount": 20}`, [createEndpoint]);

  const exampleResponseCreate = `{
    "success": true,
    "transaction_id": 75,
    "status": "pendente",
    "amount": "20.00",
    "txid": "3BUDSFCVC3A7EEWS9PZG4KGNLC543X45",
    "qr_code_text": "00020126860014br.gov.bcb.pix2564pix.ecomovi.com.br/qr/v3/at/926c9dd0-e5ae-404b-9758-25ea370c1e895204000053039865802BR5925SANTOS_INTERMEDIACOES_LTD6009JOINVILLE62070503***6304B2C9",
    "expires_at": null
}`;

  const exampleCurlCreate = `curl -X POST ${createEndpoint} \\
  -H "Content-Type: application/json" \\
  -H "X-Auth-Key: SEU_AUTH_KEY" \\
  -H "X-Secret-Key: SEU_SECRET_KEY" \\
  -d '{"amount": 20}'`;

  const fieldsCreate = [
    ["amount", "number", ">= 0.01", "Valor do depósito em BRL."],
  ];

  const errorsCreate = [
    ["401", "Headers de autenticação ausentes ou inválidos."],
    ["409", "Depósito duplicado (idempotente)."],
    ["422", "Validação: payload com campos faltantes/invalidos."],
    ["502", "Falha no provedor ao gerar a cobrança PIX."],
  ];

  /* ------- Criar Saque (Withdraw Out) ------- */
  const exampleRequestWithdraw = useMemo(
    () => `POST ${withdrawEndpoint}
Content-Type: application/json
X-Auth-Key: <sua_auth_key>
X-Secret-Key: <sua_secret_key>

{
  "amount": 5,
  "pixkey": "seuemail@gmail.com",
  "pixkey_type": "email",
  "description": "Saque do João"
}`, [withdrawEndpoint]);

  const exampleResponseWithdraw = `{
  "success": true,
  "withdraw_id": 42,
  "status": "processing",
  "amount": "5.00",
  "fee_amount": "0.50",
  "amount_net": "4.50",
  "message": "Saque registrado e enviado."
}`;

  const exampleCurlWithdraw = `curl -X POST ${withdrawEndpoint} \\
  -H "Content-Type: application/json" \\
  -H "X-Auth-Key: SEU_AUTH_KEY" \\
  -H "X-Secret-Key: SEU_SECRET_KEY" \\
  -d '{
    "amount": 5,
    "pixkey": "seuemail@gmail.com",
    "pixkey_type": "email",
    "description": "Saque do João"
  }'`;

  const fieldsWithdraw = [
    ["amount", "number", ">= 1.00", "Valor solicitado para saque (líquido será calculado pela taxa do cliente)."],
    ["pixkey", "string", "1..200", "Chave Pix de destino (e-mail, CPF, CNPJ ou telefone)."],
    ["pixkey_type", "string", "EMAIL | CPF | CNPJ | PHONE | EVP", "Tipo da chave Pix."],
    ["description", "string", "opcional", "Descrição/observação do saque."],
  ];

  const errorsWithdraw = [
    ["401", "Headers de autenticação ausentes ou inválidos."],
    ["402", "Saldo insuficiente para o saque bruto (valor + taxa)."],
    ["422", "Validação: payload com campos faltantes/invalidos."],
    ["502", "Falha ao criar saque."],
  ];

  /* ------- Accordions state (endpoints) ------- */
  const [openCreate, setOpenCreate]     = useState(false);
  const [openWithdraw, setOpenWithdraw] = useState(false);

  return (
    <AuthenticatedLayout>
      <Head title={title} />
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <header className="flex items-center justify-between gap-4 mb-6">
          <div className="flex items-center gap-3">
            <div className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600/15 border border-emerald-700/30">
              <BookOpenText size={18} className="text-emerald-400" />
            </div>
            <div>
              <h1 className="text-2xl font-semibold text-white">{title}</h1>
              <p className="text-sm text-zinc-400">Guia de integração e endpoints</p>
            </div>
          </div>
          <div className="hidden sm:flex items-center gap-2">
            <Badge tone="neutral">
              <ShieldCheck size={14} className="opacity-80" />
              {version}
            </Badge>
            <button
              type="button"
              onClick={() => copyToClipboard(base_url)}
              className="inline-flex items-center gap-2 rounded-full border border-emerald-700/30 bg-emerald-600/10 px-3 py-1.5 text-xs text-emerald-300 hover:bg-emerald-600/15"
            >
              <Link2 size={14} className="opacity-80" />
              {base_url}
              <Copy size={14} className="opacity-70" />
            </button>
          </div>
        </header>

        {/* =====================================================
            Introdução – 4 painéis em cima (grupo 1)
        ====================================================== */}
        <AccordionGroup>
          <div className="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <InfoPanel id="intro-api"       title="API RESTful"        icon={Server}      tone="green">
              <p className="text-sm text-zinc-300 leading-relaxed">
                A EquitPay expõe uma API <strong>RESTful</strong> com verbos HTTP padronizados, respostas em
                <strong> JSON</strong> e códigos <strong>HTTP</strong> consistentes. Utilize <strong>HTTPS</strong> sempre —
                conexões não seguras são rejeitadas.
              </p>
            </InfoPanel>

            <InfoPanel id="intro-auth"      title="Autenticação"       icon={KeyRound}    tone="green">
              <ul className="text-sm text-zinc-300 space-y-2">
                <li>Envie: <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">X-Auth-Key</code> e <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">X-Secret-Key</code>.</li>
                <li>Guarde as chaves no <strong>backend</strong> (nunca no front).</li>
                <li>Credenciais inválidas: <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">401 Unauthorized</code>.</li>
              </ul>
            </InfoPanel>

            <InfoPanel id="intro-best"      title="Boas práticas"      icon={BookOpenText} tone="green">
              <ul className="text-sm text-zinc-300 space-y-2">
                <li><strong>Idempotência</strong> com <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">external_id</code>.</li>
                <li><strong>Timeouts</strong> curtos + <strong>retries</strong> para 5xx/429.</li>
                <li>Logar <strong>request_id</strong>, <strong>timestamp</strong> e resumo do corpo.</li>
              </ul>
            </InfoPanel>

            <InfoPanel id="intro-webhooks"  title="Webhooks"           icon={Webhook}     tone="green">
              <p className="text-sm text-zinc-300">
                Eventos são <strong>assíncronos</strong>. Responda com <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">200 OK</code>.
              </p>
              <ul className="mt-2 text-sm text-zinc-300 space-y-2">
                <li>Valide <strong>assinatura</strong> quando habilitada (ex.: <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">X-Signature</code>).</li>
                <li>Falhas recebem reenvios com <strong>backoff</strong>.</li>
              </ul>
            </InfoPanel>
          </div>
        </AccordionGroup>

        {/* =====================================================
            Introdução – 3 painéis embaixo (grupo 2)
        ====================================================== */}
        <AccordionGroup>
          <div className="mt-3 sm:mt-4 grid gap-3 sm:gap-4 grid-cols-1 md:grid-cols-3">
            <InfoPanel id="intro-errors" title="Erros & Respostas" icon={ShieldCheck} tone="green">
              <ul className="text-sm text-zinc-300 space-y-2">
                <li><code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">2xx</code> Sucesso.</li>
                <li><code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">4xx</code> Erros do cliente (validação, auth, recurso).</li>
                <li><code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">5xx</code> Erros temporários do provedor/infra.</li>
              </ul>
              <p className="mt-2 text-xs text-zinc-400">Respostas trazem JSON útil para debug.</p>
            </InfoPanel>

            <InfoPanel id="intro-limits" title="Limites & Performance" icon={Code2} tone="green">
              <ul className="text-sm text-zinc-300 space-y-2">
                <li><strong>30 req/min</strong> por IP/cliente — acima disso: <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">429 Too Many Requests</code>.</li>
                <li>Prefira <strong>HTTP keep-alive</strong> e <strong>compressão</strong> no cliente.</li>
                <li>Precisa de mais volume? Fale com a gente para <strong>rate up</strong>.</li>
              </ul>
            </InfoPanel>

            <InfoPanel id="intro-env" title="Ambiente & Versão" icon={Server} tone="green">
              <div className="flex flex-wrap items-center gap-3">
                <Badge tone="neutral"><Server size={14} className="opacity-80" /> Base URL</Badge>
                <span className="text-sm text-zinc-300">{base_url}</span>
                <span className="hidden sm:inline text-zinc-600">•</span>
                <Badge tone="blue"><BookOpenText size={14} className="opacity-80" /> Versão {version}</Badge>
                <span className="hidden sm:inline text-zinc-600">•</span>
                <Badge tone="green"><ShieldCheck size={14} className="opacity-80" /> TLS 1.2+</Badge>
              </div>
              <p className="mt-3 text-sm text-zinc-300">
                Para testes, use credenciais de <strong>sandbox</strong> (quando habilitado).
              </p>
            </InfoPanel>
          </div>
        </AccordionGroup>

        {/* =======================
            Cards rápidos
        ======================== */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:p-6 mt-6 mb-6">
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="rounded-xl border border-white/10 bg-black/30 p-4">
              <div className="flex items-center gap-2 text-white mb-1">
                <Server size={16} className="text-emerald-400" />
                <span className="text-sm font-semibold">Base URL</span>
              </div>
              <p className="text-sm text-zinc-300 break-all">{base_url}</p>
            </div>
            <div className="rounded-xl border border-white/10 bg-black/30 p-4">
              <div className="flex items-center gap-2 text-white mb-1">
                <Zap size={16} className="text-emerald-400" />
                <span className="text-sm font-semibold">Endpoints</span>
              </div>
              <p className="text-sm text-zinc-300">
                POST /transaction/pix • POST /withdraw/out
              </p>
            </div>
            <div className="rounded-xl border border-white/10 bg-black/30 p-4">
              <div className="flex items-center gap-2 text-white mb-1">
                <KeyRound size={16} className="text-emerald-400" />
                <span className="text-sm font-semibold">Autenticação</span>
              </div>
              <p className="text-sm text-zinc-300">
                Headers <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">X-Auth-Key</code> e{" "}
                <code className="px-1 py-0.5 rounded bg-black/40 border border-white/10">X-Secret-Key</code>
              </p>
            </div>
          </div>
        </section>

        {/* =======================
            Acordeões de Endpoints
        ======================== */}
        {/* Criar Pagamento */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] mb-4">
          <button
            type="button"
            onClick={() => setOpenCreate(v => !v)}
            aria-expanded={openCreate}
            aria-controls="accordion-criar-pagamento"
            className={cls("w-full flex items-center justify-between gap-3 px-4 sm:px-6 py-4","hover:bg-white/[0.04] transition")}
          >
            <div className="flex items-center gap-3">
              <Badge tone="green">POST</Badge>
              <span className="text-white font-medium">Criar Pagamento</span>
              <span className="text-xs text-zinc-400 hidden sm:inline">/transaction/pix</span>
            </div>
            <ChevronDown size={18} className={cls("text-zinc-300 transition-transform", openCreate ? "rotate-180" : "rotate-0")} />
          </button>

          <Collapsible open={openCreate}>
            <div id="accordion-criar-pagamento" className="px-4 sm:px-6 pb-6 space-y-6">
              {/* Segurança */}
              <div className="rounded-xl border border-emerald-800/30 bg-emerald-600/5 p-4 sm:p-5">
                <div className="flex items-start gap-3">
                  <Shield className="mt-0.5 text-emerald-400" size={18} />
                  <div>
                    <h3 className="text-sm font-semibold text-white mb-1">Segurança</h3>
                    <ul className="list-disc pl-5 text-sm text-emerald-200/90 space-y-1">
                      <li>Envie <strong>X-Auth-Key</strong> e <strong>X-Secret-Key</strong> nos headers.</li>
                      <li>Use <strong>HTTPS</strong> e guarde as chaves com segurança (nunca no front).</li>
                      <li>Idempotência por <code>external_id</code> (se não enviar, geramos).</li>
                    </ul>
                  </div>
                </div>
              </div>

              {/* Headers */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02]">
                <div className="px-4 py-3 border-b border-white/10 text-sm text-white font-medium">Headers necessários</div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">Header</th>
                        <th className="text-left px-4 py-3 font-medium">Tipo</th>
                        <th className="text-left px-4 py-3 font-medium">Obrigatório</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {[
                        ["X-Auth-Key", "string", "Sim", "Chave pública do cliente."],
                        ["X-Secret-Key", "string", "Sim", "Chave secreta do cliente."],
                        ["Content-Type", "string", "Sim", "application/json"],
                      ].map(([h, t, o, d]) => (
                        <tr key={h}>
                          <td className="px-4 py-3 font-mono">{h}</td>
                          <td className="px-4 py-3">{t}</td>
                          <td className="px-4 py-3">{o}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Payload */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02]">
                <div className="px-4 py-3 border-b border-white/10 text-sm text-white font-medium">Body (JSON)</div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">Campo</th>
                        <th className="text-left px-4 py-3 font-medium">Tipo</th>
                        <th className="text-left px-4 py-3 font-medium">Regra</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {fieldsCreate.map(([c, t, r, d]) => (
                        <tr key={c}>
                          <td className="px-4 py-3 font-mono">{c}</td>
                          <td className="px-4 py-3">{t}</td>
                          <td className="px-4 py-3">{r}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Exemplos */}
              <div className="grid gap-4 lg:grid-cols-2">
                <CodeBlock title="Requisição HTTP" code={exampleRequestCreate} />
                <CodeBlock title="Resposta 200 (application/json)" code={exampleResponseCreate} />
                <div className="lg:col-span-2">
                  <CodeBlock title="cURL" code={exampleCurlCreate} compact />
                </div>
              </div>

              {/* Erros */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02] p-4 sm:p-5">
                <h3 className="text-sm font-semibold text-white mb-2">Códigos de erro</h3>
                <div className="overflow-x-auto rounded-xl border border-white/10">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">HTTP</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {errorsCreate.map(([c, d]) => (
                        <tr key={c}>
                          <td className="px-4 py-3 font-mono">{c}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="mt-3 flex items-start gap-2 text-amber-300">
                  <AlertTriangle size={16} className="mt-0.5" />
                  <p className="text-sm">
                    Em caso de <strong>409 Depósito duplicado</strong>, verifique o <code>external_id</code> enviado.
                  </p>
                </div>
              </div>
            </div>
          </Collapsible>
        </section>

        {/* Criar Saque */}
        <section className="rounded-2xl border border-white/10 bg-white/[0.03]">
          <button
            type="button"
            onClick={() => setOpenWithdraw(v => !v)}
            aria-expanded={openWithdraw}
            aria-controls="accordion-criar-saque"
            className={cls("w-full flex items-center justify-between gap-3 px-4 sm:px-6 py-4","hover:bg-white/[0.04] transition")}
          >
            <div className="flex items-center gap-3">
              <Badge tone="amber"><Banknote size={12}/> POST</Badge>
              <span className="text-white font-medium">Criar Saque</span>
              <span className="text-xs text-zinc-400 hidden sm:inline">/withdraw/out</span>
            </div>
            <ChevronDown size={18} className={cls("text-zinc-300 transition-transform", openWithdraw ? "rotate-180" : "rotate-0")} />
          </button>

          <Collapsible open={openWithdraw}>
            <div id="accordion-criar-saque" className="px-4 sm:px-6 pb-6 space-y-6">
              {/* Headers */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02]">
                <div className="px-4 py-3 border-b border-white/10 text-sm text-white font-medium">Headers necessários</div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">Header</th>
                        <th className="text-left px-4 py-3 font-medium">Tipo</th>
                        <th className="text-left px-4 py-3 font-medium">Obrigatório</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {[
                        ["X-Auth-Key", "string", "Sim", "Chave pública do cliente."],
                        ["X-Secret-Key", "string", "Sim", "Chave secreta do cliente."],
                        ["Content-Type", "string", "Sim", "application/json"],
                      ].map(([h, t, o, d]) => (
                        <tr key={h}>
                          <td className="px-4 py-3 font-mono">{h}</td>
                          <td className="px-4 py-3">{t}</td>
                          <td className="px-4 py-3">{o}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Payload */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02]">
                <div className="px-4 py-3 border-b border-white/10 text-sm text-white font-medium">Body (JSON)</div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">Campo</th>
                        <th className="text-left px-4 py-3 font-medium">Tipo</th>
                        <th className="text-left px-4 py-3 font-medium">Regra</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {fieldsWithdraw.map(([c, t, r, d]) => (
                        <tr key={c}>
                          <td className="px-4 py-3 font-mono">{c}</td>
                          <td className="px-4 py-3">{t}</td>
                          <td className="px-4 py-3">{r}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Exemplos */}
              <div className="grid gap-4 lg:grid-cols-2">
                <CodeBlock title="Requisição HTTP" code={exampleRequestWithdraw} />
                <CodeBlock title="Resposta 200 (application/json)" code={exampleResponseWithdraw} />
                <div className="lg:col-span-2">
                  <CodeBlock title="cURL" code={exampleCurlWithdraw} compact />
                </div>
              </div>

              {/* Erros */}
              <div className="rounded-xl border border-white/10 bg-white/[0.02] p-4 sm:p-5">
                <h3 className="text-sm font-semibold text-white mb-2">Códigos de erro</h3>
                <div className="overflow-x-auto rounded-xl border border-white/10">
                  <table className="w-full text-sm">
                    <thead className="bg-black/30 text-zinc-300">
                      <tr>
                        <th className="text-left px-4 py-3 font-medium">HTTP</th>
                        <th className="text-left px-4 py-3 font-medium">Descrição</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 text-zinc-300">
                      {errorsWithdraw.map(([c, d]) => (
                        <tr key={c}>
                          <td className="px-4 py-3 font-mono">{c}</td>
                          <td className="px-4 py-3">{d}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="mt-3 flex items-start gap-2 text-amber-300">
                  <AlertTriangle size={16} className="mt-0.5" />
                  <p className="text-sm">
                    Em caso de <strong>402 Saldo insuficiente</strong>, verifique o saldo disponível subtraído da taxa de saque.
                  </p>
                </div>
              </div>
            </div>
          </Collapsible>
        </section>

      </div>
    </AuthenticatedLayout>
  );
}
