import * as React from "react";
import { Input } from "@/Components/ui/input";
import { AlertCircle, Play, Loader2 } from "lucide-react";

const MATCH_URL_YOUTUBE =
  /(?:youtu\.be\/|youtube(?:-nocookie|education)?\.com\/(?:embed\/|v\/|watch\/|watch\?v=|watch\?.+&v=|shorts\/|live\/))((\w|-){11})|youtube\.com\/playlist\?list=|youtube\.com\/user\//;

const MATCH_URL_VIMEO = /vimeo\.com\/(?!progressive_redirect).+/;

function canPlay(url: string): boolean {
  if (!url || typeof url !== "string") return false;
  return MATCH_URL_YOUTUBE.test(url) || MATCH_URL_VIMEO.test(url);
}

function getEmbedUrl(url: string): string | null {
  const ytMatch = url.match(MATCH_URL_YOUTUBE);
  if (ytMatch) {
    const videoId = ytMatch[1];
    if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
    return null;
  }

  const vMatch = url.match(MATCH_URL_VIMEO);
  if (vMatch) {
    const vimeoId = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoId) return `https://player.vimeo.com/video/${vimeoId[1]}?autoplay=1`;
    return null;
  }

  return null;
}

interface UrlVideoProps {
  value?: string;
  onChange?: (_value: string) => void;
  error?: string;
  disabled?: boolean;
  id?: string;
  placeholder?: string;
}

export function UrlVideo({
  value = "",
  onChange,
  error,
  disabled,
  id,
  placeholder = "YouTube or Vimeo link",
}: UrlVideoProps) {
  const [playing, setPlaying] = React.useState(false);
  const [loading, setLoading] = React.useState(false);
  const [loadError, setLoadError] = React.useState(false);
  const [vimeoThumbnailUrl, setVimeoThumbnailUrl] = React.useState<string | null>(null);
  const [ytFallback, setYtFallback] = React.useState(false);
  const currentVideoId = React.useRef<string | null>(null);

  const handleInputChange = React.useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      setPlaying(false);
      setLoading(false);
      setLoadError(false);
      setVimeoThumbnailUrl(null);
      setYtFallback(false);
      onChange?.(e.target.value);
    },
    [onChange],
  );

  const isValid = React.useMemo(() => canPlay(value), [value]);
  const embedUrl = React.useMemo(() => (isValid ? getEmbedUrl(value) : null), [value, isValid]);

  const ytThumbnailUrl = React.useMemo(() => {
    if (!isValid) return null;
    const ytMatch = value.match(MATCH_URL_YOUTUBE);
    if (ytMatch && ytMatch[1]) {
      const quality = ytFallback ? "hqdefault" : "maxresdefault";
      return `https://img.youtube.com/vi/${ytMatch[1]}/${quality}.jpg`;
    }
    return null;
  }, [value, isValid, ytFallback]);

  React.useEffect(() => {
    if (!isValid) return;

    const vMatch = value.match(MATCH_URL_VIMEO);
    if (!vMatch) {
      const ytMatch = value.match(MATCH_URL_YOUTUBE);
      if (!ytFallback && ytMatch && ytMatch[1]) {
        currentVideoId.current = ytMatch[1];
        const img = new Image();
        // eslint-disable-next-line @eslint-react/web-api-no-leaked-event-listener -- detached Image is garbage collected with its listener
        img.addEventListener("error", () => {
          if (currentVideoId.current === ytMatch[1]) {
            setYtFallback(true);
          }
        });
        img.src = `https://img.youtube.com/vi/${ytMatch[1]}/maxresdefault.jpg`;
      }
      return;
    }

    const controller = new AbortController();

    (async () => {
      try {
        const res = await fetch(
          `https://vimeo.com/api/oembed.json?url=${encodeURIComponent(value)}`,
          {
            signal: controller.signal,
          },
        );
        const data = await res.json();
        if (data.thumbnail_url) {
          setVimeoThumbnailUrl(data.thumbnail_url);
        }
      } catch {
        // ignore
      }
    })();

    return () => controller.abort();
  }, [value, isValid, ytFallback]);

  const thumbnailUrl: string | null = ytThumbnailUrl || vimeoThumbnailUrl;

  const handlePlay = React.useCallback(() => {
    if (!embedUrl || disabled) return;
    setLoading(true);
    setPlaying(true);
  }, [embedUrl, disabled]);

  return (
    <div className="space-y-3">
      <Input
        id={id}
        type="url"
        value={value}
        onChange={handleInputChange}
        placeholder={placeholder}
        disabled={disabled}
        aria-invalid={!!error}
      />

      {value.trim() && !isValid && (
        <div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>Invalid or unsupported video URL. Please enter a valid YouTube or Vimeo link.</span>
        </div>
      )}

      {loadError && (
        <div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>Failed to load video. Please check the URL and try again.</span>
        </div>
      )}

      {isValid && embedUrl && !playing && (
        <div
          className="group relative aspect-video cursor-pointer overflow-hidden rounded-lg border bg-muted"
          onClick={handlePlay}
          role="button"
          tabIndex={disabled ? -1 : 0}
          onKeyDown={(e) => {
            if (!disabled && (e.key === "Enter" || e.key === " ")) {
              handlePlay();
            }
          }}
          aria-label="Play video"
        >
          {thumbnailUrl && (
            <img
              src={thumbnailUrl}
              alt=""
              loading="lazy"
              className="absolute inset-0 h-full w-full object-cover"
            />
          )}
          <div className="absolute inset-0 flex items-center justify-center bg-black/40 transition-opacity group-hover:opacity-80">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/90 text-primary-foreground shadow-lg transition-transform group-hover:scale-110">
              <Play className="h-8 w-8 fill-current pl-0.5" />
            </div>
          </div>
        </div>
      )}

      {isValid && embedUrl && playing && (
        <div className="relative aspect-video overflow-hidden rounded-lg border bg-muted">
          {loading && (
            <div className="absolute inset-0 z-10 flex items-center justify-center bg-black/40">
              <Loader2 className="h-8 w-8 animate-spin text-white" />
            </div>
          )}
          <iframe
            src={embedUrl}
            className="h-full w-full"
            allow="autoplay; encrypted-media; picture-in-picture"
            allowFullScreen
            title="Video player"
            onLoad={() => setLoading(false)}
            onError={() => {
              setLoadError(true);
              setLoading(false);
            }}
          />
        </div>
      )}
    </div>
  );
}

export default UrlVideo;
