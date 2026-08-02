import { cva } from "class-variance-authority";
import { Slot } from "@/lib/slot";
import * as React from "react";
import { useComposedRefs } from "@/lib/compose-refs";
import { cn } from "@/lib/utils";
import { useAsRef } from "@/hooks/use-as-ref";
import { useIsomorphicLayoutEffect } from "@/hooks/use-isomorphic-layout-effect";
import { useLazyRef } from "@/hooks/use-lazy-ref";

const ROOT_NAME = "Cropper";
const ROOT_IMPL_NAME = "CropperImpl";
const IMAGE_NAME = "CropperImage";
const VIDEO_NAME = "CropperVideo";
const AREA_NAME = "CropperArea";
const MIN_CROP_SIZE = 60;

const MAX_CACHE_SIZE = 200;
const DPR = globalThis !== undefined ? window.devicePixelRatio || 1 : 1;

const rotationSizeCache = new Map<string, { width: number; height: number }>();
const cropSizeCache = new Map<string, { width: number; height: number }>();
const croppedAreaCache = new Map<
  string,
  { croppedAreaPercentages: CroppedArea; croppedAreaPixels: CroppedAreaPixels }
>();
const onPositionClampCache = new Map<string, Point>();

interface Point {
  x: number;
  y: number;
}

interface Size {
  width: number;
  height: number;
}

interface MediaSize extends Size {
  naturalWidth: number;
  naturalHeight: number;
}

interface CroppedArea {
  x: number;
  y: number;
  width: number;
  height: number;
}

type CroppedAreaPixels = CroppedArea;

interface CropState {
  crop: Point;
  zoom: number;
  rotation: number;
  mediaSize: MediaSize | null;
  cropSize: Size | null;
  cropSizeBaseline: Size | null;
  cropSizeOverride: Size | null;
  isDragging: boolean;
  isWheelZooming: boolean;
}

interface Store {
  subscribe: (_cb: () => void) => () => void;
  getState: () => CropState;
  setState: <K extends keyof CropState>(_key: K, _value: CropState[K]) => void;
  notify: () => void;
  batch: (_fn: () => void) => void;
}

interface CropperContextValue {
  minZoom: number;
  maxZoom: number;
  zoomSpeed: number;
  keyboardStep: number;
  aspectRatio: number;
  shape: "rectangle" | "circle";
  objectFit: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
  preventScrollZoom: boolean;
  allowOverflow: boolean;
  withGrid: boolean;
  rootRef: React.RefObject<HTMLDivElement | null>;
  onCropSizeBaselineChange?: (_baseline: Size) => void;
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}

function quantize(n: number, step: number = 2 / DPR): number {
  return Math.round(n / step) * step;
}

function quantizePosition(n: number, step: number = 4 / DPR): number {
  return Math.round(n / step) * step;
}

function quantizeZoom(n: number, step: number = 0.01): number {
  return Math.round(n / step) * step;
}

function quantizeRotation(n: number, step: number = 1): number {
  return Math.round(n / step) * step;
}

function snapToDevicePixel(n: number): number {
  return Math.round(n * DPR) / DPR;
}

function lruGet<K, V>(map: Map<K, V>, key: K): V | undefined {
  const v = map.get(key);
  if (v !== undefined) {
    map.delete(key);
    map.set(key, v);
  }
  return v;
}

function lruSet<K, V>(map: Map<K, V>, key: K, val: V, max: number = MAX_CACHE_SIZE): void {
  if (map.has(key)) {
    map.delete(key);
  }
  map.set(key, val);
  if (map.size > max) {
    const firstKey = map.keys().next().value;
    if (firstKey !== undefined) {
      map.delete(firstKey);
    }
  }
}

function getDistanceBetweenPoints(pointA: Point, pointB: Point): number {
  return Math.hypot(pointA.y - pointB.y, pointA.x - pointB.x);
}

function getCenter(a: Point, b: Point): Point {
  return {
    x: (b.x + a.x) * 0.5,
    y: (b.y + a.y) * 0.5,
  };
}

function getRotationBetweenPoints(pointA: Point, pointB: Point): number {
  return (Math.atan2(pointB.y - pointA.y, pointB.x - pointA.x) * 180) / Math.PI;
}

function getRadianAngle(degreeValue: number): number {
  return (degreeValue * Math.PI) / 180;
}

function rotateSize(width: number, height: number, rotation: number): Size {
  const cacheKey = `${quantize(width)}-${quantize(height)}-${quantizeRotation(rotation)}`;

  const cached = lruGet(rotationSizeCache, cacheKey);
  if (cached) {
    return cached;
  }
  const rotRad = getRadianAngle(rotation);
  const cosRot = Math.cos(rotRad);
  const sinRot = Math.sin(rotRad);

  const result = {
    width: Math.abs(cosRot * width) + Math.abs(sinRot * height),
    height: Math.abs(sinRot * width) + Math.abs(cosRot * height),
  };

  lruSet(rotationSizeCache, cacheKey, result, MAX_CACHE_SIZE);
  return result;
}

function getCropSize(
  mediaWidth: number,
  mediaHeight: number,
  contentWidth: number,
  contentHeight: number,
  aspect: number,
  rotation: number = 0,
): Size {
  const cacheKey = `${quantize(mediaWidth, 8)}-${quantize(mediaHeight, 8)}-${quantize(contentWidth, 8)}-${quantize(contentHeight, 8)}-${quantize(aspect, 0.01)}-${quantizeRotation(rotation)}`;

  const cached = lruGet(cropSizeCache, cacheKey);
  if (cached) {
    return cached;
  }
  const { width, height } = rotateSize(mediaWidth, mediaHeight, rotation);
  const fittingWidth = Math.min(width, contentWidth);
  const fittingHeight = Math.min(height, contentHeight);

  const result =
    fittingWidth > fittingHeight * aspect
      ? {
          width: fittingHeight * aspect,
          height: fittingHeight,
        }
      : {
          width: fittingWidth,
          height: fittingWidth / aspect,
        };

  lruSet(cropSizeCache, cacheKey, result, MAX_CACHE_SIZE);
  return result;
}

function onPositionClamp(
  position: Point,
  mediaSize: Size,
  cropSize: Size,
  zoom: number,
  rotation: number = 0,
): Point {
  const quantizedX = quantizePosition(position.x);
  const quantizedY = quantizePosition(position.y);

  const cacheKey = `${quantizedX}-${quantizedY}-${quantize(mediaSize.width)}-${quantize(mediaSize.height)}-${quantize(cropSize.width)}-${quantize(cropSize.height)}-${quantizeZoom(zoom)}-${quantizeRotation(rotation)}`;

  const cached = lruGet(onPositionClampCache, cacheKey);
  if (cached) {
    return cached;
  }
  const { width, height } = rotateSize(mediaSize.width, mediaSize.height, rotation);

  const maxPositionX = width * zoom * 0.5 - cropSize.width * 0.5;
  const maxPositionY = height * zoom * 0.5 - cropSize.height * 0.5;

  const result = {
    x: clamp(position.x, -maxPositionX, maxPositionX),
    y: clamp(position.y, -maxPositionY, maxPositionY),
  };

  lruSet(onPositionClampCache, cacheKey, result, MAX_CACHE_SIZE);
  return result;
}

