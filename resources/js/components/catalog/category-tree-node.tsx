import {
    ChevronDown,
    ChevronRight,
    Folder,
    FolderOpen,
    FolderPlus,
    Package,
    Pencil,
    Trash2,
} from 'lucide-react';
import type { CategoryRow } from '@/components/catalog/category-dialog';
import { PermissionButton } from '@/components/catalog/permission-button';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionCheck } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

export type CategoryTreeNode = CategoryRow & {
    children: CategoryTreeNode[];
    totalProductCount: number;
};

type Props = {
    node: CategoryTreeNode;
    expandedIds: Set<number>;
    toggleExpand: (id: number) => void;
    onAddChild: (parent: CategoryRow) => void;
    onEdit: (category: CategoryRow) => void;
    onDelete: (category: CategoryRow) => void;
    searchTerm: string;
    canManage: PermissionCheck;
    depth?: number;
};

export function CategoryTreeNodeItem({
    node,
    expandedIds,
    toggleExpand,
    onAddChild,
    onEdit,
    onDelete,
    searchTerm,
    canManage,
    depth = 0,
}: Props) {
    const hasChildren = node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);

    const isMatch =
        searchTerm.trim() !== '' &&
        node.name
            .toLocaleLowerCase('tr')
            .includes(searchTerm.toLocaleLowerCase('tr'));

    return (
        <div className="flex flex-col">
            <div
                className={cn(
                    'group relative flex items-center justify-between rounded-lg border border-transparent px-3 py-2 text-sm transition-colors hover:bg-accent/40',
                    isMatch && 'border-primary/40 bg-primary/5',
                )}
            >
                {/* Sol Kısım: İkonlar, İsim ve Sayılar */}
                <div className="flex items-center gap-2 overflow-hidden">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => toggleExpand(node.id)}
                            className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label={isExpanded ? 'Daralt' : 'Genişlet'}
                        >
                            {isExpanded ? (
                                <ChevronDown className="size-4" />
                            ) : (
                                <ChevronRight className="size-4" />
                            )}
                        </button>
                    ) : (
                        <span className="size-6 shrink-0" />
                    )}

                    <div className="flex items-center gap-2">
                        {hasChildren && isExpanded ? (
                            <FolderOpen className="size-4.5 shrink-0 text-amber-500" />
                        ) : (
                            <Folder className="size-4.5 shrink-0 text-amber-500/80" />
                        )}

                        <span
                            className={cn(
                                'truncate font-medium',
                                isMatch && 'font-semibold text-primary',
                            )}
                        >
                            {node.name}
                        </span>
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5">
                        {hasChildren && (
                            <Badge
                                variant="outline"
                                className="h-5 px-1.5 text-[11px] font-normal text-muted-foreground"
                            >
                                <span className="mr-1 font-mono tabular-nums">
                                    {node.children.length}
                                </span>
                                alt kategori
                            </Badge>
                        )}

                        <Badge
                            variant="secondary"
                            className="h-5 gap-1 px-1.5 text-[11px] font-normal text-muted-foreground"
                        >
                            <Package className="size-3" />
                            <span className="font-mono font-medium text-foreground tabular-nums">
                                {node.productCount}
                            </span>
                            ürün
                        </Badge>
                    </div>
                </div>

                {/* Sağ Kısım: Hızlı Aksiyon Butonları */}
                <TooltipProvider delayDuration={200}>
                    <div className="flex items-center gap-1 opacity-90 transition-opacity group-hover:opacity-100 focus-within:opacity-100 sm:opacity-0">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <PermissionButton
                                    check={canManage}
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground"
                                    aria-label={`${node.name} için alt kategori ekle`}
                                    onClick={() => onAddChild(node)}
                                >
                                    <FolderPlus className="size-3.5" />
                                </PermissionButton>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                Alt Kategori Ekle
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <PermissionButton
                                    check={canManage}
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground"
                                    aria-label={`${node.name} düzenle`}
                                    onClick={() => onEdit(node)}
                                >
                                    <Pencil className="size-3.5" />
                                </PermissionButton>
                            </TooltipTrigger>
                            <TooltipContent side="top">Düzenle</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <PermissionButton
                                    check={canManage}
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    aria-label={`${node.name} sil`}
                                    onClick={() => onDelete(node)}
                                >
                                    <Trash2 className="size-3.5" />
                                </PermissionButton>
                            </TooltipTrigger>
                            <TooltipContent side="top">Sil</TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            {/* Alt Dallar (Children) */}
            {hasChildren && isExpanded && (
                <div className="relative ml-6 space-y-0.5 border-l border-border/80 pl-2">
                    {node.children.map((child) => (
                        <CategoryTreeNodeItem
                            key={child.id}
                            node={child}
                            expandedIds={expandedIds}
                            toggleExpand={toggleExpand}
                            onAddChild={onAddChild}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            searchTerm={searchTerm}
                            canManage={canManage}
                            depth={depth + 1}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
