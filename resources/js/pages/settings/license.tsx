import { Head } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SettingsLayout from '@/layouts/settings/layout';

export default function License() {
    return (
        <SettingsLayout>
            <Head title="Lisans ve Abonelik" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Lisans ve Abonelik"
                    description="Mevcut paket ve lisans kullanım detaylarınız."
                />

                <Card className="gap-0 py-0 overflow-hidden border-border bg-card shadow-xs">
                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                <KeyRound className="size-4 text-primary" />
                                KobiConnect Kurumsal Lisans
                            </CardTitle>
                            <Badge variant="success" className="text-xs">
                                Aktif
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="p-4 space-y-3">
                        <div className="flex items-start gap-3 rounded-lg border border-success/20 bg-success/5 p-3 text-xs text-success">
                            <ShieldCheck className="size-4 shrink-0 mt-0.5" />
                            <div>
                                <strong className="font-semibold">Sınırsız Pazaryeri Entegrasyonu</strong>
                                <p className="text-[11px] text-muted-foreground mt-0.5">
                                    Tüm pazaryeri bağlantıları, sipariş ve stok senkronizasyon özellikleri aktif durumdadır.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
