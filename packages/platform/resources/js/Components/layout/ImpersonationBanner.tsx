import { router, usePage } from "@inertiajs/react";
import { AlertTriangle, LogOut } from "lucide-react";

interface Impersonator {
  id: number;
  name: string;
  email: string;
}

export default function ImpersonationBanner() {
  const pageProps = usePage().props as Record<string, unknown>;
  const impersonating = pageProps.impersonating as boolean;
  const impersonator = pageProps.impersonator as Impersonator | null;

  if (!impersonating || !impersonator) return null;

  const handleStop = () => {
    router.post(route("dashboard.admin.impersonate.stop"));
  };

  return (
    <div className="bg-amber-500/10 border-b border-amber-500/20 dark:bg-amber-500/15 dark:border-amber-500/30 px-4 py-2">
      <div className="mx-auto flex max-w-7xl items-center justify-between">
        <div className="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
          <AlertTriangle className="size-4" />
          <span>
            You are acting as <strong>{impersonator.name}</strong> ({impersonator.email})
          </span>
        </div>
        <button
          onClick={handleStop}
          className="inline-flex items-center gap-1.5 rounded-md bg-amber-600 dark:bg-amber-500 px-3 py-1 text-xs font-medium text-white shadow-sm transition-colors hover:bg-amber-700 dark:hover:bg-amber-600"
        >
          <LogOut className="size-3.5" />
          Stop Impersonating
        </button>
      </div>
    </div>
  );
}
