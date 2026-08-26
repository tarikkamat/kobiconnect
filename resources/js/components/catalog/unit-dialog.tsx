import { router } from '@inertiajs/react';
import { Pencil, Scale } from 'lucide-react';
import { useState } from 'react';
import UnitController from '@/actions/App/Http/Controllers/Catalog/UnitController';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type UnitRow = {
    id: number;
    name: string;
    shortName: string;
    productCount: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unitToEdit?: UnitRow | null;
};

export function UnitDialog({ open, onOpenChange, unitToEdit }: Props) {
    const isEditing = Boolean(unitToEdit);
    const formKey = unitToEdit ? `edit-${unitToEdit.id}` : 'create-unit';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Birimi Düzenle
                            </>
                        ) : (
                            <>
                                <Scale className="size-5 text-primary" />
                                Yeni Birim Ekle
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Ürün birim adı ve kısa kodunu güncelleyin.'
                            : 'Adet, servis, kg gibi ürün birimleri tanımlayarak ürün detay ve satın alma adımlarında gösterin.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <UnitFormContent
                        key={formKey}
                        unitToEdit={unitToEdit}
                        isEditing={isEditing}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function UnitFormContent({
    unitToEdit,
    isEditing,
    onClose,
}: {
    unitToEdit?: UnitRow | null;
    isEditing: boolean;
    onClose: () => void;
}) {
    const [name, setName] = useState(unitToEdit?.name ?? '');
    const [shortName, setShortName] = useState(unitToEdit?.shortName ?? '');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{
        name?: string;
        short_name?: string;
    }>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        if (isEditing && unitToEdit) {
            router.patch(
                UnitController.update.url({ unit: unitToEdit.id }),
                { name, short_name: shortName },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setProcessing(false);
                        onClose();
                    },
                    onError: (errs) => {
                        setProcessing(false);
                        setErrors(errs);
                        toastError(errs);
                    },
                },
            );
        } else {
            router.post(
                UnitController.store.url(),
                { name, short_name: shortName },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setProcessing(false);
                        onClose();
                    },
                    onError: (errs) => {
                        setProcessing(false);
                        setErrors(errs);
                        toastError(errs);
                    },
                },
            );
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="grid gap-4 py-4">
                <div className="grid gap-2">
                    <Label htmlFor="unit-name">Birim Adı</Label>
                    <Input
                        id="unit-name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Örn: Adet, Servis, Kilogram, Paket..."
                        autoFocus
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="unit-short-name">
                        Kısa Ad (Sembol / Kod)
                    </Label>
                    <Input
                        id="unit-short-name"
                        value={shortName}
                        onChange={(e) => setShortName(e.target.value)}
                        placeholder="Örn: adet, srv, kg, pkt..."
                        required
                    />
                    <InputError message={errors.short_name} />
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    Vazgeç
                </Button>
                <Button
                    type="submit"
                    disabled={
                        processing ||
                        name.trim() === '' ||
                        shortName.trim() === ''
                    }
                >
                    {processing
                        ? 'Kaydediliyor...'
                        : isEditing
                          ? 'Güncelle'
                          : 'Oluştur'}
                </Button>
            </DialogFooter>
        </form>
    );
}