function getCroppedArea(
  crop: Point,
  mediaSize: MediaSize,
  cropSize: Size,
  aspect: number,
  zoom: number,
  rotation: number = 0,
  allowOverflow: boolean = false,
): { croppedAreaPercentages: CroppedArea; croppedAreaPixels: CroppedAreaPixels } {
  const cacheKey = `${quantizePosition(crop.x)}-${quantizePosition(crop.y)}-${quantize(mediaSize.width)}-${quantize(mediaSize.height)}-${quantize(mediaSize.naturalWidth)}-${quantize(mediaSize.naturalHeight)}-${quantize(cropSize.width)}-${quantize(cropSize.height)}-${quantize(aspect, 0.01)}-${quantizeZoom(zoom)}-${quantizeRotation(rotation)}-${allowOverflow}`;

  const cached = lruGet(croppedAreaCache, cacheKey);

  if (cached) return cached;

  const onAreaLimit = !allowOverflow
    ? (max: number, value: number) => Math.min(max, Math.max(0, value))
    : (_max: number, value: number) => value;

  const mediaBBoxSize = rotateSize(mediaSize.width, mediaSize.height, rotation);
  const mediaNaturalBBoxSize = rotateSize(
    mediaSize.naturalWidth,
    mediaSize.naturalHeight,
    rotation,
  );

  const croppedAreaPercentages: CroppedArea = {
    x: onAreaLimit(
      100,
      (((mediaBBoxSize.width - cropSize.width / zoom) / 2 - crop.x / zoom) / mediaBBoxSize.width) *
        100,
    ),
    y: onAreaLimit(
      100,
      (((mediaBBoxSize.height - cropSize.height / zoom) / 2 - crop.y / zoom) /
        mediaBBoxSize.height) *
        100,
    ),
    width: onAreaLimit(100, ((cropSize.width / mediaBBoxSize.width) * 100) / zoom),
    height: onAreaLimit(100, ((cropSize.height / mediaBBoxSize.height) * 100) / zoom),
  };

  const widthInPixels = Math.round(
    onAreaLimit(
      mediaNaturalBBoxSize.width,
      (croppedAreaPercentages.width * mediaNaturalBBoxSize.width) / 100,
    ),
  );
  const heightInPixels = Math.round(
    onAreaLimit(
      mediaNaturalBBoxSize.height,
      (croppedAreaPercentages.height * mediaNaturalBBoxSize.height) / 100,
    ),
  );
  const isImageWiderThanHigh = mediaNaturalBBoxSize.width >= mediaNaturalBBoxSize.height * aspect;

  const sizePixels = isImageWiderThanHigh
    ? {
        width: Math.round(heightInPixels * aspect),
        height: heightInPixels,
      }
    : {
        width: widthInPixels,
        height: Math.round(widthInPixels / aspect),
      };

  const croppedAreaPixels: CroppedAreaPixels = {
    ...sizePixels,
    x: Math.round(
      onAreaLimit(
        mediaNaturalBBoxSize.width - sizePixels.width,
        (croppedAreaPercentages.x * mediaNaturalBBoxSize.width) / 100,
      ),
    ),
    y: Math.round(
      onAreaLimit(
        mediaNaturalBBoxSize.height - sizePixels.height,
        (croppedAreaPercentages.y * mediaNaturalBBoxSize.height) / 100,
      ),
    ),
  };

  const result = { croppedAreaPercentages, croppedAreaPixels };

  lruSet(croppedAreaCache, cacheKey, result, MAX_CACHE_SIZE);
  return result;
}

const StoreContext = React.createContext<Store | null>(null);

function useStoreContext(consumerName: string): Store {
  const context = React.useContext(StoreContext);
  if (!context) {
    throw new Error(`\`${consumerName}\` must be used within \`${ROOT_NAME}\``);
  }
  return context;
}

function useStore<T>(selector: (_state: CropState) => T): T {
  const store = useStoreContext("useStore");

  const getSnapshot = React.useCallback(() => selector(store.getState()), [store, selector]);

  return React.useSyncExternalStore(store.subscribe, getSnapshot, getSnapshot);
}

function useEffectiveCropSize(): Size | null {
  return useStore((state) => state.cropSizeOverride ?? state.cropSize);
}

const CropperContext = React.createContext<CropperContextValue | null>(null);

function useCropperContext(consumerName: string): CropperContextValue {
  const context = React.useContext(CropperContext);
  if (!context) {
    throw new Error(`\`${consumerName}\` must be used within \`${ROOT_NAME}\``);
  }
  return context;
}

interface CropperProps {
  crop?: Point;
  zoom?: number;
  minZoom?: number;
  maxZoom?: number;
  zoomSpeed?: number;
  rotation?: number;
  keyboardStep?: number;
  aspectRatio?: number;
  shape?: "rectangle" | "circle";
  objectFit?: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
  allowOverflow?: boolean;
  preventScrollZoom?: boolean;
  withGrid?: boolean;
  cropSizeOverride?: Size | null;
  onCropChange?: (_crop: Point) => void;
  onCropSizeChange?: (_cropSize: Size) => void;
  onCropSizeBaselineChange?: (_baseline: Size) => void;
  onCropAreaChange?: (_percentages: CroppedArea, _pixels: CroppedAreaPixels) => void;
  onCropComplete?: (_percentages: CroppedArea, _pixels: CroppedAreaPixels) => void;
  onZoomChange?: (_zoom: number) => void;
  onRotationChange?: (_rotation: number) => void;
  onMediaLoaded?: (_mediaSize: MediaSize) => void;
  onInteractionStart?: () => void;
  onInteractionEnd?: () => void;
  className?: string;
  children?: React.ReactNode;
}

