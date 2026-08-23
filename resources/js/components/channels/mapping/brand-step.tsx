import { Form, router } from '@inertiajs/react';
import { CaseSensitive, Search } from 'lucide-react';
import { Fragment, useState } from 'react';
import MappingController from '@/actions/App/Http/Controllers/Channels/MappingController';
import { PermissionButton } from '@/components/catalog/permission-button';
import type { WizardProps } from '@/components/channels/mapping/types';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermissionCheck } from '@/hooks/use-permission';

type Props = WizardProps & {
    canManage: PermissionCheck;
    onSaved: () => void;
};

/**
 * 4. adim — kendi markalarimiz → pazaryeri markalari.
 *
 * Arama her tusa basista degil, dugmeyle tetiklenir: pazaryeri marka ucunun
 * butcesi dakikada 50 istek (TRENDYOL.md §7.2) ve arama BUYUK/KUCUK HARFE
 * DUYARLI oldugu icin yarim yazilmis bir isim zaten sonuc dondurmez.
 */
export function BrandStep({
    connection,
    category,
    brands,
    brandSearch,
    brandResult,
    canManage,
    onSaved,
}: Props) {
    const [term, setTerm] = useState(brandSearch);
    const [assignments, setAssignments] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            brands
                .filter((brand) => brand.remoteBrandId !== null)
                .map((brand) => [String(brand.id), brand.remoteBrandId ?? '']),
        ),
    );

    const search = (): void => {
        router.reload({
            only: ['brandResult', 'brandSearch'],
            data: { brand: term },
        });
    };

    const rows = Object.entries(assignments).filter(
        ([, remoteBrandId]) => remoteBrandId !== '',
    );

    if (brands.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                Bu kategoride markalı ürün yok, eşlenecek marka da yok.
            </p>
        );
    }

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-lg border border-border p-4">
                <div className="grid gap-2">
                    <Label htmlFor="brand-search">Pazaryeri markası ara</Label>
                    <div className="flex gap-2">
                        <Input
                            id="brand-search"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    search();
                                }
                            }}
                            placeholder="Markanın tam adı"
                        />
                        <Button type="button" onClick={search}>
                            <Search />
                            Ara
                        </Button>
                    </div>
                    <p className="flex items-start gap-2 text-xs text-muted-foreground">
                        <CaseSensitive className="mt-0.5 size-4 shrink-0" />
                        Arama büyük/küçük harfe duyarlıdır ve yalnızca birebir
                        aynı isim bulunur. “TRENDYOLMİLLA” ile “Trendyolmilla”
                        aynı marka sayılmaz — markayı pazaryerinde yazıldığı
                        gibi yazın.
                    </p>
                </div>

                {brandResult === undefined ||
                brandResult.query === '' ? null : brandResult.brand === null ? (
                    <Alert className="mt-4">
                        <AlertTitle>
                            “{brandResult.query}” bulunamadı
                        </AlertTitle>
                        <AlertDescription>
                            Bu isimde bir pazaryeri markası yok. Büyük/küçük
                            harfleri ve Türkçe karakterleri kontrol edin. Marka
                            gerçekten yoksa pazaryerinde oluşturulması gerekir;
                            marka oluşturma bu sürümde yapılamıyor.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="mt-4 rounded-lg border border-border p-3">
                        <p className="text-sm font-medium">
                            {brandResult.brand.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Pazaryeri no:{' '}
                            <span className="font-mono tabular-nums">
                                {brandResult.brand.remoteId}
                            </span>
                        </p>
                        <div className="mt-2 flex flex-wrap gap-1">
                            {brands.map((brand) => (
                                <Button
                                    key={brand.id}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setAssignments((current) => ({
                                            ...current,
                                            [String(brand.id)]:
                                                brandResult.brand?.remoteId ??
                                                '',
                                        }))
                                    }
                                >
                                    {brand.name} markasına ata
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </section>

            <section className="rounded-lg border border-border p-4">
                <Form
                    {...MappingController.storeBrands.form({
                        connection: connection.id,
                        category: category.id,
                    })}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={onSaved}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {rows.map(([brandId, remoteBrandId], position) => (
                                <Fragment key={brandId}>
                                    <input
                                        type="hidden"
                                        name={`brands[${position}][brand_id]`}
                                        value={brandId}
                                    />
                                    <input
                                        type="hidden"
                                        name={`brands[${position}][remote_brand_id]`}
                                        value={remoteBrandId}
                                    />
                                </Fragment>
                            ))}

                            <InputError message={errors.brands} />

                            <div className="grid gap-2">
                                {brands.map((brand) => {
                                    const assigned =
                                        assignments[String(brand.id)] ?? '';

                                    return (
                                        <div
                                            key={brand.id}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span>{brand.name}</span>
                                            {assigned === '' ? (
                                                <Badge variant="outline">
                                                    Eşlenmedi
                                                </Badge>
                                            ) : (
                                                <Badge>
                                                    Pazaryeri no:{' '}
                                                    <span className="font-mono tabular-nums">
                                                        {assigned}
                                                    </span>
                                                </Badge>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Marka eşlemesi bağlantı geneline yazılır: aynı
                                marka başka kategorilerde de eşlenmiş sayılır.
                                Yeniden atama eskisinin üzerine yazar.
                            </p>

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
            </section>
        </div>
    );
}
