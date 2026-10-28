import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { route as ziggyRoute } from "ziggy-js";
import { PageProps as AppPageProps } from "./";

declare global {
  // eslint-disable-next-line no-unused-vars
  var route: typeof ziggyRoute;
}

declare module "@inertiajs/core" {
  // eslint-disable-next-line no-unused-vars
  interface PageProps extends InertiaPageProps, AppPageProps {}

  // Inertia 3.8 routes `usePage()`'s return type through
  // `Page<TPageProps & SharedPageProps>`, where `SharedPageProps` resolves
  // from `InertiaConfig` rather than from `PageProps`. The augmentation above
  // is therefore no longer on that path, and consumers that call `usePage()`
  // without a generic see `props.auth` collapse to `{}`. Augmenting
  // `sharedPageProps` restores it. Purely additive: on <3.8 the interface
  // simply does not exist, so the merge is inert and the `PageProps`
  // augmentation above keeps serving.
  interface InertiaConfig {
    sharedPageProps: AppPageProps;
  }
}
