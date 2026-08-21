import { toast } from 'sonner';

/**
 * Satir ici duzenlemelerde form yoktur, dolayisiyla hatayi gosterecek bir alan
 * da yoktur: ilk dogrulama mesaji toast olarak verilir. Basari mesajlari
 * sunucudan `Inertia::flash('toast', ...)` ile gelir.
 */
export function toastError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem tamamlanamadı.');
}
