import * as React from 'react';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

export interface SearchableSelectOption {
    value: string;
    label: string;
}

export interface SearchableSelectProps {
    value?: string | null;
    onValueChange: (value: string) => void;
    options: SearchableSelectOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    className?: string;
    id?: string;
    name?: string;
}

export function SearchableSelect({
    value,
    onValueChange,
    options,
    placeholder = 'Seçiniz...',
    searchPlaceholder = 'Ara...',
    emptyText = 'Sonuç bulunamadı.',
    disabled = false,
    className,
    id,
    name,
}: SearchableSelectProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const inputRef = React.useRef<HTMLInputElement>(null);

    const selectedOption = React.useMemo(() => {
        return options.find((opt) => opt.value === value);
    }, [options, value]);

    const filteredOptions = React.useMemo(() => {
        if (!search.trim()) {
            return options;
        }
        const query = search.trim().toLocaleLowerCase('tr');
        return options.filter((opt) =>
            opt.label.toLocaleLowerCase('tr').includes(query)
        );
    }, [options, search]);

    const handleSelect = (val: string) => {
        onValueChange(val);
        setOpen(false);
        setSearch('');
    };

    return (
        <div className="relative w-full">
            {name && <input type="hidden" name={name} value={value ?? ''} />}
            <Popover
                open={open}
                onOpenChange={(nextOpen) => {
                    if (disabled) return;
                    setOpen(nextOpen);
                    if (!nextOpen) {
                        setSearch('');
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <button
                        id={id}
                        type="button"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        className={cn(
                            'flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs text-foreground shadow-xs transition-colors hover:bg-secondary/50 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-left',
                            !selectedOption && 'text-muted-foreground',
                            className
                        )}
                    >
                        <span className="truncate">
                            {selectedOption ? selectedOption.label : placeholder}
                        </span>
                        <ChevronDown className="size-4 opacity-50 shrink-0 ml-2" />
                    </button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    sideOffset={4}
                    className="w-[var(--radix-popover-trigger-width)] min-w-[220px] p-0 border-border bg-popover text-popover-foreground shadow-md rounded-md overflow-hidden z-50"
                    onOpenAutoFocus={(e) => {
                        e.preventDefault();
                        inputRef.current?.focus();
                    }}
                >
                    <div className="flex items-center border-b border-border px-2.5 py-1.5 gap-2 bg-muted/20">
                        <Search className="size-3.5 text-muted-foreground shrink-0" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="flex-1 bg-transparent text-xs text-foreground placeholder:text-muted-foreground outline-none border-none p-0 focus:ring-0"
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="text-muted-foreground hover:text-foreground p-0.5 rounded-xs"
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </div>
                    <div className="max-h-56 overflow-y-auto p-1 text-xs space-y-0.5">
                        {filteredOptions.length === 0 ? (
                            <div className="py-6 text-center text-xs text-muted-foreground">
                                {emptyText}
                            </div>
                        ) : (
                            filteredOptions.map((opt) => {
                                const isSelected = opt.value === value;
                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => handleSelect(opt.value)}
                                        className={cn(
                                            'flex w-full cursor-pointer select-none items-center justify-between rounded-sm px-2 py-1.5 text-xs text-foreground hover:bg-accent hover:text-accent-foreground outline-none transition-colors text-left',
                                            isSelected &&
                                                'bg-accent/60 font-medium text-accent-foreground'
                                        )}
                                    >
                                        <span className="truncate">{opt.label}</span>
                                        {isSelected && (
                                            <Check className="size-3.5 text-primary shrink-0 ml-2" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
