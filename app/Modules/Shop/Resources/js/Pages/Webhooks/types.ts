export interface Webhook {
    id: number
    name: string
    url: string
    secret: string
    events: string[]
    is_active: boolean
    headers: Record<string, string> | null
    timeout: number
    retry_attempts: number
    retry_delay: number
    calls_count?: number
    created_at: string
}

export interface WebhooksPage {
    webhooks: {
        data: Webhook[]
        current_page: number
        last_page: number
        prev_page_url: string | null
        next_page_url: string | null
        links: { url: string | null; label: string; active: boolean }[]
    }
}
