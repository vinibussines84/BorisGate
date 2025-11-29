// resources/js/Pages/Api/Docs.jsx
import React, { useState, useRef, useEffect } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  BookOpenText,
  ShieldCheck,
  Copy,
  Check,
  Code2,
  ChevronDown,
  Banknote,
  Webhook,
  Search,
} from "lucide-react";

/* ============== Helpers ============== */
function cls(...a) {
  return a.filter(Boolean).join(" ");
}
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}

/* ============== Components ============== */
function Badge({ children }) {
  return (
    <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.05] text-zinc-300 px-3 py-1.5 text-xs backdrop-blur-sm">
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
    <div className="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm shadow-inner shadow-black/50">
      <div className="flex items-center justify-between px-4 py-2 border-b border-white/10 bg-white/[0.03]">
        <div className="flex items-center gap-2 text-xs text-zinc-400">
          <Code2 size={14} />
          {title}
        </div>
        <button
          onClick={doCopy}
          className="inline-flex items-center gap-1.5 border border-white/10 bg-white/[0.05] hover:bg-white/[0.1] text-[11px] text-zinc-300 px-2 py-1 rounded-md transition"
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? "Copied" : "Copy"}
        </button>
      </div>
      <pre
        className={cls(
          "overflow-x-auto text-xs text-zinc-200 font-mono",
          compact ? "p-3" : "p-4"
        )}
      >
        {code}
      </pre>
    </div>
  );
}

function Collapsible({ open, children }) {
  const ref = useRef(null);
  const [maxH, setMaxH] = useState(0);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const h = el.scrollHeight;
    requestAnimationFrame(() => setMaxH(open ? h : 0));
  }, [open, children]);

  return (
    <div
      style={{ maxHeight: `${maxH}px` }}
      className="overflow-hidden transition-[max-height] duration-300 ease-out"
    >
      <div ref={ref}>{children}</div>
    </div>
  );
}

function Section({ children }) {
  return (
    <section className="rounded-2xl border border-white/10 bg-white/[0.03] shadow-lg shadow-black/40 backdrop-blur-md hover:bg-white/[0.05] transition">
      {children}
    </section>
  );
}

