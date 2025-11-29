// resources/js/Pages/Dashboard.jsx
import React, { useEffect, useState, Suspense, lazy, memo } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";

/* ========= Lazy imports (carrega sob demanda) ========= */
const PaymentAccountCard = lazy(() => import("../Components/PaymentAccountCard"));
const NewDashboardCard = lazy(() => import("../Components/NewDashboardCard"));
const DiscoverMoreCard = lazy(() => import("../Components/DiscoverMoreCard"));
const SidebarCard = lazy(() => import("../Components/SidebarCard"));
const SidebarBannerCard = lazy(() => import("../Components/SidebarBannerCard"));

/* ===============================================================
   Error Boundary — protege cada widget
=============================================================== */
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }
  static getDerivedStateFromError() {
    return { hasError: true };
  }
  componentDidCatch(error, info) {
    if (import.meta.env.DEV) console.error("[Dashboard widget error]", error, info);
  }
  render() {
    if (this.state.hasError) {
      return (
        <div className="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200">
          Something went wrong while loading this block.
        </div>
      );
    }
    return this.props.children;
  }
}

/* ===============================================================
   Overlay Loader para qualquer Card
=============================================================== */
const CardWithOverlay = memo(function CardWithOverlay({ loading, minHeight = 220, children }) {
  return (
    <div className="relative rounded-2xl overflow-hidden block" style={{ minHeight, minWidth: 0 }}>
      {!loading && <div className="opacity-100 transition-opacity duration-200">{children}</div>}
      {loading && (
        <div className="absolute inset-0 z-10">
          <div className="absolute inset-0 rounded-2xl bg-black/40 lg:backdrop-blur-sm" />
          <div className="absolute inset-0 p-5 flex flex-col gap-3">
            <div className="h-5 w-40 rounded-md bg-white/10 animate-pulse" />
            <div className="h-8 w-56 rounded-md bg-white/15 animate-pulse" />
            <div className="mt-2 grid grid-cols-3 gap-3">
              <div className="h-16 rounded-xl bg-white/10 animate-pulse" />
              <div className="h-16 rounded-xl bg-white/10 animate-pulse" />
              <div className="h-16 rounded-xl bg-white/10 animate-pulse" />
            </div>
          </div>
          <div className="absolute inset-0 rounded-2xl ring-1 ring-white/10" />
        </div>
      )}
    </div>
  );
});

/* ===============================================================
   DASHBOARD PRINCIPAL — otimizado
=============================================================== */
export default function Dashboard() {
  const [isLoading, setIsLoading] = useState(false);
  const [hasLoadedOnce, setHasLoadedOnce] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setHasLoadedOnce(true), 100);
    const onStart = () => setIsLoading(true);
    const onFinish = () => setTimeout(() => setIsLoading(false), 80);

    const unStart = router.on("start", onStart);
    const unFinish = router.on("finish", onFinish);

    return () => {
      clearTimeout(timer);
      unStart?.();
      unFinish?.();
    };
  }, []);

  const showSidebar = hasLoadedOnce;

  return (
    <AuthenticatedLayout boxed={false} header={null}>
      <Head title="Dashboard" />

      <div
        className={`w-full text-gray-100 transition-opacity duration-500 ${
          hasLoadedOnce ? "opacity-100" : "opacity-0"
        }`}
      >
        <div className="py-6 px-4 sm:px-6 lg:px-8">
          <div className="w-full max-w-6xl flex flex-col lg:flex-row gap-6">

            {/* ================== LEFT COLUMN ================== */}
            <div className="flex-1 space-y-6">

              {/* === ACCOUNT + RESTRICTIONS === */}
              <div className="flex flex-col md:flex-row gap-6">
                <div className="flex-1">
                  <ErrorBoundary>
                    <CardWithOverlay loading={isLoading} minHeight={260}>
                      <Suspense fallback={<CardWithOverlay loading minHeight={260} />}>
                        <PaymentAccountCard minHeight={260} />
                      </Suspense>
                    </CardWithOverlay>
                  </ErrorBoundary>
                </div>

                <div className="flex-1">
                  <ErrorBoundary>
                    <CardWithOverlay loading={isLoading} minHeight={260}>
                      <Suspense fallback={<CardWithOverlay loading minHeight={260} />}>
                        <NewDashboardCard minHeight={260} />
                      </Suspense>
                    </CardWithOverlay>
                  </ErrorBoundary>
                </div>
              </div>

              {/* === DISCOVER MORE === */}
              <ErrorBoundary>
                <CardWithOverlay loading={isLoading} minHeight={200}>
                  <Suspense fallback={<CardWithOverlay loading minHeight={200} />}>
                    <DiscoverMoreCard />
                  </Suspense>
                </CardWithOverlay>
              </ErrorBoundary>
            </div>

            {/* ================== RIGHT COLUMN ================== */}
            <div className="w-full lg:w-[360px] mt-6 lg:mt-0">
              <ErrorBoundary>
                <CardWithOverlay loading={!showSidebar || isLoading} minHeight={420}>
                  <Suspense fallback={<CardWithOverlay loading minHeight={420} />}>
                    {showSidebar && <SidebarCard />}
                  </Suspense>
                </CardWithOverlay>
              </ErrorBoundary>

              <div className="mt-4">
                <ErrorBoundary>
                  <Suspense fallback={<CardWithOverlay loading minHeight={110} />}>
                    <SidebarBannerCard
                      src="/images/young-business-woman-standing-suit-office.jpg"
                      title="Support"
                      message="Hi! I need help with my account."
                      minHeight={110}
                      darken={0.35}
                      gradientFrom="rgba(0,0,0,0.70)"
                      gradientVia="rgba(0,0,0,0.40)"
                      gradientTo="rgba(16,185,129,0.22)"
                      focusX={90}
                      focusY={30}
                    />
                  </Suspense>
                </ErrorBoundary>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
