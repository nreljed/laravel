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
import { cn } from "@/lib/utils";

type LayoutVariant = "default" | "admin";

interface AuthenticatedLayoutProps {
  header?: ReactNode;
  title?: string;
  maxWidth?: string;
  variant?: LayoutVariant;
}

export default function AuthenticatedLayout({
  header,
  title,
  children,
  maxWidth,
  variant = "default",
}: PropsWithChildren<AuthenticatedLayoutProps>) {
  const isAdmin = variant === "admin";
  const breadcrumbTitle = title ?? (typeof header === "string" ? header : "Dashboard");

  return (
    <SidebarProvider>
      <Head title={isAdmin && title ? title : "Dashboard"} />
      <AppSidebar />
      <SidebarInset>
        <ImpersonationBanner />
        <header
          className={cn(
            "flex h-16 shrink-0 items-center gap-2 border-b bg-background",
            isAdmin
              ? "transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
              : "sticky top-0 z-30 justify-between px-4"
          )}
        >
          <div className={cn("flex items-center gap-2 min-w-0", !isAdmin && "flex-1")}>
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem>
                  <BreadcrumbPage>{breadcrumbTitle}</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
          <div className={cn("flex items-center gap-2", !isAdmin && "px-4")}>
            {!isAdmin && <CommandSearch />}
            <NotificationBell />
            <NavUser variant="basic" />
          </div>
        </header>
        <div
          className={cn(
            "flex-1 p-4",
            isAdmin ? "flex flex-col gap-4" : "min-w-0"
          )}
        >
          <div className={`mx-auto w-full ${maxWidth ?? "max-w-7xl"}`}>{children}</div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}
