import { useState, useCallback, useRef } from "react";
import ReactCrop, { type Crop, centerCrop, makeAspectCrop } from "react-image-crop";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Slider } from "@/Components/ui/slider";
import { ZoomIn, ZoomOut, RotateCcw, Loader2 } from "lucide-react";
import "react-image-crop/dist/ReactCrop.css";

const AVATAR_SIZE = 256;
const AVATAR_QUALITY = 0.85;

function centerAspectCrop(mediaWidth: number, mediaHeight: number) {
  return centerCrop(
    makeAspectCrop({ unit: "%", width: 80 }, 1, mediaWidth, mediaHeight),
    mediaWidth,
    mediaHeight,
  );
}

interface CropperDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  imageSrc: string;
  onSave: (_blob: Blob) => void;
}

export function CropperDialog({ open, onOpenChange, imageSrc, onSave }: CropperDialogProps) {
  const imgRef = useRef<HTMLImageElement | null>(null);
  const [crop, setCrop] = useState<Crop>();
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [saving, setSaving] = useState(false);
  const [completedCrop, setCompletedCrop] = useState<Crop | null>(null);

  const onImageLoad = useCallback((e: React.SyntheticEvent<HTMLImageElement>) => {
    const { width, height } = e.currentTarget;
    imgRef.current = e.currentTarget;
    setCrop(centerAspectCrop(width, height));
  }, []);

  const handleSave = useCallback(async () => {
    if (!completedCrop || saving || !imgRef.current) return;
    setSaving(true);

    try {
      const image = imgRef.current;
      const canvas = document.createElement("canvas");
      canvas.width = AVATAR_SIZE;
      canvas.height = AVATAR_SIZE;
      const ctx = canvas.getContext("2d")!;

      const scaleX = image.naturalWidth / image.width;
      const scaleY = image.naturalHeight / image.height;
      const cropX = completedCrop.x * scaleX;
      const cropY = completedCrop.y * scaleY;
      const cropW = completedCrop.width * scaleX;
      const cropH = completedCrop.height * scaleY;

      if (Math.abs(rotation) > 0.5) {
        const rotRad = (rotation * Math.PI) / 180;
        const bboxW = Math.abs(Math.cos(rotRad) * cropW) + Math.abs(Math.sin(rotRad) * cropH);
        const bboxH = Math.abs(Math.sin(rotRad) * cropW) + Math.abs(Math.cos(rotRad) * cropH);
        const tempCanvas = document.createElement("canvas");
        tempCanvas.width = Math.ceil(bboxW);
        tempCanvas.height = Math.ceil(bboxH);
        const tempCtx = tempCanvas.getContext("2d")!;
        tempCtx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
        tempCtx.rotate(rotRad);
        tempCtx.drawImage(image, cropX, cropY, cropW, cropH, -cropW / 2, -cropH / 2, cropW, cropH);

        ctx.drawImage(tempCanvas, 0, 0, bboxW, bboxH, 0, 0, AVATAR_SIZE, AVATAR_SIZE);
      } else {
        ctx.drawImage(image, cropX, cropY, cropW, cropH, 0, 0, AVATAR_SIZE, AVATAR_SIZE);
      }

      canvas.toBlob(
        (blob) => {
          setSaving(false);
          if (blob) onSave(blob);
        },
        "image/webp",
        AVATAR_QUALITY,
      );
    } catch {
      setSaving(false);
    }
  }, [completedCrop, rotation, saving, onSave]);

  const handleReset = useCallback(() => {
    setZoom(1);
    setRotation(0);
    if (imgRef.current) {
      const { width, height } = imgRef.current;
      setCrop(centerAspectCrop(width, height));
    }
  }, []);

  const handleDialogClose = useCallback(
    (requestedOpen: boolean) => {
      if (!requestedOpen && !saving) {
        setZoom(1);
        setRotation(0);
        setCrop(undefined);
        setCompletedCrop(null);
        onOpenChange(false);
      }
      if (requestedOpen) {
        onOpenChange(true);
      }
    },
    [saving, onOpenChange],
  );

  return (
    <Dialog open={open} onOpenChange={handleDialogClose}>
      <DialogContent className="max-w-2xl sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Crop Profile Picture</DialogTitle>
        </DialogHeader>

        <div className="relative w-full h-[400px] rounded-lg overflow-hidden bg-muted flex items-center justify-center">
          {imageSrc && (
            <ReactCrop
              crop={crop}
              onChange={(c) => setCrop(c)}
              onComplete={(c) => setCompletedCrop(c)}
              aspect={1}
              circularCrop
              className="max-h-[400px]"
            >
              <img
                ref={imgRef}
                src={imageSrc}
                onLoad={onImageLoad}
                alt="Crop preview"
                style={{ transform: `scale(${zoom}) rotate(${rotation}deg)`, maxHeight: 400 }}
              />
            </ReactCrop>
          )}
        </div>

        <div className="space-y-3">
          <div>
            <span className="text-xs font-medium text-muted-foreground mb-2 block">Zoom</span>
            <div className="flex items-center gap-3">
              <ZoomOut className="w-4 h-4 shrink-0 text-muted-foreground" />
              <Slider
                value={[zoom]}
                onValueChange={([v]) => setZoom(v ?? 1)}
                min={1}
                max={3}
                step={0.01}
                className="flex-1"
              />
              <ZoomIn className="w-4 h-4 shrink-0 text-muted-foreground" />
              <span className="w-12 text-xs text-muted-foreground text-right tabular-nums">
                {zoom.toFixed(2)}x
              </span>
            </div>
          </div>

          <div>
            <span className="text-xs font-medium text-muted-foreground mb-2 block">Rotation</span>
            <div className="flex items-center gap-3">
              <RotateCcw className="w-4 h-4 shrink-0 text-muted-foreground" />
              <Slider
                value={[rotation]}
                onValueChange={([v]) => setRotation(v ?? 0)}
                min={-180}
                max={180}
                step={1}
                className="flex-1"
              />
              <span className="w-12 text-xs text-muted-foreground text-right tabular-nums">
                {rotation}&deg;
              </span>
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => handleDialogClose(false)} disabled={saving}>
            Cancel
          </Button>
          <Button
            variant="outline"
            onClick={handleReset}
            disabled={saving || (zoom === 1 && rotation === 0)}
            className={zoom !== 1 || rotation !== 0 ? "text-primary border-primary/50" : ""}
          >
            <RotateCcw className="w-4 h-4 mr-1.5" />
            Reset
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {saving ? "Saving..." : "Save"}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
