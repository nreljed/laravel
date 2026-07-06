import type { ReactNode } from "react";
import { Card, CardContent } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription, EmptyMedia } from "@/Components/ui/empty";

interface Column<T> {
  key: string;
  header: string;
  cell: (item: T) => ReactNode;
  className?: string;
}

interface TableListProps<T> {
  columns: Column<T>[];
  data: T[];
  onRowClick?: (item: T) => void;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyIcon?: ReactNode;
}

export default function TableList<T>({
  columns,
  data,
  onRowClick,
  emptyTitle = "No data found",
  emptyDescription = "No records match your criteria.",
  emptyIcon,
}: TableListProps<T>) {
  if (data.length === 0) {
    return (
      <Card>
        <CardContent className="py-12">
          <Empty>
            <EmptyHeader>
              {emptyIcon && <EmptyMedia variant="icon">{emptyIcon}</EmptyMedia>}
              <EmptyTitle>{emptyTitle}</EmptyTitle>
              <EmptyDescription>{emptyDescription}</EmptyDescription>
            </EmptyHeader>
          </Empty>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardContent className="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((col) => (
                <TableHead key={col.key} className={col.className}>
                  {col.header}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.map((item, i) => (
              <TableRow
                key={i}
                onClick={() => onRowClick?.(item)}
                className={onRowClick ? "cursor-pointer" : undefined}
              >
                {columns.map((col) => (
                  <TableCell key={col.key} className={col.className}>
                    {col.cell(item)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
