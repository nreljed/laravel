export interface OrderSummary {
    id: number
    order_number: string
    customer_name: string | null
    customer_email: string | null
    status: string
}

export interface OrderItemSummary {
    id: number
    product_name: string
    product_sku: string | null
    quantity: number
}

export interface Shipment {
    id: number
    shipment_number: string
    carrier: string | null
    tracking_number: string | null
    tracking_url: string | null
    status: string
    shipped_at: string | null
    delivered_at: string | null
    items: { product_name: string; product_sku: string | null; quantity: number }[]
}

export interface ShipmentsPage {
    order: OrderSummary
    orderItems: OrderItemSummary[]
    shipments: Shipment[]
}