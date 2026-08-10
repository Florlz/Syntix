export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M24 3.75 42 14.1v19.8L24 44.25 6 33.9V14.1L24 3.75Z"
                stroke="currentColor"
                strokeWidth="2.4"
                strokeLinejoin="round"
            />
            <path
                d="m6 14.1 18 10.35L42 14.1M24 24.45v19.8"
                stroke="currentColor"
                strokeWidth="2.4"
                strokeLinejoin="round"
            />
            <path
                d="m15.2 14.65 8.8-5.1 8.8 5.1-8.8 5.1-8.8-5.1Zm0 18.7 8.8 5.1 8.8-5.1"
                stroke="currentColor"
                strokeWidth="2.4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