/* ============== Page ============== */
export default function ApiDocs({
  title = "API Documentation",
  version = "v1",
  base_url = "https://app.closedpay.com.br/api",
}) {
  const [openCreate, setOpenCreate] = useState(false);
  const [openWithdraw, setOpenWithdraw] = useState(false);
  const [openBalance, setOpenBalance] = useState(false);
  const [openStatus, setOpenStatus] = useState(false);

  const createEndpoint = `${base_url}/transaction/pix`;
  const withdrawEndpoint = `${base_url}/withdraw/out`;
  const balanceEndpoint = `${base_url}/v1/balance/available`;
  const statusEndpoint = `${base_url}/v1/transaction/status/{txid}`;

  const exampleCreate = `POST ${createEndpoint}
Content-Type: application/json
X-Auth-Key: <your_auth_key>
X-Secret-Key: <your_secret_key>

{
  "amount": 49.90,
  "name": "John Doe",
  "email": "john@example.com",
  "document": "12345678900",
  "phone": "11999999999"
}`;

  const exampleWithdraw = `POST ${withdrawEndpoint}
Content-Type: application/json
X-Auth-Key: <your_auth_key>
X-Secret-Key: <your_secret_key>

{
  "amount": 500,
  "key": "john@example.com",
  "key_type": "EMAIL",
  "description": "Withdraw via API"
}`;

  const exampleBalance = `GET ${balanceEndpoint}
X-Auth-Key: <your_auth_key>
X-Secret-Key: <your_secret_key>`;

  const exampleStatus = `GET ${statusEndpoint}
X-Auth-Key: <your_auth_key>
X-Secret-Key: <your_secret_key>`;

  return (
    <AuthenticatedLayout>
      <Head title={title} />
      <div className="max-w-6xl mx-auto py-10 px-4">
        {/* Header */}
        <header className="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-10 gap-3">
          <div className="flex items-center gap-3">
            <div className="h-11 w-11 rounded-xl bg-white/[0.05] border border-white/10 flex items-center justify-center shadow-inner">
              <BookOpenText size={18} className="text-zinc-300" />
            </div>
            <div>
              <h1 className="text-2xl font-light text-zinc-100 tracking-tight">
                {title}
              </h1>
              <p className="text-sm text-zinc-500">
                Clean and minimal API reference
              </p>
            </div>
          </div>
          <Badge>
            <ShieldCheck size={14} />
            {version}
          </Badge>
        </header>

        {/* Sections */}
        <div className="space-y-6">
          <Section>
            <div className="p-6">
              <h2 className="text-lg text-white font-medium mb-2">
                Authentication
              </h2>
              <p className="text-sm text-zinc-400 mb-3">
                Use the following headers to authenticate API requests:
              </p>
              <ul className="text-sm text-zinc-400 space-y-1">
                <li>
                  <code className="bg-black/40 border border-white/10 rounded px-2 py-0.5">
                    X-Auth-Key
                  </code>{" "}
                  — Public client key.
                </li>
                <li>
                  <code className="bg-black/40 border border-white/10 rounded px-2 py-0.5">
                    X-Secret-Key
                  </code>{" "}
                  — Private client key.
                </li>
              </ul>
            </div>
          </Section>

          <Section>
            <div className="p-6">
              <h2 className="text-lg text-white font-medium mb-2">Base URL</h2>
              <p className="text-sm text-zinc-400">{base_url}</p>
            </div>
          </Section>

          {/* Create Transaction */}
          <Section>
            <button
              onClick={() => setOpenCreate((v) => !v)}
              className="flex items-center justify-between w-full px-6 py-4 hover:bg-white/[0.05] transition"
            >
              <div className="flex items-center gap-3 text-white">
                <Badge>POST</Badge>
                <span className="font-medium">Create Transaction</span>
                <span className="hidden sm:inline text-xs text-zinc-500">
                  /transaction/pix
                </span>
              </div>
              <ChevronDown
                size={18}
                className={cls(
                  "text-zinc-400 transition-transform",
                  openCreate ? "rotate-180" : "rotate-0"
                )}
              />
            </button>
            <Collapsible open={openCreate}>
              <div className="p-6 space-y-6">
                <CodeBlock title="HTTP Request" code={exampleCreate} />
              </div>
            </Collapsible>
          </Section>

          {/* Transaction Status */}
          <Section>
            <button
              onClick={() => setOpenStatus((v) => !v)}
              className="flex items-center justify-between w-full px-6 py-4 hover:bg-white/[0.05] transition"
            >
              <div className="flex items-center gap-3 text-white">
                <Badge>GET</Badge>
                <span className="font-medium">Transaction Status</span>
                <span className="hidden sm:inline text-xs text-zinc-500">
                  /v1/transaction/status/{"{txid}"}
                </span>
              </div>
              <ChevronDown
                size={18}
                className={cls(
                  "text-zinc-400 transition-transform",
                  openStatus ? "rotate-180" : "rotate-0"
                )}
              />
            </button>
            <Collapsible open={openStatus}>
              <div className="p-6 space-y-6">
                <CodeBlock title="HTTP Request" code={exampleStatus} />
              </div>
            </Collapsible>
          </Section>

          {/* Withdraw */}
          <Section>
            <button
              onClick={() => setOpenWithdraw((v) => !v)}
              className="flex items-center justify-between w-full px-6 py-4 hover:bg-white/[0.05] transition"
            >
              <div className="flex items-center gap-3 text-white">
                <Badge>POST</Badge>
                <span className="font-medium">Create Withdraw</span>
                <span className="hidden sm:inline text-xs text-zinc-500">
                  /withdraw/out
                </span>
              </div>
              <ChevronDown
                size={18}
                className={cls(
                  "text-zinc-400 transition-transform",
                  openWithdraw ? "rotate-180" : "rotate-0"
                )}
              />
            </button>
            <Collapsible open={openWithdraw}>
              <div className="p-6 space-y-6">
                <CodeBlock title="HTTP Request" code={exampleWithdraw} />
              </div>
            </Collapsible>
          </Section>

          {/* Balance */}
          <Section>
            <button
              onClick={() => setOpenBalance((v) => !v)}
              className="flex items-center justify-between w-full px-6 py-4 hover:bg-white/[0.05] transition"
            >
              <div className="flex items-center gap-3 text-white">
                <Badge>GET</Badge>
                <span className="font-medium">Balance Inquiry</span>
                <span className="hidden sm:inline text-xs text-zinc-500">
                  /v1/balance/available
                </span>
              </div>
              <ChevronDown
                size={18}
                className={cls(
                  "text-zinc-400 transition-transform",
                  openBalance ? "rotate-180" : "rotate-0"
                )}
              />
            </button>
            <Collapsible open={openBalance}>
              <div className="p-6 space-y-6">
                <CodeBlock title="HTTP Request" code={exampleBalance} />
              </div>
            </Collapsible>
          </Section>

          {/* Webhook */}
          <Section>
            <div className="p-6">
              <div className="flex items-center gap-2 mb-2 text-white">
                <Webhook size={18} />
                <h2 className="text-lg font-medium">Webhook</h2>
              </div>
              <p className="text-sm text-zinc-400 leading-relaxed">
                Webhooks are asynchronous events triggered when a transaction
                status changes. Your endpoint should respond with{" "}
                <code className="bg-black/40 border border-white/10 rounded px-2 py-0.5">
                  200 OK
                </code>{" "}
                to confirm receipt.
              </p>
            </div>
          </Section>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
