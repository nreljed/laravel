import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ArrowLeft, Save } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Role {
    id: number;
    name: string;
    slug: string;
}

interface UserData {
    id?: number;
    username?: string;
    name: string;
    email: string;
    role_id?: number;
}

interface PageProps {
    user?: UserData;
    roles: Role[];
}

export default function AdminUsersForm() {
    const { props } = usePage();
    const { user, roles } = props as unknown as PageProps;
    const isEditing = !!user?.id;

    const { data, setData, post, put, processing, errors } = useForm({
        username: user?.username ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        role_id: String(user?.role_id ?? ''),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('dashboard.admin.users.update', { user: user!.id }), {
                onSuccess: () => {},
            });
        } else {
            post(route('dashboard.admin.users.store'), {
                onSuccess: () => {},
            });
        }
    };

    return (
        <AdminLayout title={isEditing ? 'Edit User' : 'Create User'}>
            <Head title={isEditing ? 'Edit User' : 'Create User'} />

            <div className="mb-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('dashboard.admin.users.index')}>
                        <ArrowLeft className="mr-1 size-4" /> Back to Users
                    </Link>
                </Button>
            </div>

            <Card className="max-w-lg">
                <CardHeader>
                    <CardTitle>{isEditing ? 'Edit User' : 'Create User'}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="username">Username</FieldLabel>
                                <Input
                                    id="username"
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value.replace(/[^a-zA-Z0-9_.-]/g, ''))}
                                    required
                                />
                                <FieldError errors={errors.username ? [{ message: errors.username }] : undefined} />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="name">Display Name</FieldLabel>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <FieldError errors={errors.name ? [{ message: errors.name }] : undefined} />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                <FieldError errors={errors.email ? [{ message: errors.email }] : undefined} />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password">
                                    {isEditing ? 'New Password (leave blank to keep)' : 'Password'}
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required={!isEditing}
                                />
                                <FieldError errors={errors.password ? [{ message: errors.password }] : undefined} />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="role">Role</FieldLabel>
                                <Select value={data.role_id} onValueChange={(v) => setData('role_id', v)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={String(role.id)}>
                                                {role.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError errors={errors.role_id ? [{ message: errors.role_id }] : undefined} />
                            </Field>

                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={route('dashboard.admin.users.index')}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-1 size-4" /> {isEditing ? 'Update User' : 'Create User'}
                                </Button>
                            </div>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
