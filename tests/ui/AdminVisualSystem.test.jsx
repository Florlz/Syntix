import React from 'react';
import { render, screen } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

test('admin masthead and section expose bulletin landmarks without business logic', async () => {
    const { AdminMasthead, AdminSection } = await import('../../resources/js/Components/Admin/AdminSurface');

    render(<>
        <AdminMasthead eyebrow="SIKLAB 2026" title="Departments" actions={<button>New department</button>} />
        <AdminSection title="Directory">Rows</AdminSection>
    </>);

    expect(screen.getByRole('heading', { name: 'Departments', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('SIKLAB 2026')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'New department' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Directory' })).toHaveClass('border-border');
    expect(screen.getByRole('heading', { name: 'Directory', level: 2 })).toBeInTheDocument();
});

test('admin sections forward custom classes and optional descriptions', async () => {
    const { AdminSection } = await import('../../resources/js/Components/Admin/AdminSurface');

    render(<AdminSection as="article" title="Readiness" description="Three assignments need attention." actions={<button>Resolve</button>} className="test-hook">Status rows</AdminSection>);

    const article = screen.getByRole('article', { name: 'Readiness' });
    expect(article).toHaveClass('test-hook');
    expect(screen.getByText('Three assignments need attention.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Resolve' })).toBeInTheDocument();
});

test('admin empty states keep their title and recovery action accessible', async () => {
    const { AdminEmptyState } = await import('../../resources/js/Components/Admin/AdminSurface');

    render(<AdminEmptyState title="No departments yet" description="Add the first department to begin registration." action={<button>Add department</button>} className="empty-hook" />);

    const region = screen.getByRole('region', { name: 'No departments yet' });
    expect(region).toHaveClass('border-dashed', 'empty-hook');
    expect(screen.getByText('Add the first department to begin registration.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add department' })).toBeInTheDocument();
});

test('shared controls keep event-day touch targets and visible field boundaries', () => {
    for (const file of ['PrimaryButton.jsx', 'SecondaryButton.jsx', 'DangerButton.jsx']) {
        const source = readFileSync(resolve(projectRoot, 'resources/js/Components', file), 'utf8');
        expect(source).toContain('min-h-11');
        expect(source).not.toContain('min-h-10');
    }

    const inputSource = readFileSync(resolve(projectRoot, 'resources/js/Components/TextInput.jsx'), 'utf8');
    expect(inputSource).toContain('min-h-11');
    expect(inputSource).toContain('border-control-border');
});
