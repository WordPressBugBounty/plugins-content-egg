/**
 * Replay a `previous` snapshot to undo an AI / Smart-Groups op. Each entry is
 * { module_id, unique_id, fields }; we restore it through the normal single-
 * item update path (serialized by the store's write queue). Sequential so the
 * writes don't race.
 *
 * @param {Array}    previous      - Array of { module_id, unique_id, fields } snapshots
 * @param {Function} updateProduct - Async function(module_id, unique_id, fields)
 * @return {Promise<void>}
 */
export async function runAiUndo( previous, updateProduct ) {
	for ( const entry of previous || [] ) {
		// eslint-disable-next-line no-await-in-loop
		await updateProduct( entry.module_id, entry.unique_id, entry.fields );
	}
}
