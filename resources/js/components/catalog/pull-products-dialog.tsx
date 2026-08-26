import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PermissionCheck } from '@/hooks/use-permission';

type PullableConnection = {
    id: number;
    name: string;
    marketplace: string;
};

export function PullProductsDialog({
    connections,
    check,
}: {
    connections: PullableConnection[];
    check: PermissionCheck;
}) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        connection_id: connections.length > 0 ? String(connections[0].id) : '',
    });

    const submit = (e: React.FormEvent): void => {
        e.preventDefault();

        post(ProductController.pull.url(), {
            preserveScroll: true,
            onError: toastError,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    if (connections.length === 0) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Download className="size-4" />
                    Pazaryerinden Çek
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Pazaryerinden Ürün Çek</DialogTitle>
                        <DialogDescription>
                            Seçilen pazaryeri bağlantısındaki ürünler taranarak
                            kataloğa aktarılır. Mevcut barkod veya SKU'lar
                            otomatik eşleştirilir.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="connection_id">
                            Pazaryeri Bağlantısı
                        </Label>
                        <Select
                            value={data.connection_id}
                            onValueChange={(val) =>
                                setData('connection_id', val)
                            }
                        >
                            <SelectTrigger
                                id="connection_id"
                                aria-label="Pazaryeri Bağlantısı"
                            >
                                <SelectValue placeholder="Bağlantı seçin" />
                            </SelectTrigger>
                            <SelectContent>
                                {connections.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name} ({c.marketplace})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.connection_id} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            İptal
                        </Button>
                        <PermissionButton
                            check={check}
                            type="submit"
                            disabled={processing || !data.connection_id}
                        >
                            {processing ? 'Çekiliyor...' : 'Ürünleri Çek'}
                        </PermissionButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
