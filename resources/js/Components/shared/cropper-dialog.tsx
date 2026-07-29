import * as React from "react";
import { Cropper, CropperImage, CropperArea } from "./cropper";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Slider } from "@/Components/ui/slider";
import { ZoomIn, ZoomOut, RotateCcw, RotateCw, Maximize2, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

const AVATAR_SIZE = 256;
const AVATAR_QUALITY = 0.85;
const MIN_CROP_PX = 60;

function rotateSize(
  width: number,
  height: number,
  rotation: number,
): { width: number; height: number } {
  const rotRad = (rotation * Math.PI) / 180;
  return {
    width: Math.abs(Math.cos(rotRad) * width) + Math.abs(Math.sin(rotRad) * height),
    height: Math.abs(Math.sin(rotRad) * width) + Math.abs(Math.cos(rotRad) * height),
  };
}

interface CropperDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  imageSrc: string;
  onSave: (_blob: Blob) => void;
}

interface CroppedAreaData {
  x: number;
  y: number;
  width: number;
  height: number;
}

export function CropperDialog({ open, onOpenChange, imageSrc, onSave }: CropperDialogProps) {
  const [crop, setCrop] = React.useState({ x: 0, y: 0 });
  const [zoom, setZoom] = React.useState(1);
  const [rotation, setRotation] = React.useState(0);
  const [saving, setSaving] = React.useState(false);
  const [cropSizeBaseline, setCropSizeBaseline] = React.useState<{
    width: number;
    height: number;
  } | null>(null);
  const [sizeOverride, setSizeOverride] = React.useState<{ width: number; height: number } | null>(
    null,
  );
  const cropRef = React.useRef<null | {
    croppedArea: CroppedAreaData;
    croppedAreaPixels: CroppedAreaData;
  }>(null);

  const handleCropSizeBaselineChange = React.useCallback(
    (baseline: { width: number; height: number }) => {
      setCropSizeBaseline(baseline);
    },
    [],
  );

  const handleCropComplete = React.useCallback(
    (croppedArea: CroppedAreaData, croppedAreaPixels: CroppedAreaData) => {
      cropRef.current = { croppedArea, croppedAreaPixels };
    },
    [],
  );

  const handleSave = React.useCallback(async () => {
    if (saving || !cropRef.current) return;
    setSaving(true);

    try {
      const { croppedAreaPixels } = cropRef.current;
      const img = new Image();
      img.src = imageSrc;
      await img.decode();

      const canvas = document.createElement("canvas");
      canvas.width = AVATAR_SIZE;
      canvas.height = AVATAR_SIZE;
      const ctx = canvas.getContext("2d") as CanvasRenderingContext2D;

      if (Math.abs(rotation) > 0.5) {
        const bbox = rotateSize(img.naturalWidth, img.naturalHeight, rotation);
        const tempCanvas = document.createElement("canvas");
        tempCanvas.width = Math.ceil(bbox.width);
        tempCanvas.height = Math.ceil(bbox.height);
        const tempCtx = tempCanvas.getContext("2d") as CanvasRenderingContext2D;

        tempCtx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
        tempCtx.rotate((rotation * Math.PI) / 180);
        tempCtx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);

        ctx.drawImage(
          tempCanvas,
          croppedAreaPixels.x,
          croppedAreaPixels.y,
          croppedAreaPixels.width,
          croppedAreaPixels.height,
          0,
          0,
          AVATAR_SIZE,
          AVATAR_SIZE,
        );
      } else {
        ctx.drawImage(
          img,
          croppedAreaPixels.x,
          croppedAreaPixels.y,
          croppedAreaPixels.width,
          croppedAreaPixels.height,
          0,
          0,
          AVATAR_SIZE,
          AVATAR_SIZE,
        );
      }

      canvas.toBlob(
        (blob: Blob | null) => {
          setSaving(false);
          if (blob) {
            onSave(blob);
          }
        },
        "image/webp",
        AVATAR_QUALITY,
      );
    } catch {
      setSaving(false);
    }
  }, [imageSrc, rotation, saving, onSave]);

  const handleReset = React.useCallback(() => {
    setCrop({ x: 0, y: 0 });
    setZoom(1);
    setRotation(0);
    setSizeOverride(null);
  }, []);

  const handleDialogClose = React.useCallback(
    (requestedOpen: boolean) => {
      if (!requestedOpen && !saving) {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setRotation(0);
        setSizeOverride(null);
        cropRef.current = null;
        onOpenChange(false);
      }
      if (requestedOpen) {
        onOpenChange(true);
      }
    },
    [saving, onOpenChange],
  );

  const maxSize = cropSizeBaseline?.width ?? 400;
  const currentSize = sizeOverride?.width ?? maxSize;
  const isResized = sizeOverride !== null;

  return (
    <Dialog open={open} onOpenChange={handleDialogClose}>
      <DialogContent className="max-w-2xl sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Crop Profile Picture</DialogTitle>
        </DialogHeader>

        <div className="relative w-full h-[400px] rounded-lg overflow-hidden bg-muted flex items-center justify-center">
          {imageSrc && (
            <Cropper
              key={imageSrc}
              aspectRatio={1}
              shape="circle"
              crop={crop}
              zoom={zoom}
              rotation={rotation}
              minZoom={1}
              maxZoom={3}
              cropSizeOverride={sizeOverride}
              onCropChange={setCrop}
              onZoomChange={setZoom}
              onRotationChange={setRotation}
              onCropComplete={handleCropComplete}
              onCropSizeBaselineChange={handleCropSizeBaselineChange}
            >
              <CropperImage src={imageSrc} alt="Crop preview" objectFit="contain" />
              <CropperArea shape="circle" />
            </Cropper>
          )}
        </div>

        <div className="space-y-3">
          <div>
            <span className="text-xs font-medium text-muted-foreground mb-2 block">Size</span>
            <div className="flex items-center gap-3">
              <Maximize2 className="w-4 h-4 shrink-0 text-muted-foreground" />
              <Slider
                value={[currentSize]}
                onValueChange={(v) => {
                  const val = (Array.isArray(v) ? v[0] : v) ?? 0;
                  if (val >= maxSize) {
                    setSizeOverride(null);
                  } else {
                    setSizeOverride({ width: val, height: val });
                  }
                }}
                min={MIN_CROP_PX}
                max={maxSize}
                step={1}
                className="flex-1"
              />
              <span className="w-12 text-xs text-muted-foreground text-right tabular-nums">
                {Math.round(currentSize)}px
              </span>
            </div>
          </div>

          <div>
            <span className="text-xs font-medium text-muted-foreground mb-2 block">Zoom</span>
            <div className="flex items-center gap-3">
              <ZoomOut className="w-4 h-4 shrink-0 text-muted-foreground" />
              <Slider
                value={[zoom]}
                onValueChange={(v) => setZoom((Array.isArray(v) ? v[0] : v) ?? 1)}
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
                onValueChange={(v) => setRotation((Array.isArray(v) ? v[0] : v) ?? 0)}
                min={-180}
                max={180}
                step={1}
                className="flex-1"
              />
              <RotateCw className="w-4 h-4 shrink-0 text-muted-foreground" />
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
            disabled={saving || (zoom === 1 && rotation === 0 && !isResized)}
            className={cn(
              zoom !== 1 || rotation !== 0 || isResized ? "text-primary border-primary/50" : "",
            )}
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
