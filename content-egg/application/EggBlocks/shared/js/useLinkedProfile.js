/**
 * useLinkedProfile
 *
 * Detects whether the current post is linked to a TMN profile (via post meta)
 * and, if so, fetches the editor-facing summary used to populate source-key
 * dropdowns in block inspectors.
 *
 * Returns:
 *   {
 *     loading: boolean,
 *     summary: null | {
 *       profile_type: 'offer' | 'business',
 *       profile_id: number,
 *       profile_name: string,
 *       has_logo: boolean,
 *       available_url_keys: Array<{ key: string, label: string, url: string }>
 *     }
 *   }
 *
 * `summary` is null when:
 *   - TMN is inactive (404 — route does not exist)
 *   - The post has no linked profile (404 — tmn_post_no_linked_profile)
 *   - The post hasn't been saved yet (no post id available)
 *
 * In all "no summary" cases the consuming block should fall back to manual mode.
 */

import { useState, useEffect } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import apiFetch from "@wordpress/api-fetch";

export default function useLinkedProfile() {
    const postId = useSelect(
        (select) => select("core/editor")?.getCurrentPostId() ?? null,
        []
    );

    const [state, setState] = useState({ loading: true, summary: null });

    useEffect(() => {
        let cancelled = false;

        if (!postId) {
            setState({ loading: false, summary: null });
            return;
        }

        setState((prev) => ({ ...prev, loading: true }));

        apiFetch({ path: `/tmn/v1/posts/${postId}/linked-profile` })
            .then((data) => {
                if (cancelled) return;
                setState({ loading: false, summary: data || null });
            })
            .catch(() => {
                if (cancelled) return;
                setState({ loading: false, summary: null });
            });

        return () => {
            cancelled = true;
        };
    }, [postId]);

    return state;
}