function Cropper(props: CropperProps) {
  const {
    crop = { x: 0, y: 0 },
    zoom = 1,
    minZoom = 1,
    maxZoom = 3,
    zoomSpeed = 1,
    rotation = 0,
    keyboardStep = 1,
    aspectRatio = 4 / 3,
    shape = "rectangle",
    objectFit = "contain",
    allowOverflow = false,
    preventScrollZoom = false,
    withGrid = false,
    cropSizeOverride: cropSizeOverrideProp = null,
    onCropChange,
    onCropSizeChange,
    onCropSizeBaselineChange,
    onCropAreaChange,
    onCropComplete,
    onZoomChange,
    onRotationChange,
    onMediaLoaded,
    onInteractionStart,
    onInteractionEnd,
    className,
    ...rootProps
  } = props;

  const listenersRef = useLazyRef(() => new Set<() => void>());
  const stateRef = useLazyRef<CropState>(() => ({
    crop,
    zoom,
    rotation,
    mediaSize: null,
    cropSize: null,
    cropSizeBaseline: null,
    cropSizeOverride: null,
    isDragging: false,
    isWheelZooming: false,
  }));

  const propsRef = useAsRef({
    onCropChange,
    onCropSizeChange,
    onCropSizeBaselineChange,
    onCropAreaChange,
    onCropComplete,
    onZoomChange,
    onRotationChange,
    onMediaLoaded,
    onInteractionStart,
    onInteractionEnd,
  });

  const rootRef = React.useRef<HTMLDivElement | null>(null);
  const isBatchingRef = React.useRef(false);

  const store = React.useMemo((): Store => {
    let raf: number | null = null;

    function notifyCropAreaChange() {
      if (raf != null) return;
      // eslint-disable-next-line react-hooks/immutability
      raf = requestAnimationFrame(() => {
        raf = null;
        const s = stateRef.current;
        if (s?.mediaSize && s.cropSize && propsRef.current.onCropAreaChange) {
          const { croppedAreaPercentages, croppedAreaPixels } = getCroppedArea(
            s.crop,
            s.mediaSize,
            s.cropSize,
            aspectRatio,
            s.zoom,
            s.rotation,
          );
          propsRef.current.onCropAreaChange(croppedAreaPercentages, croppedAreaPixels);
        }
      });
    }

    function notify() {
      for (const cb of listenersRef.current) {
        cb();
      }
    }

    return {
      subscribe: (cb: () => void) => {
        listenersRef.current.add(cb);
        return () => listenersRef.current.delete(cb);
      },
      getState: () => stateRef.current,
      setState: <K extends keyof CropState>(key: K, value: CropState[K]) => {
        if (Object.is(stateRef.current[key], value)) return;

        stateRef.current[key] = value;

        // eslint-disable-next-line unicorn/prefer-switch
        if (key === "crop") {
          propsRef.current.onCropChange?.(value as Point);
        } else if (key === "zoom") {
          propsRef.current.onZoomChange?.(value as number);
        } else if (key === "rotation") {
          propsRef.current.onRotationChange?.(value as number);
        } else if (key === "cropSize") {
          const sizeValue = value as Size;
          propsRef.current.onCropSizeChange?.(sizeValue);
          if (sizeValue && stateRef.current.mediaSize && propsRef.current.onCropComplete) {
            const { croppedAreaPercentages, croppedAreaPixels } = getCroppedArea(
              stateRef.current.crop,
              stateRef.current.mediaSize,
              sizeValue,
              aspectRatio,
              stateRef.current.zoom,
              stateRef.current.rotation,
            );
            propsRef.current.onCropComplete(croppedAreaPercentages, croppedAreaPixels);
          }
        } else if (key === "mediaSize") {
          propsRef.current.onMediaLoaded?.(value as MediaSize);
        } else if (key === "isDragging") {
          if (value) {
            propsRef.current.onInteractionStart?.();
          } else {
            propsRef.current.onInteractionEnd?.();
            const currentState = stateRef.current;
            if (
              currentState?.mediaSize &&
              currentState.cropSize &&
              propsRef.current.onCropComplete
            ) {
              const { croppedAreaPercentages, croppedAreaPixels } = getCroppedArea(
                currentState.crop,
                currentState.mediaSize,
                currentState.cropSize,
                aspectRatio,
                currentState.zoom,
                currentState.rotation,
              );
              propsRef.current.onCropComplete(croppedAreaPercentages, croppedAreaPixels);
            }
          }
        }

        if (
          ["crop", "zoom", "rotation", "mediaSize", "cropSize"].includes(key) &&
          propsRef.current.onCropAreaChange
        ) {
          notifyCropAreaChange();
        }
      },
      notify,
      batch: (fn: () => void) => {
        isBatchingRef.current = true;
        try {
          fn();
        } finally {
          isBatchingRef.current = false;
          notify();
        }
      },
    };
  }, [listenersRef, stateRef, propsRef, aspectRatio]);

  useIsomorphicLayoutEffect(() => {
    const updates: Partial<Pick<CropState, "crop" | "zoom" | "rotation">> = {};
    let hasUpdates = false;
    if (crop !== undefined) {
      const currentState = store.getState();
      if (!Object.is(currentState.crop, crop)) {
        updates.crop = crop;
        hasUpdates = true;
      }
    }

    let shouldRecompute = false;

    if (zoom !== undefined) {
      const currentState = store.getState();
      if (currentState.zoom !== zoom) {
        updates.zoom = zoom;
        hasUpdates = true;
        shouldRecompute = true;
      }
    }

    if (rotation !== undefined) {
      const currentState = store.getState();
      if (currentState.rotation !== rotation) {
        updates.rotation = rotation;
        hasUpdates = true;
        shouldRecompute = true;
      }
    }

    if (hasUpdates) {
      store.batch(() => {
        for (const key of Object.keys(updates) as Array<keyof typeof updates>) {
          const value = updates[key];
          if (value !== undefined) {
            store.setState(key, value);
          }
        }
      });

      if (shouldRecompute && rootRef.current) {
        requestAnimationFrame(() => {
          const currentState = store.getState();
          if (currentState.cropSize && currentState.mediaSize) {
            const newPosition = !allowOverflow
              ? onPositionClamp(
                  currentState.crop,
                  currentState.mediaSize,
                  currentState.cropSize,
                  currentState.zoom,
                  currentState.rotation,
                )
              : currentState.crop;

            if (
              Math.abs(newPosition.x - currentState.crop.x) > 0.001 ||
              Math.abs(newPosition.y - currentState.crop.y) > 0.001
            ) {
              store.setState("crop", newPosition);
            }
          }
        });
      }
    }
  }, [crop, zoom, rotation, store, allowOverflow]);

  React.useEffect(() => {
    const currentState = store.getState();
    if (cropSizeOverrideProp) {
      const baseline = currentState.cropSizeBaseline;
      if (baseline) {
        const clampedWidth = clamp(cropSizeOverrideProp.width, MIN_CROP_SIZE, baseline.width);
        const clampedHeight = clamp(cropSizeOverrideProp.height, MIN_CROP_SIZE, baseline.height);
        let effectiveSize: Size =
          aspectRatio >= 1
            ? { width: clampedWidth, height: clampedWidth / aspectRatio }
            : { width: clampedHeight * aspectRatio, height: clampedHeight };
        store.batch(() => {
          store.setState("cropSizeOverride", effectiveSize);
          store.setState("cropSize", effectiveSize);
        });
      } else {
        store.batch(() => {
          store.setState("cropSizeOverride", cropSizeOverrideProp);
          store.setState("cropSize", cropSizeOverrideProp);
        });
      }
    } else if (currentState.cropSizeOverride !== null) {
      store.batch(() => {
        store.setState("cropSizeOverride", null);
        if (currentState.cropSizeBaseline) {
          store.setState("cropSize", currentState.cropSizeBaseline);
        }
      });
    }
  }, [cropSizeOverrideProp, store, aspectRatio]);

  const contextValue = React.useMemo(
    (): CropperContextValue => ({
      minZoom,
      maxZoom,
      zoomSpeed,
      keyboardStep,
      aspectRatio,
      shape,
      objectFit,
      preventScrollZoom,
      allowOverflow,
      withGrid,
      rootRef,
      onCropSizeBaselineChange,
    }),
    [
      minZoom,
      maxZoom,
      zoomSpeed,
      keyboardStep,
      aspectRatio,
      shape,
      objectFit,
      preventScrollZoom,
      allowOverflow,
      withGrid,
      onCropSizeBaselineChange,
    ],
  );

  return (
    <StoreContext value={store}>
      <CropperContext value={contextValue}>
        <div
          data-slot="cropper-wrapper"
          className={cn("relative size-full overflow-hidden", className)}
        >
          <CropperImpl {...rootProps} />
        </div>
      </CropperContext>
    </StoreContext>
  );
}

interface CropperImplProps {
  onWheelZoom?: (_event: WheelEvent) => void;
  onKeyUp?: (_event: React.KeyboardEvent<HTMLDivElement>) => void;
  onKeyDown?: (_event: React.KeyboardEvent<HTMLDivElement>) => void;
  onMouseDown?: (_event: React.MouseEvent<HTMLDivElement>) => void;
  onTouchStart?: (_event: React.TouchEvent<HTMLDivElement>) => void;
  asChild?: boolean;
  className?: string;
  ref?: React.Ref<HTMLDivElement>;
  children?: React.ReactNode;
}

