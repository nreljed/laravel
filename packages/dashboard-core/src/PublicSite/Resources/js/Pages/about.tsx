import PublicLayout from "@/Layouts/PublicLayout";

export default function About() {
  return (
    <PublicLayout
      title="About"
      description="We build modern web applications with cutting-edge technology."
    >
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold tracking-tight text-foreground">About Us</h1>
        <p className="mt-4 text-lg text-muted-foreground">
          We build modern web applications with cutting-edge technology.
        </p>
      </div>
    </PublicLayout>
  );
}
