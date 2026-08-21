/** Kullanicinin verdigi semadaki kart konumlari; logolar `public/apps` altindan. */
const CARDS = [
    { slug: 'trendyol', x: 580, y: 130 },
    { slug: 'shopify', x: 610, y: 240 },
    { slug: 'ideasoft', x: 630, y: 350 },
    { slug: 'ciceksepeti', x: 620, y: 460 },
    { slug: 'hepsiburada', x: 600, y: 570 },
    { slug: 'pttavm', x: 560, y: 680 },
    { slug: 'amazon', x: 510, y: 790 },
    { slug: 'n11', x: 460, y: 900 },
];

/** Bezier bitis noktalari (kart sol kenari, dikey ortasi). */
const ENDPOINTS = CARDS.map(({ x, y }) => ({ x, y: y + 50 }));

export function IntegrationsDiagram({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 1200 1800"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
            className={className}
        >
            <defs>
                <linearGradient id="bgGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#f5f4f0" />
                    <stop offset="60%" stopColor="#eae8e2" />
                    <stop offset="100%" stopColor="#dcdad3" />
                </linearGradient>

                <filter
                    id="softShadow"
                    x="-20%"
                    y="-20%"
                    width="140%"
                    height="140%"
                >
                    <feDropShadow
                        dx="0"
                        dy="12"
                        stdDeviation="16"
                        floodColor="#000000"
                        floodOpacity="0.07"
                    />
                    <feDropShadow
                        dx="0"
                        dy="2"
                        stdDeviation="4"
                        floodColor="#000000"
                        floodOpacity="0.04"
                    />
                </filter>

                <filter
                    id="hubShadow"
                    x="-50%"
                    y="-50%"
                    width="200%"
                    height="200%"
                >
                    <feDropShadow
                        dx="0"
                        dy="8"
                        stdDeviation="12"
                        floodColor="#000000"
                        floodOpacity="0.15"
                    />
                </filter>

                <filter
                    id="lineGlow"
                    x="-20%"
                    y="-20%"
                    width="140%"
                    height="140%"
                >
                    <feDropShadow
                        dx="0"
                        dy="2"
                        stdDeviation="3"
                        floodColor="#a09d96"
                        floodOpacity="0.2"
                    />
                </filter>

                <radialGradient id="hubCore" cx="35%" cy="35%" r="65%">
                    <stop offset="0%" stopColor="#ffffff" />
                    <stop offset="50%" stopColor="#b8b5ad" />
                    <stop offset="100%" stopColor="#6b6862" />
                </radialGradient>

                <radialGradient id="hubOuter" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stopColor="#e8e6e1" />
                    <stop offset="100%" stopColor="#cfccc4" />
                </radialGradient>
            </defs>

            <rect width="1200" height="1800" fill="url(#bgGrad)" />

            <circle
                cx="260"
                cy="580"
                r="420"
                fill="none"
                stroke="#e0ded7"
                strokeWidth="1"
                strokeDasharray="4 8"
            />
            <circle
                cx="260"
                cy="580"
                r="280"
                fill="none"
                stroke="#e0ded7"
                strokeWidth="1"
                strokeDasharray="2 6"
            />

            <g
                stroke="#ada89e"
                strokeWidth="2.5"
                fill="none"
                filter="url(#lineGlow)"
                strokeLinecap="round"
            >
                <path d="M 260 580 C 400 580, 480 180, 580 180" />
                <path d="M 260 580 C 410 580, 510 290, 610 290" />
                <path d="M 260 580 C 430 580, 540 400, 630 400" />
                <path d="M 260 580 C 440 580, 540 510, 620 510" />
                <path d="M 260 580 C 440 580, 530 620, 600 620" />
                <path d="M 260 580 C 420 580, 500 730, 560 730" />
                <path d="M 260 580 C 400 580, 470 840, 510 840" />
                <path d="M 260 580 C 370 580, 420 950, 460 950" />
            </g>

            <g fill="#ada89e">
                {ENDPOINTS.map(({ x, y }) => (
                    <circle key={`${x}-${y}`} cx={x} cy={y} r="4" />
                ))}
            </g>

            <g transform="translate(260, 580)" filter="url(#hubShadow)">
                <circle
                    cx="0"
                    cy="0"
                    r="48"
                    fill="url(#hubOuter)"
                    stroke="#ffffff"
                    strokeWidth="3"
                />
                <circle cx="0" cy="0" r="32" fill="#ffffff" />
                <circle cx="0" cy="0" r="26" fill="url(#hubCore)" />
                <circle cx="0" cy="0" r="8" fill="#ffffff" />
            </g>

            {CARDS.map(({ slug, x, y }) => (
                <g
                    key={slug}
                    transform={`translate(${x}, ${y})`}
                    filter="url(#softShadow)"
                >
                    <rect width="360" height="100" rx="20" fill="#ffffff" />
                    <image
                        href={`/apps/${slug}.svg`}
                        x="50"
                        y="25"
                        width="260"
                        height="50"
                        preserveAspectRatio="xMidYMid meet"
                    />
                </g>
            ))}
        </svg>
    );
}
