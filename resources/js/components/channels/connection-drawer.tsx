import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import ConnectionController from '@/actions/App/Http/Controllers/Channels/ConnectionController';
import { PermissionButton } from '@/components/catalog/permission-button';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { PermissionCheck } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

export type Option = { value: string; label: string };

/**
 * Kimlik alanlari sunucudan gelir (`MarketplaceDriver::credentialFields()`).
 * Burada pazaryerine gore `if` YOKTUR: Trendyol sayisal satici id + anahtar
 * cifti ister, Hepsiburada merchantId UUID'si + tek servis anahtari; ucuncusu
 * eklendiginde bu dosya degismez.
 */
export type CredentialField = {
    name: string;
    label: string;
    type: 'text' | 'secret' | 'select' | 'checkbox';
    help?: string;
    options?: string[];
    default?: string;
    identity?: boolean;
};

export type Marketplace = {
    value: string;
    label: string;
    logo: string;
    /** Kendi tuvalinde kucuk cizilmis markalar icin carpan (config/apps.php). */
    logoScale: number;
    /** Koyu wordmark karanlik temada beyaza boyanir (config/apps.php). */
    logoDarkInvert: boolean;
    capabilities: Option[];
    fields: CredentialField[];
};

export type ConnectionRow = {
    id: number;
    name: string;
    marketplace: string;
    marketplaceLabel: string;
    status: string;
    statusLabel: string;
    capabilities: Option[];
    lastHealthCheckAt: string | null;
    lastHealthError: string | null;
    webhookUrl: string;
    sellerId: string;
    /** Gizli olmayan alanlar. Sirlar SUNUCUDAN HIC GELMEZ. */
    credentials: {
        values: Record<string, string | boolean>;
        secretsStored: boolean;
    };
};

