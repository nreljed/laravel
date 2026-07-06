import { Link, usePage } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { ArrowLeft } from 'lucide-react'

interface Call {
    id: number
    event: string
    status: number
    attempts: number
    max_attempts: number
    response_status: number | null
    response_body: string | null
    payload: Record<string, unknown>
    created_at: string
    completed_at: string | null
}

interface CallsPage {
    webhook: { id: number; name: string }
    calls: { data: Call[] }
}

function StatusBadge({ status }: { status: number }) {
    if (status === 1) return <Badge className="bg-green-500">Success</Badge>
    if (status === 2) return <Badge variant="destructive">Failed</Badge>
    return <Badge variant="secondary">Pending</Badge>
}

function StatusCodeBadge({ code }: { code: number | null }) {
    if (!code) return <span className="text-muted-foreground">—</span>
    const ok = code >= 200 && code < 300
    return <Badge variant={ok ? 'outline' : 'destructive'}>{code}</Badge>
}

export default function Calls() {
    const { webhook, calls } = usePage<CallsPage>().props

    return (
        <AdminLayout title={`Webhook Calls - ${webhook.name}`}>
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href={route('dashboard.shop.webhooks.index')}>
                        <Button variant="outline" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{webhook.name}</h1>
                        <p className="text-muted-foreground">Webhook delivery log</p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Delivery History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Event</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Response</TableHead>
                                    <TableHead>Attempts</TableHead>
                                    <TableHead>Sent At</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {calls.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                            No deliveries yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {calls.data.map((call) => (
                                    <TableRow key={call.id}>
                                        <TableCell className="font-mono text-xs">{call.event}</TableCell>
                                        <TableCell><StatusBadge status={call.status} /></TableCell>
                                        <TableCell><StatusCodeBadge code={call.response_status} /></TableCell>
                                        <TableCell>{call.attempts}/{call.max_attempts}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {call.created_at}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    )
}
