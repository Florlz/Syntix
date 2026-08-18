const DATABASE_NAME = 'syntix-scoring';
const STORE_NAME = 'commands';
const DATABASE_VERSION = 2;
const RETRYABLE_STATES = new Set(['pending', 'syncing', 'unknown']);

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains(STORE_NAME)) {
                const store = database.createObjectStore(STORE_NAME, { keyPath: 'command_uuid' });
                store.createIndex('state', 'state');
                store.createIndex('depends_on_command_uuid', 'depends_on_command_uuid');
                store.createIndex('contest_id', 'contest_id');
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function readCommands() {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function getCommand(commandUuid) {
    const database = await openDatabase();

    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).get(commandUuid);
        request.onsuccess = () => resolve(request.result ?? null);
        request.onerror = () => reject(request.error);
    });
}

export async function enqueueCommand(command) {
    const database = await openDatabase();
    const stored = { ...command, state: command.state ?? 'pending', created_at: command.created_at ?? new Date().toISOString() };

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put(stored);
        transaction.oncomplete = () => resolve(stored);
        transaction.onerror = () => reject(transaction.error);
    });
}

export function selectContestDependency(command, commands) {
    return commands
        .filter((candidate) => String(candidate.contest_id) === String(command.contest_id) && RETRYABLE_STATES.has(candidate.state))
        .sort((left, right) => left.created_at.localeCompare(right.created_at))
        .at(-1)?.command_uuid ?? null;
}

export async function enqueueContestCommand(command) {
    const commands = await readCommands();
    const dependency = selectContestDependency(command, commands);

    return enqueueCommand({
        ...command,
        depends_on_command_uuid: command.depends_on_command_uuid ?? dependency,
    });
}

export async function listPendingCommands() {
    return (await readCommands()).filter((command) => RETRYABLE_STATES.has(command.state));
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

function persistedReceipt(command) {
    if (!command || command.state !== 'applied') return null;

    return command.receipt ?? {
        command_uuid: command.command_uuid,
        disposition: 'applied',
        resulting_revision: command.resulting_revision,
        response: command.response,
    };
}

function isConflict(error) {
    const message = error instanceof Error ? error.message.toLowerCase() : '';

    return message.includes('conflict') || message.includes('stale') || message.includes('dependency') || message.includes('idempotency');
}

export async function synchronizePendingCommands(send) {
    const commands = (await listPendingCommands()).sort((left, right) => left.created_at.localeCompare(right.created_at));
    const receipts = new Map();

    for (const command of commands) {
        let dependency = null;
        if (command.depends_on_command_uuid) {
            const dependencyCommand = await getCommand(command.depends_on_command_uuid);
            dependency = receipts.get(command.depends_on_command_uuid) ?? persistedReceipt(dependencyCommand);
            if (!dependency) continue;
        }

        await updateCommand(command.command_uuid, { state: 'syncing' });
        const envelope = dependency?.resulting_revision == null
            ? command
            : { ...command, base_revision: dependency.resulting_revision };

        try {
            const receipt = await send(envelope);
            receipts.set(command.command_uuid, receipt);
            await updateCommand(command.command_uuid, {
                state: receipt.disposition === 'applied' ? 'applied' : receipt.disposition,
                receipt,
                resulting_revision: receipt.resulting_revision,
                response: receipt.response,
                error_code: receipt.error_code,
            });
        } catch (error) {
            await updateCommand(command.command_uuid, {
                state: isConflict(error) ? 'conflicted' : 'unknown',
                error_code: error instanceof Error ? error.message : 'unknown_after_timeout',
            });
        }
    }

    return [...receipts.values()];
}
