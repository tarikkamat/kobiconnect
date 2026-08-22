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
    seats: { used: number };
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

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Ekip & Roller"
                    description="Kullanıcıları davet edin ve rollerini yönetin."
                />

                <p className="text-sm text-muted-foreground">
                    Aktif kullanıcı:{' '}
                    <span className="tabular-nums">{seats.used}</span>. Erişimi
                    kaldırılan kullanıcı yeniden rol atanana kadar sayılmaz.
                </p>

                <Form
                    {...TeamController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm font-medium">
                                Yeni kullanıcı davet et
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Ad soyad</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-posta</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="role">Rol</Label>
                                <Select name="role" defaultValue={roles[0]}>
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Kullanıcı e-postasındaki bağlantıdan kendi
                                şifresini belirler; siz bir şifre
                                oluşturmazsınız.
                            </p>

                            <PermissionButton
                                check={inviteCheck}
                                type="submit"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Davet gönder
                            </PermissionButton>
                        </>
                    )}
                </Form>

                <Separator />

                <div className="grid gap-3">
                    {members.map((member) => {
                        const roleCheck = lastOwnerCheck(member);

                        return (
                            <div
                                key={member.id}
                                className="grid gap-3 rounded-xl border p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-medium">
                                            <span className="truncate">
                                                {member.name}
                                            </span>
                                            {member.roles.includes(
                                                ownerRole,
                                            ) && (
                                                <ShieldCheck
                                                    className="size-4 text-muted-foreground"
                                                    aria-label="Sahip"
                                                />
                                            )}
                                            {member.isSelf && (
                                                <Badge variant="outline">
                                                    Siz
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {member.email}
                                        </p>
                                    </div>

                                    <Badge
                                        variant={
                                            member.active
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {member.active ? 'Aktif' : 'Devre dışı'}
                                    </Badge>
                                </div>

                                <div className="flex flex-wrap items-end gap-2">
                                    <div className="grid flex-1 gap-2">
                                        <Label
                                            htmlFor={`role-${member.id}`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            Rol
                                        </Label>
                                        <RoleSelect
                                            id={`role-${member.id}`}
                                            check={roleCheck}
                                            roles={roles}
                                            value={member.roles[0] ?? ''}
                                            onChange={(role) =>
                                                assignRole(member, role)
                                            }
                                        />
                                    </div>

                                    <PermissionButton
                                        check={removeCheck(member)}
                                        variant="outline"
                                        disabled={!member.active}
                                        onClick={() => setPendingRemove(member)}
                                    >
                                        <UserMinus />
                                        Erişimi kaldır
                                    </PermissionButton>
                                </div>

                                {!member.active && (
                                    <p className="text-xs text-muted-foreground">
                                        Bu kullanıcının hiçbir yetkisi yok ve
                                        koltuk tüketmiyor. Rol atayarak geri
                                        açabilirsiniz.
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
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
