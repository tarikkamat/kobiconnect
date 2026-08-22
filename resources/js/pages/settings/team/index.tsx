import { Form, Head, router } from '@inertiajs/react';
import { ShieldCheck, UserMinus } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/App/Http/Controllers/Team/TeamController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionCheck } from '@/hooks/use-permission';
import { usePermission } from '@/hooks/use-permission';
import { destroy, index, update } from '@/routes/team';

type Member = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    active: boolean;
    isSelf: boolean;
    joinedAt: string | null;
};

type Props = {
    members: Member[];
    roles: string[];
    ownerRole: string;
    ownerCount: number;
    seats: { used: number; max: number | null };
};

export default function TeamSettings({
    members,
    roles,
    ownerRole,
    ownerCount,
    seats,
}: Props) {
    const canManage = usePermission()('users.manage');
    const [pendingRemove, setPendingRemove] = useState<Member | null>(null);

    const inviteCheck: PermissionCheck = canManage;

    /**
     * Son Sahip korumasi. Sunucu da ayni kurali zorlar (TeamController), burasi
     * sebebini onceden soyler — aksiyon gizlenmez, devre disi birakilir.
     */
    const lastOwnerCheck = (member: Member): PermissionCheck => {
        if (member.roles.includes(ownerRole) && ownerCount <= 1) {
            return {
                allowed: false,
                reason: 'Son "Sahip" rolü kaldırılamaz: hesabınız sahipsiz kalırsa faturalama ve plan yönetimi kalıcı olarak kilitlenir. Önce başka bir kullanıcıyı Sahip yapın.',
            };
        }

        return canManage;
    };

    const removeCheck = (member: Member): PermissionCheck => {
        if (member.isSelf) {
            return {
                allowed: false,
                reason: 'Kendi erişiminizi kaldıramazsınız.',
            };
        }

        return lastOwnerCheck(member);
    };

    const assignRole = (member: Member, role: string): void => {
        if (member.roles[0] === role) {
            return;
        }

        router.patch(
            update.url({ user: member.id }),
            { role },
            { preserveScroll: true, onError: toastError },
        );
    };

    return (
        <>
            <Head title="Ekip & Roller" />

            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Yeni Kullanıcı Davet Et</CardTitle>
                    </CardHeader>

                    <Form
                        {...TeamController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <CardContent className="grid gap-5">
                                    <div className="flex flex-wrap items-baseline gap-2.5 lg:flex-nowrap">
                                        <Label
                                            htmlFor="name"
                                            className="flex w-full max-w-40"
                                        >
                                            Ad soyad
                                        </Label>
                                        <div className="grid w-full gap-1.5">
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                placeholder="Ad Soyad"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-baseline gap-2.5 lg:flex-nowrap">
                                        <Label
                                            htmlFor="email"
                                            className="flex w-full max-w-40"
                                        >
                                            E-posta
                                        </Label>
                                        <div className="grid w-full gap-1.5">
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                                autoComplete="off"
                                                placeholder="ornek@sirket.com"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-baseline gap-2.5 lg:flex-nowrap">
                                        <Label
                                            htmlFor="role"
                                            className="flex w-full max-w-40"
                                        >
                                            Rol
                                        </Label>
                                        <div className="grid w-full gap-1.5">
                                            <Select
                                                name="role"
                                                defaultValue={roles[0]}
                                            >
                                                <SelectTrigger
                                                    id="role"
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem
                                                            key={role}
                                                            value={role}
                                                        >
                                                            {role}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.role} />
                                        </div>
                                    </div>

                                    <p className="text-2sm text-secondary-foreground">
                                        Kullanıcı e-postasındaki bağlantıdan
                                        kendi şifresini belirler; siz bir şifre
                                        oluşturmazsınız.
                                    </p>
                                </CardContent>

                                <CardFooter className="justify-end">
                                    <PermissionButton
                                        check={inviteCheck}
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Davet gönder
                                    </PermissionButton>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ekip Üyeleri</CardTitle>
                        <CardToolbar>
                            <span className="text-sm text-secondary-foreground">
                                Aktif kullanıcı:{' '}
                                <span className="font-medium text-mono tabular-nums">
                                    {seats.used}
                                </span>
                            </span>
                        </CardToolbar>
                    </CardHeader>
                    <CardTable>
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-accent/60">
                                    <TableHead className="h-10 min-w-48">
                                        Kullanıcı
                                    </TableHead>
                                    <TableHead className="h-10 min-w-40">
                                        Rol
                                    </TableHead>
                                    <TableHead className="h-10 min-w-24">
                                        Durum
                                    </TableHead>
                                    <TableHead className="h-10 w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((member) => (
                                    <TableRow key={member.id}>
                                        <TableCell className="py-3">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate text-sm font-medium text-mono">
                                                    {member.name}
                                                </span>
                                                {member.roles.includes(
                                                    ownerRole,
                                                ) && (
                                                    <ShieldCheck
                                                        className="size-4 shrink-0 text-muted-foreground"
                                                        aria-label="Sahip"
                                                    />
                                                )}
                                                {member.isSelf && (
                                                    <Badge
                                                        variant="secondary"
                                                        appearance="light"
                                                        size="sm"
                                                    >
                                                        Siz
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="truncate text-2sm text-secondary-foreground">
                                                {member.email}
                                            </p>
                                        </TableCell>

                                        <TableCell className="py-3">
                                            <RoleSelect
                                                id={`role-${member.id}`}
                                                ariaLabel={`${member.name} rolü`}
                                                check={lastOwnerCheck(member)}
                                                roles={roles}
                                                value={member.roles[0] ?? ''}
                                                onChange={(role) =>
                                                    assignRole(member, role)
                                                }
                                            />
                                        </TableCell>

                                        <TableCell className="py-3">
                                            {member.active ? (
                                                <Badge
                                                    variant="success"
                                                    appearance="light"
                                                >
                                                    Aktif
                                                </Badge>
                                            ) : (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span tabIndex={0}>
                                                            <Badge
                                                                variant="secondary"
                                                                appearance="light"
                                                            >
                                                                Devre dışı
                                                            </Badge>
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Bu kullanıcının hiçbir
                                                        yetkisi yok ve koltuk
                                                        tüketmiyor. Rol atayarak
                                                        geri açabilirsiniz.
                                                    </TooltipContent>
                                                </Tooltip>
                                            )}
                                        </TableCell>

                                        <TableCell className="py-3 text-end">
                                            <PermissionButton
                                                check={removeCheck(member)}
                                                variant="ghost"
                                                mode="icon"
                                                disabled={!member.active}
                                                title="Erişimi kaldır"
                                                aria-label={`${member.name} erişimini kaldır`}
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    setPendingRemove(member)
                                                }
                                            >
                                                <UserMinus />
                                            </PermissionButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardTable>
                </Card>
            </div>

            <Dialog
                open={pendingRemove !== null}
                onOpenChange={(open) => !open && setPendingRemove(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Erişim kaldırılsın mı?</DialogTitle>
                        <DialogDescription>
                            {pendingRemove?.name} kullanıcısının tüm rolleri
                            kaldırılacak. Hesap silinmez, geçmişi durur ve
                            koltuğu serbest kalır; rol atayarak geri
                            açabilirsiniz.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPendingRemove(null)}
                        >
                            Vazgeç
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (pendingRemove === null) {
                                    return;
                                }

                                router.delete(
                                    destroy.url({ user: pendingRemove.id }),
                                    {
                                        preserveScroll: true,
                                        onError: toastError,
                                    },
                                );
                                setPendingRemove(null);
                            }}
                        >
                            Erişimi kaldır
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Devre disi Radix trigger pointer olayi uretmedigi icin tooltip tetikleyicisi
 * sarmalayici bir span'dir — PermissionButton ile ayni kalip.
 */
function RoleSelect({
    id,
    check,
    roles,
    value,
    onChange,
}: {
    id: string;
    check: PermissionCheck;
    roles: string[];
    value: string;
    onChange: (role: string) => void;
}) {
    const select = (
        <Select
            value={value}
            onValueChange={onChange}
            disabled={!check.allowed}
        >
            <SelectTrigger id={id} className="w-full">
                <SelectValue placeholder="Rol yok" />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role} value={role}>
                        {role}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    if (check.reason === null) {
        return select;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0} className="inline-flex w-full">
                    {select}
                </span>
            </TooltipTrigger>
            <TooltipContent>{check.reason}</TooltipContent>
        </Tooltip>
    );
}

TeamSettings.layout = {
    breadcrumbs: [
        {
            title: 'Ekip & Roller',
            href: index(),
        },
    ],
};
