import { cn } from "@/lib/utils";

interface SeoScoreProps {
  score: number;
  size?: "sm" | "md" | "lg";
  className?: string;
}

const sizeConfig = {
  sm: { container: "h-12 w-12", text: "text-sm", stroke: 3, radius: 18, viewBox: 42 },
  md: { container: "h-16 w-16", text: "text-lg", stroke: 4, radius: 26, viewBox: 58 },
  lg: { container: "h-20 w-20", text: "text-xl", stroke: 5, radius: 34, viewBox: 74 },
} as const;

function SeoScore({ score, size = "md", className }: SeoScoreProps) {
  const clamped = Math.max(0, Math.min(100, score));
  const config = sizeConfig[size];

  const circumference = 2 * Math.PI * config.radius;
  const offset = circumference - (clamped / 100) * circumference;

  const color =
    clamped >= 70
      ? "text-green-600 dark:text-green-500"
      : clamped >= 40
        ? "text-yellow-600 dark:text-yellow-500"
        : "text-red-600 dark:text-red-500";

  const strokeColor =
    clamped >= 70
      ? "stroke-green-500 dark:stroke-green-400"
      : clamped >= 40
        ? "stroke-yellow-500 dark:stroke-yellow-400"
        : "stroke-red-500 dark:stroke-red-400";

  return (
    <div
      className={cn(
        "relative inline-flex items-center justify-center",
        config.container,
        className,
      )}
    >
      <svg
        className="-rotate-90"
        viewBox={`0 0 ${config.viewBox} ${config.viewBox}`}
        width={config.viewBox}
        height={config.viewBox}
      >
        <circle
          cx={config.viewBox / 2}
          cy={config.viewBox / 2}
          r={config.radius}
          fill="none"
          className="stroke-muted"
          strokeWidth={config.stroke}
        />
        <circle
          cx={config.viewBox / 2}
          cy={config.viewBox / 2}
          r={config.radius}
          fill="none"
          className={cn(strokeColor, "transition-all duration-500")}
          strokeWidth={config.stroke}
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
        />
      </svg>
      <span className={cn("absolute font-bold tabular-nums", config.text, color)}>{clamped}</span>
    </div>
  );
}

export { SeoScore };
