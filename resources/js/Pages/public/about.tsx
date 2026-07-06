import PublicLayout from "@/Layouts/PublicLayout";
import { Head } from "@inertiajs/react";

export default function About() {
  return (
    <PublicLayout>
      <Head title="About" />

      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold tracking-tight text-gray-900">About Us</h1>
        <p className="mt-4 text-lg text-gray-600">
          We build modern web applications with cutting-edge technology.
        </p>
      </div>
    </PublicLayout>
  );
}
