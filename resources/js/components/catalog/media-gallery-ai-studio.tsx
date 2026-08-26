import {
    Image as ImageIcon,
    Loader2,
    Plus,
    RefreshCw,
    Sparkles,
    Star,
    Trash2,
    UploadCloud,
    Wand2,
} from 'lucide-react';
import React, { useRef, useState } from 'react';
import { toast } from 'sonner';
import AiOptimizationController from '@/actions/App/Http/Controllers/Catalog/AiOptimizationController';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';

export type ProductImageItem = {
    id: string;
    url: string;
    name?: string;
    isAi?: boolean;
};

type AiStudioPreset = {
    id: string;
    label: string;
    icon: string;
    description: string;
    prompt: string;
};

export const AI_STUDIO_PRESETS: AiStudioPreset[] = [
    {
        id: 'clean_white',
        label: 'Saf Beyaz Stüdyo',
        icon: '✨',
        description: 'Pazaryeri uyumlu, pürüzsüz saf beyaz stüdyo fonu.',
        prompt: 'Clean pure solid white background, high-end professional commercial product studio lighting, soft subtle drop shadow, sharp crisp focus, 4k ultra-detailed commercial e-commerce photograph.',
    },
    {
        id: 'studio_shadow',
        label: 'Doğal Gölge & Işık',
        icon: '💡',
        description: 'Doğal yumuşak zemin gölgeleri ile stüdyo ışığı.',
        prompt: 'Professional e-commerce product photograph, neutral studio background with subtle soft ground contact shadow, diffused softbox lighting, ultra sharp texture, high commercial finish.',
    },
    {
        id: 'podium',
        label: 'Minimalist Podyum',
        icon: '🏛️',
        description: 'Modern mermer/taş podyum üzerinde estetik sunum.',
        prompt: 'Product placed elegantly on a modern minimalist luxury round stone podium platform, soft clean gradient studio background, warm studio highlights, clean architectural aesthetic.',
    },
    {
        id: 'lifestyle',
        label: 'Yaşam Alanı / Sahne',
        icon: '🌿',
        description: 'Doğal gün ışığı alan şık ve modern mekan ortamı.',
        prompt: 'Aesthetic e-commerce lifestyle product shot in a stylish modern minimalist room setting, soft sunlit indoor ambient light, beautiful subtle bokeh background, commercial magazine quality.',
    },
];

function getXsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

type Props = {
    images: ProductImageItem[];
    setImages: React.Dispatch<React.SetStateAction<ProductImageItem[]>>;
    productName: string;
};

