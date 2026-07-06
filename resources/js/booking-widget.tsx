import "./bootstrap";
import "../css/app.css";

import { createRoot, type Root } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/Components/ui/tooltip";
import { Toaster } from "@/Components/ui/sonner";
import BookingWidget from "@modules-booking/components/BookingWidget";

const widgetRoots: Root[] = [];

function mountWidgets(): void {
  const el = document.querySelector("#booking-widget") as HTMLElement | null;
  const containers: HTMLElement[] = el ? [el] : [];

  for (const container of containers) {
    if ((container as HTMLElement & { _reactRoot?: Root })._reactRoot) return;

    const root = createRoot(container);
    (container as HTMLElement & { _reactRoot?: Root })._reactRoot = root;
    widgetRoots.push(root);

    const props = {
      propertyId: Number(container.dataset.propertyId) || 0,
      minDate: container.dataset.minDate || new Date().toISOString().split("T", 1)[0],
      maxDate: container.dataset.maxDate || undefined,
      guests: Number(container.dataset.guests) || 1,
      theme: (container.dataset.theme as "light" | "dark" | "system") || "system",
    };

    root.render(
      <ThemeProvider
        attribute="class"
        defaultTheme={props.theme}
        enableSystem
        disableTransitionOnChange
      >
        <TooltipProvider>
          <BookingWidget {...props} />
          <Toaster position="top-right" />
        </TooltipProvider>
      </ThemeProvider>,
    );
  }
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mountWidgets);
  } else {
    mountWidgets();
  }
}
