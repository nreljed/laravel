import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { useAuth } from "@/hooks/use-auth";

export default function DashboardIndex() {
  const { user } = useAuth();

  return (
    <AuthenticatedLayout header="Dashboard">
      <Head title="Dashboard" />

      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">
            Welcome back, {user?.name || "User"}!
          </h2>
          <p className="text-muted-foreground">
            Use the sidebar to navigate your features and settings.
          </p>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
