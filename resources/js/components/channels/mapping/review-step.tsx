import { AlertTriangle, CircleCheck, Info } from 'lucide-react';
import type { WizardProps } from '@/components/channels/mapping/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * 5. adim — yerel on-dogrulama ozeti (BACKEND-PLAN §7.5).
 *
 * Kullanici gonderimden dort saat sonra "reddedildi" gormemeli: pazaryerinin
 * kategori ve bayrak kurallari burada, gonderimden ONCE isletilir.
 */
export function ReviewStep({ category, mapping, issues }: WizardProps) {
    const errors = issues.filter((issue) => issue.level === 'error');
    const warnings = issues.filter((issue) => issue.level === 'warning');

    return (
        <div className="grid gap-4">
            <section className="rounded-lg border border-border p-4">
                <h3 className="text-sm font-medium">Gönderilecek eşleme</h3>
                <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                    <dt className="text-muted-foreground">Kategori</dt>
                    <dd>{category.path}</dd>
                    <dt className="text-muted-foreground">
                        Pazaryeri kategorisi
                    </dt>
                    <dd>{mapping?.remotePath ?? '—'}</dd>
                    <dt className="text-muted-foreground">Etkilenen ürün</dt>
                    <dd className="font-mono tabular-nums">
                        {category.productCount}
                    </dd>
                </dl>
            </section>

            {errors.length === 0 ? (
                <Alert>
                    <CircleCheck />
                    <AlertTitle>Eşleme tamam</AlertTitle>
                    <AlertDescription>
                        Bu kategorideki ürünler pazaryerine gönderilmeye hazır.
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert variant="destructive">
                    <AlertTriangle />
                    <AlertTitle>
                        <span className="font-mono tabular-nums">
                            {errors.length}
                        </span>{' '}
                        eksik giderilmeden gönderim yapılamaz
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc pl-4">
                            {errors.map((issue) => (
                                <li key={issue.message}>{issue.message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            {warnings.length === 0 ? null : (
                <Alert>
                    <Info />
                    <AlertTitle>Dikkat edilmesi gerekenler</AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc pl-4">
                            {warnings.map((issue) => (
                                <li key={issue.message}>{issue.message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            <p className="text-xs text-muted-foreground">
                Bu kontroller pazaryerinin belgelenmiş kurallarına göre yerelde
                yapılır. Gönderim sonucu kalem bazında yine pazaryerinden gelir;
                buradaki liste boş olduğunda reddedilme olasılığı belirgin
                şekilde azalır.
            </p>
        </div>
    );
}
