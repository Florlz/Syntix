import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'min-h-10 rounded-sm border-border bg-surface text-foreground placeholder:text-muted focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-muted ' +
                className
            }
            ref={localRef}
        />
    );
});