type Props = {
    marketplace: Marketplace | null;
    connection: ConnectionRow | null;
    canManage: PermissionCheck;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ConnectionDrawer({
    marketplace,
    connection,
    canManage,
    open,
    onOpenChange,
}: Props) {
    const editing = connection !== null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                <SheetHeader className="gap-4">
                    {/* Baslik logodur; ekran okuyucu icin metin gizli kalir. */}
                    <SheetTitle className="sr-only">
                        {marketplace?.label ?? 'Bağlantı'}
                        {editing ? ' — bağlantıyı düzenle' : ' bağlantısı kur'}
                    </SheetTitle>

                    {marketplace === null ? null : (
                        // ponytail: cerceve yok. Koyu wordmark'lar beyaza
                        // boyanir (config/apps.php: logo_dark_invert), renkli
                        // logolar oldugu gibi kalir.
                        <div className="flex items-center justify-start">
                            <img
                                src={marketplace.logo}
                                alt={marketplace.label}
                                style={
                                    marketplace.logoScale === 1
                                        ? undefined
                                        : { scale: marketplace.logoScale }
                                }
                                className={cn(
                                    'max-h-7 max-w-40 object-contain object-left',
                                    marketplace.logoDarkInvert &&
                                        'brightness-0 invert',
                                )}
                            />
                        </div>
                    )}

                    <SheetDescription asChild>
                        <Alert>
                            <ShieldCheck />
                            <AlertDescription>
                                Kimlik bilgileri şifrelenerek saklanır ve
                                kaydedildikten sonra ekrana geri getirilmez.
                                Kaydettiğinizde bağlantı pazaryerinde hemen
                                denenir.
                            </AlertDescription>
                        </Alert>
                    </SheetDescription>

                    <Separator />
                </SheetHeader>

                {marketplace === null ? null : (
                    <ConnectionForm
                        key={connection?.id ?? `new-${marketplace.value}`}
                        marketplace={marketplace}
                        connection={connection}
                        canManage={canManage}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function ConnectionForm({
    marketplace,
    connection,
    canManage,
    onDone,
}: {
    marketplace: Marketplace;
    connection: ConnectionRow | null;
    canManage: PermissionCheck;
    onDone: () => void;
}) {
    // Select ve Checkbox yerli bir <input> uretmez; degerleri burada tutulur ve
    // gizli input'larla gonderilir. Metin alanlari kontrolsuz kalir, sirlar ise
    // hicbir zaman doldurulmaz.
    const [values, setValues] = useState<Record<string, string | boolean>>(() =>
        Object.fromEntries(
            marketplace.fields
                .filter(
                    (field) =>
                        field.type === 'select' || field.type === 'checkbox',
                )
                .map((field) => [
                    field.name,
                    connection?.credentials.values[field.name] ??
                        (field.type === 'checkbox'
                            ? false
                            : (field.default ?? field.options?.[0] ?? '')),
                ]),
        ),
    );

    return (
        <Form
            {...(connection === null
                ? ConnectionController.store.form()
                : ConnectionController.update.form({
                      connection: connection.id,
                  }))}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-4 px-4 pb-4"
        >
            {({ processing, errors }) => (
                <>
                    {connection === null ? (
                        <input
                            type="hidden"
                            name="marketplace"
                            value={marketplace.value}
                        />
                    ) : null}

                    <div className="grid gap-2">
                        <Label htmlFor="name">Bağlantı adı</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            defaultValue={connection?.name}
                            placeholder="Örn. Ana mağaza"
                        />
                        <InputError message={errors.name} />
                    </div>

                    {marketplace.fields.map((field) => (
                        <CredentialInput
                            key={field.name}
                            field={field}
                            connection={connection}
                            value={values[field.name]}
                            onChange={(value) =>
                                setValues((current) => ({
                                    ...current,
                                    [field.name]: value,
                                }))
                            }
                            error={errors[field.name]}
                        />
                    ))}

                    <SheetFooter className="px-0">
                        <PermissionButton
                            check={canManage}
                            type="submit"
                            disabled={processing}
                        >
                            {connection === null
                                ? 'Kur ve test et'
                                : 'Kaydet ve test et'}
                        </PermissionButton>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onDone}
                        >
                            Vazgeç
                        </Button>
                    </SheetFooter>
                </>
            )}
        </Form>
    );
}

function CredentialInput({
    field,
    connection,
    value,
    onChange,
    error,
}: {
    field: CredentialField;
    connection: ConnectionRow | null;
    value: string | boolean | undefined;
    onChange: (value: string | boolean) => void;
    error?: string;
}) {
    const help =
        field.help === undefined ? null : (
            <p className="text-xs text-muted-foreground">{field.help}</p>
        );

    if (field.type === 'checkbox') {
        return (
            <div className="grid gap-2">
                <div className="flex items-start gap-2">
                    <input
                        type="hidden"
                        name={field.name}
                        value={value === true ? '1' : '0'}
                    />
                    <Checkbox
                        id={field.name}
                        checked={value === true}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                        className="mt-1"
                    />
                    <div className="grid gap-1">
                        <Label htmlFor={field.name}>{field.label}</Label>
                        {help}
                    </div>
                </div>
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={field.name}>{field.label}</Label>

            {field.type === 'select' ? (
                <>
                    <input
                        type="hidden"
                        name={field.name}
                        value={typeof value === 'string' ? value : ''}
                    />
                    <Select
                        value={typeof value === 'string' ? value : ''}
                        onValueChange={onChange}
                    >
                        <SelectTrigger id={field.name}>
                            <SelectValue placeholder="Seçin" />
                        </SelectTrigger>
                        <SelectContent>
                            {(field.options ?? []).map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </>
            ) : field.type === 'secret' ? (
                <Input
                    id={field.name}
                    name={field.name}
                    type="password"
                    autoComplete="off"
                    required={connection === null}
                    placeholder={
                        connection?.credentials.secretsStored
                            ? 'Kayıtlı — değiştirmek için yeni değer girin'
                            : ''
                    }
                />
            ) : (
                <Input
                    id={field.name}
                    name={field.name}
                    required
                    defaultValue={
                        (connection?.credentials.values[field.name] as
                            string | undefined) ?? field.default
                    }
                />
            )}

            {help}
            <InputError message={error} />
        </div>
    );
}
