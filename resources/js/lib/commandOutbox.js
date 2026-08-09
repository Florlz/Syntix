const DATABASE_NAME = 'syntix-scoring';
const STORE_NAME = 'commands';
const DATABASE_VERSION = 1;

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains(STORE_NAME)) {
                const store = database.createObjectStore(STORE_NAME, { keyPath: 'command_uuid' });
                store.createIndex('state', 'state');
                store.createIndex('depends_on_command_uuid', 'depends_on_command_uuid');
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function enqueueCommand(command) {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put({
            ...command,
            state: command.state ?? 'pending',
            created_at: command.created_at ?? new Date().toISOString(),
        });
        transaction.oncomplete = () => resolve(command);
        transaction.onerror = () => reject(transaction.error);
    });
}

export async function listPendingCommands() {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result.filter((command) => command.state === 'pending' || command.state === 'conflicted'));
        request.onerror = () => reject(request.error);
    });
}

export async function updateCommand(commandUuid, patch) {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.get(commandUuid);
        request.onsuccess = () => {
            if (!request.result) {
                reject(new Error('command_not_found'));
                return;
            }
            store.put({ ...request.result, ...patch });
        };
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
    });
}

export async function clearOutbox() {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).clear();
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
    });
}

export async function synchronizePendingCommands(send) {
    const commands = (await listPendingCommands()).sort((left, right) =>
        left.created_at.localeCompare(right.created_at),
    );
    const receipts = new Map();

    for (const command of commands) {
        if (command.depends_on_command_uuid && !receipts.has(command.depends_on_command_uuid)) {
            continue;
        }

        await updateCommand(command.command_uuid, { state: 'syncing' });
        const dependency = command.depends_on_command_uuid
            ? receipts.get(command.depends_on_command_uuid)
            : null;
        const envelope = dependency?.resulting_revision == null
            ? command
            : { ...command, base_revision: dependency.resulting_revision };

        try {
            const receipt = await send(envelope);
            receipts.set(command.command_uuid, receipt);
            await updateCommand(command.command_uuid, {
                state: receipt.disposition === 'applied' ? 'applied' : receipt.disposition,
                resulting_revision: receipt.resulting_revision,
                response: receipt.response,
                error_code: receipt.error_code,
            });
        } catch (error) {
            await updateCommand(command.command_uuid, {
                state: 'unknown',
                error_code: error instanceof Error ? error.message : 'unknown_after_timeout',
            });
        }
    }
}
