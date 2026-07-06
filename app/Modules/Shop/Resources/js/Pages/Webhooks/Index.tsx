import { Link, router, usePage, useForm } from '@inertiajs/react'
import { useState } from 'react'
import { Pencil, Plus, TestTube, Trash2, Webhook } from 'lucide-react'
import { type Webhook as WebhookType, type WebhooksPage } from './types'
import AdminLayout from '@/Layouts/AdminLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { toast } from 'sonner'
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/Components/ui/pagination'

const EVENT_OPTIONS = [
    { value: 'order.created', label: 'Order Created' },
    { value: 'order.status_changed', label: 'Order Status Changed' },
    { value: 'order.refunded', label: 'Order Refunded' },
    { value: 'product.updated', label: 'Product Updated' },
    { value: 'product.stock_changed', label: 'Product Stock Changed' },
    { value: 'product.low_stock', label: 'Low Stock Alert' },
    { value: 'customer.created', label: 'Customer Created' },
]

export default function Index() {
    const { webhooks } = usePage<WebhooksPage>().props
    const [dialogOpen, setDialogOpen] = useState(false)
    const [editing, setEditing] = useState<WebhookType | null>(null)

    const form = useForm({
        name: '',
        url: '',
        events: [] as string[],
        headers: '',
        timeout: 10,
        retry_attempts: 3,
        retry_delay: 10,
    })

    function openCreate() {
        setEditing(null)
        form.reset()
        setDialogOpen(true)
    }

    function openEdit(webhook: WebhookType) {
        setEditing(webhook)
        form.setData({
            name: webhook.name,
            url: webhook.url,
            events: webhook.events,
            headers: webhook.headers ? JSON.stringify(webhook.headers, null, 2) : '',
            timeout: webhook.timeout,
            retry_attempts: webhook.retry_attempts,
            retry_delay: webhook.retry_delay,
        })
        setDialogOpen(true)
    }

    function handleSubmit() {
        let headers: Record<string, string> | null = null
        if (form.data.headers.trim()) {
            try {
                headers = JSON.parse(form.data.headers)
            } catch {
                toast.error('Invalid JSON in headers field')
                return
            }
        }

        const payload = {
            ...form.data,
            headers,
        }

        if (editing) {
            form.put(route('dashboard.shop.webhooks.update', editing.id), {
                data: payload,
                onSuccess: () => { setDialogOpen(false); toast.success('Webhook updated') },
                onError: () => toast.error('Failed to update webhook'),
            })
        } else {
            form.post(route('dashboard.shop.webhooks.store'), {
                data: payload,
                onSuccess: () => { setDialogOpen(false); toast.success('Webhook created') },
                onError: () => toast.error('Failed to create webhook'),
            })
        }
    }

    function handleDelete(webhook: WebhookType) {
        if (!confirm(`Delete webhook "${webhook.name}"?`)) return
        router.delete(route('dashboard.shop.webhooks.destroy', webhook.id), {
            onSuccess: () => toast.success('Webhook deleted'),
        })
    }

    function handleToggleActive(webhook: WebhookType) {
        router.post(route('dashboard.shop.webhooks.toggle-active', webhook.id), {}, {
            onSuccess: () => toast.success(webhook.is_active ? 'Webhook deactivated' : 'Webhook activated'),
        })
    }

    function handleTest(webhook: WebhookType) {
        router.post(route('dashboard.shop.webhooks.test', webhook.id), {}, {
            onSuccess: () => toast.success('Test webhook dispatched'),
            onError: () => toast.error('Failed to dispatch test webhook'),
        })
    }

    return (
        <AdminLayout title="Webhooks">
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Webhooks</h1>
                        <p className="text-muted-foreground">
                            Send real-time event notifications to external services.
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" /> Add Webhook
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Webhooks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>URL</TableHead>
                                    <TableHead>Events</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Calls</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {webhooks.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground py-8">
                                            No webhooks configured yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {webhooks.data.map((webhook) => (
                                    <TableRow key={webhook.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Webhook className="h-4 w-4 text-muted-foreground" />
                                                {webhook.name}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-[200px] truncate font-mono text-xs">
                                            {webhook.url}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {webhook.events.map((event) => (
                                                    <Badge key={event} variant="outline" className="text-xs">
                                                        {event}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Switch
                                                checked={webhook.is_active}
                                                onCheckedChange={() => handleToggleActive(webhook)}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Link href={route('dashboard.shop.webhooks.calls', webhook.id)}
                                                className="text-xs text-primary hover:underline">
                                                {webhook.calls_count ?? 0} calls
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => handleTest(webhook)} title="Send test">
                                                    <TestTube className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(webhook)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => handleDelete(webhook)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {webhooks.last_page > 1 && (
                    <Pagination>
                        <PaginationContent>
                            {webhooks.prev_page_url && (
                                <PaginationItem>
                                    <PaginationPrevious href={webhooks.prev_page_url} />
                                </PaginationItem>
                            )}
                            {Array.from({ length: webhooks.last_page }, (_, i) => i + 1).map((page) => (
                                <PaginationItem key={page}>
                                    <PaginationLink href={webhooks.links?.[page]?.url} isActive={page === webhooks.current_page} />
                                </PaginationItem>
                            ))}
                            {webhooks.next_page_url && (
                                <PaginationItem>
                                    <PaginationNext href={webhooks.next_page_url} />
                                </PaginationItem>
                            )}
                        </PaginationContent>
                    </Pagination>
                )}

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Edit Webhook' : 'Create Webhook'}</DialogTitle>
                            <DialogDescription>
                                {editing ? 'Update the webhook endpoint configuration.' : 'Add a new webhook endpoint to receive event notifications.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)} placeholder="My Webhook" />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="url">Payload URL</Label>
                                <Input id="url" value={form.data.url}
                                    onChange={(e) => form.setData('url', e.target.value)}
                                    placeholder="https://example.com/webhooks/costbreaker" />
                                {form.errors.url && <p className="text-sm text-destructive">{form.errors.url}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Events</Label>
                                <div className="flex flex-wrap gap-2">
                                    {EVENT_OPTIONS.map((opt) => (
                                        <Badge key={opt.value}
                                            variant={form.data.events.includes(opt.value) ? 'default' : 'outline'}
                                            className="cursor-pointer"
                                            onClick={() => {
                                                const events = form.data.events.includes(opt.value)
                                                    ? form.data.events.filter((e) => e !== opt.value)
                                                    : [...form.data.events, opt.value]
                                                form.setData('events', events)
                                            }}>
                                            {opt.label}
                                        </Badge>
                                    ))}
                                </div>
                                {form.errors.events && <p className="text-sm text-destructive">{form.errors.events}</p>}
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="timeout">Timeout (s)</Label>
                                    <Input id="timeout" type="number" min={1} max={60} value={form.data.timeout}
                                        onChange={(e) => form.setData('timeout', parseInt(e.target.value) || 10)} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="retry_attempts">Retry Attempts</Label>
                                    <Input id="retry_attempts" type="number" min={0} max={10} value={form.data.retry_attempts}
                                        onChange={(e) => form.setData('retry_attempts', parseInt(e.target.value) || 3)} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="retry_delay">Retry Delay (s)</Label>
                                    <Input id="retry_delay" type="number" min={1} max={300} value={form.data.retry_delay}
                                        onChange={(e) => form.setData('retry_delay', parseInt(e.target.value) || 10)} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="headers">Custom Headers (JSON)</Label>
                                <Textarea id="headers" value={form.data.headers}
                                    onChange={(e) => form.setData('headers', e.target.value)}
                                    placeholder='{ "X-Api-Key": "your-key" }' rows={3} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
                            <Button onClick={handleSubmit} disabled={form.processing}>
                                {editing ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    )
}
