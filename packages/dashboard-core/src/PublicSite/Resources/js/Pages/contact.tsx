import PublicLayout from "@/Layouts/PublicLayout";

export default function Contact() {
  return (
    <PublicLayout title="Contact" description="Get in touch with our team to discuss your project.">
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold tracking-tight text-foreground">Contact Us</h1>
        <p className="mt-4 text-lg text-muted-foreground">
          Get in touch with our team to discuss your project.
        </p>
      </div>
    </PublicLayout>
  );
}
