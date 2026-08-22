export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex min-h-10 items-center justify-center rounded-sm border border-danger bg-danger px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-danger/90 focus:outline-hidden focus:ring-2 focus:ring-danger focus:ring-offset-2 focus:ring-offset-surface disabled:cursor-not-allowed ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
