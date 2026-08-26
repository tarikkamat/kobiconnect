import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgePercent,
    Coins,
    Plus,
    Sliders,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import PriceListController from '@/actions/App/Http/Controllers/Catalog/PriceListController';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Option = { value: string; label: string };
type IdName = { id: number; name: string };

type RuleState = {
    field: string;
    condition_value: any;
    adjustment_type: 'percentage' | 'fixed';
    adjustment_value: number | string;
};

export default function PriceListCreate({
    roundingMethods = [],
    ruleFields = [],
    adjustmentTypes = [],
    categories = [],
    brands = [],
    tags = [],
    products = [],
}: {
    types?: Option[];
    roundingMethods: Option[];
    ruleFields: Option[];
    adjustmentTypes: Option[];
    categories: IdName[];
    brands: IdName[];
    tags: IdName[];
    products: IdName[];
}) {
    const [name, setName] = useState('');
    const [type, setType] = useState<'manual' | 'currency' | 'dynamic'>(
        'manual',
    );
    const [sourceCurrency, setSourceCurrency] = useState('TRY');
    const [targetCurrency, setTargetCurrency] = useState('TRY');
    const [exchangeRate, setExchangeRate] = useState<string>('1.000000');
    const [roundingMethod, setRoundingMethod] = useState('none');
    const [isActive, setIsActive] = useState(true);
    const [description, setDescription] = useState('');

    const [rules, setRules] = useState<RuleState[]>([
        {
            field: 'all',
            condition_value: null,
            adjustment_type: 'percentage',
            adjustment_value: '10',
        },
    ]);

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const addRule = () => {
        setRules((prev) => [
            ...prev,
            {
                field: 'category',
                condition_value: categories[0]?.id
                    ? String(categories[0].id)
                    : null,
                adjustment_type: 'percentage',
                adjustment_value: '10',
            },
        ]);
    };

    const removeRule = (idx: number) => {
        setRules((prev) => prev.filter((_, i) => i !== idx));
    };

    const updateRule = (idx: number, key: keyof RuleState, val: any) => {
        setRules((prev) =>
            prev.map((r, i) => (i === idx ? { ...r, [key]: val } : r)),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload: any = {
            name,
            type,
            source_currency: sourceCurrency,
            target_currency: targetCurrency,
            exchange_rate:
                type === 'currency' ? parseFloat(exchangeRate) : null,
            rounding_method: roundingMethod,
            is_active: isActive,
            description: description || null,
        };

        if (type === 'dynamic') {
            payload.rules = rules.map((r, pos) => ({
                field: r.field,
                condition_value: r.condition_value,
                adjustment_type: r.adjustment_type,
                adjustment_value: parseFloat(String(r.adjustment_value)) || 0,
                position: pos,
            }));
        }

        router.post(PriceListController.store.url(), payload, {
            preserveScroll: true,
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
                toastError(errs);
            },
        });
    };

    return (
        <>
            <Head title="Yeni Fiyat Listesi Oluştur" />

            <div className="mx-auto flex max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 gap-1.5"
                    >
                        <Link href={PriceListController.index.url()}>
                            <ArrowLeft className="size-4" />
                            Tüm Fiyat Listeleri
                        </Link>
                    </Button>
                </div>

                <div>
                    <Heading
                        title="Yeni Fiyat Listesi Oluştur"
                        description="Satış kanalınıza, mağazanıza veya pazaryerine özel fiyatlandırma tanımlayın."
                    />
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Temel Bilgiler */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                1. Liste Tipi & Temel Bilgiler
                            </CardTitle>
                            <CardDescription>
                                Listenin nasıl hesaplanacağını ve temel para
                                birimini belirleyin.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="list-name">
                                    Fiyat Listesi Adı
                                </Label>
                                <Input
                                    id="list-name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Örn: Trendyol Özel Fiyat Listesi, Dolar İhracat Fiyatları..."
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            {/* Liste Tipi Seçimi (3 Tip Kart) */}
                            <div className="space-y-2">
                                <Label>Hesaplama Modeli</Label>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {/* Manuel */}
                                    <div
                                        onClick={() => setType('manual')}
                                        className={`cursor-pointer rounded-lg border p-4 transition-all ${
                                            type === 'manual'
                                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                : 'border-border hover:border-primary/50'
                                        }`}
                                    >
                                        <div className="mb-1.5 flex items-center gap-2">
                                            <Sliders className="size-4 text-emerald-500" />
                                            <span className="text-sm font-semibold">
                                                Manuel Liste
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Her ürün ve varyant için fiyatları
                                            tek tek elle belirleyin.
                                        </p>
                                    </div>

                                    {/* Kura Göre */}
                                    <div
                                        onClick={() => setType('currency')}
                                        className={`cursor-pointer rounded-lg border p-4 transition-all ${
                                            type === 'currency'
                                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                : 'border-border hover:border-primary/50'
                                        }`}
                                    >
                                        <div className="mb-1.5 flex items-center gap-2">
                                            <Coins className="size-4 text-amber-500" />
                                            <span className="text-sm font-semibold">
                                                Kura Göre Liste
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Belirlediğiniz döviz kuruna göre
                                            fiyatları otomatik çevirin.
                                        </p>
                                    </div>

                                    {/* Dinamik */}
                                    <div
                                        onClick={() => setType('dynamic')}
                                        className={`cursor-pointer rounded-lg border p-4 transition-all ${
                                            type === 'dynamic'
                                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                : 'border-border hover:border-primary/50'
                                        }`}
                                    >
                                        <div className="mb-1.5 flex items-center gap-2">
                                            <BadgePercent className="size-4 text-blue-500" />
                                            <span className="text-sm font-semibold">
                                                Dinamik Liste
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Kategori veya markaya özel yüzde /
                                            sabit tutar kuralları tanımlayın.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="target-currency">
                                        Hedef Para Birimi
                                    </Label>
                                    <Select
                                        value={targetCurrency}
                                        onValueChange={setTargetCurrency}
                                    >
                                        <SelectTrigger id="target-currency">
                                            <SelectValue placeholder="Para Birimi" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="TRY">
                                                TRY (Türk Lirası - ₺)
                                            </SelectItem>
                                            <SelectItem value="USD">
                                                USD (Amerikan Doları - $)
                                            </SelectItem>
                                            <SelectItem value="EUR">
                                                EUR (Euro - €)
                                            </SelectItem>
                                            <SelectItem value="GBP">
                                                GBP (İngiliz Sterlini - £)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="rounding">
                                        Yuvarlama Kuralı
                                    </Label>
                                    <Select
                                        value={roundingMethod}
                                        onValueChange={setRoundingMethod}
                                    >
                                        <SelectTrigger id="rounding">
                                            <SelectValue placeholder="Yuvarlama" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roundingMethods.map((r) => (
                                                <SelectItem
                                                    key={r.value}
                                                    value={r.value}
                                                >
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Kura Göre Ayarlar */}
                    {type === 'currency' && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Coins className="size-4 text-amber-500" />
                                    2. Kur ve Çevrim Ayarları
                                </CardTitle>
                                <CardDescription>
                                    Kaynak para birimindeki fiyatlar bu kur ile
                                    çarpılarak yeni listede sunulur.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="source-currency">
                                            Kaynak Para Birimi
                                        </Label>
                                        <Select
                                            value={sourceCurrency}
                                            onValueChange={setSourceCurrency}
                                        >
                                            <SelectTrigger id="source-currency">
                                                <SelectValue placeholder="Kaynak Para Birimi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="TRY">
                                                    TRY (Türk Lirası - ₺)
                                                </SelectItem>
                                                <SelectItem value="USD">
                                                    USD (Amerikan Doları - $)
                                                </SelectItem>
                                                <SelectItem value="EUR">
                                                    EUR (Euro - €)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="exchange-rate">
                                            Döviz Kuru (1 {sourceCurrency} = ?{' '}
                                            {targetCurrency})
                                        </Label>
                                        <Input
                                            id="exchange-rate"
                                            type="number"
                                            step="0.000001"
                                            value={exchangeRate}
                                            onChange={(e) =>
                                                setExchangeRate(e.target.value)
                                            }
                                            placeholder="Örn: 0.028500"
                                            required
                                        />
                                        <InputError
                                            message={errors.exchange_rate}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Dinamik Kurallar */}
                    {type === 'dynamic' && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <BadgePercent className="size-4 text-blue-500" />
                                            2. Fiyatlama Kuralları
                                        </CardTitle>
                                        <CardDescription>
                                            Kurallar sırayla uygulanır; ilk
                                            eşleşen kural fiyatı belirler.
                                        </CardDescription>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addRule}
                                        className="gap-1 text-xs"
                                    >
                                        <Plus className="size-3.5" />
                                        Kural Ekle
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {rules.map((rule, idx) => (
                                    <div
                                        key={idx}
                                        className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-2xs sm:flex-row sm:items-center"
                                    >
                                        <div className="w-full sm:w-1/4">
                                            <Select
                                                value={rule.field}
                                                onValueChange={(val) => {
                                                    updateRule(
                                                        idx,
                                                        'field',
                                                        val,
                                                    );

                                                    if (val === 'all') {
                                                        updateRule(
                                                            idx,
                                                            'condition_value',
                                                            null,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue placeholder="Kapsam" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {ruleFields.map((f) => (
                                                        <SelectItem
                                                            key={f.value}
                                                            value={f.value}
                                                            className="text-xs"
                                                        >
                                                            {f.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Kapsam Değeri Seçimi */}
                                        {rule.field !== 'all' && (
                                            <div className="w-full sm:w-1/3">
                                                {rule.field === 'category' && (
                                                    <Select
                                                        value={String(
                                                            rule.condition_value ??
                                                                '',
                                                        )}
                                                        onValueChange={(val) =>
                                                            updateRule(
                                                                idx,
                                                                'condition_value',
                                                                parseInt(
                                                                    val,
                                                                    10,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Kategori seçin" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {categories.map(
                                                                (c) => (
                                                                    <SelectItem
                                                                        key={
                                                                            c.id
                                                                        }
                                                                        value={String(
                                                                            c.id,
                                                                        )}
                                                                        className="text-xs"
                                                                    >
                                                                        {c.name}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                )}

                                                {rule.field === 'brand' && (
                                                    <Select
                                                        value={String(
                                                            rule.condition_value ??
                                                                '',
                                                        )}
                                                        onValueChange={(val) =>
                                                            updateRule(
                                                                idx,
                                                                'condition_value',
                                                                parseInt(
                                                                    val,
                                                                    10,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Marka seçin" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {brands.map((b) => (
                                                                <SelectItem
                                                                    key={b.id}
                                                                    value={String(
                                                                        b.id,
                                                                    )}
                                                                    className="text-xs"
                                                                >
                                                                    {b.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                )}

                                                {rule.field === 'tag' && (
                                                    <Select
                                                        value={String(
                                                            rule.condition_value ??
                                                                '',
                                                        )}
                                                        onValueChange={(val) =>
                                                            updateRule(
                                                                idx,
                                                                'condition_value',
                                                                parseInt(
                                                                    val,
                                                                    10,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Etiket seçin" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {tags.map((t) => (
                                                                <SelectItem
                                                                    key={t.id}
                                                                    value={String(
                                                                        t.id,
                                                                    )}
                                                                    className="text-xs"
                                                                >
                                                                    {t.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                )}

                                                {rule.field === 'product' && (
                                                    <Select
                                                        value={String(
                                                            rule.condition_value ??
                                                                '',
                                                        )}
                                                        onValueChange={(val) =>
                                                            updateRule(
                                                                idx,
                                                                'condition_value',
                                                                parseInt(
                                                                    val,
                                                                    10,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 text-xs">
                                                            <SelectValue placeholder="Ürün seçin" />
                                                        </SelectTrigger>
                                                        <SelectContent className="max-h-56">
                                                            {products.map(
                                                                (p) => (
                                                                    <SelectItem
                                                                        key={
                                                                            p.id
                                                                        }
                                                                        value={String(
                                                                            p.id,
                                                                        )}
                                                                        className="text-xs"
                                                                    >
                                                                        {p.name}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                )}
                                            </div>
                                        )}

                                        {/* Artış/İndirim Tipi */}
                                        <div className="w-full sm:w-1/4">
                                            <Select
                                                value={rule.adjustment_type}
                                                onValueChange={(val) =>
                                                    updateRule(
                                                        idx,
                                                        'adjustment_type',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue placeholder="Değişim Tipi" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {adjustmentTypes.map(
                                                        (a) => (
                                                            <SelectItem
                                                                key={a.value}
                                                                value={a.value}
                                                                className="text-xs"
                                                            >
                                                                {a.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Değer */}
                                        <div className="w-full sm:w-28">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={rule.adjustment_value}
                                                onChange={(e) =>
                                                    updateRule(
                                                        idx,
                                                        'adjustment_value',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Örn: 15 veya -10"
                                                className="h-8 text-xs"
                                                required
                                            />
                                        </div>

                                        {rules.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeRule(idx)}
                                                className="size-8 shrink-0 self-end text-destructive hover:bg-destructive/10 sm:self-center"
                                                aria-label="Kuralı sil"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}

                    {/* Açıklama ve Durum */}
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div className="grid gap-2">
                                <Label htmlFor="list-desc">
                                    Açıklama / Notlar (İsteğe Bağlı)
                                </Label>
                                <Textarea
                                    id="list-desc"
                                    value={description}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    placeholder="Fiyat listesi hakkında açıklamalar..."
                                    rows={2}
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label
                                        htmlFor="active-toggle"
                                        className="cursor-pointer text-sm font-medium"
                                    >
                                        Aktif Fiyat Listesi
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Pasif listeler kanallarda ve
                                        entegrasyonlarda kullanılmaz.
                                    </p>
                                </div>
                                <Checkbox
                                    id="active-toggle"
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(Boolean(checked))
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        <Button asChild variant="outline" disabled={processing}>
                            <Link href={PriceListController.index.url()}>
                                Vazgeç
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || name.trim() === ''}
                        >
                            {processing
                                ? 'Oluşturuluyor...'
                                : 'Fiyat Listesini Oluştur'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