function CropperImpl(props: CropperImplProps) {
  const {
    onWheelZoom: onWheelZoomProp,
    onKeyUp: onKeyUpProp,
    onKeyDown: onKeyDownProp,
    onMouseDown: onMouseDownProp,
    onTouchStart: onTouchStartProp,
    asChild,
    className,
    ref = null,
    ...rootImplProps
  } = props;

  const context = useCropperContext(ROOT_IMPL_NAME);
  const store = useStoreContext(ROOT_IMPL_NAME);
  const crop = useStore((state) => state.crop);
  const zoom = useStore((state) => state.zoom);
  const rotation = useStore((state) => state.rotation);
  const mediaSize = useStore((state) => state.mediaSize);
  const cropSize = useStore((state) => state.cropSize);

  const propsRef = useAsRef({
    onWheelZoom: onWheelZoomProp,
    onKeyUp: onKeyUpProp,
    onKeyDown: onKeyDownProp,
    onMouseDown: onMouseDownProp,
    onTouchStart: onTouchStartProp,
  });

  const composedRef = useComposedRefs(ref, context.rootRef);
  const dragStartPositionRef = React.useRef<Point>({ x: 0, y: 0 });
  const dragStartCropRef = React.useRef<Point>({ x: 0, y: 0 });
  const contentPositionRef = React.useRef<Point>({ x: 0, y: 0 });
  const lastPinchDistanceRef = React.useRef(0);
  const lastPinchRotationRef = React.useRef(0);
  const rafDragTimeoutRef = React.useRef<number | null>(null);
  const rafPinchTimeoutRef = React.useRef<number | null>(null);
  const wheelTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const isTouchingRef = React.useRef(false);
  const gestureZoomStartRef = React.useRef(0);
  const gestureRotationStartRef = React.useRef(0);

  const onRefsCleanup = React.useCallback(() => {
    if (rafDragTimeoutRef.current) {
      cancelAnimationFrame(rafDragTimeoutRef.current);
      rafDragTimeoutRef.current = null;
    }
    if (rafPinchTimeoutRef.current) {
      cancelAnimationFrame(rafPinchTimeoutRef.current);
      rafPinchTimeoutRef.current = null;
    }
    if (wheelTimerRef.current) {
      clearTimeout(wheelTimerRef.current);
      wheelTimerRef.current = null;
    }
    isTouchingRef.current = false;
  }, []);

  const onCacheCleanup = React.useCallback(() => {
    if (onPositionClampCache.size > MAX_CACHE_SIZE * 1.5) {
      onPositionClampCache.clear();
    }
    if (croppedAreaCache.size > MAX_CACHE_SIZE * 1.5) {
      croppedAreaCache.clear();
    }
  }, []);

  const getMousePoint = React.useCallback(
    (event: { clientX: number; clientY: number }): Point => ({
      x: Number(event.clientX),
      y: Number(event.clientY),
    }),
    [],
  );

  const getTouchPoint = React.useCallback(
    (touch: { clientX: number; clientY: number }): Point => ({
      x: Number(touch.clientX),
      y: Number(touch.clientY),
    }),
    [],
  );

  const onContentPositionChange = React.useCallback(() => {
    if (!context.rootRef?.current) return;
    const bounds = context.rootRef.current.getBoundingClientRect();
    contentPositionRef.current = { x: bounds.left, y: bounds.top };
  }, [context.rootRef]);

  const getPointOnContent = React.useCallback(
    ({ x, y }: Point, contentTopLeft: Point): Point => {
      if (!context.rootRef?.current) {
        return { x: 0, y: 0 };
      }
      const contentRect = context.rootRef.current.getBoundingClientRect();
      return {
        x: contentRect.width / 2 - (x - contentTopLeft.x),
        y: contentRect.height / 2 - (y - contentTopLeft.y),
      };
    },
    [context.rootRef],
  );

  const getPointOnMedia = React.useCallback(
    ({ x, y }: Point): Point => {
      return {
        x: (x + crop.x) / zoom,
        y: (y + crop.y) / zoom,
      };
    },
    [crop, zoom],
  );

  const recomputeCropPosition = React.useCallback(() => {
    if (!cropSize || !mediaSize) return;

    const newPosition = !context.allowOverflow
      ? onPositionClamp(crop, mediaSize, cropSize, zoom, rotation)
      : crop;

    if (Math.abs(newPosition.x - crop.x) > 0.001 || Math.abs(newPosition.y - crop.y) > 0.001) {
      store.setState("crop", newPosition);
    }
  }, [cropSize, mediaSize, context.allowOverflow, crop, zoom, rotation, store]);

  const onZoomChange = React.useCallback(
    (newZoom: number, point: Point, shouldUpdatePosition: boolean = true) => {
      if (!cropSize || !mediaSize) return;

      const clampedZoom = clamp(newZoom, context.minZoom, context.maxZoom);

      store.batch(() => {
        if (shouldUpdatePosition) {
          const zoomPoint = getPointOnContent(point, contentPositionRef.current);
          const zoomTarget = getPointOnMedia(zoomPoint);
          const requestedPosition: Point = {
            x: zoomTarget.x * clampedZoom - zoomPoint.x,
            y: zoomTarget.y * clampedZoom - zoomPoint.y,
          };

          const newPosition = !context.allowOverflow
            ? onPositionClamp(requestedPosition, mediaSize, cropSize, clampedZoom, rotation)
            : requestedPosition;

          store.setState("crop", newPosition);
        }
        store.setState("zoom", clampedZoom);
      });

      requestAnimationFrame(() => {
        recomputeCropPosition();
      });
    },
    [
      cropSize,
      mediaSize,
      context.minZoom,
      context.maxZoom,
      context.allowOverflow,
      getPointOnContent,
      getPointOnMedia,
      rotation,
      store,
      recomputeCropPosition,
    ],
  );

  const onDragStart = React.useCallback(
    ({ x, y }: Point) => {
      dragStartPositionRef.current = { x, y };
      dragStartCropRef.current = { ...crop };
      store.setState("isDragging", true);
    },
    [crop, store],
  );

  const onDrag = React.useCallback(
    ({ x, y }: Point) => {
      if (rafDragTimeoutRef.current) {
        cancelAnimationFrame(rafDragTimeoutRef.current);
      }

      rafDragTimeoutRef.current = requestAnimationFrame(() => {
        if (!cropSize || !mediaSize) return;
        if (x === undefined || y === undefined) return;

        const offsetX = x - dragStartPositionRef.current.x;
        const offsetY = y - dragStartPositionRef.current.y;

        if (Math.abs(offsetX) < 2 && Math.abs(offsetY) < 2) {
          return;
        }

        const requestedPosition: Point = {
          x: dragStartCropRef.current.x + offsetX,
          y: dragStartCropRef.current.y + offsetY,
        };

        const newPosition = !context.allowOverflow
          ? onPositionClamp(requestedPosition, mediaSize, cropSize, zoom, rotation)
          : requestedPosition;

        const currentCrop = store.getState().crop;
        if (
          Math.abs(newPosition.x - currentCrop.x) > 1 ||
          Math.abs(newPosition.y - currentCrop.y) > 1
        ) {
          store.setState("crop", newPosition);
        }
      });
    },
    [cropSize, mediaSize, context.allowOverflow, zoom, rotation, store],
  );

  const onMouseMove = React.useCallback(
    (event: MouseEvent) => onDrag(getMousePoint(event)),
    [getMousePoint, onDrag],
  );

  const onTouchMove = React.useCallback(
    (event: TouchEvent) => {
      event.preventDefault();
      if (event.touches.length === 2) {
        // eslint-disable-next-line unicorn/prefer-spread
        const touches = Array.from(event.touches);
        const [firstTouch, secondTouch] = touches;
        if (firstTouch && secondTouch) {
          const pointA = getTouchPoint(firstTouch);
          const pointB = getTouchPoint(secondTouch);
          const center = getCenter(pointA, pointB);
          onDrag(center);

          if (rafPinchTimeoutRef.current) {
            cancelAnimationFrame(rafPinchTimeoutRef.current);
          }

          rafPinchTimeoutRef.current = requestAnimationFrame(() => {
            const distance = getDistanceBetweenPoints(pointA, pointB);
            const distanceRatio = distance / lastPinchDistanceRef.current;

            if (Math.abs(distanceRatio - 1) > 0.01) {
              const newZoom = zoom * distanceRatio;
              onZoomChange(newZoom, center, false);
              lastPinchDistanceRef.current = distance;
            }

            const rotationAngle = getRotationBetweenPoints(pointA, pointB);
            const rotationDiff = rotationAngle - lastPinchRotationRef.current;

            if (Math.abs(rotationDiff) > 0.5) {
              const newRotation = rotation + rotationDiff;
              store.setState("rotation", newRotation);
              lastPinchRotationRef.current = rotationAngle;
            }
          });
        }
      } else if (event.touches.length === 1) {
        const firstTouch = event.touches[0];
        if (firstTouch) {
          onDrag(getTouchPoint(firstTouch));
        }
      }
    },
    [getTouchPoint, onDrag, zoom, onZoomChange, rotation, store],
  );

  const onGestureChange = React.useCallback(
    (event: Event) => {
      event.preventDefault();
      if (isTouchingRef.current) {
        return;
      }

      const gestureEvent = event as Event & {
        scale: number;
        rotation: number;
        clientX: number;
        clientY: number;
      };
      const point = { x: Number(gestureEvent.clientX), y: Number(gestureEvent.clientY) };
      const newZoom = gestureZoomStartRef.current - 1 + gestureEvent.scale;
      onZoomChange(newZoom, point, true);

      const newRotation = gestureRotationStartRef.current + gestureEvent.rotation;
      store.setState("rotation", newRotation);
    },
    [onZoomChange, store],
  );

  const onGestureEnd = React.useCallback(() => {
    document.removeEventListener("gesturechange", onGestureChange);
    if (onGestureEndRef.current) {
      document.removeEventListener("gestureend", onGestureEndRef.current);
    }
  }, [onGestureChange]);

  const onGestureEndRef = React.useRef(onGestureEnd);

  React.useEffect(() => {
    onGestureEndRef.current = onGestureEnd; // eslint-disable-line react-hooks/immutability
  }, [onGestureEnd]);

  const onGestureStart = React.useCallback(
    (event: Event) => {
      event.preventDefault();
      document.addEventListener("gesturechange", onGestureChange);
      if (onGestureEndRef.current) {
        document.addEventListener("gestureend", onGestureEndRef.current);
      }
      gestureZoomStartRef.current = zoom;
      gestureRotationStartRef.current = rotation;
    },
    [zoom, rotation, onGestureChange],
  );

  const onSafariZoomPrevent = React.useCallback((event: Event) => event.preventDefault(), []);

  const onEventsCleanup = React.useCallback(() => {
    document.removeEventListener("mousemove", onMouseMove);
    document.removeEventListener("touchmove", onTouchMove);
    document.removeEventListener("gesturechange", onGestureChange);
    if (onGestureEndRef.current) {
      document.removeEventListener("gestureend", onGestureEndRef.current);
    }
  }, [onMouseMove, onTouchMove, onGestureChange]);

  const onDragStopped = React.useCallback(() => {
    isTouchingRef.current = false;
    store.setState("isDragging", false);
    onRefsCleanup();
    if (onDragStoppedRef.current) {
      document.removeEventListener("mouseup", onDragStoppedRef.current);
      document.removeEventListener("touchend", onDragStoppedRef.current);
    }
    onEventsCleanup();
  }, [store, onEventsCleanup, onRefsCleanup]);

  const onDragStoppedRef = React.useRef(onDragStopped);

  React.useEffect(() => {
    onDragStoppedRef.current = onDragStopped; // eslint-disable-line react-hooks/immutability
  }, [onDragStopped]);

  const getWheelDelta = React.useCallback((event: WheelEvent) => {
    let deltaX = event.deltaX;
    let deltaY = event.deltaY;
    let deltaZ = event.deltaZ;

    if (event.deltaMode === 1) {
      deltaX *= 16;
      deltaY *= 16;
      deltaZ *= 16;
    } else if (event.deltaMode === 2) {
      deltaX *= 400;
      deltaY *= 400;
      deltaZ *= 400;
    }

    return { deltaX, deltaY, deltaZ };
  }, []);

  const onWheelZoom = React.useCallback(
    (event: WheelEvent) => {
      propsRef.current.onWheelZoom?.(event);
      if (event.defaultPrevented) return;

      event.preventDefault();
      const point = getMousePoint(event);
      const { deltaY } = getWheelDelta(event);
      const newZoom = zoom - (deltaY * context.zoomSpeed) / 200;
      onZoomChange(newZoom, point, true);

      store.batch(() => {
        const currentState = store.getState();
        if (!currentState.isWheelZooming) {
          store.setState("isWheelZooming", true);
        }
        if (!currentState.isDragging) {
          store.setState("isDragging", true);
        }
      });

      if (wheelTimerRef.current) {
        clearTimeout(wheelTimerRef.current);
      }
      wheelTimerRef.current = setTimeout(() => {
        store.batch(() => {
          store.setState("isWheelZooming", false);
          store.setState("isDragging", false);
        });
      }, 250);
    },
    [propsRef, getMousePoint, zoom, context.zoomSpeed, onZoomChange, getWheelDelta, store],
  );

  const onKeyUp = React.useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      propsRef.current.onKeyUp?.(event);
      if (event.defaultPrevented) return;

      const arrowKeys = new Set(["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"]);

      if (arrowKeys.has(event.key)) {
        event.preventDefault();
        store.setState("isDragging", false);
      }
    },
    [propsRef, store],
  );

  const onKeyDown = React.useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      propsRef.current.onKeyDown?.(event);
      if (!cropSize || !mediaSize || event.defaultPrevented) return;

      let step = context.keyboardStep;
      if (event.shiftKey) {
        step *= 0.2;
      }

      type ArrowKey = "ArrowUp" | "ArrowDown" | "ArrowLeft" | "ArrowRight";
      const keyCallbacks: Record<ArrowKey, () => Point> = {
        ArrowUp: () => ({ ...crop, y: crop.y - step }),
        ArrowDown: () => ({ ...crop, y: crop.y + step }),
        ArrowLeft: () => ({ ...crop, x: crop.x - step }),
        ArrowRight: () => ({ ...crop, x: crop.x + step }),
      };

      const callback = keyCallbacks[event.key as ArrowKey];
      if (!callback) return;

      event.preventDefault();

      let newCrop = callback();

      if (!context.allowOverflow) {
        newCrop = onPositionClamp(newCrop, mediaSize, cropSize, zoom, rotation);
      }

      if (!event.repeat) {
        store.setState("isDragging", true);
      }

      store.setState("crop", newCrop);
    },
    [
      propsRef,
      cropSize,
      mediaSize,
      context.keyboardStep,
      context.allowOverflow,
      crop,
      zoom,
      rotation,
      store,
    ],
  );

  const onMouseDown = React.useCallback(
    (event: React.MouseEvent<HTMLDivElement>) => {
      propsRef.current.onMouseDown?.(event);
      if (event.defaultPrevented) return;

      event.preventDefault();
      document.addEventListener("mousemove", onMouseMove);
      if (onDragStoppedRef.current) {
        document.addEventListener("mouseup", onDragStoppedRef.current);
      }
      onContentPositionChange();
      onDragStart(getMousePoint(event));
    },
    [propsRef, getMousePoint, onDragStart, onMouseMove, onContentPositionChange],
  );

  const onTouchStart = React.useCallback(
    (event: React.TouchEvent<HTMLDivElement>) => {
      propsRef.current.onTouchStart?.(event);
      if (event.defaultPrevented) return;

      isTouchingRef.current = true;
      document.addEventListener("touchmove", onTouchMove, { passive: false });
      if (onDragStoppedRef.current) {
        document.addEventListener("touchend", onDragStoppedRef.current);
      }
      onContentPositionChange();

      if (event.touches.length === 2) {
        // eslint-disable-next-line unicorn/prefer-spread
        const touches = Array.from(event.touches);
        const [firstTouch, secondTouch] = touches;
        if (firstTouch && secondTouch) {
          const pointA = getTouchPoint(firstTouch);
          const pointB = getTouchPoint(secondTouch);
          lastPinchDistanceRef.current = getDistanceBetweenPoints(pointA, pointB);
          lastPinchRotationRef.current = getRotationBetweenPoints(pointA, pointB);
          onDragStart(getCenter(pointA, pointB));
        }
      } else if (event.touches.length === 1) {
        const firstTouch = event.touches[0];
        if (firstTouch) {
          onDragStart(getTouchPoint(firstTouch));
        }
      }
    },
    [propsRef, onTouchMove, onContentPositionChange, getTouchPoint, onDragStart],
  );

  React.useEffect(() => {
    const content = context.rootRef?.current;
    if (!content) return;

    if (!context.preventScrollZoom) {
      content.addEventListener("wheel", onWheelZoom, { passive: false });
    }

    content.addEventListener("gesturestart", onSafariZoomPrevent);
    content.addEventListener("gesturestart", onGestureStart);

    return () => {
      if (!context.preventScrollZoom) {
        content.removeEventListener("wheel", onWheelZoom);
      }
      content.removeEventListener("gesturestart", onSafariZoomPrevent);
      content.removeEventListener("gesturestart", onGestureStart);
      onRefsCleanup();
    };
  }, [
    context.rootRef,
    context.preventScrollZoom,
    onWheelZoom,
    onRefsCleanup,
    onSafariZoomPrevent,
    onGestureStart,
  ]);

  React.useEffect(() => {
    return () => {
      onRefsCleanup();
      onCacheCleanup();
    };
  }, [onRefsCleanup, onCacheCleanup]);

  const RootPrimitive = asChild ? Slot : "div";

  return (
    <RootPrimitive
      data-slot="cropper"
      tabIndex={0}
      {...rootImplProps}
      ref={composedRef}
      className={cn(
        "absolute inset-0 flex cursor-move touch-none select-none items-center justify-center overflow-hidden outline-none",
        className,
      )}
      onKeyUp={onKeyUp}
      onKeyDown={onKeyDown}
      onMouseDown={onMouseDown}
      onTouchStart={onTouchStart}
    />
  );
}

