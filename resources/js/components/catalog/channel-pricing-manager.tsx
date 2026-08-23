import {
    ArrowRight,
    Calculator,
    Check,
    Globe,
    HelpCircle,
    Info,
    Store,
    TrendingUp,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import { cn } from '@/lib/utils';

export type ChannelConnectionItem = {
    id: number;
    name: string;
    marketplace: string;
};

type Props = {
    connections: ChannelConnectionItem[];
    selectedChannelIds: number[];
    onToggleChannel: (id: number) => void;
    basePrice: number | string;
    onApplySuggestedPrice?: (price: string) => void;
};

// Default standard commissions per marketplace for suggestions
const DEFAULT_COMMISSIONS: Record<string, number> = {
    trendyol: 18,
    hepsiburada: 17,
    amazon: 15,
    ciceksepeti: 20,
    n11: 16,
    pazarama: 14,
};

export function ChannelPricingManager({
    connections,
    selectedChannelIds,
    onToggleChannel,
    basePrice,
    onApplySuggestedPrice,
}: Props) {
    const numBasePrice = parseFloat(String(basePrice)) || 0;

    // Commission rates entered by the user per channel connection ID
    const [commissionRates, setCommissionRates] = useState<Record<number, number>>(() => {
        const initial: Record<number, number> = {};
        connections.forEach((conn) => {
            initial[conn.id] = DEFAULT_COMMISSIONS[conn.marketplace.toLowerCase()] ?? 15;
        });
        return initial;
    });

    const handleCommissionChange = (connId: number, val: string) => {
        const parsed = parseFloat(val);
        setCommissionRates((prev) => ({
            ...prev,
            [connId]: isNaN(parsed) ? 0 : parsed,
        }));
    };

    // Calculate suggested price: Base / (1 - (Commission / 100))
    // e.g. Base 100 TL with 20% commission => 100 / 0.8 = 125 TL
    const calculateSuggestedPrice = (commission: number): number => {
        if (numBasePrice <= 0) return 0;
        if (commission >= 100) return numBasePrice;
        const rate = commission / 100;
        const suggested = numBasePrice / (1 - rate);
        return Math.round(suggested * 100) / 100;
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                <div>
                    <h3 className="text-sm font-semibold text-foreground">
                        Satış Kanalları & Komisyon Fiyatlandırması
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Ürünün hangi pazaryerlerinde satışa açılacağını seçin ve komisyon oranlarına göre önerilen satış fiyatını hesaplayın.
                    </p>
                </div>
            </div>

            {connections.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground space-y-1">
                    <Store className="size-6 mx-auto opacity-40 mb-1" />
                    <p className="font-medium text-foreground">Henüz bağlı bir satış kanalı bulunmuyor.</p>
                    <p>Kanallar menüsünden Trendyol, Hepsiburada vb. mağazalarınızı bağlayabilirsiniz.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {connections.map((conn) => {
                        const isSelected = selectedChannelIds.includes(conn.id);
                        const commission = commissionRates[conn.id] ?? 15;
                        const suggestedPrice = calculateSuggestedPrice(commission);
                        const commissionAmount = suggestedPrice > numBasePrice ? Math.round((suggestedPrice - numBasePrice) * 100) / 100 : 0;

                        return (
                            <div
                                key={conn.id}
                                className={cn(
                                    'p-3.5 rounded-xl border transition-all space-y-3 bg-card',
                                    isSelected
                                        ? 'border-primary/60 ring-1 ring-primary/20 shadow-xs'
                                        : 'border-border opacity-85 hover:opacity-100'
                                )}
                            >
                                {/* Top Header: Channel Name & Checkbox */}
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        <MarketplaceAvatar
                                            code={conn.marketplace}
                                            name={conn.name}
                                            size="md"
                                            className="size-8 rounded-full p-0.5"
                                        />
                                        <div className="min-w-0">
                                            <p className="text-xs font-semibold text-foreground truncate">
                                                {conn.name}
                                            </p>
                                            <p className="text-[10px] text-muted-foreground capitalize">
                                                {conn.marketplace}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={isSelected ? 'default' : 'secondary'}
                                            className="text-[10px] h-5"
                                        >
                                            {isSelected ? 'Satışta' : 'Kapalı'}
                                        </Badge>
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => onToggleChannel(conn.id)}
                                            aria-label={`${conn.name} satışa aç/kapat`}
                                        />
                                    </div>
                                </div>

                                {/* Hidden input for form submission */}
                                {isSelected && (
                                    <input type="hidden" name="channel_ids[]" value={conn.id} />
                                )}

                                {/* Channel Commission & Suggested Price Box */}
                                {isSelected && (
                                    <div className="pt-2 border-t border-border/60 space-y-2.5 animate-in fade-in duration-100">
                                        <div className="flex items-center justify-between gap-2 text-xs">
                                            <div className="flex items-center gap-1.5">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    Pazaryeri Komisyonu (%)
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <span className="text-[11px] text-muted-foreground">%</span>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max="90"
                                                    step="0.5"
                                                    value={commission}
                                                    onChange={(e) => handleCommissionChange(conn.id, e.target.value)}
                                                    className="w-16 h-7 text-xs text-right font-mono"
                                                />
                                            </div>
                                        </div>

                                        {/* Suggested Price Calculation Box */}
                                        <div className="rounded-lg bg-secondary/30 p-2.5 space-y-1.5">
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-muted-foreground">Taban (Net) Fiyat:</span>
                                                <span className="font-mono font-medium">{numBasePrice.toFixed(2)} TL</span>
                                            </div>
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-muted-foreground">Pazaryeri Kesintisi (%{commission}):</span>
                                                <span className="font-mono text-muted-foreground">+{commissionAmount.toFixed(2)} TL</span>
                                            </div>
                                            <div className="flex items-center justify-between text-xs pt-1 border-t border-border/50 font-semibold text-foreground">
                                                <span className="flex items-center gap-1 text-primary">
                                                    <Calculator className="size-3.5" />
                                                    Önerilen Satış Fiyatı:
                                                </span>
                                                <span className="font-mono text-primary text-sm tabular-nums">
                                                    {suggestedPrice.toFixed(2)} TL
                                                </span>
                                            </div>
                                        </div>

                                        {onApplySuggestedPrice && suggestedPrice > 0 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onApplySuggestedPrice(suggestedPrice.toFixed(2))}
                                                className="w-full text-[11px] h-6 text-primary hover:bg-primary/10 gap-1"
                                            >
                                                <ArrowRight className="size-3" />
                                                Önerilen Fiyatı Ürün Fiyatına Uygula ({suggestedPrice.toFixed(2)} TL)
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
