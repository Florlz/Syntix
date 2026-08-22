import ApplicationLogo from '@/Components/ApplicationLogo';
import Link from '@/Components/PrefetchLink';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-background px-4 pt-8 text-foreground sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden border border-border bg-surface px-6 py-6 sm:max-w-md sm:rounded-sm">
                {children}
            </div>
        </div>
    );
}
