import { Head, Link } from '@inertiajs/react';
import {
    Award,
    Boxes,
    FolderTree,
    LayoutGrid,
    Scale,
    SlidersHorizontal,
    Tag,
    Zap,
} from 'lucide-react';
import AttributeController from '@/actions/App/Http/Controllers/Catalog/AttributeController';
import BrandController from '@/actions/App/Http/Controllers/Catalog/BrandController';
import CategoryController from '@/actions/App/Http/Controllers/Catalog/CategoryController';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
import ProductGroupController from '@/actions/App/Http/Controllers/Catalog/ProductGroupController';
import TagController from '@/actions/App/Http/Controllers/Catalog/TagController';
import UnitController from '@/actions/App/Http/Controllers/Catalog/UnitController';
import Heading from '@/components/heading';

type DefinitionItem = {
    title: string;
    description: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
};

export default function DefinitionsIndex() {
    const definitionItems: DefinitionItem[] = [
        {
            title: 'Kategoriler',
            description:
                'Ürünlerinizi kategorilere ayırarak ziyaretçilerinizin aradıkları ürünü daha hızlı bulmalarını sağlayın.',
            href: CategoryController.index.url(),
            icon: FolderTree,
        },
        {
            title: 'Markalar',
            description:
                'Marka detay sayfalarında aynı marka ürünleri gösterin ve marka bazlı raporlar oluşturun.',
            href: BrandController.index.url(),
            icon: Award,
        },
        {
            title: 'Özel Alanlar',
            description:
                'Sezon Bilgisi, Cinsiyet gibi özel alanlar tanımlayarak ürün filtreleri oluşturun ve temanızda gösterin.',
            href: AttributeController.index.url(),
            icon: SlidersHorizontal,
        },
        {
            title: 'Varyant Türleri',
            description:
                'Ürünlerinize beden, renk, boyut gibi varyasyon türleri ekleyerek çeşitlilik sağlayın.',
            href: AttributeController.index.url(),
            icon: Boxes,
        },
        {
            title: 'Ürün Grupları',
            description:
                'Ürünlerinizi belirli kriterlere göre gruplayarak detay sayfasında nasıl görüneceklerini ayarlayın.',
            href: ProductGroupController.index.url(),
            icon: LayoutGrid,
        },
        {
            title: 'Dinamik Kategoriler',
            description:
                'Belirleyeceğiniz koşullara uyan ürünler yeni kategoriye otomatik eklensin.',
            href: DynamicCategoryController.index.url(),
            icon: Zap,
        },
        {
            title: 'Etiketler',
            description:
                'Ürünlerinize etiketler ekleyerek dışa aktarma işlemleri için filtrelemeyi kolaylaştırın.',
            href: TagController.index.url(),
            icon: Tag,
        },
        {
            title: 'Ürün Birimleri',
            description:
                'Servis, adet gibi özel birimler tanımlayarak ürün birim fiyatlarını detay ve satın alma adımlarında gösterin.',
            href: UnitController.index.url(),
            icon: Scale,
        },
    ];

    return (
        <>
            <Head title="Tanımlamalar" />

            <div className="space-y-6">
                <div>
                    <Heading
                        title="Tanımlamalar"
                        description="Ürünleriniz için kategori, marka, varyant, etiket ve birim gibi temel katalog parametrelerini yönetin."
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {definitionItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                className="group flex items-start gap-4 rounded-xl border border-border/70 bg-card p-4 transition-all duration-200 hover:border-primary/40 hover:bg-muted/40 hover:shadow-xs"
                            >
                                <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/30 text-muted-foreground transition-colors group-hover:border-primary/30 group-hover:bg-primary/10 group-hover:text-primary">
                                    <Icon className="size-5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-semibold text-foreground transition-colors group-hover:text-primary">
                                        {item.title}
                                    </h3>
                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
