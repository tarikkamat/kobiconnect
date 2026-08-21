export type MatchAttribute = {
    name: string;
    value: string;
};

/** Karsilastirmanin iki yaninin ortak sekli. */
export type MatchSide = {
    name: string | null;
    brand: string | null;
    category: string | null;
    images: string[];
    attributes: MatchAttribute[];
};

export type MatchProposal = {
    /** Pazaryeri tarafindaki dayanak — normalize SKU. */
    reference: string;
    /** Kendi kaydimiz. Bu SKU katalogda yoksa null olur. */
    ours:
        | (MatchSide & { variantId: number; productId: number; sku: string })
        | null;
    proposed: MatchSide & { remoteId: string };
};
