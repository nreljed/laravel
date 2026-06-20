import "../css/booking-widget.css";
import * as React from "react";
import { createRoot } from "react-dom/client";
import { Toaster } from "sonner";
import BookingWidget from "./components/BookingWidget";

document.addEventListener("DOMContentLoaded", () => {
  const hasToaster = document.querySelector("[data-crm-booking-toaster]");

  for (const el of document.querySelectorAll<HTMLElement>("[data-crm-booking-widget]")) {
    let formattingSettings;
    try {
      formattingSettings = el.dataset.formattingSettings
        ? JSON.parse(el.dataset.formattingSettings)
        : undefined;
    } catch {
      formattingSettings = undefined;
    }

    let bookingSettings;
    try {
      bookingSettings = el.dataset.bookingSettings
        ? JSON.parse(el.dataset.bookingSettings)
        : undefined;
    } catch {
      bookingSettings = undefined;
    }

    const weekStartsOn = el.dataset.weekStartsOn ? Number(el.dataset.weekStartsOn) : undefined;

    const root = createRoot(el);
    root.render(
      <React.Fragment>
        {!hasToaster && el.dataset.mode !== "form" && <Toaster position="top-center" richColors />}
        <BookingWidget
          propertyId={Number(el.dataset.propertyId)}
          showPrice={el.dataset.showPrice !== "false"}
          buttonText={el.dataset.buttonText || "Book Now"}
          mode={(el.dataset.mode as "full" | "calendar" | "form") || "full"}
          formattingSettings={formattingSettings}
          bookingSettings={bookingSettings}
          weekStartsOn={weekStartsOn}
        />
      </React.Fragment>,
    );
  }
});
