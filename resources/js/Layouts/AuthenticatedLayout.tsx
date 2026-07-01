import { PropsWithChildren, ReactNode } from "react";
import { Head } from "@inertiajs/react";
import { AppSidebar } from "@/Components/app-sidebar";
import { SidebarInset, SidebarProvider, SidebarTrigger } from "@/Components/ui/sidebar";
import { Separator } from "@/Components/ui/separator";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/Components/ui/breadcrumb";
import { CommandSearch } from "@/Components/command-search";
import ImpersonationBanner from "@/Components/ImpersonationBanner";
import NotificationBell from "@/Components/NotificationBell";
import { NavUser } from "@/Components/nav-user";

export default function AuthenticatedLayout({
  header,
  children,
  maxWidth,
}: PropsWithChildren<{ header?: ReactNode; maxWidth?: string }>) {
  return (
    <SidebarProvider>
      <Head title="Dashboard" />
      <AppSidebar />
      <SidebarInset>
        <ImpersonationBanner />
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-2 border-b bg-background px-4">
          <div className="flex items-center gap-2 min-w-0">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem>
                  <BreadcrumbPage>
                    {typeof header === "string" ? header : "Dashboard"}
                  </BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
          <div className="flex items-center gap-2">
            <CommandSearch />
            <NotificationBell />
            <NavUser variant="basic" />
          </div>
        </header>
        <div className="flex-1 min-w-0 p-4">
          <div className={`mx-auto w-full ${maxWidth ?? "max-w-7xl"}`}>{children}</div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}
