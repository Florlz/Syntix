import { Link } from '@inertiajs/react';

const DEFAULT_CACHE_FOR = 30_000;

function methodFromHref(href, method) {
    if (href && typeof href === 'object' && 'method' in href) {
        return href.method;
    }

    return method;
}

/**
 * Shared Inertia link defaults for application navigation.
 *
 * GET links prefetch on hover and keep the response warm briefly. Mutation
 * links are automatically excluded because Inertia only permits GET prefetches.
 */
export default function PrefetchLink({
    href,
    method = 'get',
    prefetch = 'hover',
    cacheFor = DEFAULT_CACHE_FOR,
    ...props
}) {
    const isGet = String(methodFromHref(href, method)).toLowerCase() === 'get';
    const resolvedPrefetch = isGet ? prefetch : false;

    return (
        <Link
            {...props}
            href={href}
            method={method}
            prefetch={resolvedPrefetch}
            cacheFor={resolvedPrefetch === false ? 0 : cacheFor}
        />
    );
}
