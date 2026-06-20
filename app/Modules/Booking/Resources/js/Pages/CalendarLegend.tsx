interface CalendarLegendProps {
  colors: Record<number, string>;
  properties: { id: number; title: string }[];
}

export default function CalendarLegend({ colors, properties }: CalendarLegendProps) {
  return (
    <div className="flex flex-wrap gap-x-4 gap-y-1.5 items-center">
      <span className="text-xs font-medium text-muted-foreground mr-1">Properties:</span>
      {properties.map((p) => {
        const color = colors[p.id] || "#6b7280";
        return (
          <div key={p.id} className="flex items-center gap-1.5 text-xs">
            <span
              className="w-2.5 h-2.5 rounded-full shrink-0"
              style={{ backgroundColor: color }}
            />
            <span className="text-foreground">{p.title}</span>
          </div>
        );
      })}
    </div>
  );
}
