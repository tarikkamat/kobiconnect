import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, TriangleAlert, X } from 'lucide-react';
import { useState } from 'react';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProposalCard } from '@/components/channels/matching/proposal-card';
import type { MatchProposal } from '@/components/channels/matching/types';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/use-permission';
import { approve, index, reject } from '@/routes/matches';

type Props = {
    connections: { id: number; name: string }[];
    connectionId: number | null;
    proposals: MatchProposal[];
    nextCursor: string | null;
    error: string | null;
};

/**
 * Ön eşleşme gelen kutusu — HEPSIBURADA.md §3 H10.
 *
 * Pazaryeri gonderdigimiz urunun kendi katalogundaki bir kayitla ayni oldugunu
 * dusunuyor ve kararimizi bekliyor. Karar verilmeden urun SATISA ACILMAZ.
 *
 * Ekranda bilerek OLMAYAN sey: otomatik onay. Ne bir "hepsini onayla"
 * kestirmesi, ne bir kural motoru. Onay ticari bir karardir — pazaryerinin
 * basligini, gorsellerini ve attribute'larini devralirsiniz — ve toplu aksiyon
 * yalnizca EKRANDA ACIKCA SECILMIS satirlar uzerinde calisir.
 */
export default function MatchInbox({
    connections,
    connectionId,
    proposals,
    nextCursor,
    error,
}: Props) {
    const can = usePermission()('catalog.manage');
    const [selected, setSelected] = useState<string[]>([]);

    const toggle = (reference: string, isSelected: boolean): void =>
        setSelected((current) =>
            isSelected
                ? [...current, reference]
                : current.filter((value) => value !== reference),
        );

    return (
        <>
            <Head title="Ön Eşleşme" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Ön Eşleşme Gelen Kutusu"
                        description="Pazaryeri, gönderdiğiniz ürünü kendi kataloğundaki bir kayıtla eşleştirmeyi öneriyor. Siz karar verene kadar ürün satışa açılmaz."
                    />

                    {connections.length > 1 && (
                        <div className="w-56">
                            <Select
                                value={String(connectionId ?? '')}
                                onValueChange={(value) =>
                                    router.get(
                                        index.url(undefined, {
                                            query: { connection: value },
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger aria-label="Bağlantı">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {connections.map((connection) => (
                                        <SelectItem
                                            key={connection.id}
                                            value={String(connection.id)}
                                        >
                                            {connection.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {error !== null && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertTitle>Kuyruk okunamadı</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {connectionId === null ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                        Bağlı pazaryerlerinizin hiçbiri ön eşleşme akışı
                        sunmuyor. Bu ekran yalnızca ürünlerinizi kendi
                        kataloğuyla eşleştirmeyi öneren pazaryerleri için
                        çalışır.
                    </p>
                ) : (
                    <>
                        <Alert>
                            <TriangleAlert />
                            <AlertTitle>
                                Onay ticari bir karardır, teknik bir onay değil
                            </AlertTitle>
                            <AlertDescription>
                                Onayladığınızda pazaryerinin başlığını,
                                görsellerini ve özelliklerini devralırsınız;
                                yanlış eşleşme, A ürününü B’nin sayfasında
                                satmak demektir. Bu yüzden otomatik onay yoktur:
                                her karar, iki kaydı yan yana gördükten sonra
                                elle verilir. Reddederseniz ürün kendi katalog
                                kaydımız olarak ilerler.
                            </AlertDescription>
                        </Alert>

                        {proposals.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                                Bekleyen ön eşleşme önerisi yok.
                            </p>
                        ) : (
                            <>
                                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border px-4 py-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                selected.length ===
                                                proposals.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? proposals.map(
                                                              (proposal) =>
                                                                  proposal.reference,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Görünen önerilerin hepsini seç"
                                        />
                                        Görünen{' '}
                                        <span className="font-mono tabular-nums">
                                            {proposals.length}
                                        </span>{' '}
                                        öneriyi seç
                                    </label>

                                    {/* Toplu karar da tekil karar da AYNI ucu
                                        kullanir; tek fark gonderilen referans
                                        sayisidir. */}
                                    <Form
                                        {...approve.form({
                                            connection: connectionId,
                                        })}
                                        options={{ preserveScroll: true }}
                                        onSuccess={() => setSelected([])}
                                    >
                                        {selected.map((reference) => (
                                            <input
                                                key={reference}
                                                type="hidden"
                                                name="references[]"
                                                value={reference}
                                            />
                                        ))}
                                        <PermissionButton
                                            check={can}
                                            type="submit"
                                            disabled={selected.length === 0}
                                        >
                                            <Check />
                                            Seçilenleri onayla (
                                            <span className="font-mono tabular-nums">
                                                {selected.length}
                                            </span>
                                            )
                                        </PermissionButton>
                                    </Form>

                                    <Form
                                        {...reject.form({
                                            connection: connectionId,
                                        })}
                                        options={{ preserveScroll: true }}
                                        onSuccess={() => setSelected([])}
                                    >
                                        {selected.map((reference) => (
                                            <input
                                                key={reference}
                                                type="hidden"
                                                name="references[]"
                                                value={reference}
                                            />
                                        ))}
                                        <PermissionButton
                                            check={can}
                                            type="submit"
                                            variant="outline"
                                            disabled={selected.length === 0}
                                        >
                                            <X />
                                            Seçilenleri reddet (
                                            <span className="font-mono tabular-nums">
                                                {selected.length}
                                            </span>
                                            )
                                        </PermissionButton>
                                    </Form>
                                </div>

                                <div className="flex flex-col gap-4">
                                    {proposals.map((proposal) => (
                                        <ProposalCard
                                            key={proposal.reference}
                                            proposal={proposal}
                                            connectionId={connectionId}
                                            selected={selected.includes(
                                                proposal.reference,
                                            )}
                                            onSelectedChange={(isSelected) =>
                                                toggle(
                                                    proposal.reference,
                                                    isSelected,
                                                )
                                            }
                                            can={can}
                                        />
                                    ))}
                                </div>

                                {nextCursor !== null && (
                                    <Link
                                        href={index.url(undefined, {
                                            query: {
                                                connection: connectionId,
                                                cursor: nextCursor,
                                            },
                                        })}
                                        className="self-center text-sm underline underline-offset-4"
                                    >
                                        Sonraki öneriler
                                    </Link>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

MatchInbox.layout = {
    breadcrumbs: [
        {
            title: 'Ön Eşleşme',
            href: index(),
        },
    ],
};
