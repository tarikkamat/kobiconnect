import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <span className="size-2.5 shrink-0 rounded-full bg-primary group-data-[collapsible=icon]:mx-auto" />
            <span className="text-[15px] font-semibold tracking-tight group-data-[collapsible=icon]:hidden">
                {name}
            </span>
        </>
    );
}
