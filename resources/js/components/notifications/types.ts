/**
 * `NotificationController::present()` cikitisi — zil ve bildirim sayfasi ayni
 * sekli okur, iki ayri tip iki ayri gercek uretirdi.
 */
export type NotificationItem = {
    id: string;
    event: string | null;
    eventLabel: string | null;
    group: string | null;
    title: string;
    body: string;
    /** Bildirimin isaret ettigi ekran; olay bir ekrana bagli degilse null. */
    url: string | null;
    read: boolean;
    createdAt: string | null;
};
