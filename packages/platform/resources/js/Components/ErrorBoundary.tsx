import { Component, type ErrorInfo, type ReactNode } from "react";
import { Button } from "./ui/button";

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
}

/**
 * App-level error boundary. Catches render errors so a bad page never blanks
 * the whole app; resets on navigation via the `key` passed at the mount site.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true };
  }

  state: ErrorBoundaryState = { hasError: false };

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    console.error("[ErrorBoundary]", error, errorInfo);
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background p-4 text-center">
          <h1 className="text-2xl font-bold text-foreground">Something went wrong</h1>
          <p className="max-w-md text-muted-foreground">
            An unexpected error occurred while rendering this page. You can try again, or reload the
            application.
          </p>
          <div className="flex gap-2">
            <Button type="button" onClick={() => this.setState({ hasError: false })}>
              Try again
            </Button>
            <Button type="button" variant="outline" onClick={() => location.reload()}>
              Reload
            </Button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
