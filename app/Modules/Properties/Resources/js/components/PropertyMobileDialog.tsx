import { useState } from "react"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/Components/ui/dialog"
import { Info } from "lucide-react"
import { formatPrice, formatSurface } from "@modules-properties/lib/property-formatting"

interface PropertyMobileDialogProperty {
  id: number
  title: string
  date: string
  status: string
  publish_date?: string | null
  taxonomies?: { status?: string; type?: string; city?: string }
  meta?: { price?: string; surface?: string }
}

interface PropertyMobileDialogProps {
  property: PropertyMobileDialogProperty
  settings?: Record<string, string | undefined>
}

export function PropertyMobileDialog({ property, settings = {} }: PropertyMobileDialogProps) {
  const [open, setOpen] = useState(false)

  const price = property.meta?.price ? formatPrice(property.meta.price, {
    property_default_currency: settings?.property_default_currency || "USD",
    property_currency_position: (settings?.property_currency_position as "before" | "after") || "before",
    property_thousands_separator: settings?.property_thousands_separator || ",",
    property_decimal_separator: settings?.property_decimal_separator || ".",
  }) : null

  const surface = property.meta?.surface ? formatSurface(property.meta.surface, {
    property_surface_unit: settings?.property_surface_unit || "sq_m",
    property_thousands_separator: settings?.property_thousands_separator || ",",
    property_decimal_separator: settings?.property_decimal_separator || ".",
  }) : null

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="ghost" size="icon" className="h-8 w-8">
          <Info className="h-4 w-4" />
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{property.title}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Status</span>
            <Badge variant={property.status === "publish" ? "default" : "secondary"}>
              {property.status === "publish" ? "Published" : property.status}
            </Badge>
          </div>
          {property.taxonomies?.type && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Type</span>
              <span className="text-sm font-medium">{property.taxonomies.type}</span>
            </div>
          )}
          {property.taxonomies?.city && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">City</span>
              <span className="text-sm font-medium">{property.taxonomies.city}</span>
            </div>
          )}
          {property.taxonomies?.status && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Listing Status</span>
              <span className="text-sm font-medium">{property.taxonomies.status}</span>
            </div>
          )}
          {price && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Price</span>
              <span className="text-sm font-medium">{price}</span>
            </div>
          )}
          {surface && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Surface</span>
              <span className="text-sm font-medium">{surface}</span>
            </div>
          )}
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Date</span>
            <span className="text-sm">{property.date}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}

export default PropertyMobileDialog