const cropperMediaVariants = cva("will-change-transform", {
  variants: {
    objectFit: {
      contain: "absolute inset-0 m-auto max-h-full max-w-full",
      cover: "h-auto w-full",
      "horizontal-cover": "h-auto w-full",
      "vertical-cover": "h-full w-auto",
    },
  },
  defaultVariants: {
    objectFit: "contain",
  },
});

function useMediaComputation({
  mediaRef,
  context,
  store,
  rotation,
  getNaturalDimensions,
  onCropSizeBaselineChange,
}: {
  mediaRef: React.RefObject<HTMLImageElement | HTMLVideoElement | null>;
  context: CropperContextValue;
  store: Store;
  rotation: number;
  getNaturalDimensions: (_media: HTMLImageElement | HTMLVideoElement) => {
    width: number;
    height: number;
  };
  onCropSizeBaselineChange?: (_baseline: Size) => void;
}): { computeSizes: () => undefined | { mediaSize: MediaSize; cropSize: Size } } {
  // eslint-disable-next-line react-hooks/preserve-manual-memoization
  const computeSizes = React.useCallback(() => {
    const media = mediaRef.current;
    const content = context.rootRef?.current;
    if (!media || !content) return;

    const contentWidth = content.clientWidth;
    const contentHeight = content.clientHeight;
    const containerAspect = contentWidth / contentHeight;
    const { width: naturalWidth, height: naturalHeight } = getNaturalDimensions(media);
    const isScaledDown = media.offsetWidth < naturalWidth || media.offsetHeight < naturalHeight;
    const mediaAspect = naturalWidth / naturalHeight;

    let renderedMediaSize: Size;

    if (isScaledDown) {
      const objectFitCallbacks: Record<string, () => Size> = {
        contain: () =>
          containerAspect > mediaAspect
            ? {
                width: contentHeight * mediaAspect,
                height: contentHeight,
              }
            : {
                width: contentWidth,
                height: contentWidth / mediaAspect,
              },

        "horizontal-cover": () => ({
          width: contentWidth,
          height: contentWidth / mediaAspect,
        }),

        "vertical-cover": () => ({
          width: contentHeight * mediaAspect,
          height: contentHeight,
        }),

        cover: () =>
          containerAspect < mediaAspect
            ? {
                width: contentWidth,
                height: contentWidth / mediaAspect,
              }
            : {
                width: contentHeight * mediaAspect,
                height: contentHeight,
              },
      };

      const callback = objectFitCallbacks[context.objectFit];
      renderedMediaSize = callback
        ? callback()
        : containerAspect > mediaAspect
          ? {
              width: contentHeight * mediaAspect,
              height: contentHeight,
            }
          : {
              width: contentWidth,
              height: contentWidth / mediaAspect,
            };
    } else {
      renderedMediaSize = {
        width: media.offsetWidth,
        height: media.offsetHeight,
      };
    }

    const mediaSize: MediaSize = {
      ...renderedMediaSize,
      naturalWidth,
      naturalHeight,
    };

    const cropSize = getCropSize(
      mediaSize.width,
      mediaSize.height,
      contentWidth,
      contentHeight,
      context.aspectRatio,
      rotation,
    );

    store.batch(() => {
      store.setState("mediaSize", mediaSize);
      store.setState("cropSizeBaseline", cropSize);
      onCropSizeBaselineChange?.(cropSize);
      const existingOverride = store.getState().cropSizeOverride;
      if (existingOverride) {
        const clampedWidth = clamp(existingOverride.width, MIN_CROP_SIZE, cropSize.width);
        const clampedHeight = clamp(existingOverride.height, MIN_CROP_SIZE, cropSize.height);
        const effectiveSize: Size =
          context.aspectRatio >= 1
            ? { width: clampedWidth, height: clampedWidth / context.aspectRatio }
            : { width: clampedHeight * context.aspectRatio, height: clampedHeight };
        store.setState("cropSizeOverride", effectiveSize);
        store.setState("cropSize", effectiveSize);
      } else {
        store.setState("cropSize", cropSize);
      }
    });

    requestAnimationFrame(() => {
      const currentState = store.getState();
      if (currentState.cropSize && currentState.mediaSize) {
        const newPosition = onPositionClamp(
          currentState.crop,
          currentState.mediaSize,
          currentState.cropSize,
          currentState.zoom,
          currentState.rotation,
        );

        if (
          Math.abs(newPosition.x - currentState.crop.x) > 0.001 ||
          Math.abs(newPosition.y - currentState.crop.y) > 0.001
        ) {
          store.setState("crop", newPosition);
        }
      }
    });

    return { mediaSize, cropSize };
  }, [
    mediaRef,
    context.aspectRatio,
    context.rootRef,
    context.objectFit,
    store,
    rotation,
    getNaturalDimensions,
    onCropSizeBaselineChange,
  ]);

  return { computeSizes };
}

