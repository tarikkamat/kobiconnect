import { Head } from '@inertiajs/react';
import { Check, Copy, ShieldCheck, Sparkles } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useClipboard } from '@/hooks/use-clipboard';
import SettingsLayout from '@/layouts/settings/layout';

interface McpProps {
    endpoint: string;
    actionCount: number;
}

/*
 * Kullanici tenant kimligini ASLA elle yazmamali: adres burada hazir durur,
 * kopyalanir ve istemciye yapistirilir.
 *
 * ponytail: istemci basina ayri sekme/rehber yok. Her MCP istemcisi ayni tek
 * adresi ister; anlatilacak fark yok.
 */
export default function Mcp({ endpoint, actionCount }: McpProps) {
    const [copied, copy] = useClipboard();

    return (
        <SettingsLayout>
            <Head title="Yapay Zekâ Bağlantısı (MCP)" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Yapay Zekâ Bağlantısı (MCP)"
                    description="Claude, ChatGPT gibi MCP destekleyen asistanları panelinize bağlayın."
                />

                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <Sparkles className="size-4 text-primary" />
                                Bağlantı adresi
                            </CardTitle>
                            <Badge variant="secondary" className="text-xs">
                                {actionCount} işlem
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3 p-4">
                        <div className="flex items-center gap-2">
                            <code className="flex-1 truncate rounded-md border border-border bg-muted/40 px-3 py-2 font-mono text-xs">
                                {endpoint}
                            </code>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => copy(endpoint)}
                                aria-label="Bağlantı adresini kopyala"
                            >
                                {copied === endpoint ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                                {copied === endpoint ? 'Kopyalandı' : 'Kopyala'}
                            </Button>
                        </div>

                        <ol className="list-decimal space-y-1 pl-5 text-xs text-muted-foreground">
                            <li>
                                Asistanınızın ayarlarında “MCP sunucusu ekle”yi
                                seçin ve bu adresi yapıştırın.
                            </li>
                            <li>
                                Açılan tarayıcı ekranında KobiConnect
                                hesabınızla giriş yapın.
                            </li>
                            <li>
                                “İzin ver” dedikten sonra asistan panelinizi
                                kullanmaya hazırdır.
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                            <ShieldCheck className="size-4 text-primary" />
                            Güvenlik
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <ul className="space-y-2 text-xs text-muted-foreground">
                            <li>
                                Şifreniz asistana verilmez; bağlantı OAuth ile
                                kurulur ve izni istediğiniz an geri
                                alabilirsiniz.
                            </li>
                            <li>
                                Asistan yalnızca{' '}
                                <strong>sizin yetkilerinizle</strong> çalışır;
                                göremediğiniz bir ekranı o da göremez.
                            </li>
                            <li>
                                Parola, passkey ve hesap silme işlemleri MCP’ye
                                kapalıdır. Müşteri verisi KVKK gereği maskeli
                                döner.
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
