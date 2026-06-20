import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { getUserInitials } from '@/hooks/use-auth';
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/Components/ui/pagination';
import { Plus, Search, Pencil, Trash2 } from 'lucide-react';
import { useAuth } from '@/hooks/use-auth';
import { FormEventHandler, useState } from 'react';

interface Role {
    id: number;
    name: string;
    slug: string;
}

interface User {
    id: number;
    username: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: Role[];
    created_at: string;
}

interface PaginatedResponse {
    data: User[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PageProps {
    users: PaginatedResponse;
    roles: Role[];
    filters: { search?: string };
}

export default function AdminUsersIndex() {
    const { props } = usePage();
    const { users, filters } = props as unknown as PageProps;
    const { isAdmin } = useAuth();
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('dashboard.admin.users.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title="Users">
            <Head title="Users" />

            <div className="flex items-center justify-between mb-4">
                <form onSubmit={handleSearch} className="flex items-center gap-2">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            placeholder="Search users..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-64 pl-8"
                        />
                    </div>
                    <Button type="submit" variant="secondary" size="sm">Search</Button>
                </form>
                <Button asChild>
                    <Link href={route('dashboard.admin.users.create')}>
                        <Plus className="mr-1 size-4" /> Add User
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Users ({users.total})</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Username</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Joined</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-24">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-mono text-sm">{user.username}</TableCell>
                                    <TableCell className="flex items-center gap-3">
                                        <Avatar className="size-8">
                                            <AvatarFallback>{getUserInitials(user.name)}</AvatarFallback>
                                        </Avatar>
                                        <span className="font-medium">{user.name}</span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{user.email}</TableCell>
                                    <TableCell>
                                        {user.roles.map((role) => (
                                            <Badge key={role.id} variant="secondary">{role.name}</Badge>
                                        ))}
                                        {user.roles.length === 0 && <span className="text-muted-foreground text-sm">—</span>}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        {user.email_verified_at ? (
                                            <Badge className="bg-green-600">Verified</Badge>
                                        ) : (
                                            <Badge variant="secondary">Unverified</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon-xs" asChild>
                                                <Link href={route('dashboard.admin.users.edit', { user: user.id })}>
                                                    <Pencil className="size-3.5" />
                                                </Link>
                                            </Button>
                                            {isAdmin && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-xs"
                                                    className="text-destructive"
                                                    onClick={() => {
                                                        if (confirm('Delete this user?')) {
                                                            router.delete(route('dashboard.admin.users.destroy', { user: user.id }));
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {users.last_page > 1 && (
                        <Pagination className="mt-4">
                            <PaginationContent>
                                {users.links.map((link, i) => {
                                    if (link.label.includes('Previous')) {
                                        return (
                                            <PaginationItem key={i}>
                                                {link.url ? <PaginationPrevious href={link.url} /> : <PaginationPrevious href="#" />}
                                            </PaginationItem>
                                        );
                                    }
                                    if (link.label.includes('Next')) {
                                        return (
                                            <PaginationItem key={i}>
                                                {link.url ? <PaginationNext href={link.url} /> : <PaginationNext href="#" />}
                                            </PaginationItem>
                                        );
                                    }
                                    return (
                                        <PaginationItem key={i}>
                                            <PaginationLink href={link.url ?? '#'} isActive={link.active}>
                                                {link.label}
                                            </PaginationLink>
                                        </PaginationItem>
                                    );
                                })}
                            </PaginationContent>
                        </Pagination>
                    )}
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