interface CropperImageProps extends Omit<
  React.ComponentPropsWithoutRef<"img">,
  "onLoad" | "objectFit"
> {
  asChild?: boolean;
  objectFit?: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
  snapPixels?: boolean;
  onLoad?: (_event: Event) => void;
  ref?: React.Ref<HTMLImageElement>;
}

function CropperImage(props: CropperImageProps): React.JSX.Element {
  const {
    className,
    style,
    asChild,
    ref = null,
    onLoad,
    objectFit,
    snapPixels = false,
    ...imageProps
  } = props;

  const context = useCropperContext(IMAGE_NAME);
  const store = useStoreContext(IMAGE_NAME);
  const crop = useStore((state) => state.crop);
  const zoom = useStore((state) => state.zoom);
  const rotation = useStore((state) => state.rotation);

  const imageRef = React.useRef<HTMLImageElement | null>(null);
  const composedRef = useComposedRefs(ref, imageRef);

  const getNaturalDimensions = React.useCallback(
    (media: HTMLImageElement | HTMLVideoElement) => ({
      width: "naturalWidth" in media ? media.naturalWidth : media.videoWidth,

      height: "naturalHeight" in media ? media.naturalHeight : media.videoHeight,
    }),
    [],
  );

  const { computeSizes } = useMediaComputation({
    mediaRef: imageRef,
    context,
    store,
    rotation,
    getNaturalDimensions,
    onCropSizeBaselineChange: context.onCropSizeBaselineChange,
  });

  const onMediaLoad = React.useCallback(() => {
    const image = imageRef.current;
    if (!image) return;

    computeSizes();

    onLoad?.(new Event("load"));
  }, [computeSizes, onLoad]);

  React.useEffect(() => {
    const image = imageRef.current;
    if (image?.complete && image.naturalWidth > 0) {
      onMediaLoad();
    }
  }, [onMediaLoad]);

  React.useEffect(() => {
    const content = context.rootRef?.current;
    if (!content) return;

    if (typeof ResizeObserver !== "undefined") {
      const resizeObserver = new ResizeObserver(() => {
        const callback = () => {
          const image = imageRef.current;
          if (image?.complete && image.naturalWidth > 0) {
            computeSizes();
          }
        };

        if ("requestIdleCallback" in globalThis) {
          requestIdleCallback(callback as IdleRequestCallback);
        } else {
          // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- fire-and-forget idle flush; observer teardown below
          setTimeout(callback, 16);
        }
      });

      resizeObserver.observe(content);

      return () => {
        resizeObserver.disconnect();
      };
    }

    const onWindowResize = () => {
      const image = imageRef.current;
      if (image?.complete && image.naturalWidth > 0) {
        computeSizes();
      }
    };

    window.addEventListener("resize", onWindowResize);
    return () => {
      window.removeEventListener("resize", onWindowResize);
    };
  }, [context.rootRef, computeSizes]);

  const ImagePrimitive = asChild ? Slot : "img";

  return (
    <ImagePrimitive
      data-slot="cropper-image"
      {...imageProps}
      ref={composedRef}
      className={cn(
        cropperMediaVariants({
          objectFit: objectFit ?? context.objectFit,
          className,
        }),
      )}
      style={{
        transform: snapPixels
          ? `translate(${snapToDevicePixel(crop.x)}px, ${snapToDevicePixel(crop.y)}px) rotate(${rotation}deg) scale(${zoom})`
          : `translate(${crop.x}px, ${crop.y}px) rotate(${rotation}deg) scale(${zoom})`,
        ...style,
      }}
      onLoad={onMediaLoad}
    />
  );
}

