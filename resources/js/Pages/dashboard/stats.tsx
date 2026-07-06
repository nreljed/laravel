import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";

export default function Stats() {
  return (
    <AuthenticatedLayout
      header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Statistics</h2>}
    >
      <Head title="Statistics" />

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Monthly Views</CardTitle>
          </CardHeader>
          <CardContent className="h-64 flex items-center justify-center text-gray-400">
            Chart placeholder (Recharts)
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>User Growth</CardTitle>
          </CardHeader>
          <CardContent className="h-64 flex items-center justify-center text-gray-400">
            Chart placeholder (Recharts)
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
