import { PropsWithChildren } from "react";
import { Head, usePage } from "@inertiajs/react";
import { BrandLogo } from "@/Components/frontend/BrandLogo";

export default function GuestLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
  const pageProps = usePage().props;
  const brand = (pageProps.brand as { logo?: string; favicon?: string; name?: string }) || {};

  return (
    <>
      <Head title={title} />
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-background to-muted p-4">
        <div className="w-full max-w-md space-y-6">
          <div className="flex justify-center">
            <BrandLogo
              favicon={brand.favicon || null}
              name={brand.name || null}
              size="lg"
              showName
            />
          </div>
          {children}
        </div>
      </div>
    </>
  );
}
