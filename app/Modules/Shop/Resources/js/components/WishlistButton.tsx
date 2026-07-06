import { useState, useCallback } from "react";
import { Heart } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { toast } from "sonner";
import { router } from "@inertiajs/react";

interface WishlistButtonProps {
  productId: number;
  initiallyActive?: boolean;
  onToggle?: (active: boolean) => void;
}

export function WishlistButton({ productId, initiallyActive = false, onToggle }: WishlistButtonProps) {
  const [active, setActive] = useState(initiallyActive);

  const handleClick = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    const isGuest = !(document.querySelector('meta[name="user-authenticated"]')?.getAttribute('content') === 'true');
    if (isGuest) {
      toast.error("Sign in to save favorites");
      return;
    }

    router.post(route("store.wishlist.toggle", productId), {}, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page: any) => {
        const res = page.props as Record<string, unknown>;
        const added = (res.wishlistResult as { added?: boolean })?.added;
        const newActive = added ?? !active;
        setActive(newActive);
        toast.success(newActive ? "Added to wishlist" : "Removed from wishlist");
        onToggle?.(newActive);
      },
      onError: () => toast.error("Failed to update wishlist"),
    });
  }, [productId, active, onToggle]);

  return (
    <Button type="button" variant="ghost" size="icon" onClick={handleClick}>
      <Heart
        className={`h-5 w-5 transition-colors ${active ? "fill-red-500 text-red-500" : "text-muted-foreground hover:text-red-400"}`}
      />
    </Button>
  );
}
