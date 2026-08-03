import { Head, Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useAuth } from "@/hooks/use-auth";

interface ErrorPageProps {
  status: number;
  retryAfter?: number | null;
}

const errorMessages: Record<
  number,
  { title: string; message: string; description: string; icon: string }
> = {
  401: {
    title: "Unauthorized",
    message: "Unauthorized",
    description: "You are not authorized to access this page.",
    icon: "M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z",
  },
  402: {
    title: "Payment Required",
    message: "Payment Required",
    description: "Your subscription has expired or payment is required to continue.",
    icon: "M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z",
  },
  403: {
    title: "Forbidden",
    message: "Forbidden",
    description: "You do not have permission to perform this action.",
    icon: "M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636",
  },
  404: {
    title: "Not Found",
    message: "Not Found",
    description: "The page you are looking for does not exist or has been moved.",
    icon: "M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z",
  },
  419: {
    title: "Page Expired",
    message: "Page Expired",
    description: "Your session has expired. Please refresh the page and try again.",
    icon: "M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z",
  },
  429: {
    title: "Too Many Requests",
    message: "Too Many Requests",
    description: "You have made too many requests. Please wait a moment and try again.",
    icon: "M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z",
  },
  500: {
    title: "Server Error",
    message: "Server Error",
    description: "Something went wrong on our end. Please try again later.",
    icon: "M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z",
  },
  503: {
    title: "Service Unavailable",
    message: "Service Unavailable",
    description: "The application is currently undergoing maintenance.",
    icon: "M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3",
  },
};

export default function ErrorPage({ status, retryAfter }: ErrorPageProps) {
  const error = errorMessages[status] ?? errorMessages[500];
  const { isLoggedIn } = useAuth();
  const [remaining, setRemaining] = useState(retryAfter ?? 0);

  useEffect(() => {
    if (remaining <= 0) return;

    const timer = setInterval(() => {
      setRemaining((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [retryAfter]);

  if (!error) return null;
  const backUrl =
    typeof document !== "undefined" && document.referrer && document.referrer !== location.href
      ? document.referrer
      : "/";

  const rateLimited = status === 429 && remaining > 0;

  return (
    <>
      <Head title={`${error.title} — Cost Breaker 360`} />

      <div className="relative flex items-top justify-center min-h-screen sm:items-center sm:pt-0">
        <div className="max-w-xl mx-auto sm:px-6 lg:px-8">
          <div className="flex flex-col items-center pt-8 sm:pt-0 text-center">
            <div className="flex items-center justify-center w-20 h-20 rounded-full bg-muted mb-6">
              <svg
                className="w-10 h-10 text-muted-foreground"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth="1.5"
                stroke="currentColor"
              >
                <path strokeLinecap="round" strokeLinejoin="round" d={error.icon} />
              </svg>
            </div>

            <div className="flex items-center gap-4">
              <span className="text-5xl font-bold text-foreground">{status}</span>
              <span className="text-5xl font-bold text-muted-foreground/30">|</span>
              <span className="text-xl text-muted-foreground">{error.message}</span>
            </div>

            <p className="mt-4 text-sm text-muted-foreground">
              {rateLimited
                ? `Please wait ${remaining} second${remaining !== 1 ? "s" : ""} before trying again.`
                : error.description}
            </p>

            <div className="mt-8 flex items-center gap-4">
              {isLoggedIn && (
                <a
                  href={route("dashboard")}
                  className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2"
                >
                  Go to Dashboard
                </a>
              )}
              <a
                href={backUrl}
                className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
              >
                &larr; Go Back
              </a>
              {!isLoggedIn && (
                <Link
                  href="/"
                  className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
                >
                  Home
                </Link>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
