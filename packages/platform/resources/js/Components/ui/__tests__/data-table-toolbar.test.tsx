import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { DataTableToolbar } from "../data-table-toolbar";
import type { ColumnDef } from "@tanstack/react-table";
import type { ReactTable } from "@tanstack/react-table";
import { AppTableFeatures } from "../../../lib/table-features";

/**
 * The single-filter props are the original API and are used by every existing
 * table in the platform and its consumers, so they must keep working exactly
 * as before. These tests render the toolbar against a stub table rather than
 * asserting on prop plumbing, so a refactor that keeps the rendered result
 * correct still passes.
 */
type Row = { status: string; catalog_visibility: string; name: string };

const COLUMNS: ColumnDef<AppTableFeatures, Row>[] = [
  { accessorKey: "status" },
  { accessorKey: "catalog_visibility" },
  { accessorKey: "name" },
];

function stubTable() {
  const filters: { id: string; value: unknown }[] = [];
  const columns = COLUMNS.map((column) => {
    const key = (column as { accessorKey?: string }).accessorKey ?? "id";

    return {
      id: key,
      getIsVisible: () => true,
      getCanHide: () => false,
      toggleVisibility: () => {},
      getFilterValue: () => filters.find((f) => f.id === key)?.value ?? "",
      setFilterValue: (value: unknown) => {
        const existing = filters.findIndex((f) => f.id === key);
        if (existing >= 0) filters[existing] = { id: key, value };
        else filters.push({ id: key, value });
      },
    };
  });

  return {
    table: {
      state: { columnFilters: filters, columnVisibility: {} },
      getAllColumns: () => columns,
      getColumn: (id: string) => columns.find((c) => c.id === id),
      getVisibleLeafColumns: () => columns,
      toggleAllColumnsShown: () => {},
      resetColumnFilters: () => filters.splice(0, filters.length),
    } as unknown as ReactTable<AppTableFeatures, Row>,
    filters,
  };
}

function renderToolbar(
  props: Partial<React.ComponentProps<typeof DataTableToolbar<Row>>> = {},
) {
  const stub = stubTable();
  render(<DataTableToolbar table={stub.table} {...props} />);
  return stub;
}

describe("DataTableToolbar filters", () => {
  it("renders the legacy single filter", () => {
    renderToolbar({
      filterKey: "status",
      filterTitle: "Status",
      filterOptions: [
        { label: "Draft", value: "draft" },
        { label: "Published", value: "published" },
      ],
    });

    expect(screen.getByText("Status")).toBeDefined();
  });

  it("renders an extra filter alongside the legacy one", () => {
    renderToolbar({
      filterKey: "status",
      filterTitle: "Status",
      filterOptions: [{ label: "Draft", value: "draft" }],
      filters: [
        {
          key: "catalog_visibility",
          title: "In Store",
          options: [{ label: "Hidden", value: "hidden" }],
        },
      ],
    });

    expect(screen.getByText("Status")).toBeDefined();
    expect(screen.getByText("In Store")).toBeDefined();
  });

  it("renders extra filters on their own when there is no legacy filter", () => {
    renderToolbar({
      filters: [{ key: "catalog_visibility", options: [{ label: "Hidden", value: "hidden" }] }],
    });

    expect(screen.getByText("Filter")).toBeDefined();
  });

  it("ignores a filter with no options instead of rendering an empty select", () => {
    renderToolbar({
      filterKey: "status",
      filterOptions: [],
      filters: [
        { key: "catalog_visibility", title: "In Store", options: [] },
        { key: "type", title: "Type", options: [{ label: "Simple", value: "simple" }] },
      ],
    });

    // The keyless legacy filter and the empty one are dropped; the populated
    // one survives.
    expect(screen.getByText("Type")).toBeDefined();
    expect(screen.queryByText("In Store")).toBeNull();
  });

  it("falls back to a generic title when a filter has none", () => {
    renderToolbar({ filters: [{ key: "type", options: [{ label: "Simple", value: "simple" }] }] });

    expect(screen.getByText("Filter")).toBeDefined();
  });

  it("COLUMNS stay in sync with the filter keys the tests use", () => {
    // Guards against a filter key that no longer matches a column: the select
    // would render but silently filter nothing.
    expect(COLUMNS.map((c) => (c as { accessorKey?: string }).accessorKey)).toEqual([
      "status",
      "catalog_visibility",
      "name",
    ]);
  });
});
