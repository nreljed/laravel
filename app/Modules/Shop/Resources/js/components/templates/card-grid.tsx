import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";
import { ShoppingBag } from "lucide-react";

interface CardGridProps<T> {
  data: T[];
  renderCard: (item: T) => ReactNode;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyIcon?: ReactNode;
  emptyAction?: ReactNode;
  gridClassName?: string;
}

export default function CardGrid<T>({
  data,
  renderCard,
  emptyTitle = "No data found",
  emptyDescription = "No records match your criteria.",
  emptyIcon,
  emptyAction,
  gridClassName = "grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4",
}: CardGridProps<T>) {
  if (data.length === 0) {
    return (
      <Empty>
        <EmptyHeader>
          <EmptyMedia variant="icon">{emptyIcon ?? <ShoppingBag />}</EmptyMedia>
          <EmptyTitle>{emptyTitle}</EmptyTitle>
          <EmptyDescription>{emptyDescription}</EmptyDescription>
        </EmptyHeader>
        {emptyAction && <div className="mt-4">{emptyAction}</div>}
      </Empty>
    );
  }

  return <div className={gridClassName}>{data.map((item) => renderCard(item))}</div>;
}
