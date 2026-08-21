import { Form } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import MappingController from '@/actions/App/Http/Controllers/Channels/MappingController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { NONE } from '@/components/channels/mapping/types';
import type {
    LocalAttribute,
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
import type { PermissionCheck } from '@/hooks/use-permission';

type Props = WizardProps & {
    canManage: PermissionCheck;
    onSaved: () => void;
};

/** yerel deger id → pazaryeri deger id */
type ValueSelection = Record<string, string>;

/**
 * 3. adim — kendi ozellik degerlerimiz ↔ pazaryeri degerleri.
 *
 * Otomatik eslesenler on-isaretli gelir (sunucu isim benzerligine bakar),
 * kullanici yalnizca farklari cozer. Serbest metin kabul eden bir ozellikte
 * deger eslemesi gerekmiyor; o satirlar hic gosterilmez.
 */
export function ValueStep({
    connection,
    category,
    attributes,
    localAttributes,
    canManage,
    onSaved,
}: Props) {
    const mapped = attributes.filter(
        (attribute) =>
            attribute.mappingId !== null && attribute.values.length > 0,
    );

    const [selection, setSelection] = useState<ValueSelection>(() => {
        const initial: ValueSelection = {};

        for (const attribute of mapped) {
            const local = localAttributes.find(
                (candidate) => candidate.id === attribute.attributeId,
            );

            for (const value of local?.values ?? []) {
                initial[`${attribute.mappingId}:${value.id}`] =
                    attribute.valueMappings[String(value.id)] ??
                    attribute.suggestedValues[String(value.id)] ??
                    NONE;
            }
        }

        return initial;
    });

    const rows = Object.entries(selection)
        .filter(([, remoteValueId]) => remoteValueId !== NONE)
        .map(([key, remoteValueId]) => {
            const [mappingId, attributeValueId] = key.split(':');

            return { mappingId, attributeValueId, remoteValueId };
        });

    if (mapped.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                Eşlenmiş özelliklerin hiçbirinde pazaryeri değer listesi yok.
                Serbest metin kabul eden özelliklerde değer eşlemesi gerekmez;
                bu adımı atlayabilirsiniz.
            </p>
        );
    }

    return (
        <Form
            {...MappingController.storeValues.form({
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
                        <Fragment
                            key={`${row.mappingId}:${row.attributeValueId}`}
                        >
                            <input
                                type="hidden"
                                name={`values[${position}][mapping_id]`}
                                value={row.mappingId}
                            />
                            <input
                                type="hidden"
                                name={`values[${position}][attribute_value_id]`}
                                value={row.attributeValueId}
                            />
                            <input
                                type="hidden"
                                name={`values[${position}][remote_value_id]`}
                                value={row.remoteValueId}
                            />
                        </Fragment>
                    ))}

                    <InputError message={errors.values} />

                    {mapped.map((attribute) => (
                        <AttributeValues
                            key={attribute.remoteId}
                            attribute={attribute}
                            local={localAttributes.find(
                                (candidate) =>
                                    candidate.id === attribute.attributeId,
                            )}
                            selection={selection}
                            onChange={(key, value) =>
                                setSelection((current) => ({
                                    ...current,
                                    [key]: value,
                                }))
                            }
                        />
                    ))}

                    <PermissionButton
                        check={canManage}
                        type="submit"
                        disabled={processing}
                        className="justify-self-start"
                    >
                        Kaydet ve devam et
                    </PermissionButton>
                </>
            )}
        </Form>
    );
}

function AttributeValues({
    attribute,
    local,
    selection,
    onChange,
}: {
    attribute: RemoteAttribute;
    local: LocalAttribute | undefined;
    selection: ValueSelection;
    onChange: (key: string, value: string) => void;
}) {
    if (local === undefined || local.values.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border p-4">
            <h3 className="flex items-center gap-2 text-sm font-medium">
                {local.name}
                <span className="text-muted-foreground">→</span>
                {attribute.name}
                {attribute.allowMultiple ? null : (
                    <Badge variant="outline">Tek seçim</Badge>
                )}
            </h3>

            <div className="mt-3 grid gap-2">
                {local.values.map((value) => {
                    const key = `${attribute.mappingId}:${value.id}`;
                    const current = selection[key] ?? NONE;
                    const auto =
                        attribute.valueMappings[String(value.id)] ===
                            undefined &&
                        attribute.suggestedValues[String(value.id)] === current;

                    return (
                        <div
                            key={value.id}
                            className="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span className="flex items-center gap-2 text-sm">
                                {value.value}
                                {auto && current !== NONE ? (
                                    <Badge variant="secondary">
                                        Otomatik eşleşti
                                    </Badge>
                                ) : null}
                            </span>

                            <Select
                                value={current}
                                onValueChange={(next) => onChange(key, next)}
                            >
                                <SelectTrigger
                                    aria-label={`${value.value} karşılığı`}
                                    className="w-64"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — eşleme yok —
                                    </SelectItem>
                                    {attribute.values.map((remote) => (
                                        <SelectItem
                                            key={remote.remoteId}
                                            value={remote.remoteId}
                                        >
                                            {remote.value}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    );
                })}
            </div>

            {attribute.allowCustom ? null : (
                <p className="mt-3 text-xs text-muted-foreground">
                    Bu özellik serbest metin kabul etmiyor: eşlenmemiş her değer
                    için ürün gönderimi reddedilir.
                </p>
            )}
        </section>
    );
}
