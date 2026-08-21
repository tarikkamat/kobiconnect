import { Head, useRemember } from '@inertiajs/react';
import { AlertTriangle, Check, Lock } from 'lucide-react';
import { AttributeStep } from '@/components/channels/mapping/attribute-step';
import { BrandStep } from '@/components/channels/mapping/brand-step';
import { CategoryStep } from '@/components/channels/mapping/category-step';
import { ReviewStep } from '@/components/channels/mapping/review-step';
import type { WizardProps } from '@/components/channels/mapping/types';
import { ValueStep } from '@/components/channels/mapping/value-step';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index } from '@/routes/mapping';

const STEPS = [
    'Kategori',
    'Özellikler',
    'Değerler',
    'Marka',
    'Önizleme',
] as const;

export default function MappingWizard(props: WizardProps) {
    const canManage = usePermission()('channels.manage');

    /*
     * Sihirbaz uzundur ve kesintiye ugrar: kullanici sekmeyi degistirir, bir
     * urune bakip geri doner. Adim numarasi `useRemember` ile gecmis girdisine
     * yazilir, boylece geri/ileri gezinmesi kullaniciyi bastan baslatmaz.
     * Eslemenin KENDISI zaten her adimda sunucuya kaydediliyor — burada tutulan
     * tek sey "neredeydim" bilgisi.
     */
    const [step, setStep] = useRemember(
        0,
        `mapping:${props.connection.id}:${props.category.id}`,
    );

    const mapped = props.mapping !== null;
    const errorCount = props.issues.filter(
        (issue) => issue.level === 'error',
    ).length;

    return (
        <>
            <Head title={`${props.category.name} eşlemesi`} />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title={`${props.category.name} · ${props.connection.name}`}
                    description={`${props.category.path} — ${props.category.productCount} ürün. Eşleme tamamlanmadan bu kategorideki ürünler pazaryerine gönderilemez.`}
                />

                {props.catalogError === null ? null : (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Pazaryeri kataloğu okunamadı</AlertTitle>
                        <AlertDescription>
                            {props.catalogError}
                        </AlertDescription>
                    </Alert>
                )}

                {props.lock.locked ? (
                    <Alert>
                        <Lock />
                        <AlertTitle>Bazı alanlar kilitli</AlertTitle>
                        <AlertDescription>{props.lock.reason}</AlertDescription>
                    </Alert>
                ) : null}

                <nav
                    aria-label="Sihirbaz adımları"
                    className="flex flex-wrap gap-1 rounded-xl border p-1"
                >
                    {STEPS.map((label, position) => {
                        const disabled = position > 0 && !mapped;
                        const button = (
                            <Button
                                key={label}
                                type="button"
                                variant={
                                    step === position ? 'default' : 'ghost'
                                }
                                size="sm"
                                disabled={disabled}
                                aria-current={
                                    step === position ? 'step' : undefined
                                }
                                onClick={() => setStep(position)}
                                className={cn('gap-2')}
                            >
                                <span className="text-xs opacity-70">
                                    {position + 1}
                                </span>
                                {label}
                            </Button>
                        );

                        if (!disabled) {
                            return button;
                        }

                        return (
                            <Tooltip key={label}>
                                <TooltipTrigger asChild>
                                    <span tabIndex={0} className="inline-flex">
                                        {button}
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Önce kategori eşlemesini kaydedin; sonraki
                                    adımlar seçilen pazaryeri kategorisine göre
                                    şekilleniyor.
                                </TooltipContent>
                            </Tooltip>
                        );
                    })}

                    <span className="ml-auto flex items-center gap-2 px-3 text-xs text-muted-foreground">
                        {errorCount === 0 && mapped ? (
                            <>
                                <Check className="size-4" /> Gönderime hazır
                            </>
                        ) : (
                            <>
                                <AlertTriangle className="size-4" />
                                {errorCount} eksik
                            </>
                        )}
                    </span>
                </nav>

                {step === 0 ? (
                    <CategoryStep
                        {...props}
                        canManage={canManage}
                        onSaved={() => setStep(1)}
                    />
                ) : null}

                {step === 1 ? (
                    <AttributeStep
                        {...props}
                        canManage={canManage}
                        onSaved={() => setStep(2)}
                    />
                ) : null}

                {step === 2 ? (
                    <ValueStep
                        {...props}
                        canManage={canManage}
                        onSaved={() => setStep(3)}
                    />
                ) : null}

                {step === 3 ? (
                    <BrandStep
                        {...props}
                        canManage={canManage}
                        onSaved={() => setStep(4)}
                    />
                ) : null}

                {step === 4 ? <ReviewStep {...props} /> : null}
            </div>
        </>
    );
}

MappingWizard.layout = {
    breadcrumbs: [
        {
            title: 'Eşlemeler',
            href: index(),
        },
    ],
};
