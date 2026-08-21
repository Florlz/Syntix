import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

export default function PrintPortal({ children }) {
    const [mountNode, setMountNode] = useState(null);

    useEffect(() => {
        let node = document.getElementById('syntix-print-root');
        const createdHere = !node;

        if (!node) {
            node = document.createElement('div');
            node.id = 'syntix-print-root';
            document.body.appendChild(node);
        }

        node.setAttribute('aria-hidden', 'true');

        setMountNode(node);

        return () => {
            if (createdHere) node.remove();
        };
    }, []);

    return mountNode ? createPortal(children, mountNode) : null;
}
