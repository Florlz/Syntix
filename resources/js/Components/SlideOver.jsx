import React from 'react';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { Fragment } from 'react';
import AppIcon from '@/Components/AppIcon';

export default function SlideOver({ show, title, onClose, children, initialFocus }) {
    return <Transition show={show} as={Fragment}>
        <Dialog as="div" className="relative z-50" onClose={onClose} initialFocus={initialFocus}>
            <TransitionChild as={Fragment} enter="ease-out duration-200" enterFrom="opacity-0" enterTo="opacity-100" leave="ease-in duration-150" leaveFrom="opacity-100" leaveTo="opacity-0">
                <div className="fixed inset-0 bg-foreground/45" aria-hidden="true" />
            </TransitionChild>
            <div className="fixed inset-0 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden">
                    <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
                        <TransitionChild as={Fragment} enter="transform transition ease-out duration-200" enterFrom="translate-x-full" enterTo="translate-x-0" leave="transform transition ease-in duration-150" leaveFrom="translate-x-0" leaveTo="translate-x-full">
                            <DialogPanel className="pointer-events-auto w-screen max-w-2xl overflow-y-auto border-l border-border bg-background text-foreground shadow-[0_0_24px_rgb(0_26_63/0.12)]">
                                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-surface px-5 py-4 sm:px-7">
                                    <DialogTitle className="text-xl font-bold text-foreground">{title}</DialogTitle>
                                    <button type="button" onClick={onClose} className="grid size-10 place-items-center rounded-sm text-muted hover:bg-surface-muted hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring" aria-label="Close panel"><AppIcon name="close" /></button>
                                </div>
                                <div className="p-5 sm:p-7">{children}</div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </div>
        </Dialog>
    </Transition>;
}
