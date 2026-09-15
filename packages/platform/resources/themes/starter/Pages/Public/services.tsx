import PublicLayout from "@/Layouts/PublicLayout";

export default function Services() {
  return (
    <PublicLayout
      title="Services"
      description="Cost-effective, high-performance software solutions tailored to your needs."
    >
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold tracking-tight text-foreground">Our Services</h1>
        <p className="mt-4 text-lg text-muted-foreground">
          Cost-effective, high-performance software solutions tailored to your needs.
        </p>
      </div>
    </PublicLayout>
  );
}
