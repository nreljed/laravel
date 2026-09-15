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
}
