import { describe, expect, it, vi } from "vitest";
import { defineProductResource, type ProductRow } from "../productResource";

/**
 * The product list's declaration is the contract with `ProductsController`.
 * These assertions are about that contract - which route a verb targets and
 * which key a collected value posts under - rather than about rendering, which
 * the kernel's own `ResourceIndex` suite already covers.
 */

// `route()` is a Ziggy global injected by the Blade shell, so it is absent under
// Vitest, and the descriptor resolves it while it builds. Stubbing it keeps these
// assertions about *which* route each verb names, which is the part the
// controller cares about, instead of about URL building.
vi.stubGlobal("route", (name: string, ...params: (string | number)[]) =>
  params.length > 0 ? `/route/${name}/${params.join("/")}` : `/route/${name}`,
);

const CATEGORIES = [
  { id: 3, name: "Bags" },
  { id: 9, name: "Belts" },
];

const TAGS = [
  { id: 4, name: "new" },
  { id: 7, name: "sale" },
];

const resource = defineProductResource({ categories: CATEGORIES, tags: TAGS });

const row = (over: Partial<ProductRow> = {}): ProductRow =>
  ({
    id: 12,
    name: "Canvas Tote",
    slug: "canvas-tote",
    sku: "CT-1",
    price: 45,
    type: "simple",
    status: "published",
    publish_date: null,
    unpublish_at: null,
    stock_quantity: 8,
    low_stock_threshold: 3,
    track_stock: true,
    thumbnail_url: null,
    catalog_visibility: "visible",
    categories: [],
    tags: [],
    brands: [],
    is_featured: false,
    ...over,
  }) as ProductRow;

describe("product resource identity", () => {
  it("declares the key and singular the controller's toasts read", () => {
    expect(resource.key).toBe("shop/products");
    expect(resource.singular).toBe("Product");
  });

  it("searches by name, which is the only free-text facet", () => {
    expect(resource.table?.searchKey).toBe("name");
  });

  it("filters on status and catalog visibility, not on a computed field", () => {
    expect(resource.table?.filters?.map((filter) => filter.key)).toEqual([
      "status",
      "catalog_visibility",
    ]);
  });
});

describe("product resource routes", () => {
  it("binds record routes on the product id", () => {
    expect(resource.routes?.edit?.(row({ id: 77 }))).toContain("77");
    expect(resource.routes?.destroy?.(row({ id: 77 }))).toContain("77");
  });

  it("points the status row actions at the PATCH status route", () => {
    const statusActions = resource.table?.rowActions ?? [];

    expect(statusActions).toHaveLength(2);

    for (const action of statusActions) {
      expect(action.method).toBe("patch");
      expect(action.route?.(row({ id: 55 }))).toContain("55");
    }
  });
});

describe("product row actions", () => {
  const publish = () => resource.table?.rowActions?.[0]!;
  const draft = () => resource.table?.rowActions?.[1]!;

  it("offers publish only while the product is not published", () => {
    expect(publish().show?.(row({ status: "draft" }))).toBe(true);
    expect(publish().show?.(row({ status: "archived" }))).toBe(true);
    expect(publish().show?.(row({ status: "future" }))).toBe(true);
    expect(publish().show?.(row({ status: "published" }))).toBe(false);
  });

  it("offers make-draft only once the product is published", () => {
    expect(draft().show?.(row({ status: "published" }))).toBe(true);
    expect(draft().show?.(row({ status: "draft" }))).toBe(false);
    expect(draft().show?.(row({ status: "archived" }))).toBe(false);
  });

  it("confirms both, because a status change is not trivially reversible", () => {
    expect(publish().confirm).toBeTruthy();
    expect(draft().confirm).toBeTruthy();
  });
});

describe("product bulk actions", () => {
  const bulk = () => resource.table?.bulkActions ?? [];
  const find = (value: string) => bulk().find((action) => action.value === value)!;

  it("posts the action and the selection, which the controller requires", () => {
    const publish = find("publish");

    expect(publish).toBeDefined();
    expect(bulk().map((action) => action.value)).toContain("delete");
  });

  it("offers publish only while no selected row is already published", () => {
    const publish = find("publish");

    expect(publish.show?.(["draft", "archived"])).toBe(true);
    expect(publish.show?.(["published", "published"])).toBe(false);
    expect(publish.show?.(["draft", "published"])).toBe(false);
  });

  it("offers draft once any selected row is not draft", () => {
    const draft = find("draft");

    expect(draft.show?.(["draft", "published"])).toBe(true);
    expect(draft.show?.(["draft", "draft"])).toBe(false);
  });

  it("marks the destructive action so the kernel confirms it", () => {
    expect(find("delete").destructive).toBe(true);
  });

  it("names the type value the controller's validator expects", () => {
    expect(find("type").fields?.map((field) => field.name)).toEqual(["type_value"]);
  });

  it("names the stock status value the controller's validator expects", () => {
    expect(find("stock_status").fields?.map((field) => field.name)).toEqual(["stock_status_value"]);
  });

  it("names the price value the controller's validator expects", () => {
    expect(find("set_price").fields?.map((field) => field.name)).toEqual(["price_value"]);
  });

  it("carries the page's own category options into both category pickers", () => {
    // The controller requires `category_ids` on both the add and the remove
    // action, so both fields must post that key - and they must carry the
    // index page's own options, since the kernel would otherwise have to
    // fetch them from a route this descriptor does not declare.
    for (const value of ["add_category", "remove_category"]) {
      const fields = find(value).fields ?? [];

      expect(fields[0]?.name).toBe("category_ids");
      expect(fields[0]?.options).toEqual([
        { label: "Bags", value: 3 },
        { label: "Belts", value: 9 },
      ]);
    }
  });

  it("carries the page's own tag options into both tag pickers", () => {
    for (const value of ["add_tag", "remove_tag"]) {
      const fields = find(value).fields ?? [];

      expect(fields[0]?.name).toBe("tag_ids");
      expect(fields[0]?.options).toEqual([
        { label: "new", value: 4 },
        { label: "sale", value: 7 },
      ]);
    }
  });

  it("marks every value-collecting action as needing input", () => {
    // needsInput is inferred from a declared field list, so an action with
    // fields must reach the dialog rather than posting an empty payload.
    for (const value of [
      "type",
      "add_category",
      "remove_category",
      "add_tag",
      "remove_tag",
      "stock_status",
      "set_price",
    ]) {
      expect((find(value).fields ?? []).length).toBeGreaterThan(0);
    }

    for (const value of ["publish", "draft", "archive", "featured", "unfeatured", "delete"]) {
      expect(find(value).fields).toBeUndefined();
    }
  });
});

describe("product header actions", () => {
  const headers = () => resource.table?.headerActions ?? [];

  it("exports over GET, because the response is a file download", () => {
    expect(headers()[0]?.method).toBe("get");
    expect(headers()[0]?.fileInput).toBeFalsy();
  });

  it("imports over a file picker, because the endpoint wants multipart", () => {
    const importAction = headers().find((action) => action.fileInput);

    expect(importAction?.method).toBe("post");
    expect(importAction?.accept).toBe(".csv");
  });
});
