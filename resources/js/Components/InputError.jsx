export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <p
            {...props}
            aria-live="polite"
            className={'text-sm text-red-600 ' + className}
        >
            {message}
        </p>
    ) : null;
}
