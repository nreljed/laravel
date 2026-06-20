import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function DemoIndex() {
    return (
        <AuthenticatedLayout>
            <Head title="Demo Module" />
            <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
                <h1 className="text-2xl font-bold">Demo Module</h1>
                <p className="text-muted-foreground">
                    This is the demo module page. The module system is working.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
