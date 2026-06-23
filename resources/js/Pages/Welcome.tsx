import { Head, Link, usePage } from '@inertiajs/react';
import { BrandLogo } from '@/Components/BrandLogo';

export default function Welcome() {
    const { props } = usePage();
    const brand = (props.brand as { logo?: string; name?: string }) || {};
    const appName = brand.name || 'Cost Breaker 360';

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background">
                <nav className="flex items-center justify-between border-b border-border px-6 py-4">
                    <Link href="/" className="flex items-center gap-2">
                        <BrandLogo logo={brand.logo || null} name={appName} size="sm" showName />
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link href={route('about')} className="text-sm text-muted-foreground hover:text-foreground">About</Link>
                        <Link href={route('services')} className="text-sm text-muted-foreground hover:text-foreground">Services</Link>
                        <Link href={route('contact')} className="text-sm text-muted-foreground hover:text-foreground">Contact</Link>
                        {props.auth?.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Get started
                                </Link>
                            </>
                        )}
                    </div>
                </nav>

                <main className="flex flex-1 items-center justify-center px-6">
                    <div className="mx-auto max-w-2xl text-center">
                        <h1 className="text-5xl font-bold tracking-tight text-foreground sm:text-6xl">
                            {appName}
                        </h1>
                        <p className="mt-6 text-lg leading-8 text-muted-foreground">
                            Zero-cost SaaS infrastructure. Built with Laravel, React, and shadcn/ui.
                            Manage your costs, track analytics, and scale for free.
                        </p>
                        <div className="mt-10 flex items-center justify-center gap-4">
                            {props.auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('register')}
                                        className="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                    >
                                        Get Started Free
                                    </Link>
                                    <Link
                                        href={route('login')}
                                        className="rounded-md border border-border bg-background px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                                    >
                                        Sign In
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </main>

                <footer className="border-t border-border py-6 text-center text-sm text-muted-foreground">
                    &copy; {new Date().getFullYear()} {appName}. Built with zero-cost infrastructure.
                </footer>
            </div>
        </>
    );
}
