import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Pencil, Shield } from 'lucide-react';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
}

interface PageProps {
    roles: Role[];
}

export default function AdminRolesIndex() {
    const { roles } = usePage().props as unknown as PageProps;

    return (
        <AdminLayout title="Roles">
            <Head title="Roles" />

            <Card>
                <CardHeader>
                    <CardTitle>Roles ({roles.length})</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Users</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead className="w-20">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Shield className="size-4 text-muted-foreground" />
                                            <span className="font-medium">{role.name}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {role.description ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{role.users_count}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{role.permissions_count}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="icon-xs" asChild>
                                            <Link href={route('dashboard.admin.roles.edit', { role: role.id })}>
                                                <Pencil className="size-3.5" />
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
