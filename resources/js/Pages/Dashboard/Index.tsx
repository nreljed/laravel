import { useMemo } from "react";
import { Link, Head } from "@inertiajs/react";
import { Users, TrendingUp, FileText, UserPlus } from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { useAuth } from "@/hooks/use-auth";

interface StatData {
  label: string;
  icon?: string;
  total?: number;
  published?: number;
  draft?: number;
  value?: number;
}

interface DashboardIndexProps {
  stats?: Record<string, StatData>;
}

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
  Users,
  TrendingUp,
  FileText,
  UserPlus,
};

export default function DashboardIndex({ stats = {} }: DashboardIndexProps) {
  const { user } = useAuth();

  const statCards = useMemo(
    () =>
      Object.entries(stats).map(([key, data]) => {
        const Icon = iconMap[data.icon || ""] || FileText;
        return {
          key,
          title: data.label || key,
          icon: Icon,
          value: data.total ?? data.value ?? 0,
        };
      }),
    [stats],
  );

  return (
    <AuthenticatedLayout header="Dashboard">
      <Head title="Dashboard" />

      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">
            Welcome back, {user?.name || "User"}!
          </h2>
          <p className="text-muted-foreground">Here's what's happening with your platform today.</p>
        </div>

        {statCards.length > 0 && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {statCards.map((card) => {
              const Icon = card.icon;
              return (
                <Card key={card.key}>
                  <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium">{card.title}</CardTitle>
                    <Icon className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <p className="text-3xl font-bold">{card.value}</p>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button asChild className="w-full">
                <Link href={route("dashboard.profile.edit")}>
                  <UserPlus className="mr-2 h-4 w-4" />
                  Edit Profile
                </Link>
              </Button>
              <Button asChild variant="outline" className="w-full">
                <Link href={route("dashboard.account")}>
                  <Users className="mr-2 h-4 w-4" />
                  Account Settings
                </Link>
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Recent Activity</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">
                Activity feed coming soon. This will show recent user actions, registrations, and
                system events.
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
