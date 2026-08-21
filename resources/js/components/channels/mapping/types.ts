/**
 * Esleme sihirbazinin sunucudan aldigi prop'lar — FRONTEND-PLAN §4.1.
 *
 * Bayraklar (`isRequired`, `allowCustom`, `allowMultiple`, `isVarianter`,
 * `isSlicer`) pazaryerinin gerceğidir ve sunucuda referans katalogundan
 * kopyalanir; arayuz onlari yalnizca GOSTERIR, geri gondermez.
 */

export type MappingStatus = 'unmapped' | 'partial' | 'mapped';

export type MappingCategoryRow = {
    id: number;
    name: string;
    depth: number;
    productCount: number;
    remotePath: string | null;
    attributeCount: number;
    status: MappingStatus;
};

export type RemoteCategoryOption = {
    remoteId: string;
    path: string;
    isLeaf: boolean;
};

export type CategorySuggestion = {
    remoteId: string;
    path: string;
    /** Isim benzerligi yuzdesi; 100 = normalize edilmis isim birebir ayni. */
    score: number;
};

export type LocalAttribute = {
    id: number;
    name: string;
    values: { id: number; value: string }[];
};

export type RemoteAttribute = {
    remoteId: string;
    name: string;
    isRequired: boolean;
    allowCustom: boolean;
    allowMultiple: boolean;
    isVarianter: boolean;
    isSlicer: boolean;
    /** Kayitli esleme; yoksa null. */
    attributeId: number | null;
    mappingId: number | null;
    /** Isim benzerliginden gelen oneri; yalnizca eslenmemis satirlarda dolu. */
    suggestedAttributeId: number | null;
    values: { remoteId: string; value: string }[];
    /** yerel deger id → pazaryeri deger id (kayitli). */
    valueMappings: Record<string, string>;
    /** yerel deger id → pazaryeri deger id (onerilen). */
    suggestedValues: Record<string, string>;
};

export type BrandRow = {
    id: number;
    name: string;
    remoteBrandId: string | null;
};

export type MappingIssue = {
    level: 'error' | 'warning';
    message: string;
};

export type MappingLock = {
    locked: boolean;
    reason: string | null;
};

export type WizardProps = {
    connection: { id: number; name: string };
    category: { id: number; name: string; path: string; productCount: number };
    mapping: {
        remoteCategoryId: string;
        remotePath: string;
        isLeaf: boolean;
    } | null;
    lock: MappingLock;
    suggestions: CategorySuggestion[];
    search: string;
    /** Yalnizca kismi yeniden yukleme ile gelir. */
    searchResults?: RemoteCategoryOption[];
    attributes: RemoteAttribute[];
    localAttributes: LocalAttribute[];
    brands: BrandRow[];
    brandSearch: string;
    brandResult?: {
        query: string;
        brand: { remoteId: string; name: string } | null;
    };
    issues: MappingIssue[];
    catalogError: string | null;
};

export const NONE = 'none';
