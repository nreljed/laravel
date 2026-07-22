import * as React from "react";
import { Skeleton } from "@/Components/ui/skeleton";

const MapSectionInner = React.lazy(() => import("./map-section-inner"));

function MapSectionSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-[400px] w-full rounded-lg" />
      <div className="flex gap-2">
        <Skeleton className="h-6 w-32" />
        <Skeleton className="h-6 w-20" />
      </div>
    </div>
  );
}

export { MapSectionInner, MapSectionSkeleton };
