import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Cable,
    CircleCheck,
    PackageOpen,
    RefreshCw,
    Rocket,
    Sparkles,
    Store,
    Layers,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export interface OnboardingStep {
    done: boolean;
    title: string;
    description: string;
    href: string;
}

interface OnboardingOverlayProps {
    steps: OnboardingStep[];
}

const STEP_ICONS = [Cable, PackageOpen, RefreshCw];

const WIZARD_STEPS = [
    {
        icon: Store,
        badge: '1. Adım',
        title: 'Pazaryeri Mağazalarınızı Bağlayın',
        description:
            'Trendyol, Hepsiburada ve diğer pazar yerlerindeki mağazalarınızı API anahtarlarınızla saniyeler içinde entegre edin. Siparişler ve stoklar bu bağlantılar üzerinden anlık olarak akar.',
        highlight:
            'API bilgilerinizi girerek bağlantınızı hemen aktifleştirin.',
        buttonText: 'Pazaryerlerini Bağla',
    },
    {
        icon: Layers,
        badge: '2. Adım',
        title: 'Kataloğunuza Ürün Ekleyin',
        description:
            'Ürünlerinizi varyant, barkod, fiyat ve emniyet stok bilgileriyle birlikte kataloğunuza ekleyin. Eşleşen ürünleriniz tüm kanallarda tek bir havuzdan yönetilir.',
        highlight:
            'Tekli veya çoklu varyantlı ürünlerinizi sisteme tanımlayın.',
        buttonText: 'Kataloğa Ürün Ekle',
    },
    {
        icon: ShoppingCart,
        badge: '3. Adım',
        title: 'İlk Senkronu Çalıştırın',
        description:
            'Bağlı mağazalarınızdan gelen siparişler otomatik olarak çekilir. Stoklarınız satılan adet kadar anında düşer ve tüm pazaryerlerinde güncellenir.',
        highlight: 'Gelen siparişleri ve senkronizasyon durumunu takip edin.',
        buttonText: 'Siparişlere Git',
    },
];

