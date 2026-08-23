export default function Heading({
    title,
    description,
    variant = 'default',
    badge,
}: {
    title: string;
    description?: React.ReactNode;
    variant?: 'default' | 'small';
    badge?: React.ReactNode;
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <div className="flex items-center gap-2">
                <h2
                    className={
                        variant === 'small'
                            ? 'mb-0.5 text-base font-medium'
                            : 'text-xl font-medium tracking-[-0.02em]'
                    }
                >
                    {title}
                </h2>
                {badge}
            </div>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
