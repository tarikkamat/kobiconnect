/**
 * Auth kapak paneli: capraz akan pazaryeri logo kartlari.
 * Sadece transform animasyonu kullanir (GPU-dostu, filter/SVG yok).
 * `h` logonun render yuksekligi (px); bazi svg'lerde genis bos pay
 * oldugundan gorunur icerik esit boyda dursun diye slug basina ayarli.
 */
const LOGOS = [
    { slug: 'trendyol', h: 50 },
    { slug: 'shopify', h: 30 },
    { slug: 'hepsiburada', h: 26 },
    { slug: 'amazon', h: 30 },
    { slug: 'n11', h: 30 },
    { slug: 'ciceksepeti', h: 100 },
    { slug: 'pttavm', h: 28 },
    { slug: 'ideasoft', h: 90 },
    { slug: 'ikas', h: 26 },
    { slug: 'ticimax', h: 32 },
    { slug: 'pazarama', h: 22 },
    { slug: 'woocommerce', h: 24 },
];

/** Her satir farkli logoyla baslasin diye diziyi dondurur. */
function rotated(offset: number) {
    return [...LOGOS.slice(offset), ...LOGOS.slice(0, offset)];
}

const ROWS = [
    { offset: 0, speed: 70, reverse: false },
    { offset: 3, speed: 90, reverse: true },
    { offset: 6, speed: 60, reverse: false },
    { offset: 9, speed: 80, reverse: true },
];

const STYLES = `
@keyframes mkt-scroll {
    to { transform: translate3d(-50%, 0, 0); }
}
.mkt-row {
    animation: mkt-scroll var(--speed) linear infinite;
}
.mkt-row-reverse {
    animation-direction: reverse;
}
@media (prefers-reduced-motion: reduce) {
    .mkt-row { animation: none; }
}
`;

function LogoStrip({ offset }: { offset: number }) {
    return (
        <div className="flex shrink-0 gap-6 pr-6">
            {rotated(offset).map(({ slug, h }) => (
                <div
                    key={slug}
                    className="flex h-24 w-52 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-white/10"
                >
                    <img
                        src={`/apps/${slug}.svg`}
                        alt=""
                        draggable={false}
                        className="w-auto max-w-40"
                        style={{ height: h }}
                    />
                </div>
            ))}
        </div>
    );
}

export function MarketplaceMarquee({ className }: { className?: string }) {
    return (
        <div className={className} aria-hidden="true">
            <style>{STYLES}</style>

            <div className="absolute inset-0 overflow-hidden bg-zinc-950">
                {/* Hafif renk derinligi icin sabit koyu zemin uzerine glow. */}
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(120,113,108,0.25),transparent_60%)]" />

                <div className="absolute top-1/2 left-1/2 flex w-[200%] -translate-x-1/2 -translate-y-1/2 -rotate-12 flex-col gap-6">
                    {ROWS.map(({ offset, speed, reverse }) => (
                        <div key={offset} className="flex overflow-hidden">
                            <div
                                className={`mkt-row flex shrink-0 ${reverse ? 'mkt-row-reverse' : ''}`}
                                style={
                                    {
                                        '--speed': `${speed}s`,
                                    } as React.CSSProperties
                                }
                            >
                                <LogoStrip offset={offset} />
                                <LogoStrip offset={offset} />
                            </div>
                        </div>
                    ))}
                </div>

                {/* Kenar karartmalari: ustte/altta yumusak gecis, metin icin taban. */}
                <div className="absolute inset-x-0 top-0 h-1/4 bg-gradient-to-b from-zinc-950 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-zinc-950 from-35% via-zinc-950/85 to-transparent" />
            </div>
        </div>
    );
}
