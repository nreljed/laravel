import { Link, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { Package, Plus, Truck } from 'lucide-react'
import { type ShipmentsPage as ShipmentsPageType } from './types'
import AdminLayout from '@nreljed/platform/Layouts/AdminLayout'
import { Button } from '@nreljed/platform/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@nreljed/platform/Components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@nreljed/platform/Components/ui/dialog'
import { Input } from '@nreljed/platform/Components/ui/input'
import { Label } from '@nreljed/platform/Components/ui/label'
import { Textarea } from '@nreljed/platform/Components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@nreljed/platform/Components/ui/table'
import { Badge } from '@nreljed/platform/Components/ui/badge'
import { toast } from '@nreljed/platform/Components/ui/toast'

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
}

export default function Index() {
    const { order, orderItems, shipments } = usePage<ShipmentsPageType>().props
    const [dialogOpen, setDialogOpen] = useBoolean(false)

    const form = useForm({
        item_quantities: {} as Record<number, number>,
        carrier: '',
        tracking_number: '',
        tracking_url: '',
        notes: '',
    })

    function handleCreate() {
        const quantities = Object.fromEntries(
            orderItems
                .filter((item) => form.data.item_quantities[item.id] > 0)
                .map((item) => [item.id, form.data.item_quantities[item.id] ?? item.quantity]),
        )

        if (Object.keys(quantities).length === 0) {
            toast.add({ title: 'Select at least one item quantity', type: 'error' })
            return
        }

        form.transform((data) => ({ ...data, item_quantities: quantities }))
        form.post(route('dashboard.shop.shipments.store', order.id), {
            onSuccess: () => { setDialogOpen(false); toast.add({ title: 'Shipment created', type: 'success' }) },
            onError: () => toast.add({ title: 'Failed to create shipment', type: 'error' }),
        })
    }

    function handleAction(shipmentId: number, action: 'ship' | 'deliver', tracking?: string) {
        router.put(route('dashboard.shop.shipments.update', shipmentId), {
            action,
            ...(tracking !== undefined ? { tracking_number: tracking } : {}),
        }, {
            onSuccess: () => toast.add({ title: 'Shipment updated', type: 'success' }),
            onError: () => toast.add({ title: 'Failed to update shipment', type: 'error' }),
        })
    }

    return (
        <AdminLayout title={`Shipments — ${order.order_number}`}>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Shipments</h1>
                        <p className="text-muted-foreground">
                            Manage fulfillment for <Link className="text-primary hover:underline" href={route('dashboard.shop.orders.show', order.id)}>Order #{order.order_number}</Link>
                            {" "}· {order.customer_name ?? order.customer_email}
                        </p>
                    </div>
                    <Button onClick={() => setDialogOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" /> Create Shipment
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Shipments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Number</TableHead>
                                    <TableHead>Carrier</TableHead>
                                    <TableHead>Tracking</TableHead>
                                    <TableHead>Items</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {shipments.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground py-8">
                                            No shipments yet for this order.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {shipments.map((shipment) => (
                                    <TableRow key={shipment.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Package className="h-4 w-4 text-muted-foreground" />
                                                {shipment.shipment_number}
                                            </div>
                                        </TableCell>
                                        <TableCell>{shipment.carrier ?? '—'}</TableCell>
                                        <TableCell className="max-w-[180px] truncate font-mono text-xs">
                                            {shipment.tracking_url
                                                ? <a href={shipment.tracking_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">{shipment.tracking_number}</a>
                                                : (shipment.tracking_number ?? '—')}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {shipment.items.map((item) => (
                                                    <Badge key={item.product_sku ?? item.product_name} variant="outline" className="text-xs">
                                                        {item.quantity}× {item.product_name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={shipment.status === 'delivered' ? 'default' : 'outline'}>
                                                {STATUS_LABELS[shipment.status] ?? shipment.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {shipment.status === 'pending' && (
                                                    <Button variant="outline" size="sm" onClick={() => handleAction(shipment.id, 'ship')}>
                                                        <Truck className="mr-1 h-3 w-3" /> Mark shipped
                                                    </Button>
                                                )}
                                                {shipment.status === 'shipped' && (
                                                    <Button variant="outline" size="sm" onClick={() => handleAction(shipment.id, 'deliver')}>
                                                        Mark delivered
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Create Shipment</DialogTitle>
                            <DialogDescription>
                                Create a shipment for the items below. Leave a quantity at 0 to exclude it.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="space-y-3">
                                <Label>Items</Label>
                                {orderItems.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between rounded-md border p-2">
                                        <div className="text-sm">
                                            <div className="font-medium">{item.product_name}</div>
                                            <div className="text-xs text-muted-foreground">{item.product_sku ?? '—'}</div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs text-muted-foreground">Qty</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={item.quantity}
                                                className="w-20"
                                                value={form.data.item_quantities[item.id] ?? item.quantity}
                                                onChange={(e) => form.setData(`item_quantities.${item.id}`, parseInt(e.target.value) || 0)}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="carrier">Carrier</Label>
                                <Input id="carrier" value={form.data.carrier}
                                    onChange={(e) => form.setData('carrier', e.target.value)} placeholder="DHL" />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tracking_number">Tracking Number</Label>
                                <Input id="tracking_number" value={form.data.tracking_number}
                                    onChange={(e) => form.setData('tracking_number', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tracking_url">Tracking URL</Label>
                                <Input id="tracking_url" value={form.data.tracking_url}
                                    onChange={(e) => form.setData('tracking_url', e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea id="notes" value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)} rows={2} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
                            <Button onClick={handleCreate} disabled={form.processing}>
                                Create Shipment
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    )
}

function useBoolean(initial: boolean) {
    const [state, setState] = useState(initial)
    return [state, (value?: boolean) => setState(value ?? !state)] as const
}