export function MediaGalleryAiStudio({
    images,
    setImages,
    productName,
}: Props) {
    const [isUploading, setIsUploading] = useState(false);
    const [customUrlInput, setCustomUrlInput] = useState('');
    const [showUrlInput, setShowUrlInput] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // AI Refactor Modal State
    const [isRefactorOpen, setIsRefactorOpen] = useState(false);
    const [targetImage, setTargetImage] = useState<ProductImageItem | null>(
        null,
    );
    const [selectedPreset, setSelectedPreset] = useState<string>('clean_white');
    const [aiCustomInstruction, setAiCustomInstruction] = useState('');
    const [isGeneratingAi, setIsGeneratingAi] = useState(false);
    const [aiGeneratedPreview, setAiGeneratedPreview] = useState<{
        url: string;
        path?: string;
    } | null>(null);

    // Handle Upload
    const handleFileUpload = async (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        setIsUploading(true);
        const uploadPromises = Array.from(files).map(
            async (file): Promise<ProductImageItem | null> => {
                const formData = new FormData();
                formData.append('image', file);

                try {
                    const response = await fetch(
                        ProductController.uploadImage.url(),
                        {
                            method: 'POST',
                            headers: {
                                'X-XSRF-TOKEN': getXsrfToken(),
                                Accept: 'application/json',
                            },
                            body: formData,
                        },
                    );

                    if (!response.ok) {
                        const err = await response.json().catch(() => ({}));

                        throw new Error(err.message || 'Görsel yüklenemedi.');
                    }

                    const data: { url: string } = await response.json();

                    return {
                        id: String(Date.now() + Math.random()),
                        url: data.url,
                        name: file.name,
                    };
                } catch (err: unknown) {
                    const errorMsg =
                        err instanceof Error ? err.message : 'Yükleme hatası';
                    toast.error(`${file.name} yüklenemedi: ${errorMsg}`);

                    return null;
                }
            },
        );

        const results = await Promise.all(uploadPromises);
        const successful = results.filter(
            (item): item is ProductImageItem => item !== null,
        );

        if (successful.length > 0) {
            setImages((prev) => [...prev, ...successful]);
            toast.success(`${successful.length} görsel başarıyla yüklendi.`);
        }

        setIsUploading(false);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleAddCustomUrl = () => {
        const trimmed = customUrlInput.trim();

        if (!trimmed) {
            return;
        }

        setImages((prev) => [
            ...prev,
            { id: String(Date.now() + Math.random()), url: trimmed },
        ]);
        setCustomUrlInput('');
        setShowUrlInput(false);
        toast.success('Görsel URL eklendi.');
    };

    const handleRemoveImage = (id: string) => {
        setImages((prev) => prev.filter((img) => img.id !== id));
    };

    const handleSetCoverImage = (indexToCover: number) => {
        if (indexToCover === 0) {
            return;
        }

        setImages((prev) => {
            const copy = [...prev];
            const [selected] = copy.splice(indexToCover, 1);

            return [selected, ...copy];
        });
        toast.success('Kapak görseli güncellendi.');
    };

    // Open Refactor Modal for a specific image
    const handleOpenRefactor = (image: ProductImageItem | null) => {
        setTargetImage(image);
        setAiGeneratedPreview(null);
        setAiCustomInstruction('');
        setIsRefactorOpen(true);
    };

    // Trigger AI Refactoring
    const handleExecuteAiRefactor = async () => {
        const nameToUse = productName.trim() || 'Ürün';
        const preset = AI_STUDIO_PRESETS.find((p) => p.id === selectedPreset);
        const instructionParts: string[] = [];

        if (preset) {
            instructionParts.push(preset.prompt);
        }

        if (aiCustomInstruction.trim()) {
            instructionParts.push(aiCustomInstruction.trim());
        }

        setIsGeneratingAi(true);

        try {
            const response = await fetch(
                AiOptimizationController.generateImage.url(),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        name: nameToUse,
                        image_url: targetImage ? targetImage.url : undefined,
                        instruction: instructionParts.join(' '),
                    }),
                },
            );

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));

                throw new Error(
                    err.message || 'AI stüdyo fotoğrafı üretilemedi.',
                );
            }

            const resData = await response.json();

            if (resData.success && resData.image?.url) {
                setAiGeneratedPreview({
                    url: resData.image.url,
                    path: resData.image.path,
                });
                toast.success('AI stüdyo fotoğrafı başarıyla oluşturuldu!');
            } else {
                throw new Error('Geçersiz sunucu yanıtı.');
            }
        } catch (err: unknown) {
            const errorMsg =
                err instanceof Error ? err.message : 'AI üretim hatası';
            toast.error(`Hata: ${errorMsg}`);
        } finally {
            setIsGeneratingAi(false);
        }
    };

    // Apply Result to Gallery
    const handleApplyResult = (mode: 'replace' | 'add' | 'cover') => {
        if (!aiGeneratedPreview) {
            return;
        }

        const newImage: ProductImageItem = {
            id: String(Date.now()),
            url: aiGeneratedPreview.url,
            name: `${productName || 'Ürün'} (AI Stüdyo)`,
            isAi: true,
        };

        if (mode === 'replace' && targetImage) {
            setImages((prev) =>
                prev.map((item) =>
                    item.id === targetImage.id ? newImage : item,
                ),
            );
            toast.success(
                'Orijinal görsel AI stüdyo fotoğrafı ile değiştirildi.',
            );
        } else if (mode === 'cover') {
            setImages((prev) => [newImage, ...prev]);
            toast.success('AI stüdyo fotoğrafı kapak olarak eklendi.');
        } else {
            setImages((prev) => [...prev, newImage]);
            toast.success('AI stüdyo fotoğrafı galeriye eklendi.');
        }

        setIsRefactorOpen(false);
        setTargetImage(null);
        setAiGeneratedPreview(null);
    };

    return (
        <div className="space-y-4">
            {/* Quick Standalone AI Prompt Bar if no images */}
            {images.length === 0 && (
                <div className="flex flex-col gap-3 rounded-xl border border-primary/20 bg-linear-to-r from-primary/5 via-primary/2 to-transparent p-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Sparkles className="size-4" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-foreground">
                                Fotoğrafınız yok mu? AI ile stüdyo çekimi üretin
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                Ürün başlığına göre profesyonel stüdyo ortamı
                                kurgular.
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="default"
                        onClick={() => handleOpenRefactor(null)}
                        className="h-8 shrink-0 gap-1.5 self-start text-xs sm:self-auto"
                    >
                        <Wand2 className="size-3.5" />
                        AI Stüdyo Çekimi Üret
                    </Button>
                </div>
            )}

            {/* Dropzone & Upload Area */}
            <div
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    handleFileUpload(e.dataTransfer.files);
                }}
                className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-secondary/20 p-6 text-center transition-colors hover:bg-secondary/40"
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => handleFileUpload(e.target.files)}
                />
                <div className="flex size-10 items-center justify-center rounded-full bg-secondary text-foreground">
                    {isUploading ? (
                        <Loader2 className="size-5 animate-spin text-primary" />
                    ) : (
                        <UploadCloud className="size-5 text-muted-foreground" />
                    )}
                </div>
                <div>
                    <p className="text-sm font-medium text-foreground">
                        Görselleri buraya sürükleyin veya{' '}
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="font-semibold text-primary underline underline-offset-4 hover:text-primary/80"
                        >
                            dosya seçin
                        </button>
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        PNG, JPG, WEBP • Maks. 10 MB/dosya
                    </p>
                </div>

                <div className="pt-2">
                    {!showUrlInput ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setShowUrlInput(true)}
                            className="h-7 text-xs text-muted-foreground"
                        >
                            + URL ile Görsel Ekle
                        </Button>
                    ) : (
                        <div className="mt-1 flex flex-wrap items-center justify-center gap-2">
                            <Input
                                type="url"
                                value={customUrlInput}
                                onChange={(e) =>
                                    setCustomUrlInput(e.target.value)
                                }
                                placeholder="https://..."
                                className="h-8 w-60 text-xs"
                            />
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleAddCustomUrl}
                                className="h-8 text-xs"
                            >
                                Ekle
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowUrlInput(false)}
                                className="h-8 text-xs"
                            >
                                İptal
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {/* Image Gallery Grid */}
            {images.length > 0 && (
                <div className="space-y-2.5">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            Yüklenen Görseller (İlk görsel ana kapaktır •
                            Üzerindeki ✨ butonuyla AI dönüştürün)
                        </span>
                        <span className="font-mono font-medium tabular-nums">
                            {images.length} adet
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        {images.map((img, idx) => (
                            <div
                                key={img.id}
                                className={cn(
                                    'group relative aspect-square overflow-hidden rounded-lg border bg-card shadow-xs transition-all',
                                    idx === 0
                                        ? 'border-primary ring-2 ring-primary/30'
                                        : 'border-border hover:border-muted-foreground/50',
                                )}
                            >
                                <img
                                    src={img.url}
                                    alt={`Ürün Görseli ${idx + 1}`}
                                    className="size-full object-cover"
                                    loading="lazy"
                                />

                                {/* Cover Badge */}
                                {idx === 0 && (
                                    <div className="absolute top-2 left-2 z-10">
                                        <Badge
                                            variant="default"
                                            className="h-5 bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground shadow-xs"
                                        >
                                            <Star className="mr-0.5 size-3 fill-current" />
                                            Kapak
                                        </Badge>
                                    </div>
                                )}

                                {/* AI badge */}
                                {img.isAi && idx !== 0 && (
                                    <div className="absolute top-2 left-2 z-10">
                                        <Badge
                                            variant="secondary"
                                            className="h-4 bg-secondary/80 px-1 text-[9px] backdrop-blur-xs"
                                        >
                                            AI
                                        </Badge>
                                    </div>
                                )}

                                {/* Sihirli Değnek (Magic Wand) Persistent/Hover Icon Button */}
                                <div className="absolute top-2 right-2 z-10">
                                    <button
                                        type="button"
                                        onClick={() => handleOpenRefactor(img)}
                                        className="flex size-7 items-center justify-center rounded-md border border-border bg-background/85 text-foreground shadow-xs transition-all hover:bg-primary hover:text-primary-foreground"
                                        title="Bu fotoğrafı AI Stüdyo Çekimine Dönüştür"
                                        aria-label="AI ile Fotoğrafı Dönüştür"
                                    >
                                        <Wand2 className="size-3.5 text-primary group-hover:text-inherit" />
                                    </button>
                                </div>

                                {/* Overlay Action Bar on Hover */}
                                <div className="absolute inset-0 flex flex-col justify-end bg-black/50 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                    <div className="flex items-center gap-1.5">
                                        {idx !== 0 && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    handleSetCoverImage(idx)
                                                }
                                                className="h-6 flex-1 gap-1 bg-card text-[10px] text-card-foreground shadow-xs"
                                            >
                                                <Star className="size-3 text-warning" />
                                                Kapak Yap
                                            </Button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleRemoveImage(img.id)
                                            }
                                            className="ml-auto flex size-6 shrink-0 items-center justify-center rounded-md bg-destructive/90 text-destructive-foreground transition-colors hover:bg-destructive"
                                            title="Görseli Sil"
                                            aria-label="Görseli Sil"
                                        >
                                            <Trash2 className="size-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* AI STUDIO REFACTOR MODAL / DIALOG */}
            <Dialog open={isRefactorOpen} onOpenChange={setIsRefactorOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <Sparkles className="size-4" />
                            </div>
                            <DialogTitle className="text-base">
                                {targetImage
                                    ? 'Fotoğrafı AI Stüdyo Çekimine Dönüştür'
                                    : 'AI Stüdyo Ürün Fotoğrafı Oluştur'}
                            </DialogTitle>
                        </div>
                        <DialogDescription className="text-xs">
                            {targetImage
                                ? 'Yüklenen fotoğrafınız profesyonel e-ticaret stüdyo ışığı ve seçtiğiniz konseptle yeniden tasarlanır.'
                                : 'Ürün adı ve seçilen konsepti baz alarak sıfırdan e-ticaret stüdyo fotoğrafı üretir.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        {/* Before & After comparison if generating/previewing */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {/* Original Image box */}
                            {targetImage && (
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Orijinal Fotoğraf
                                    </Label>
                                    <div className="relative flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary/20">
                                        <img
                                            src={targetImage.url}
                                            alt="Original"
                                            className="size-full object-cover"
                                        />
                                        <Badge
                                            variant="secondary"
                                            className="absolute top-2 left-2 text-[10px]"
                                        >
                                            Kaynak
                                        </Badge>
                                    </div>
                                </div>
                            )}

                            {/* Result Preview Box */}
                            <div
                                className={cn(
                                    'space-y-1.5',
                                    !targetImage && 'sm:col-span-2',
                                )}
                            >
                                <Label className="text-xs font-medium text-muted-foreground">
                                    AI Stüdyo Çekimi{' '}
                                    {aiGeneratedPreview && '✨'}
                                </Label>
                                <div
                                    className={cn(
                                        'relative flex flex-col items-center justify-center overflow-hidden rounded-lg border p-4 text-center transition-all',
                                        aiGeneratedPreview
                                            ? 'aspect-square border-primary bg-card p-0 ring-2 ring-primary/20'
                                            : 'aspect-square border-dashed border-border bg-secondary/15',
                                    )}
                                >
                                    {isGeneratingAi ? (
                                        <div className="flex flex-col items-center justify-center gap-2 p-4 text-primary">
                                            <Loader2 className="size-8 animate-spin" />
                                            <p className="text-xs font-medium">
                                                Stüdyo fotoğrafı işleniyor...
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
                                                Işık ve zemin optimize ediliyor
                                            </p>
                                        </div>
                                    ) : aiGeneratedPreview ? (
                                        <>
                                            <img
                                                src={aiGeneratedPreview.url}
                                                alt="AI Result"
                                                className="size-full object-cover"
                                            />
                                            <Badge
                                                variant="success"
                                                className="absolute top-2 left-2 text-[10px]"
                                            >
                                                Stüdyo Çekimi Hazır
                                            </Badge>
                                        </>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center gap-1.5 text-muted-foreground">
                                            <ImageIcon className="size-8 opacity-40" />
                                            <p className="text-xs">
                                                Konsept seçip "Dönüştür"e
                                                tıklayın
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Presets Selection (Non-overflowing, clean responsive grid) */}
                        <div className="space-y-2">
                            <Label className="text-xs font-medium">
                                Stüdyo Konsepti Seçin
                            </Label>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {AI_STUDIO_PRESETS.map((preset) => (
                                    <button
                                        key={preset.id}
                                        type="button"
                                        onClick={() =>
                                            setSelectedPreset(preset.id)
                                        }
                                        className={cn(
                                            'flex min-w-0 items-start gap-2.5 rounded-lg border p-2.5 text-left text-xs transition-all',
                                            selectedPreset === preset.id
                                                ? 'border-primary bg-primary/10 font-medium text-foreground ring-1 ring-primary/40'
                                                : 'border-border bg-card text-muted-foreground hover:bg-secondary/60',
                                        )}
                                    >
                                        <span className="shrink-0 text-base">
                                            {preset.icon}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold text-foreground">
                                                {preset.label}
                                            </p>
                                            <p className="mt-0.5 line-clamp-2 text-[11px] leading-tight text-muted-foreground">
                                                {preset.description}
                                            </p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Custom Instruction */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-medium text-muted-foreground">
                                Ek Özel Talimat (İsteğe Bağlı)
                            </Label>
                            <Input
                                value={aiCustomInstruction}
                                onChange={(e) =>
                                    setAiCustomInstruction(e.target.value)
                                }
                                placeholder="Örn: Arkaya hafif buğu ekle, ürünü hafif açılı konumlandır..."
                                className="h-8 text-xs"
                            />
                        </div>
                    </div>

                    <DialogFooter className="flex-col gap-2 border-t border-border pt-2 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsRefactorOpen(false)}
                            className="text-xs"
                        >
                            Kapat
                        </Button>

                        <div className="flex flex-wrap items-center gap-2">
                            {!aiGeneratedPreview ? (
                                <Button
                                    type="button"
                                    onClick={handleExecuteAiRefactor}
                                    disabled={isGeneratingAi}
                                    size="sm"
                                    className="gap-2 text-xs"
                                >
                                    {isGeneratingAi ? (
                                        <>
                                            <Loader2 className="size-3.5 animate-spin" />
                                            Dönüştürülüyor...
                                        </>
                                    ) : (
                                        <>
                                            <Wand2 className="size-3.5" />
                                            {targetImage
                                                ? 'Fotoğrafı Dönüştür'
                                                : 'AI Fotoğrafı Üret'}
                                        </>
                                    )}
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleExecuteAiRefactor}
                                        disabled={isGeneratingAi}
                                        className="gap-1 text-xs"
                                    >
                                        <RefreshCw className="size-3" />
                                        Tekrar Üret
                                    </Button>

                                    {targetImage && (
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="sm"
                                            onClick={() =>
                                                handleApplyResult('replace')
                                            }
                                            className="text-xs"
                                        >
                                            Orijinaliyle Değiştir
                                        </Button>
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleApplyResult('add')}
                                        className="gap-1 text-xs"
                                    >
                                        <Plus className="size-3" />
                                        Galeriye Ekle
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            handleApplyResult('cover')
                                        }
                                        className="gap-1 text-xs"
                                    >
                                        <Star className="size-3 fill-current text-warning" />
                                        Kapak Yap
                                    </Button>
                                </>
                            )}
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
