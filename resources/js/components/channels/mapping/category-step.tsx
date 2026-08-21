import { Form, router } from '@inertiajs/react';
import { Info, Search, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import MappingController from '@/actions/App/Http/Controllers/Channels/MappingController';
import { PermissionButton } from '@/components/catalog/permission-button';
import type { WizardProps } from '@/components/channels/mapping/types';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { PermissionCheck } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

type Props = WizardProps & {
    canManage: PermissionCheck;
    onSaved: () => void;
};

/**
 * 1. adim — kendi kategorimiz solda, pazaryeri kategorileri sagda.
 *
 * ponytail: sagda gezilebilir bir agac YOK. Pazaryeri agacinda on binlerce
 * dugum var; hepsini prop olarak tasimak megabaytlarca JSON demek. Bunun yerine
 * arama ve oneri sunucuda calisir, sonuclar tam yol ("Giyim > Kadın > Elbise")
 * ile gosterilir — hiyerarsi gorunur kalir, yuk kalmaz. Gercekten gezinme
 * gerekirse ayni uctan seviye seviye bir liste eklenir.
 */
export function CategoryStep({
    connection,
    category,
    mapping,
    lock,
    suggestions,
    search,
    searchResults,
    canManage,
    onSaved,
}: Props) {
    const [term, setTerm] = useState(search);
    const [selected, setSelected] = useState(mapping?.remoteCategoryId ?? '');
    const [searching, setSearching] = useState(false);

    /*
     * Arama sunucuda calisir ama pazaryerine YENI ISTEK ATMAZ: kategori agaci
     * cache'ten okunur (TTL 7 gun, BACKEND-PLAN §7.6). Yine de her tusa basista
     * gitmemesi icin 300 ms geciktirilir.
     */
    useEffect(() => {
        if (term === search) {
            return;
        }

        const timer = setTimeout(() => {
            setSearching(true);

            router.reload({
                only: ['searchResults', 'search'],
                data: { q: term },
                onFinish: () => setSearching(false),
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [term, search]);

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <section className="rounded-xl border p-4">
                <h3 className="text-sm font-medium">Kendi kategoriniz</h3>
                <p className="mt-2 text-lg">{category.name}</p>
                <p className="text-sm text-muted-foreground">{category.path}</p>
                <p className="mt-4 text-sm text-muted-foreground">
                    Bu kategorideki {category.productCount} ürün, seçtiğiniz
                    pazaryeri kategorisinin kurallarına göre gönderilecek.
                </p>

                {mapping === null ? null : (
                    <div className="mt-4 rounded-lg bg-muted p-3 text-sm">
                        <span className="text-muted-foreground">
                            Kayıtlı eşleme:
                        </span>{' '}
                        {mapping.remotePath}
                    </div>
                )}
            </section>

            <section className="rounded-xl border p-4">
                <Form
                    {...MappingController.storeCategory.form({
                        connection: connection.id,
                        category: category.id,
                    })}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={onSaved}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="remote-category-search">
                                    Pazaryeri kategorisi
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-2.5 left-2 size-4 text-muted-foreground" />
                                    <Input
                                        id="remote-category-search"
                                        value={term}
                                        onChange={(event) =>
                                            setTerm(event.target.value)
                                        }
                                        placeholder="Kategori adı veya yol ara"
                                        className="pl-8"
                                    />
                                    {searching ? (
                                        <Spinner className="absolute top-2.5 right-2 size-4" />
                                    ) : null}
                                </div>
                                <input
                                    type="hidden"
                                    name="remote_category_id"
                                    value={selected}
                                />
                                <InputError
                                    message={errors.remote_category_id}
                                />
                            </div>

                            {suggestions.length === 0 ? null : (
                                <div className="grid gap-2">
                                    <p className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                        <Sparkles className="size-3.5" />
                                        İsim benzerliğine göre öneriler
                                    </p>
                                    {suggestions.map((suggestion) => (
                                        <CategoryOption
                                            key={suggestion.remoteId}
                                            path={suggestion.path}
                                            isLeaf
                                            badge={`%${suggestion.score}`}
                                            selected={
                                                selected === suggestion.remoteId
                                            }
                                            disabled={lock.locked}
                                            onSelect={() =>
                                                setSelected(suggestion.remoteId)
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {term === '' ? null : (
                                <div className="grid max-h-96 gap-2 overflow-y-auto">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Arama sonuçları
                                    </p>
                                    {(searchResults ?? []).length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Eşleşme yok.
                                        </p>
                                    ) : (
                                        (searchResults ?? []).map((result) => (
                                            <CategoryOption
                                                key={result.remoteId}
                                                path={result.path}
                                                isLeaf={result.isLeaf}
                                                selected={
                                                    selected === result.remoteId
                                                }
                                                disabled={lock.locked}
                                                onSelect={() =>
                                                    setSelected(result.remoteId)
                                                }
                                            />
                                        ))
                                    )}
                                </div>
                            )}

                            <Alert>
                                <Info />
                                <AlertTitle>
                                    Yalnızca en alttaki kategori seçilebilir
                                </AlertTitle>
                                <AlertDescription>
                                    Pazaryeri, altında başka kategori bulunan
                                    bir kategoriye ürün kabul etmiyor. Üst
                                    kategori seçilirse ürünler aktarılamaz, bu
                                    yüzden burada seçilemez hâlde gösteriliyor.
                                </AlertDescription>
                            </Alert>

                            <PermissionButton
                                check={canManage}
                                type="submit"
                                disabled={processing || selected === ''}
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

function CategoryOption({
    path,
    isLeaf,
    badge,
    selected,
    disabled,
    onSelect,
}: {
    path: string;
    isLeaf: boolean;
    badge?: string;
    selected: boolean;
    disabled: boolean;
    onSelect: () => void;
}) {
    const blocked = !isLeaf || disabled;

    return (
        <button
            type="button"
            disabled={blocked}
            onClick={onSelect}
            title={
                isLeaf
                    ? undefined
                    : 'Bu kategorinin alt kategorileri var; pazaryeri üst kategoriye ürün kabul etmiyor.'
            }
            className={cn(
                'flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm',
                selected && 'border-primary bg-primary/5',
                blocked && 'cursor-not-allowed opacity-50',
            )}
        >
            <span>{path}</span>
            <span className="flex shrink-0 items-center gap-1">
                {badge === undefined ? null : (
                    <Badge variant="secondary">{badge}</Badge>
                )}
                {isLeaf ? null : <Badge variant="outline">Üst kategori</Badge>}
            </span>
        </button>
    );
}