export function OnboardingOverlay({ steps }: OnboardingOverlayProps) {
    const [wizardOpen, setWizardOpen] = useState(false);
    const [wizardStep, setWizardStep] = useState(0);

    const completedCount = steps.filter((s) => s.done).length;
    const firstPendingStep = steps.find((s) => !s.done);

    return (
        <>
            <div className="pointer-events-auto absolute inset-0 z-20 flex items-center justify-center p-4">
                <div className="w-full max-w-xl rounded-2xl border border-border bg-card/95 p-5 shadow-2xl backdrop-blur-xl transition-all sm:p-6">
                    {/* Header */}
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <Badge
                                variant="outline"
                                className="inline-flex items-center gap-1.5 border-primary/20 bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                            >
                                <Sparkles className="size-3.5" />
                                Kurulum Rehberi
                            </Badge>
                            <span className="font-mono text-xs text-muted-foreground">
                                {completedCount} / {steps.length} Tamamlandı
                            </span>
                        </div>

                        <h2 className="text-lg font-bold tracking-tight text-foreground sm:text-xl">
                            KobiConnect&apos;e Hoş Geldiniz
                        </h2>
                        <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                            Pazaryeri mağazalarınızı bağlayarak satış, stok ve
                            siparişlerinizi tek bir merkezden anlık olarak
                            yönetmeye başlayın.
                        </p>
                    </div>

                    {/* Step Cards */}
                    <div className="mt-4 flex flex-col gap-2.5">
                        {steps.map((step, index) => {
                            const IconComponent = STEP_ICONS[index] ?? Cable;

                            return (
                                <div
                                    key={step.title}
                                    className={cn(
                                        'group flex flex-col justify-between gap-3 rounded-xl border p-3.5 transition-colors sm:flex-row sm:items-center',
                                        step.done
                                            ? 'border-border/60 bg-muted/30 opacity-75'
                                            : 'border-border bg-card hover:border-primary/40 hover:bg-secondary/40',
                                    )}
                                >
                                    <div className="flex items-start gap-3">
                                        <div
                                            className={cn(
                                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold',
                                                step.done
                                                    ? 'border-success/30 bg-success/10 text-success'
                                                    : 'border-border bg-secondary text-foreground',
                                            )}
                                        >
                                            {step.done ? (
                                                <CircleCheck className="size-4 text-success" />
                                            ) : (
                                                <IconComponent className="size-4" />
                                            )}
                                        </div>

                                        <div className="flex flex-col gap-0.5">
                                            <span
                                                className={cn(
                                                    'text-xs font-semibold tracking-tight sm:text-sm',
                                                    step.done
                                                        ? 'text-muted-foreground line-through'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {step.title}
                                            </span>
                                            <p className="text-xs leading-normal text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 items-center justify-end sm:pl-2">
                                        {step.done ? (
                                            <Badge
                                                variant="secondary"
                                                className="border-success/20 bg-success/10 text-xs text-success"
                                            >
                                                Tamamlandı
                                            </Badge>
                                        ) : (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="h-8 text-xs group-hover:border-primary/50 group-hover:bg-background"
                                            >
                                                <Link href={step.href}>
                                                    Başla
                                                    <ArrowRight className="ml-1 size-3 transition-transform group-hover:translate-x-0.5" />
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Footer CTA */}
                    <div className="mt-5 flex flex-col items-center justify-between gap-3 border-t border-border pt-4 sm:flex-row">
                        <p className="text-xs text-muted-foreground">
                            Adımları tamamladığınızda canlı gösterge paneliniz
                            aktif olacaktır.
                        </p>

                        <Button
                            type="button"
                            variant="default"
                            size="sm"
                            className="h-9 w-full gap-2 sm:w-auto"
                            onClick={() => {
                                setWizardStep(0);
                                setWizardOpen(true);
                            }}
                        >
                            <Rocket className="size-3.5" />
                            Şimdi Başla
                        </Button>
                    </div>
                </div>
            </div>

            {/* Interactive Guided Onboarding Wizard Modal */}
            <Dialog open={wizardOpen} onOpenChange={setWizardOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <div className="flex items-center justify-between pr-6">
                            <Badge
                                variant="outline"
                                className="border-primary/20 bg-primary/10 text-xs text-primary"
                            >
                                {WIZARD_STEPS[wizardStep].badge}
                            </Badge>
                            <span className="font-mono text-xs text-muted-foreground">
                                {wizardStep + 1} / {WIZARD_STEPS.length}
                            </span>
                        </div>
                        <DialogTitle className="mt-2 text-xl font-bold tracking-tight">
                            {WIZARD_STEPS[wizardStep].title}
                        </DialogTitle>
                        <DialogDescription className="mt-1 text-sm leading-relaxed">
                            {WIZARD_STEPS[wizardStep].description}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-3 rounded-xl border border-border/80 bg-secondary/40 p-4">
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                {(() => {
                                    const CurrentIcon =
                                        WIZARD_STEPS[wizardStep].icon;

                                    return <CurrentIcon className="size-5" />;
                                })()}
                            </div>
                            <div className="flex flex-col gap-1">
                                <span className="text-sm font-semibold text-foreground">
                                    Önemli İpucu
                                </span>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {WIZARD_STEPS[wizardStep].highlight}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Wizard Stepper Progress Dots */}
                    <div className="flex items-center justify-center gap-2 py-2">
                        {WIZARD_STEPS.map((_, idx) => (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => setWizardStep(idx)}
                                className={cn(
                                    'h-1.5 rounded-full transition-all',
                                    idx === wizardStep
                                        ? 'w-8 bg-primary'
                                        : 'w-2 bg-muted hover:bg-muted-foreground/40',
                                )}
                                aria-label={`Adım ${idx + 1}`}
                            />
                        ))}
                    </div>

                    {/* Modal Footer Controls */}
                    <div className="mt-2 flex flex-col-reverse items-center justify-between gap-2 border-t border-border pt-4 sm:flex-row">
                        <div>
                            {wizardStep > 0 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setWizardStep((prev) => prev - 1)
                                    }
                                    className="gap-1.5"
                                >
                                    <ArrowLeft className="size-3.5" />
                                    Önceki
                                </Button>
                            )}
                        </div>

                        <div className="flex w-full items-center justify-end gap-2 sm:w-auto">
                            {wizardStep < WIZARD_STEPS.length - 1 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setWizardStep((prev) => prev + 1)
                                    }
                                    className="gap-1.5"
                                >
                                    Sonraki
                                    <ArrowRight className="size-3.5" />
                                </Button>
                            ) : null}

                            <Button asChild variant="default" size="sm">
                                <Link
                                    href={
                                        steps[wizardStep]?.href ??
                                        firstPendingStep?.href ??
                                        '#'
                                    }
                                >
                                    {WIZARD_STEPS[wizardStep].buttonText}
                                    <ArrowRight className="ml-1 size-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
