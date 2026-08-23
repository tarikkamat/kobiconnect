import { format } from 'date-fns';
import { tr } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * Sunucu tarihleri `YYYY-MM-DD` YEREL gun olarak gonderir. `new Date(iso)`
 * bunu UTC gece yarisi sayar ve negatif ofsetli saat diliminde bir gun geri
 * kaydirir — alanlar tek tek verilerek yerel gun kurulur.
 */
function parseDay(iso: string): Date | undefined {
    const [year, month, day] = iso.split('-').map(Number);

    return year && month && day ? new Date(year, month - 1, day) : undefined;
}

function formatDay(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * Tarih araligi secici — takvim acilir pencerede, iki ay yan yana.
 * Deger DISARIDAN gelir (sunucu donemin kaynagidir); bilesen yalnizca
 * tamamlanmis bir aralikta (`from` VE `to`) `onSelect` cagirir, yoksa ilk
 * tiklamada yarim aralikla sayfa yenilenirdi.
 */
export function DateRangePicker({
    from,
    to,
    onSelect,
    className,
    label = 'Dönem',
}: {
    from: string;
    to: string;
    onSelect: (from: string, to: string) => void;
    className?: string;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected: DateRange | undefined = parseDay(from) && {
        from: parseDay(from),
        to: parseDay(to),
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={label}
                    className={cn(
                        'h-8 justify-start px-2.5 font-mono font-normal tabular-nums',
                        className,
                    )}
                >
                    <CalendarIcon />
                    {selected?.from ? (
                        selected.to ? (
                            <>
                                {format(selected.from, 'd MMM y', {
                                    locale: tr,
                                })}
                                {' – '}
                                {format(selected.to, 'd MMM y', { locale: tr })}
                            </>
                        ) : (
                            format(selected.from, 'd MMM y', { locale: tr })
                        )
                    ) : (
                        <span className="font-sans text-foreground/30">
                            Tarih seçin
                        </span>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent className="w-auto p-0" align="end">
                <Calendar
                    mode="range"
                    locale={tr}
                    autoFocus
                    defaultMonth={selected?.from}
                    selected={selected}
                    numberOfMonths={2}
                    disabled={{ after: new Date() }}
                    onSelect={(next) => {
                        if (next?.from && next.to) {
                            onSelect(formatDay(next.from), formatDay(next.to));
                            setOpen(false);
                        }
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