interface CropperVideoProps extends Omit<
  React.ComponentPropsWithoutRef<"video">,
  "onLoadedMetadata" | "objectFit"
> {
  asChild?: boolean;
  objectFit?: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
  snapPixels?: boolean;
  onLoadedMetadata?: (_event: Event) => void;
  ref?: React.Ref<HTMLVideoElement>;
}

function CropperVideo(props: CropperVideoProps) {
  const {
    className,
    style,
    asChild,
    ref = null,
    onLoadedMetadata,
    objectFit,
    snapPixels = false,
    ...videoProps
  } = props;

  const context = useCropperContext(VIDEO_NAME);
  const store = useStoreContext(VIDEO_NAME);
  const crop = useStore((state) => state.crop);
  const zoom = useStore((state) => state.zoom);
  const rotation = useStore((state) => state.rotation);

  const videoRef = React.useRef<HTMLVideoElement | null>(null);
  const composedRef = useComposedRefs(ref, videoRef);

  const getNaturalDimensions = React.useCallback(
    (media: HTMLImageElement | HTMLVideoElement) => ({
      width: "videoWidth" in media ? media.videoWidth : media.naturalWidth,

      height: "videoHeight" in media ? media.videoHeight : media.naturalHeight,
    }),
    [],
  );

  const { computeSizes } = useMediaComputation({
    mediaRef: videoRef,
    context,
    store,
    rotation,
    getNaturalDimensions,
  });

  const onMediaLoad = React.useCallback(() => {
    const video = videoRef.current;
    if (!video) return;

    computeSizes();

    onLoadedMetadata?.(new Event("loadedmetadata"));
  }, [computeSizes, onLoadedMetadata]);

  React.useEffect(() => {
    const content = context.rootRef?.current;
    if (!content) return;

    if (typeof ResizeObserver !== "undefined") {
      let isFirstResize = true;
      const resizeObserver = new ResizeObserver(() => {
        if (isFirstResize) {
          isFirstResize = false;
          return;
        }

        const callback = () => {
          const video = videoRef.current;
          if (video && video.videoWidth > 0 && video.videoHeight > 0) {
            computeSizes();
          }
        };

        if ("requestIdleCallback" in globalThis) {
          requestIdleCallback(callback as IdleRequestCallback);
        } else {
          // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- fire-and-forget idle flush; observer teardown below
          setTimeout(callback, 16);
        }
      });

      resizeObserver.observe(content);

      return () => {
        resizeObserver.disconnect();
      };
    }

    const onWindowResize = () => {
      const video = videoRef.current;
      if (video && video.videoWidth > 0 && video.videoHeight > 0) {
        computeSizes();
      }
    };

    window.addEventListener("resize", onWindowResize);
    return () => {
      window.removeEventListener("resize", onWindowResize);
    };
  }, [context.rootRef, computeSizes]);

  const VideoPrimitive = asChild ? Slot : "video";

  return (
    <VideoPrimitive
      data-slot="cropper-video"
      autoPlay
      playsInline
      loop
      muted
      controls={false}
      {...videoProps}
      ref={composedRef}
      className={cn(
        cropperMediaVariants({
          objectFit: objectFit ?? context.objectFit,
          className,
        }),
      )}
      style={{
        transform: snapPixels
          ? `translate(${snapToDevicePixel(crop.x)}px, ${snapToDevicePixel(crop.y)}px) rotate(${rotation}deg) scale(${zoom})`
          : `translate(${crop.x}px, ${crop.y}px) rotate(${rotation}deg) scale(${zoom})`,
        ...style,
      }}
      onLoadedMetadata={onMediaLoad}
    />
  );
}

const cropperAreaVariants = cva(
  "absolute top-1/2 left-1/2 box-border -translate-x-1/2 -translate-y-1/2 overflow-hidden border border-[2.5px] border-white/90 shadow-[0_0_0_9999em_rgba(0,0,0,0.5)]",
  {
    variants: {
      shape: {
        rectangle: "",
        circle: "rounded-full",
      },
      withGrid: {
        true: "before:absolute before:top-0 before:right-1/3 before:bottom-0 before:left-1/3 before:box-border before:border before:border-white/50 before:border-t-0 before:border-b-0 before:content-[''] after:absolute after:top-1/3 after:right-0 after:bottom-1/3 after:left-0 after:box-border after:border after:border-white/50 after:border-r-0 after:border-l-0 after:content-['']",
        false: "",
      },
    },
    defaultVariants: {
      shape: "rectangle",
      withGrid: false,
    },
  },
);

interface CropperAreaProps {
  className?: string;
  style?: React.CSSProperties;
  asChild?: boolean;
  ref?: React.Ref<HTMLDivElement>;
  snapPixels?: boolean;
  shape?: "rectangle" | "circle";
  withGrid?: boolean;
}

function CropperArea(props: CropperAreaProps): React.JSX.Element | null {
  const {
    className,
    style,
    asChild,
    ref,
    snapPixels = false,
    shape,
    withGrid,
    ...areaProps
  } = props;

  const context = useCropperContext(AREA_NAME);
  const effectiveCropSize = useEffectiveCropSize();

  if (!effectiveCropSize) return null;

  const AreaPrimitive = asChild ? Slot : "div";

  return (
    <AreaPrimitive
      data-slot="cropper-area"
      {...areaProps}
      ref={ref}
      className={cn(
        cropperAreaVariants({
          shape: shape ?? context.shape,
          withGrid: withGrid ?? context.withGrid,
          className,
        }),
      )}
      style={{
        width: snapPixels ? Math.round(effectiveCropSize.width) : effectiveCropSize.width,
        height: snapPixels ? Math.round(effectiveCropSize.height) : effectiveCropSize.height,
        ...style,
      }}
    />
  );
}

