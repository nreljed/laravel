import { PropsWithChildren } from "react";
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
import ImpersonationBanner from "@/Components/ImpersonationBanner";
import NotificationBell from "@/Components/NotificationBell";
import { NavUser } from "@/Components/nav-user";

export default function AdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
  return (
    <SidebarProvider>
      <Head title={title ?? "Admin"} />
      <AppSidebar />
      <SidebarInset>
        <ImpersonationBanner />
        <header className="flex h-16 shrink-0 items-center gap-2 border-b bg-background transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex flex-1 items-center gap-2 px-4">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem>
                  <BreadcrumbPage>{title ?? "Admin"}</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          </div>
          <div className="flex items-center gap-2 px-4">
            <NotificationBell />
            <NavUser variant="basic" />
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4">
          <div className="mx-auto w-full max-w-7xl">{children}</div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}
