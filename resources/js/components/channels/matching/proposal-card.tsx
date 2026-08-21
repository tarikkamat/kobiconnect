import { Form } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { PermissionButton } from '@/components/catalog/permission-button';
import type {
    MatchProposal,
    MatchSide,
} from '@/components/channels/matching/types';
import { Checkbox } from '@/components/ui/checkbox';
import type { PermissionCheck } from '@/hooks/use-permission';
import { approve, reject } from '@/routes/matches';

type Props = {
    proposal: MatchProposal;
    connectionId: number;
    selected: boolean;
    onSelectedChange: (selected: boolean) => void;
    can: PermissionCheck;
};

function Side({
    title, // "Bizim ürünümüz" / "Pazaryerinin önerdiği kayıt"
    subtitle,
    side,
    tone,
}: {
    title: string;
    subtitle: string;
    side: MatchSide | null;
    tone: 'ours' | 'proposed';
}) {
    if (side === null) {
        return (
            <div className="p-4">
                <p className="text-xs font-medium text-muted-foreground uppercase">
                    {title}
                </p>
                <p className="mt-3 text-sm text-muted-foreground">
                    Bu SKU kataloğumuzda bulunamadı. Karar yine de verilebilir
                    ama karşılaştıracak kendi kaydımız yok — önce ürünü
                    katalogda bulun.
                </p>
            </div>
        );
    }

    return (
        <div className={tone === 'proposed' ? 'bg-muted/40 p-4' : 'p-4'}>
            <p className="text-xs font-medium text-muted-foreground uppercase">
                {title}
            </p>
            <p className="text-xs text-muted-foreground">{subtitle}</p>

            {side.images.length > 0 && (
                <div className="mt-3 flex gap-2">
                    {side.images.map((url) => (
                        <img
                            key={url}
                            src={url}
                            alt=""
                            loading="lazy"
                            className="size-16 rounded-md border object-cover"
                        />
                    ))}
                </div>
            )}

            <p className="mt-3 font-medium">{side.name ?? '—'}</p>

            <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt className="text-muted-foreground">Marka</dt>
                <dd>{side.brand ?? '—'}</dd>
                <dt className="text-muted-foreground">Kategori</dt>
                <dd>{side.category ?? '—'}</dd>
                {side.attributes.map((attribute) => (
                    <div key={attribute.name} className="contents">
                        <dt className="text-muted-foreground">
                            {attribute.name}
                        </dt>
                        <dd>{attribute.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

/**
 * Tek bir on eslesme onerisi: SOLDA bizim urunumuz, SAGDA pazaryerinin onerdigi
 * kayit. Yan yana durmalarinin sebebi susleme degil: onay, pazaryerinin
 * basligini, gorsellerini ve attribute'larini DEVRALMAK demektir ve satici
 * neyi devraldigini gormeden karar veremez (HEPSIBURADA.md §3 H10).
 */
export function ProposalCard({
    proposal,
    connectionId,
    selected,
    onSelectedChange,
    can,
}: Props) {
    const decision = { connection: connectionId };

    return (
        <article className="rounded-xl border">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <label className="flex items-center gap-2 text-sm font-medium">
                    <Checkbox
                        checked={selected}
                        onCheckedChange={(checked) =>
                            onSelectedChange(checked === true)
                        }
                        aria-label={`${proposal.reference} önerisini seç`}
                    />
                    <span className="font-mono">{proposal.reference}</span>
                </label>

                <div className="flex gap-2">
                    <Form
                        {...approve.form(decision)}
                        options={{ preserveScroll: true }}
                    >
                        <input
                            type="hidden"
                            name="references[]"
                            value={proposal.reference}
                        />
                        <PermissionButton
                            check={can}
                            type="submit"
                            size="sm"
                            variant="default"
                        >
                            <Check />
                            Onayla
                        </PermissionButton>
                    </Form>

                    <Form
                        {...reject.form(decision)}
                        options={{ preserveScroll: true }}
                    >
                        <input
                            type="hidden"
                            name="references[]"
                            value={proposal.reference}
                        />
                        <PermissionButton
                            check={can}
                            type="submit"
                            size="sm"
                            variant="outline"
                        >
                            <X />
                            Reddet
                        </PermissionButton>
                    </Form>
                </div>
            </header>

            <div className="grid gap-px divide-y md:grid-cols-2 md:divide-x md:divide-y-0">
                <Side
                    title="Bizim ürünümüz"
                    subtitle={
                        proposal.ours === null ? '' : `SKU ${proposal.ours.sku}`
                    }
                    side={proposal.ours}
                    tone="ours"
                />
                <Side
                    title="Pazaryerinin önerdiği kayıt"
                    subtitle={`Pazaryeri kimliği ${proposal.proposed.remoteId}`}
                    side={proposal.proposed}
                    tone="proposed"
                />
            </div>
        </article>
    );
}