type HandleDirection = "nw" | "n" | "ne" | "e" | "se" | "s" | "sw" | "w";

const HANDLE_CURSORS: Record<HandleDirection, string> = {
  nw: "nwse-resize",
  n: "ns-resize",
  ne: "nesw-resize",
  e: "ew-resize",
  se: "nwse-resize",
  s: "ns-resize",
  sw: "nesw-resize",
  w: "ew-resize",
};

interface HandleDragState {
  direction: HandleDirection;
  startX: number;
  startY: number;
  startCropSize: Size;
  startCrop: Point;
}

interface CropperHandlesProps {
  className?: string;
  shape?: "rectangle" | "circle";
  handleSize?: number;
}

function CropperHandles(props: CropperHandlesProps): React.JSX.Element | null {
  const { className, shape: shapeProp, handleSize = 12 } = props;

  const context = useCropperContext("CropperHandles");
  const store = useStoreContext("CropperHandles");
  const effectiveCropSize = useEffectiveCropSize();
  const crop = useStore((state) => state.crop);
  const mediaSize = useStore((state) => state.mediaSize);

  const dragStateRef = React.useRef<HandleDragState | null>(null);

  const shape = shapeProp ?? context.shape;

  const directions: HandleDirection[] = React.useMemo(() => {
    if (shape === "circle") return ["nw", "ne", "se", "sw"];
    return ["nw", "n", "ne", "e", "se", "s", "sw", "w"];
  }, [shape]);

  const getHandlePosition = React.useCallback(
    (dir: HandleDirection, size: Size): { left: string; top: string } => {
      const half = { w: size.width / 2, h: size.height / 2 };
      const positions: Record<HandleDirection, { left: string; top: string }> = {
        nw: { left: `${-half.w}px`, top: `${-half.h}px` },
        n: { left: "0px", top: `${-half.h}px` },
        ne: { left: `${half.w}px`, top: `${-half.h}px` },
        e: { left: `${half.w}px`, top: "0px" },
        se: { left: `${half.w}px`, top: `${half.h}px` },
        s: { left: "0px", top: `${half.h}px` },
        sw: { left: `${-half.w}px`, top: `${half.h}px` },
        w: { left: `${-half.w}px`, top: "0px" },
      };
      return positions[dir];
    },
    [],
  );

  const computeNewSize = React.useCallback(
    (state: HandleDragState, clientX: number, clientY: number): Size => {
      const { direction, startX, startY, startCropSize } = state;
      const dx = clientX - startX;
      const dy = clientY - startY;
      const aspect = context.aspectRatio;

      let newWidth = startCropSize.width;
      let newHeight = startCropSize.height;

      if (direction.includes("e")) {
        newWidth = startCropSize.width + dx;
      } else if (direction.includes("w")) {
        newWidth = startCropSize.width - dx;
      }

      if (direction.includes("s")) {
        newHeight = startCropSize.height + dy;
      } else if (direction.includes("n")) {
        newHeight = startCropSize.height - dy;
      }

      if (shape === "circle" || direction.length === 2) {
        if (aspect >= 1) {
          newHeight = newWidth / aspect;
        } else {
          newWidth = newHeight * aspect;
        }
      } else if (direction === "n" || direction === "s") {
        newWidth = newHeight * aspect;
      } else if (direction === "e" || direction === "w") {
        newHeight = newWidth / aspect;
      }

      const maxSize = store.getState().cropSizeBaseline;
      const maxWidth = maxSize?.width ?? startCropSize.width;
      const maxHeight = maxSize?.height ?? startCropSize.height;

      newWidth = clamp(newWidth, MIN_CROP_SIZE, maxWidth);
      newHeight = clamp(newHeight, MIN_CROP_SIZE, maxHeight);

      if (aspect >= 1) {
        newHeight = newWidth / aspect;
        if (newHeight > maxHeight) {
          newHeight = maxHeight;
          newWidth = newHeight * aspect;
        }
      } else {
        newWidth = newHeight * aspect;
        if (newWidth > maxWidth) {
          newWidth = maxWidth;
          newHeight = newWidth / aspect;
        }
      }

      return {
        width: Math.max(newWidth, MIN_CROP_SIZE),
        height: Math.max(newHeight, MIN_CROP_SIZE),
      };
    },
    [context.aspectRatio, shape, store],
  );

  const onPointerMoveRef = React.useRef<(_event: PointerEvent) => void>(() => {});

  const onPointerMove = React.useCallback(
    (event: PointerEvent) => {
      const ds = dragStateRef.current;
      if (!ds) return;

      const newSize = computeNewSize(ds, event.clientX, event.clientY);
      store.batch(() => {
        store.setState("cropSizeOverride", newSize);
        store.setState("cropSize", newSize);
      });
    },
    [computeNewSize, store],
  );

  React.useEffect(() => {
    onPointerMoveRef.current = onPointerMove;
  }, [onPointerMove]);

  const onPointerUp = React.useCallback(() => {
    dragStateRef.current = null;
    store.setState("isDragging", false);
    document.removeEventListener("pointermove", onPointerMoveRef.current);
    document.removeEventListener("pointerup", onPointerUpRef.current);
  }, [store]);

  const onPointerUpRef = React.useRef(onPointerUp);

  React.useEffect(() => {
    onPointerUpRef.current = onPointerUp; // eslint-disable-line react-hooks/immutability
  }, [onPointerUp]);

  const onHandlePointerDown = React.useCallback(
    (event: React.PointerEvent, direction: HandleDirection) => {
      event.preventDefault();
      event.stopPropagation();

      if (!effectiveCropSize || !mediaSize) return;

      dragStateRef.current = {
        direction,
        startX: event.clientX,
        startY: event.clientY,
        startCropSize: { ...effectiveCropSize },
        startCrop: { ...crop },
      };

      store.setState("isDragging", true);
      document.addEventListener("pointermove", onPointerMove);
      document.addEventListener("pointerup", onPointerUpRef.current);
    },
    [effectiveCropSize, mediaSize, crop, store, onPointerMove],
  );

  if (!effectiveCropSize || !mediaSize) return null;

  return (
    <div
      data-slot="cropper-handles"
      className={cn(
        "absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none",
        className,
      )}
      style={{
        width: effectiveCropSize.width,
        height: effectiveCropSize.height,
      }}
    >
      {directions.map((dir) => {
        const pos = getHandlePosition(dir, effectiveCropSize);
        return (
          <div
            key={dir}
            data-slot="cropper-handle"
            data-ord={dir}
            role="button"
            tabIndex={0}
            aria-label={`Resize ${dir}`}
            className="pointer-events-auto absolute z-10 rounded-full border-2 border-white bg-white/80 shadow-md backdrop-blur-sm transition-[width,height] hover:bg-white"
            style={{
              width: handleSize,
              height: handleSize,
              left: pos.left,
              top: pos.top,
              transform: "translate(-50%, -50%)",
              cursor: HANDLE_CURSORS[dir],
            }}
            onPointerDown={(e) => onHandlePointerDown(e, dir)}
            onMouseDown={(e) => e.stopPropagation()}
            onTouchStart={(e) => e.stopPropagation()}
            onWheel={(e) => e.stopPropagation()}
          />
        );
      })}
    </div>
  );
}

export { Cropper, CropperArea, CropperHandles, CropperImage, CropperVideo, useStore as useCropper };
