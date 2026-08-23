import { Form } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Fragment, useState } from 'react';
import MappingController from '@/actions/App/Http/Controllers/Channels/MappingController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { NONE } from '@/components/channels/mapping/types';
import type {
    RemoteAttribute,
    WizardProps,
} from '@/components/channels/mapping/types';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionCheck } from '@/hooks/use-permission';

type Props = WizardProps & {
    canManage: PermissionCheck;
    onSaved: () => void;
};

/**
 * 2. adim — pazaryeri kategorisinin ozellikleri ve BAYRAKLARI.
 *
 * Bayraklar burada yalnizca gosterilir; kaydederken sunucu onlari referans
 * katalogundan kopyalar (TRENDYOL.md §9.7). Kullanicinin sectigi tek sey hangi
 * pazaryeri ozelliginin bizim hangi ozelligimize karsilik geldigidir.
 */
export function AttributeStep({
    connection,
    category,
    attributes,
    localAttributes,
    lock,
    canManage,
    onSaved,
}: Props) {
    const [selection, setSelection] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            attributes.map((attribute) => [
                attribute.remoteId,
                String(
                    attribute.attributeId ??
                        attribute.suggestedAttributeId ??
                        NONE,
                ),
            ]),
        ),
    );

    const varianterCount = attributes.filter(
        (attribute) =>
            attribute.isVarianter && selection[attribute.remoteId] !== NONE,
    ).length;

    const rows = attributes
        .map((attribute) => ({
            remoteId: attribute.remoteId,
            attributeId: selection[attribute.remoteId] ?? NONE,
        }))
        .filter((row) => row.attributeId !== NONE);

    if (attributes.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-border p-8 text-center text-2xl font-medium tracking-tight text-muted-foreground">
                Bu kategori için pazaryeri özellik listesi okunamadı ya da boş.
            </p>
        );
    }

    return (
        <Form
            {...MappingController.storeAttributes.form({
                connection: connection.id,
                category: category.id,
            })}
            options={{ preserveScroll: true, preserveState: true }}
            onSuccess={onSaved}
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    {rows.map((row, position) => (
                        <Fragment key={row.remoteId}>
                            <input
                                type="hidden"
                                name={`attributes[${position}][remote_attribute_id]`}
                                value={row.remoteId}
                            />
                            <input
                                type="hidden"
                                name={`attributes[${position}][attribute_id]`}
                                value={row.attributeId}
                            />
                        </Fragment>
                    ))}

                    <InputError message={errors.attributes} />

                    <div className="grid gap-3">
                        {attributes.map((attribute) => (
                            <AttributeRow
                                key={attribute.remoteId}
                                attribute={attribute}
                                localAttributes={localAttributes}
                                value={selection[attribute.remoteId] ?? NONE}
                                lockReason={
                                    lock.locked &&
                                    (attribute.isVarianter ||
                                        attribute.isSlicer)
                                        ? lock.reason
                                        : null
                                }
                                onChange={(value) =>
                                    setSelection((current) => ({
                                        ...current,
                                        [attribute.remoteId]: value,
                                    }))
                                }
                            />
                        ))}
                    </div>

                    {varianterCount > 1 ? (
                        <p className="text-sm text-destructive">
                            Kategori başına yalnızca bir varyant belirleyici
                            özellik eşlenebilir;{' '}
                            <span className="font-mono tabular-nums">
                                {varianterCount}
                            </span>{' '}
                            tane seçili.
                        </p>
                    ) : null}

                    <PermissionButton
                        check={canManage}
                        type="submit"
                        disabled={processing || varianterCount > 1}
                        className="justify-self-start"
                    >
                        Kaydet ve devam et
                    </PermissionButton>
                </>
            )}
        </Form>
    );
}

function AttributeRow({
    attribute,
    localAttributes,
    value,
    lockReason,
    onChange,
}: {
    attribute: RemoteAttribute;
    localAttributes: WizardProps['localAttributes'];
    value: string;
    lockReason: string | null;
    onChange: (value: string) => void;
}) {
    const unmappedRequired = attribute.isRequired && value === NONE;

    const select = (
        <Select
            value={value}
            onValueChange={onChange}
            disabled={lockReason !== null}
        >
            <SelectTrigger
                aria-label={`${attribute.name} eşlemesi`}
                aria-invalid={unmappedRequired}
                className="w-64"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>— eşleme yok —</SelectItem>
                {localAttributes.map((local) => (
                    <SelectItem key={local.id} value={String(local.id)}>
                        {local.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <div className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border p-3">
            <div className="grid gap-1">
                <span className="font-medium">{attribute.name}</span>
                <div className="flex flex-wrap gap-1">
                    {attribute.isRequired ? (
                        <Badge variant="destructive">Zorunlu</Badge>
                    ) : null}
                    {attribute.isVarianter ? (
                        <Badge>Bu varyantı belirler</Badge>
                    ) : null}
                    {attribute.isSlicer ? (
                        <Badge variant="secondary">Ayrı ürün kartı açar</Badge>
                    ) : null}
                    {attribute.allowCustom ? null : (
                        <Badge variant="outline">
                            Serbest metin kapalı — listeden seçim
                        </Badge>
                    )}
                    {attribute.allowMultiple ? (
                        <Badge variant="outline">Çoklu seçim</Badge>
                    ) : (
                        <Badge variant="outline">Tek seçim</Badge>
                    )}
                    {attribute.attributeId === null &&
                    attribute.suggestedAttributeId !== null ? (
                        <Badge variant="secondary">Önerildi</Badge>
                    ) : null}
                </div>
                {attribute.isSlicer ? (
                    <p className="max-w-md text-xs text-muted-foreground">
                        Bu özelliğin her değeri pazaryerinde bağımsız bir ürün
                        kartı olarak listelenir (tipik olarak renk).
                    </p>
                ) : null}
                {unmappedRequired ? (
                    <p className="text-xs text-destructive">
                        Zorunlu özellik: eşlenmeden bu kategorideki ürünler
                        gönderilemez.
                    </p>
                ) : null}
            </div>

            {lockReason === null ? (
                select
            ) : (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span
                            tabIndex={0}
                            className="inline-flex items-center gap-2"
                        >
                            <Lock className="size-4 text-muted-foreground" />
                            {select}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-xs">
                        {lockReason}
                    </TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}
