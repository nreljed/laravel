import { BrandLogo } from "@/Components/BrandLogo";
import { Link, usePage } from "@inertiajs/react";
import { PropsWithChildren } from "react";

export default function PublicLayout({ children }: PropsWithChildren) {
  const { props } = usePage();
  const brand = (props.brand as { logo?: string; name?: string }) || {};
  const appName = brand.name || "Cost Breaker 360";

  return (
    <div className="flex min-h-screen flex-col">
      <nav className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <Link href="/" className="flex items-center gap-2">
            <BrandLogo logo={brand.logo || null} name={appName} size="sm" showName />
          </Link>
          <div className="flex items-center gap-6">
            <Link
              href={route("about")}
              className="text-sm font-medium text-gray-600 hover:text-gray-900"
            >
              About
            </Link>
            <Link
              href={route("services")}
              className="text-sm font-medium text-gray-600 hover:text-gray-900"
            >
              Services
            </Link>
            <Link
              href={route("contact")}
              className="text-sm font-medium text-gray-600 hover:text-gray-900"
            >
              Contact
            </Link>
            {route().has("login") && (
              <Link
                href={route("login")}
                className="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
              >
                Sign in
              </Link>
            )}
          </div>
        </div>
      </nav>

      <main className="flex-1">{children}</main>

      <footer className="border-t border-gray-200 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-8 text-center text-sm text-gray-500 sm:px-6 lg:px-8">
          &copy; {new Date().getFullYear()} {appName}. All rights reserved.
        </div>
      </footer>
    </div>
  );
}
