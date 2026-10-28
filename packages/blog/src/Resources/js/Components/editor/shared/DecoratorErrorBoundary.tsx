import { Component, type ErrorInfo, type ReactNode } from "react";

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/**
 * Catches runtime errors thrown by DecoratorNode `decorate()` outputs.
 * Prevents a single broken node (e.g. bad image URL, failed embed) from
 * crashing the entire editor.
 */
export class DecoratorErrorBoundary extends Component<Props, State> {
  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  state: State = { hasError: false, error: null };

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error("[Editor] DecoratorNode error:", error, info.componentStack);
  }

  render() {
    if (this.state.hasError) {
      return (
        this.props.fallback ?? (
          <div className="my-2 rounded border border-destructive/50 bg-destructive/10 px-3 py-2 text-xs text-destructive">
            Failed to render element. Remove it or try again.
          </div>
        )
      );
    }
    return this.props.children;
  }
}
