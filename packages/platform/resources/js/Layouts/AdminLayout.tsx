import { PropsWithChildren } from "react";
import AuthenticatedLayout from "./AuthenticatedLayout";

interface AdminLayoutProps {
  title?: string;
  maxWidth?: string;
}

export default function AdminLayout({
  title,
  children,
  maxWidth,
}: PropsWithChildren<AdminLayoutProps>) {
  return (
    <AuthenticatedLayout title={title} maxWidth={maxWidth} variant="admin">
      {children}
    </AuthenticatedLayout>
  );
}
