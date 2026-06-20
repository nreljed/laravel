import { Head } from "@inertiajs/react";
import {
  Server, Database, Cpu, Globe, HardDrive, CheckCircle2, XCircle, Users, Puzzle,
} from "lucide-react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";

interface SystemHealthProps {
  phpVersion: string;
  laravelVersion: string;
  environment: string;
  debug: boolean;
  dbConnection: string;
  dbDriver: string;
  dbName: string;
  dbSize: string;
  cacheDriver: string;
  cacheSize: string;
  sessionDriver: string;
  queueDriver: string;
  mailDriver: string;
  phpExtensions: Record<string, boolean>;
  totalUsers: number;
  enabledModules: number;
  phpMemoryLimit: string;
  phpMaxUploadSize: string;
  phpMaxPostSize: string;
  phpMaxExecTime: string;
  serverSoftware: string;
  serverOs: string;
}

export default function SystemHealth(props: SystemHealthProps) {
  const sections = [
    {
      title: "Application",
      icon: Globe,
      items: [
        { label: "Environment", value: props.environment },
        { label: "Debug Mode", value: props.debug ? "Enabled" : "Disabled", highlight: props.debug },
        { label: "Laravel Version", value: props.laravelVersion },
        { label: "PHP Version", value: props.phpVersion },
        { label: "Server Software", value: props.serverSoftware },
        { label: "Server OS", value: props.serverOs },
      ],
    },
    {
      title: "Database",
      icon: Database,
      items: [
        { label: "Connection", value: props.dbConnection },
        { label: "Driver", value: props.dbDriver },
        { label: "Database", value: props.dbName },
        { label: "Size", value: props.dbSize },
      ],
    },
    {
      title: "System",
      icon: Cpu,
      items: [
        { label: "Cache Driver", value: props.cacheDriver },
        { label: "Cache Size", value: props.cacheSize },
        { label: "Session Driver", value: props.sessionDriver },
        { label: "Queue Driver", value: props.queueDriver },
        { label: "Mail Driver", value: props.mailDriver },
      ],
    },
    {
      title: "PHP Configuration",
      icon: HardDrive,
      items: [
        { label: "Memory Limit", value: props.phpMemoryLimit },
        { label: "Max Upload Size", value: props.phpMaxUploadSize },
        { label: "Max Post Size", value: props.phpMaxPostSize },
        { label: "Max Execution Time", value: `${props.phpMaxExecTime}s` },
      ],
    },
    {
      title: "Statistics",
      icon: Users,
      items: [
        { label: "Total Users", value: String(props.totalUsers) },
        { label: "Enabled Modules", value: String(props.enabledModules) },
      ],
    },
  ];

  return (
    <AdminLayout title="System Health">
      <Head title="System Health" />

      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">System Health</h2>
          <p className="text-muted-foreground">Server configuration and diagnostics.</p>
        </div>

        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {sections.map((section) => {
            const Icon = section.icon;
            return (
              <Card key={section.title}>
                <CardHeader className="flex flex-row items-center gap-2">
                  <Icon className="h-5 w-5 text-muted-foreground" />
                  <CardTitle className="text-lg">{section.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  <dl className="space-y-3">
                    {section.items.map((item) => (
                      <div key={item.label} className="flex items-center justify-between gap-2">
                        <dt className="text-sm text-muted-foreground">{item.label}</dt>
                        <dd className={`text-sm font-medium truncate ${item.highlight ? 'text-destructive' : ''}`}>
                          {item.value}
                        </dd>
                      </div>
                    ))}
                  </dl>
                </CardContent>
              </Card>
            );
          })}
        </div>

        <Card>
          <CardHeader className="flex flex-row items-center gap-2">
            <Puzzle className="h-5 w-5 text-muted-foreground" />
            <CardTitle className="text-lg">PHP Extensions</CardTitle>
            <CardDescription>Required extensions status</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {Object.entries(props.phpExtensions).map(([ext, loaded]) => (
                <Badge key={ext} variant={loaded ? "default" : "destructive"} className="gap-1">
                  {loaded ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                  {ext}
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
