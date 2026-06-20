import { useCallback, useMemo } from "react"
import { Section, SectionField } from "@/Components/ui/section"
import { GalleryUploader } from "@/Components/ui/gallery-uploader"
import type { GalleryImageItem, UploadResultData } from "@/Components/ui/gallery-uploader"
import { Image } from "lucide-react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match) return decodeURIComponent(match[1])
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
  return meta?.content ?? ""
}

interface MediaOptions {
  propertyId?: number | null
}

interface MediaSectionData {
  meta?: { gallery?: unknown }
}

interface MediaSectionProps {
  data: MediaSectionData
  onChange: (field: string, value: unknown) => void
  errors?: Record<string, unknown>
  loading?: boolean
  onSubmit?: (e: React.FormEvent) => void
  options?: MediaOptions
}

export function MediaSection({ data, onChange, errors, loading, onSubmit, options = {} }: MediaSectionProps) {
  const { propertyId = null } = options

  const galleryValue = useMemo(() => {
    const gallery = data.meta?.gallery
    if (!gallery) return []
    if (Array.isArray(gallery)) return gallery as GalleryImageItem[]
    try { return JSON.parse(gallery as string) as GalleryImageItem[] } catch { return [] }
  }, [data.meta?.gallery])

  const handleGalleryChange = useCallback((images: GalleryImageItem[]) => {
    onChange("meta.gallery", images)
  }, [onChange])

  const handleUpload = useCallback(async (
    file: File,
    onProgress: (pct: number) => void,
    signal: AbortSignal,
    _pendingId: string,
  ): Promise<UploadResultData> => {
    if (!propertyId) throw new Error("Property must be saved before uploading images")

    return new Promise((resolve, reject) => {
      const formData = new FormData()
      formData.append("file", file)

      const xhr = new XMLHttpRequest()

      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 90))
        }
      }

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          onProgress(100)
          try {
            const result = JSON.parse(xhr.responseText) as UploadResultData
            resolve(result)
          } catch {
            resolve({})
          }
        } else {
          reject(new Error("Upload failed"))
        }
      }

      xhr.onerror = () => reject(new Error("Network error"))
      xhr.onabort = () => reject(new Error("Upload cancelled"))

      signal.addEventListener("abort", () => {
        xhr.abort()
        reject(new Error("Upload cancelled"))
      })

      xhr.open("POST", route("dashboard.properties.upload-image", propertyId).toString())
      xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest")
      xhr.setRequestHeader("X-XSRF-TOKEN", getCsrfToken())
      xhr.send(formData)
    })
  }, [propertyId])

  const handleDelete = useCallback(async (image: GalleryImageItem) => {
    if (!propertyId) throw new Error("Property required")
    if (!image.attachment_id) return

    await fetch(route("dashboard.properties.delete-image", [propertyId, image.attachment_id]).toString(), {
      method: "DELETE",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": getCsrfToken(),
        "Accept": "application/json",
      },
    })
  }, [propertyId])

  const handleSetFeatured = useCallback(async (image: GalleryImageItem) => {
    if (!propertyId) return
    if (!image.attachment_id) return

    try {
      await router.post(route("dashboard.properties.set-featured-image", [propertyId, image.attachment_id]).toString(), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success("Cover image updated"),
      })
    } catch {
      // silent
    }
  }, [propertyId])

  const handleReorder = useCallback(async (images: GalleryImageItem[]) => {
    if (!propertyId) return
    const orderedIds = images.map((img) => img.attachment_id).filter(Boolean)

    try {
      await router.post(route("dashboard.properties.reorder-images", propertyId).toString(), {
        ordered_ids: orderedIds,
      }, { preserveScroll: true })
    } catch {
      // silent
    }
  }, [propertyId])

  return (
    <Section title="Media" description="Images to showcase your property" icon={Image} onSubmit={onSubmit} loading={loading} error={errors?.general}>
      <SectionField className="md:col-span-2">
        <GalleryUploader
          label="Property Gallery"
          description="Add multiple images"
          images={galleryValue}
          onImagesChange={handleGalleryChange}
          maxImages={20}
          onUpload={handleUpload}
          onDelete={handleDelete}
          onSetFeatured={handleSetFeatured}
          onReorder={handleReorder}
        />
      </SectionField>
    </Section>
  )
}

export default MediaSection
