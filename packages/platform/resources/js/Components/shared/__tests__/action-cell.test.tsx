import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, cleanup, act } from "@testing-library/react";
import { ActionCell, type ActionItem } from "../action-cell";

/**
 * ActionCell is shared by the blog and shop-module admin lists, and those two
 * features register their status endpoints under different verbs: blog's
 * `Route::put('/{post}/status')` and shop's `Route::patch('/products/{product}/status')`.
 *
 * The component used to hardcode `router.put`, so the product list's
 * Publish / Make Draft row actions 405'd against a PATCH route. These tests
 * pin the contract that fixed it: the default stays `put` - blog is the
 * original caller and must keep working unchanged - and a consumer whose route
 * is a PATCH declares that itself.
 *
 * The menus are stubbed the way the ResourceIndex tests stub DataTable, so what
 * is asserted is the verb that went out on the router rather than Base UI's own
 * open/close behaviour.
 */
const { routerMock, toastMock } = vi.hoisted(() => ({
    routerMock: { put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    toastMock: { add: vi.fn() },
}));

vi.mock("@inertiajs/react", () => ({
    Link: ({ children, href }: { children?: unknown; href?: string }) => <a href={href}>{children as never}</a>,
    router: routerMock,
}));

vi.mock("../../ui/toast", () => ({ toast: toastMock }));

vi.mock("../../ui/alert-dialog", () => ({
    AlertDialog: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    AlertDialogAction: ({ children, onClick }: { children?: unknown; onClick?: () => void }) => (
        <button onClick={onClick}>{children as never}</button>
    ),
    AlertDialogCancel: ({ children }: { children?: unknown }) => <button>{children as never}</button>,
    AlertDialogContent: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    AlertDialogDescription: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    AlertDialogFooter: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    AlertDialogHeader: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    AlertDialogTitle: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
}));

vi.mock("../../ui/dropdown-menu", () => ({
    DropdownMenu: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    DropdownMenuContent: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
    DropdownMenuItem: ({
        children,
        onClick,
        onSelect,
    }: {
        children?: unknown;
        onClick?: () => void;
        onSelect?: (event: unknown) => void;
    }) => (
        <button
            onClick={() => {
                onClick?.();
                onSelect?.({ preventDefault: () => undefined });
            }}
        >
            {children as never}
        </button>
    ),
    DropdownMenuSeparator: () => <hr />,
    DropdownMenuTrigger: ({ children }: { children?: unknown }) => <div>{children as never}</div>,
}));

/**
 * Open a row action, then accept the confirmation dialog it raises.
 *
 * The stubbed dialogs are always mounted, so a row action and its confirm
 * button can share a name - "Delete" does. The menu item is first in the tree
 * and the dialog action is last, so first/last disambiguates them.
 */
function runConfirmedAction(menuLabel: string | RegExp, confirmLabel: string) {
    const menu = screen.getAllByRole("button", { name: menuLabel }).at(0);

    expect(menu, `no row action named "${menuLabel}"`).toBeDefined();
    act(() => menu!.click());

    const confirm = screen.getAllByRole("button", { name: confirmLabel }).at(-1);

    expect(confirm, `no confirm button named "${confirmLabel}"`).toBeDefined();
    act(() => confirm!.click());
}

const DRAFT: ActionItem = { id: 7, title: "Blue widget", status: "draft" };
const PUBLISHED: ActionItem = { id: 9, title: "Red widget", status: "published" };

describe("ActionCell status verb", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        cleanup();
    });

    it("defaults to PUT so the blog's status route keeps working", () => {
        render(<ActionCell item={DRAFT} baseRoute="/dashboard/posts" itemLabel="Post" />);

        runConfirmedAction("Publish", "Yes, Publish");

        expect(routerMock.put).toHaveBeenCalledWith(
            "/dashboard/posts/7/status",
            { status: "published" },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerMock.patch).not.toHaveBeenCalled();
    });

    it("uses PATCH when the consumer's status route is a PATCH", () => {
        render(
            <ActionCell
                item={DRAFT}
                baseRoute="/dashboard/shop/products"
                itemLabel="Product"
                statusMethod="patch"
            />,
        );

        runConfirmedAction("Publish", "Yes, Publish");

        expect(routerMock.patch).toHaveBeenCalledWith(
            "/dashboard/shop/products/7/status",
            { status: "published" },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerMock.put).not.toHaveBeenCalled();
    });

    it("sends the draft status over the configured verb too", () => {
        render(
            <ActionCell
                item={PUBLISHED}
                baseRoute="/dashboard/shop/products"
                itemLabel="Product"
                statusMethod="patch"
            />,
        );

        runConfirmedAction("Make Draft", "Yes, Change to Draft");

        expect(routerMock.patch).toHaveBeenCalledWith(
            "/dashboard/shop/products/9/status",
            { status: "draft" },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerMock.put).not.toHaveBeenCalled();
    });

    it("still destroys with DELETE whatever the status verb is", () => {
        render(
            <ActionCell
                item={DRAFT}
                baseRoute="/dashboard/shop/products"
                itemLabel="Product"
                statusMethod="patch"
            />,
        );

        runConfirmedAction("Delete", "Delete");

        expect(routerMock.delete).toHaveBeenCalledWith(
            "/dashboard/shop/products/7",
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
