import { useState, useCallback, useEffect } from "react"
import { Section } from "@/Components/ui/section"
import { router, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import { Eye, Send, FileText } from "lucide-react"
import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card"

interface EmailTemplate {
  label: string
  group: string
  sample_data: Record<string, unknown>
}

export function TemplateSimulationSection({ asAccordion = false }: { asAccordion?: boolean }) {
  const flash = usePage().props.flash as Record<string, string> | undefined
  const emailTemplates = (usePage().props as unknown as Record<string, unknown>).emailTemplates as Record<string, EmailTemplate> | undefined

  const [selectedTemplate, setSelectedTemplate] = useState("")
  const [previewHtml, setPreviewHtml] = useState<string | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [testEmail, setTestEmail] = useState("")
  const [sending, setSending] = useState(false)

  useEffect(() => {
    if (emailTemplates && !selectedTemplate) {
      const keys = Object.keys(emailTemplates)
      if (keys.length > 0) setSelectedTemplate(keys[0])
    }
  }, [emailTemplates, selectedTemplate])

  if (flash?.status) toast.success(flash.status)

  const handlePreview = useCallback(() => {
    if (!selectedTemplate) return
    setPreviewLoading(true)
    router.get(
      route("dashboard.admin.settings.email.template-preview", { template: selectedTemplate } as any),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
          const html = (page.props as unknown as Record<string, unknown>).previewHtml as string | undefined
          if (html) setPreviewHtml(html)
          else toast.error("Failed to load template preview")
          setPreviewLoading(false)
        },
        onError: () => {
          toast.error("Failed to load template preview")
          setPreviewLoading(false)
        },
      },
    )
  }, [selectedTemplate])

  const handleSendTest = useCallback(() => {
    if (sending || !testEmail || !selectedTemplate) return
    setSending(true)
    router.post(
      route("dashboard.admin.settings.email.template-test"),
      { template: selectedTemplate, to: testEmail },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          toast.success(`Test "${selectedTemplate}" email sent to ${testEmail}`)
          setSending(false)
        },
        onError: () => {
          toast.error("Failed to send test email")
          setSending(false)
        },
      },
    )
  }, [selectedTemplate, testEmail, sending])

  const groupedTemplates: Record<string, Array<{ value: string; label: string }>> = {}

  if (emailTemplates) {
    for (const [key, tpl] of Object.entries(emailTemplates)) {
      const group = tpl.group || "Other"
      if (!groupedTemplates[group]) groupedTemplates[group] = []
      groupedTemplates[group].push({ value: key, label: tpl.label })
    }
  }

  return (
    <Section
      title="Email Template Simulation"
      description="Preview and test your email notification templates."
      icon={FileText}
      asAccordion={asAccordion}
    >
      <div className="grid gap-6">
        <div className="flex items-end gap-4">
          <div className="grid gap-2 flex-1">
            <Label htmlFor="template-select">Email Template</Label>
            <Select value={selectedTemplate} onValueChange={setSelectedTemplate}>
              <SelectTrigger id="template-select" className="w-full">
                <SelectValue placeholder="Select a template..." />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(groupedTemplates).map(([group, templates]) => (
                  <div key={group}>
                    <div className="px-2 py-1.5 text-xs font-medium text-muted-foreground">{group}</div>
                    {templates.map((tpl) => (
                      <SelectItem key={tpl.value} value={tpl.value}>
                        {tpl.label}
                      </SelectItem>
                    ))}
                  </div>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            variant="secondary"
            onClick={handlePreview}
            disabled={previewLoading || !selectedTemplate}
          >
            <Eye className="mr-2 h-4 w-4" />
            {previewLoading ? "Loading..." : "Preview"}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">Send Test Email</CardTitle>
            <CardDescription className="text-xs">
              Send a sample of this template to an email address.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-end gap-3">
              <div className="grid gap-2 flex-1">
                <Label htmlFor="sim-test-email">Recipient Email</Label>
                <Input
                  id="sim-test-email"
                  type="email"
                  value={testEmail}
                  onChange={(e) => setTestEmail(e.target.value)}
                  placeholder="your@email.com"
                />
              </div>
              <Button
                variant="secondary"
                onClick={handleSendTest}
                disabled={sending || !testEmail || !selectedTemplate}
              >
                <Send className="mr-2 h-4 w-4" />
                {sending ? "Sending..." : "Send Test"}
              </Button>
            </div>
          </CardContent>
        </Card>

        {previewHtml && (
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium">Preview</CardTitle>
            </CardHeader>
            <CardContent>
              <div
                className="rounded-md border bg-white p-4 dark:bg-gray-900 [&_img]:max-w-full"
                dangerouslySetInnerHTML={{ __html: previewHtml }}
              />
            </CardContent>
          </Card>
        )}
      </div>
    </Section>
  )
}
